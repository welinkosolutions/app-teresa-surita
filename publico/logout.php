<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/publico/logout.php
 * NOME: Logout Seguro (BLINDADO)
 * DESCRIÇÃO:
 * - Encerra sessão do app com segurança
 * - Invalida token persistente (remember login)
 * - Apaga cookie ELAB_REMEMBER
 * - Apaga cookie da sessão PHP
 * - Marca pessoa offline
 * - Registra auditoria
 * - Redireciona para a landing
 * ======================================================
 */

declare(strict_types=1);

date_default_timezone_set('America/Boa_Vista');

/* ================= ERROS (PROD SAFE) ================= */
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

/* ================= SESSION BOOTSTRAP ================= */
require_once '/home/elab/public_html/core/sessao/bootstrap_app.php';

/* ================= LOG ================= */
$LOG_PATH = '/home/elab/logs/app-logout.log';

if (!function_exists('logAppLogout')) {
    function logAppLogout(string $tipo, string $msg): void
    {
        global $LOG_PATH;

        file_put_contents(
            $LOG_PATH,
            date('Y-m-d H:i:s') .
            " | {$tipo} | {$msg} | IP=" . ($_SERVER['REMOTE_ADDR'] ?? '-') . PHP_EOL,
            FILE_APPEND
        );
    }
}

if (!function_exists('elabLogoutCookieOptions')) {
    function elabLogoutCookieOptions(int $expires): array
    {
        $secure = false;

        if (function_exists('elabAppIsSecureRequest')) {
            $secure = elabAppIsSecureRequest();
        } else {
            if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                $secure = true;
            } elseif (
                !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
                strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https'
            ) {
                $secure = true;
            } elseif (!empty($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443') {
                $secure = true;
            }
        }

        return [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}

if (!function_exists('elabLogoutClearRememberCookie')) {
    function elabLogoutClearRememberCookie(): void
    {
        setcookie('ELAB_REMEMBER', '', elabLogoutCookieOptions(time() - 3600));
        unset($_COOKIE['ELAB_REMEMBER']);
    }
}

if (!function_exists('elabLogoutClearSessionCookie')) {
    function elabLogoutClearSessionCookie(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if ((bool)ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 3600,
                    'path'     => $params['path'] ?: '/',
                    'domain'   => $params['domain'] ?? '',
                    'secure'   => (bool)($params['secure'] ?? false),
                    'httponly' => (bool)($params['httponly'] ?? true),
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }
    }
}

if (!function_exists('elabLogoutParseRememberCookie')) {
    function elabLogoutParseRememberCookie(): array
    {
        $cookie = (string)($_COOKIE['ELAB_REMEMBER'] ?? '');

        if ($cookie === '' || !str_contains($cookie, ':')) {
            return [0, ''];
        }

        [$pessoaIdRaw, $tokenBruto] = explode(':', $cookie, 2);

        $pessoaId   = (int)$pessoaIdRaw;
        $tokenBruto = trim($tokenBruto);

        if ($pessoaId <= 0 || strlen($tokenBruto) < 64 || !ctype_xdigit($tokenBruto)) {
            return [0, ''];
        }

        return [$pessoaId, $tokenBruto];
    }
}

/* ================= IDENTIFICA USUÁRIO ================= */
$ip         = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$userAgent  = (string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
$sessionUid = (int)($_SESSION['pessoa_id'] ?? 0);

[$cookieUid, $cookieToken] = elabLogoutParseRememberCookie();

$pessoaId = $sessionUid > 0 ? $sessionUid : $cookieUid;

/* ================= BANCO / REVOGAÇÃO ================= */
if ($pessoaId > 0) {
    try {
        $CORE = '/home/elab/public_html/core';
        require_once $CORE . '/data/config.php';
        require_once $CORE . '/data/data.php';

        $pdo = dbRoraima();

        if ($pdo instanceof PDO) {
            $tokenRevogado = false;

            if ($cookieUid > 0 && $cookieToken !== '') {
                $stmt = $pdo->prepare("
                    SELECT token_hash
                    FROM pessoas_login_persistente
                    WHERE pessoa_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$cookieUid]);
                $registro = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($registro && !empty($registro['token_hash']) && password_verify($cookieToken, (string)$registro['token_hash'])) {
                    $pdo->prepare("
                        DELETE FROM pessoas_login_persistente
                        WHERE pessoa_id = ?
                    ")->execute([$cookieUid]);

                    $tokenRevogado = true;
                }
            }

            if (!$tokenRevogado) {
                $pdo->prepare("
                    DELETE FROM pessoas_login_persistente
                    WHERE pessoa_id = ?
                ")->execute([$pessoaId]);
            }

            $pdo->prepare("
                UPDATE pessoas
                SET online_status = 'offline',
                    ultimo_ping = NOW()
                WHERE id = ?
            ")->execute([$pessoaId]);

            try {
                $pdo->prepare("
                    INSERT INTO auditoria_interna
                        (pessoa_id, acao, tabela_afetada, descricao, ip)
                    VALUES
                        (?, 'logout', 'pessoas_login_persistente', ?, ?)
                ")->execute([
                    $pessoaId,
                    'Logout realizado pelo usuário no app',
                    $ip
                ]);
            } catch (Throwable $e) {
                logAppLogout('WARN', 'AUDITORIA_FALHOU ID=' . $pessoaId . ' MSG=' . $e->getMessage());
            }

            try {
                $pdo->prepare("
                    INSERT INTO pessoas_logins (pessoa_id, ip, user_agent)
                    VALUES (?, ?, ?)
                ")->execute([
                    $pessoaId,
                    $ip,
                    '[LOGOUT] ' . $userAgent
                ]);
            } catch (Throwable $e) {
                logAppLogout('WARN', 'HISTORICO_LOGOUT_FALHOU ID=' . $pessoaId . ' MSG=' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        logAppLogout('ERRO', 'LOGOUT_DB_FAIL ID=' . $pessoaId . ' MSG=' . $e->getMessage());
    }
}

/* ================= LIMPEZA DE COOKIES ================= */
elabLogoutClearRememberCookie();
elabLogoutClearSessionCookie();

/* ================= DESTRUIR SESSÃO ================= */
$_SESSION = [];

if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset();
    session_destroy();
}

/* ================= HEADERS NO-CACHE ================= */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

/* ================= LOG FINAL ================= */
if ($pessoaId > 0) {
    logAppLogout('OK', 'LOGOUT_CONCLUIDO ID=' . $pessoaId);
} else {
    logAppLogout('OK', 'LOGOUT_SEM_SESSAO');
}

/* ================= REDIRECIONA ================= */
header('Location: /index.php');
exit;