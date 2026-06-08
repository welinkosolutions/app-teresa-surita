<?php
/**
 * ============================================================
 * CAMINHO: app.elab.social/endpoint/login_exec.php
 * NOME: Login Exec – App elab.social
 * DESCRIÇÃO:
 * - Login por telefone + PIN
 * - Sessão centralizada
 * - Remember login alinhado ao core/sessao/app.php
 * - Bloqueio temporário real por tentativas de PIN
 * - Vincula device_id + platform ao usuário logado
 * ============================================================
 */

declare(strict_types=1);

/* ================= TIMEZONE ================= */
date_default_timezone_set('America/Boa_Vista');

/* ================= ERROS (PROD SAFE) ================= */
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

/* ================= SESSION / REMEMBER =================
   Usa app.php para herdar:
   - bootstrap da sessão
   - restore por cookie remember
   - helpers de cookie / tabela remember
===================================================== */
require_once '/home/elab/public_html/core/sessao/app.php';

/* Se já estiver autenticado, não precisa processar login novamente */
if (!empty($_SESSION['pessoa_id'])) {
    header('Location: /dashboard/index.php');
    exit;
}

/* ================= LOG ================= */
$LOG_PATH = '/home/elab/logs/app-login.log';

if (!function_exists('logAppLogin')) {
    function logAppLogin(string $tipo, string $msg): void
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

if (!function_exists('redirectLoginError')) {
    function redirectLoginError(string $erro): never
    {
        header('Location: /publico/login.php?erro=' . urlencode($erro));
        exit;
    }
}

/* ================= METHOD ================= */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Método não permitido');
}

/* ================= INPUT ================= */
$telefone = preg_replace('/\D+/', '', (string)($_POST['telefone'] ?? ''));
$pin      = preg_replace('/\D+/', '', (string)($_POST['pin'] ?? ''));
$ip       = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$ua       = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));

$deviceId = trim((string)($_POST['device_id'] ?? ''));
$platform = trim((string)($_POST['platform'] ?? 'pwa'));

if ($deviceId !== '') {
    $deviceId = preg_replace('/[^a-zA-Z0-9_\-:.]/', '', $deviceId);
    if (strlen($deviceId) > 190) {
        $deviceId = substr($deviceId, 0, 190);
    }
}

$platformPermitidas = ['pwa', 'android_webview', 'ios_webview'];
if (!in_array($platform, $platformPermitidas, true)) {
    $platform = 'pwa';
}

if (!in_array(strlen($telefone), [10, 11], true) || strlen($pin) !== 4) {
    logAppLogin('ERRO', "INPUT_INVALIDO TEL={$telefone}");
    redirectLoginError('1');
}

/* Pequeno atrito contra automação/brute force */
usleep(250000);

/* ================= CORE / BANCO ================= */
$CORE = '/home/elab/public_html/core';
require_once $CORE . '/data/config.php';
require_once $CORE . '/data/data.php';

$transactionStarted = false;

try {
    $pdo = dbRoraima();

    if (!$pdo instanceof PDO) {
        logAppLogin('ERRO', 'PDO_NAO_INICIALIZADO');
        redirectLoginError('1');
    }

    /* ================= BUSCA PESSOA ================= */
    $stmt = $pdo->prepare("
        SELECT id, nome, status, pin, pin_tentativas, pin_bloqueado_em
        FROM pessoas
        WHERE telefone = :tel
        LIMIT 1
    ");
    $stmt->execute([':tel' => $telefone]);
    $pessoa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pessoa) {
        logAppLogin('ERRO', "TEL_NAO_ENCONTRADO TEL={$telefone}");
        redirectLoginError('2');
    }

    $pessoaId = (int)$pessoa['id'];

    /* ================= STATUS ================= */
    if (($pessoa['status'] ?? '') !== 'ativo') {
        logAppLogin('ERRO', "STATUS_NEGADO TEL={$telefone} ID={$pessoaId}");
        redirectLoginError('1');
    }

    /* ================= BLOQUEIO TEMPORÁRIO =================
       Regra:
       - 5 erros de PIN => bloqueia por 15 minutos
    ======================================================== */
    $bloqueadoEm = $pessoa['pin_bloqueado_em'] ?? null;

    if (!empty($bloqueadoEm)) {
        $bloqueadoTs = strtotime((string)$bloqueadoEm);

        if ($bloqueadoTs !== false && $bloqueadoTs > (time() - (15 * 60))) {
            logAppLogin('BLOQUEADO', "PIN_BLOQUEADO_ATIVO TEL={$telefone} ID={$pessoaId}");
            redirectLoginError('bloqueado');
        }

        /* Bloqueio expirou: limpa antes de seguir */
        $pdo->prepare("
            UPDATE pessoas
            SET pin_tentativas = 0,
                pin_bloqueado_em = NULL
            WHERE id = :id
        ")->execute([
            ':id' => $pessoaId
        ]);

        $pessoa['pin_tentativas'] = 0;
        $pessoa['pin_bloqueado_em'] = null;
    }

    /* ================= VALIDA PIN ================= */
    $hashPin = (string)($pessoa['pin'] ?? '');

    if ($hashPin === '' || !password_verify($pin, $hashPin)) {
        $tentativas = ((int)($pessoa['pin_tentativas'] ?? 0)) + 1;

        $sql = "
            UPDATE pessoas
            SET pin_tentativas = :t
        ";

        $params = [
            ':t'  => $tentativas,
            ':id' => $pessoaId,
        ];

        if ($tentativas >= 5) {
            $sql .= ", pin_bloqueado_em = NOW()";
        }

        $sql .= " WHERE id = :id";

        $pdo->prepare($sql)->execute($params);

        logAppLogin('ERRO', "PIN_INVALIDO TEL={$telefone} ID={$pessoaId} T={$tentativas}");
        redirectLoginError($tentativas >= 5 ? 'bloqueado' : 'pin');
    }

    /* Rehash do PIN, se necessário */
    $novoHashPin = null;
    if (password_needs_rehash($hashPin, PASSWORD_DEFAULT)) {
        $novoHashPin = password_hash($pin, PASSWORD_DEFAULT);
    }

    /* ================= LOGIN PERSISTENTE ================= */
    $tokenBruto = bin2hex(random_bytes(64));
    $tokenHash  = password_hash($tokenBruto, PASSWORD_DEFAULT);

    $rememberColumns = elabAppRememberTableColumns($pdo);

    $hasCol = static function (string $col) use ($rememberColumns): bool {
        return isset($rememberColumns[$col]);
    };

    $insertCols = ['pessoa_id', 'token_hash', 'ultimo_ip', 'ultimo_user_agent'];
    $insertVals = [':pid', ':token_hash', ':ip', ':ua'];
    $updateVals = [
        'token_hash = VALUES(token_hash)',
        'ultimo_ip = VALUES(ultimo_ip)',
        'ultimo_user_agent = VALUES(ultimo_user_agent)',
    ];

    $rememberParams = [
        ':pid'        => $pessoaId,
        ':token_hash' => $tokenHash,
        ':ip'         => $ip,
        ':ua'         => $ua,
    ];

    if ($hasCol('criado_em')) {
        $insertCols[] = 'criado_em';
        $insertVals[] = 'NOW()';
    }

    if ($hasCol('ultimo_uso_em')) {
        $insertCols[] = 'ultimo_uso_em';
        $insertVals[] = 'NOW()';
        $updateVals[] = 'ultimo_uso_em = NOW()';
    }

    if ($hasCol('expira_em')) {
        $insertCols[] = 'expira_em';
        $insertVals[] = 'DATE_ADD(NOW(), INTERVAL 365 DAY)';
        $updateVals[] = 'expira_em = DATE_ADD(NOW(), INTERVAL 365 DAY)';
    }

    if ($hasCol('revogado_em')) {
        $insertCols[] = 'revogado_em';
        $insertVals[] = 'NULL';
        $updateVals[] = 'revogado_em = NULL';
    }

    $sqlRemember = "
        INSERT INTO pessoas_login_persistente
        (" . implode(', ', $insertCols) . ")
        VALUES (" . implode(', ', $insertVals) . ")
        ON DUPLICATE KEY UPDATE
        " . implode(",\n        ", $updateVals);

    /* ================= TRANSAÇÃO LOGIN OK ================= */
    $pdo->beginTransaction();
    $transactionStarted = true;

    $sqlPessoa = "
        UPDATE pessoas
        SET pin_tentativas = 0,
            pin_bloqueado_em = NULL,
            ultimo_acesso_app = NOW(),
            ultimo_ip = :ip
    ";

    $paramsPessoa = [
        ':id' => $pessoaId,
        ':ip' => $ip,
    ];

    if ($novoHashPin !== null) {
        $sqlPessoa .= ",
            pin = :pin_hash
        ";
        $paramsPessoa[':pin_hash'] = $novoHashPin;
    }

    $sqlPessoa .= "
        WHERE id = :id
    ";

    $pdo->prepare($sqlPessoa)->execute($paramsPessoa);

    $pdo->prepare($sqlRemember)->execute($rememberParams);

    $pdo->prepare("
        INSERT INTO pessoas_logins (pessoa_id, ip, user_agent)
        VALUES (:pid, :ip, :ua)
    ")->execute([
        ':pid' => $pessoaId,
        ':ip'  => $ip,
        ':ua'  => $ua,
    ]);

       /* ================= DEVICE / PUSH =================
       Usa push_dispositivos como vínculo oficial do aparelho
    ======================================================== */
    if ($deviceId !== '') {
        $plataformaMap = [
            'pwa' => 'web',
            'android_webview' => 'android',
            'ios_webview' => 'ios',
        ];

        $plataformaBanco = $plataformaMap[$platform] ?? 'web';

        $pdo->prepare("
            INSERT INTO push_dispositivos
            (
                pessoa_id,
                device_token,
                plataforma,
                app_origem,
                ativo,
                ultimo_seen_em,
                criado_em,
                atualizado_em
            )
            VALUES
            (
                :pessoa_id,
                :device_token,
                :plataforma,
                :app_origem,
                'sim',
                NOW(),
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                pessoa_id = VALUES(pessoa_id),
                plataforma = VALUES(plataforma),
                app_origem = VALUES(app_origem),
                ativo = 'sim',
                ultimo_seen_em = NOW(),
                atualizado_em = NOW()
        ")->execute([
            ':pessoa_id'    => $pessoaId,
            ':device_token' => $deviceId,
            ':plataforma'   => $plataformaBanco,
            ':app_origem'   => $platform,
        ]);
    }

    $pdo->commit();
    $transactionStarted = false;

    /* ================= SESSÃO ================= */
    session_regenerate_id(true);

    $_SESSION['pessoa_id']   = $pessoaId;
    $_SESSION['pessoa_nome'] = (string)$pessoa['nome'];

    /* ================= COOKIE REMEMBER ================= */
    setcookie(
        'ELAB_REMEMBER',
        $pessoaId . ':' . $tokenBruto,
        elabAppCookieOptions(time() + (60 * 60 * 24 * 365))
    );

    logAppLogin(
        'OK',
        "LOGIN TEL={$telefone} ID={$pessoaId} DEVICE=" . ($deviceId !== '' ? $deviceId : 'SEM_DEVICE') . " PLATFORM={$platform}"
    );

    header('Location: /dashboard/index.php');
    exit;

} catch (Throwable $e) {
    if ($transactionStarted && isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    logAppLogin('ERRO', 'EXCEPTION ' . $e->getMessage());
    redirectLoginError('1');
}