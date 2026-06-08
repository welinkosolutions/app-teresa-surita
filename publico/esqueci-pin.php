<?php
/**
 * ============================================================
 * CAMINHO: v3.elab.social/publico/esqueci-pin.php
 * NOME: Esqueci SENHA – Recuperação por telefone + e-mail
 * ============================================================
 */

declare(strict_types=1);

date_default_timezone_set('America/Boa_Vista');

/* ================= DEBUG / LOG ================= */
$DEBUG = false;
ini_set('display_errors', $DEBUG ? '1' : '0');
ini_set('display_startup_errors', $DEBUG ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

/* ================= SESSION ================= */
require_once '/home/elab/public_html/core/sessao/app.php';

if (!empty($_SESSION['pessoa_id'])) {
    header('Location: /dashboard/index.php');
    exit;
}

/* ================= CORE ================= */
$CORE = '/home/elab/public_html/core';
require_once $CORE . '/data/config.php';
require_once $CORE . '/data/data.php';
require_once $CORE . '/data/mailer.php';

/* ================= HELPERS ================= */
if (!function_exists('epLog')) {
    function epLog(string $tipo, string $mensagem): void
    {
        $logPath = '/home/elab/logs/app-esqueci-pin.log';

        @file_put_contents(
            $logPath,
            date('Y-m-d H:i:s') .
            ' | ' . $tipo .
            ' | ' . $mensagem .
            ' | IP=' . ($_SERVER['REMOTE_ADDR'] ?? '-') . PHP_EOL,
            FILE_APPEND
        );
    }
}

if (!function_exists('epMaskPhone')) {
    function epMaskPhone(string $telefone): string
    {
        $d = preg_replace('/\D+/', '', $telefone);

        if (strlen($d) === 11) {
            return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7);
        }

        if (strlen($d) === 10) {
            return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6);
        }

        return $telefone;
    }
}

if (!function_exists('epMaskEmail')) {
    function epMaskEmail(string $email): string
    {
        $email = trim($email);

        if ($email === '' || !str_contains($email, '@')) {
            return $email;
        }

        [$user, $domain] = explode('@', $email, 2);

        $userMasked = strlen($user) <= 2
            ? substr($user, 0, 1) . '*'
            : substr($user, 0, 2) . str_repeat('*', max(2, strlen($user) - 2));

        return $userMasked . '@' . $domain;
    }
}

if (!function_exists('epNovoPin')) {
    function epNovoPin(): string
    {
        return str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('epNomeCurto')) {
    function epNomeCurto(string $nome): string
    {
        $nome = trim(preg_replace('/\s+/', ' ', $nome));

        if ($nome === '') {
            return 'amigo(a)';
        }

        $partes = explode(' ', $nome);
        return trim($partes[0] ?? $nome);
    }
}

if (!function_exists('epIsIosRequest')) {
    function epIsIosRequest(): bool
    {
        $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        return str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ipod');
    }
}

if (!function_exists('epEmailDomain')) {
    function epEmailDomain(string $email): string
    {
        $email = trim(strtolower($email));

        if ($email === '' || !str_contains($email, '@')) {
            return '';
        }

        [, $domain] = explode('@', $email, 2);
        return trim($domain);
    }
}

if (!function_exists('epSuccessMailAction')) {
    function epSuccessMailAction(string $email): array
    {
        $domain = epEmailDomain($email);

        if (epIsIosRequest()) {
            return [
                'href'   => 'message://',
                'label'  => 'ABRIR E-MAIL',
                'helper' => 'Vamos abrir seu app de e-mail do iPhone.',
            ];
        }

        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            return [
                'href'   => 'googlegmail://',
                'label'  => 'ABRIR O GMAIL',
                'helper' => 'Vamos tentar abrir o app do Gmail no seu celular.',
            ];
        }

        return [
            'href'   => 'mailto:',
            'label'  => 'ABRIR E-MAIL',
            'helper' => 'Vamos abrir seu app de e-mail.',
        ];
    }
}

if (!function_exists('epRegistrarAuditoria')) {
    function epRegistrarAuditoria(PDO $pdo, int $pessoaId, string $descricao): void
    {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO auditoria_interna
                    (pessoa_id, acao, tabela_afetada, descricao, ip)
                VALUES
                    (?, 'reset_pin_email', 'pessoas', ?, ?)
            ");
            $stmt->execute([
                $pessoaId,
                $descricao,
                (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            ]);
        } catch (Throwable $e) {
            epLog('WARN', 'AUDITORIA_FALHOU ID=' . $pessoaId . ' MSG=' . $e->getMessage());
        }
    }
}

if (!function_exists('epEnviarNovoPinPorEmail')) {
    function epEnviarNovoPinPorEmail(string $email, string $nome, string $novoPin, string $telefone = ''): bool
    {
        $nomeExibicao = trim($nome) !== '' ? trim($nome) : 'Olá';
        $nomeCurto = function_exists('epNomeCurto') ? epNomeCurto($nomeExibicao) : $nomeExibicao;
        $assunto = 'Sua NOVA SENHA de acesso';

        $loginUrl = 'https://v3.elab.social/publico/login.php';

        $html = '
        <!DOCTYPE html>
        <html lang="pt-br">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Sua NOVA SENHA de acesso</title>
        </head>
        <body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#20303a;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f3f4f6;margin:0;padding:14px 0;">
                <tr>
                    <td align="center" style="padding:0 10px;">

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background:#ffffff;border-radius:22px;overflow:hidden;box-shadow:0 10px 24px rgba(0,0,0,.08);">
                            <tr>
                                <td style="padding:22px 18px 20px; text-align:center;">

                                  <div style="font-size:16px;font-weight:600;line-height:1.45;color:#00221c;margin-bottom:14px;text-align:center;">
    🔓 Olá, <strong>' . htmlspecialchars($nomeCurto, ENT_QUOTES, 'UTF-8') . '</strong>.<br>
    Sua nova senha foi gerada com sucesso!
</div>

                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 14px;">
                                        <tr>
                                            <td style="background:#fbf3d9;border:1px solid #efd980;border-radius:18px;padding:18px 14px;text-align:center;">
                                                <div style="font-size:16px;font-weight:800;color:#9b7300;margin-bottom:10px;">
                                                    SENHA DE ACESSO AO APLICATIVO
                                                </div>

                                                <div style="font-size:50px;line-height:1;font-weight:900;color:#d40000;letter-spacing:10px;font-family:Arial Black,Arial,Helvetica,sans-serif;margin-bottom:12px;">
                                                    ' . htmlspecialchars($novoPin, ENT_QUOTES, 'UTF-8') . '
                                                </div>

                                                <div style="font-size:14px;line-height:1.6;color:#7a6420;max-width:380px;margin:0 auto;">
                                                    Guarde essa senha agora. Salve, copie ou tire um print deste e-mail.
                                                </div>
                                            </td>
                                        </tr>
                                    </table>

                                    <div style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#2f2f2f;font-weight:700;">
                                        No celular, toque e segure sobre a senha para copiar.
                                    </div>

                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 14px;">
                                        <tr>
                                            <td align="center">
                                                <a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '" style="display:block;background:#1f8f4f;color:#ffffff;text-decoration:none;font-size:18px;font-weight:800;padding:15px 20px;border-radius:14px;">
                                                    ➜ Ir para o login
                                                </a>
                                            </td>
                                        </tr>
                                    </table>

                                    <div style="font-size:14px;line-height:1.7;color:#6b7280;text-align:center;max-width:430px;margin:0 auto;">
                                       Se você não pediu essa senha, ignore este e-mail.
                                    </div>
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>
            </table>
        </body>
        </html>';

        $texto =
            "Oi, {$nomeCurto}!\n\n" .
            "Sua nova senha foi gerada com sucesso!\n\n" .
            "Sua NOVA SENHA: {$novoPin}\n\n" .
            "No celular, toque e segure sobre a senha para copiar.\n\n" .
            "Login: {$loginUrl}\n\n" ;
            return sendMail($email, $nomeExibicao, $assunto, $html, $texto);
    }
}
/* ================= ESTADO ================= */
$etapa = 'telefone';
$msgErro = '';
$msgOk = '';
$telefoneInput = '';
$emailInput = '';
$nomePessoaCurto = '';
$emailDestinoSucesso = '';
$acaoSucesso = [
    'href'   => '/publico/login.php',
    'label'  => 'VOLTAR AO LOGIN',
    'helper' => '',
];

/* ================= RATE LIMIT ================= */
$cooldownSegundos = 8;
$ultimaTentativa = (int)($_SESSION['esqueci_pin_last_try'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = trim((string)($_POST['acao'] ?? ''));
    $telefoneInput = preg_replace('/\D+/', '', (string)($_POST['telefone'] ?? ''));
    $emailInput = trim((string)($_POST['email'] ?? ''));

    if ($ultimaTentativa > 0 && (time() - $ultimaTentativa) < $cooldownSegundos) {
        $msgErro = 'Aguarde alguns segundos antes de tentar novamente.';
    } else {
        $_SESSION['esqueci_pin_last_try'] = time();

        try {
            $pdo = dbRoraima();

            if (!$pdo instanceof PDO) {
                throw new RuntimeException('Falha ao conectar no banco.');
            }

            if (!in_array(strlen($telefoneInput), [10, 11], true)) {
                $msgErro = 'Digite um WhatsApp válido.';
            } else {
                $stmt = $pdo->prepare("
                    SELECT id, nome, email, status
                    FROM pessoas
                    WHERE telefone = ?
                    LIMIT 1
                ");
                $stmt->execute([$telefoneInput]);
                $pessoa = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

                if (!$pessoa) {
                    $msgErro = 'Não encontramos esse telefone na base.';
                    epLog('NAO_ENCONTRADO', 'TEL=' . $telefoneInput);
                } elseif (($pessoa['status'] ?? '') !== 'ativo') {
                    $msgErro = 'Esse cadastro está temporariamente indisponível para recuperação.';
                    epLog('STATUS_INVALIDO', 'ID=' . (int)$pessoa['id']);
                } else {
                    $pessoaId   = (int)$pessoa['id'];
                    $nomePessoa = trim((string)($pessoa['nome'] ?? ''));
                    $nomePessoaCurto = epNomeCurto($nomePessoa);
                    $emailAtual = trim((string)($pessoa['email'] ?? ''));

                    if ($acao === 'buscar') {
                        if ($emailAtual !== '' && filter_var($emailAtual, FILTER_VALIDATE_EMAIL)) {
                            $novoPin  = epNovoPin();
                            $novoHash = password_hash($novoPin, PASSWORD_DEFAULT);

                            $pdo->beginTransaction();

                            $stmt = $pdo->prepare("
                                UPDATE pessoas
                                SET pin = ?,
                                    pin_tentativas = 0,
                                    pin_bloqueado_em = NULL,
                                    atualizado_em = NOW()
                                WHERE id = ?
                                LIMIT 1
                            ");
                            $stmt->execute([$novoHash, $pessoaId]);

                            epRegistrarAuditoria($pdo, $pessoaId, 'Nova SENHA enviada por e-mail já cadastrado.');

                            if (!epEnviarNovoPinPorEmail($emailAtual, $nomePessoa, $novoPin)) {
                                $pdo->rollBack();
                                $msgErro = 'Não foi possível enviar o e-mail agora.';
                            } else {
                                $pdo->commit();
                                $emailDestinoSucesso = $emailAtual;
                                $acaoSucesso = epSuccessMailAction($emailAtual);
                                $msgOk = 'Enviamos uma nova SENHA para ' . epMaskEmail($emailAtual) . '.';
                                $etapa = 'sucesso';
                                epLog('OK', 'PIN_ENVIADO ID=' . $pessoaId . ' EMAIL=' . $emailAtual);
                            }
                        } else {
                            $etapa = 'email';
                        }
                    }

                    if ($acao === 'salvar_email') {
                        $etapa = 'email';

                        if (!filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
                            $msgErro = 'Digite um e-mail válido.';
                        } else {
                            $stmt = $pdo->prepare("
                                SELECT id
                                FROM pessoas
                                WHERE email = ?
                                  AND id <> ?
                                LIMIT 1
                            ");
                            $stmt->execute([$emailInput, $pessoaId]);

                            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                                $msgErro = 'Esse e-mail já está em uso em outro cadastro.';
                            } else {
                                $novoPin  = epNovoPin();
                                $novoHash = password_hash($novoPin, PASSWORD_DEFAULT);

                                $pdo->beginTransaction();

                                $stmt = $pdo->prepare("
                                    UPDATE pessoas
                                    SET email = ?,
                                        pin = ?,
                                        pin_tentativas = 0,
                                        pin_bloqueado_em = NULL,
                                        atualizado_em = NOW()
                                    WHERE id = ?
                                    LIMIT 1
                                ");
                                $stmt->execute([$emailInput, $novoHash, $pessoaId]);

                                epRegistrarAuditoria($pdo, $pessoaId, 'E-mail cadastrado na recuperação e NOVA SENHA enviada.');

                                if (!epEnviarNovoPinPorEmail($emailInput, $nomePessoa, $novoPin)) {
                                    $pdo->rollBack();
                                    $msgErro = 'Não foi possível enviar o e-mail agora.';
                                } else {
                                    $pdo->commit();
                                    $emailDestinoSucesso = $emailInput;
                                    $acaoSucesso = epSuccessMailAction($emailInput);
                                    $msgOk = 'Pronto! Cadastramos seu e-mail e enviamos uma NOVA SENHA para ' . epMaskEmail($emailInput) . '.';
                                    $etapa = 'sucesso';
                                    epLog('OK', 'EMAIL_CADASTRADO_PIN ID=' . $pessoaId . ' EMAIL=' . $emailInput);
                                }
                            }
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            epLog('ERRO', 'EXCEPTION ' . $e->getMessage());
            $msgErro = 'Não foi possível concluir agora. Tente novamente em instantes.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Recuperar SENHA · Teresa Surita</title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root { --vh: 1vh; }
* { box-sizing: border-box; }

body {
    margin: 0;
    min-height: calc(var(--vh) * 100);
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    background:
        radial-gradient(circle at 20% 20%, #47d2c5 0%, transparent 55%),
        radial-gradient(circle at 80% 80%, #7ed6a3 0%, transparent 60%),
        linear-gradient(180deg, #38c3be 0%, #7ed6a3 100%);
}

#app {
    min-height: calc(var(--vh) * 100);
    display: flex;
    flex-direction: column;
    padding: 80px 16px 92px;
}

.top {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #fff;
    font-weight: 800;
    font-size: 15px;
    margin-bottom: 16px;
}

.form-card {
    background: rgba(255,255,255,.97);
    border-radius: 20px;
    padding: 18px 16px;
    box-shadow: 0 20px 40px rgba(0,0,0,.22);
}

.form-title {
    font-size: 28px;
    font-weight: 900;
    color: #20303a;
    margin-bottom: 8px;
    line-height: 1.1;
}

.form-subtitle {
    font-size: 14px;
    line-height: 1.55;
    color: #5f6d76;
    margin-bottom: 18px;
}

.label {
    font-weight: 800;
    font-size: 14.5px;
    margin-bottom: 6px;
    color: #333;
}

.input-main {
    width: 100%;
    padding: 14px 16px;
    border-radius: 14px;
    border: 1px solid #ddd;
    font-size: 16px;
    margin-bottom: 10px;
    background: #fff;
}

.btn-main {
    width: 100%;
    margin-top: 10px;
    padding: 14px;
    font-size: 16px;
    font-weight: 900;
    border-radius: 14px;
    border: none;
    background: linear-gradient(135deg,#0b6e7a,#169fa9);
    color: #fff;
    box-shadow: 0 10px 22px rgba(0,0,0,.22);
}

.btn-secondary {
    width: 100%;
    margin-top: 10px;
    padding: 14px;
    font-size: 15px;
    font-weight: 900;
    border-radius: 14px;
    border: none;
    background: rgba(11,110,122,.08);
    color: #0b6e7a;
    text-decoration: none;
    display: inline-flex;
    justify-content: center;
    align-items: center;
}

.msg-erro,
.msg-ok {
    text-align: center;
    margin-top: 12px;
    font-size: 14px;
    border-radius: 12px;
    padding: 10px 12px;
}

.msg-erro {
    color: #b00020;
    background: rgba(176,0,32,.08);
}

.msg-ok {
    color: #0b6e7a;
    background: rgba(11,110,122,.08);
}

.helper-box {
    background: rgba(11,110,122,.06);
    border-radius: 14px;
    padding: 12px 14px;
    margin-bottom: 12px;
    color: #47606d;
    font-size: 13px;
    line-height: 1.6;
}

.helper-box strong {
    color: #20303a;
}

.success-helper {
    margin-top: 10px;
    background: rgba(11,110,122,.06);
    border-radius: 14px;
    padding: 12px 14px;
    color: #47606d;
    font-size: 13px;
    line-height: 1.6;
}

.footer-app {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    padding: 12px 16px calc(12px + env(safe-area-inset-bottom));
    background: rgba(255,255,255,.92);
    backdrop-filter: blur(6px);
    box-shadow: 0 -6px 18px rgba(0,0,0,.18);
}

.footer-app a {
    display: flex;
    justify-content: center;
    gap: 6px;
    font-weight: 800;
    color: #0b6e7a;
    text-decoration: none;
}
</style>
</head>

<body>

<div id="app">

    <div class="top">
        <i class="bi bi-envelope-fill"></i>
        <span>Recuperar acesso</span>
    </div>

    <div class="form-card">

        <?php if ($etapa === 'telefone'): ?>
            <div class="form-title">🔒 Esqueceu a senha?</div>
            <div class="form-subtitle">
                Digite seu WhatsApp para verificar seu e-mail e solicitar uma nova senha de acesso ao aplicativo.
            </div>

            <form method="post" autocomplete="off">
                <input type="hidden" name="acao" value="buscar">

                <div class="label"><i class="bi bi-whatsapp"></i> Use o WhatsApp do Cadastro</div>
                <input
                    type="tel"
                    name="telefone"
                    id="telefone"
                    class="input-main"
                    placeholder="Digite aqui o número com DDD"
                    inputmode="numeric"
                    value="<?= htmlspecialchars(epMaskPhone($telefoneInput)) ?>"
                    required
                >

                <button type="submit" class="btn-main">Continuar</button>
            </form>

        <?php elseif ($etapa === 'email'): ?>
            <div class="form-title">Informe seu e-mail</div>
            <div class="form-subtitle">
                Encontramos seu telefone, mas ainda não há e-mail cadastrado. Informe um e-mail para receber uma NOVA SENHA.
            </div>

            <div class="helper-box">
                <strong>Olá, <?= htmlspecialchars($nomePessoaCurto) ?>.</strong><br>
                Telefone localizado: <?= htmlspecialchars(epMaskPhone($telefoneInput)) ?>
            </div>

            <form method="post" autocomplete="off">
                <input type="hidden" name="acao" value="salvar_email">
                <input type="hidden" name="telefone" value="<?= htmlspecialchars($telefoneInput) ?>">

                <div class="label">E-mail</div>
                <input
                    type="email"
                    name="email"
                    class="input-main"
                    placeholder="Digite aqui seu melhor e-mail"
                    value="<?= htmlspecialchars($emailInput) ?>"
                    required
                >

                <button type="submit" class="btn-main">Cadastrar e enviar SENHA</button>
            </form>

        <?php elseif ($etapa === 'sucesso'): ?>
            <div class="form-title">🔓 Feito!</div>
            <div class="form-subtitle">
                Sua nova senha foi enviada por e-mail e chegará em até 5 minutos. Abra sua caixa de entrada e atualize seus e-mails. Verifique também a caixa de spam.
            </div>

            <a href="<?= htmlspecialchars($acaoSucesso['href']) ?>" class="btn-secondary">
                <?= htmlspecialchars($acaoSucesso['label']) ?>
            </a>

            <?php if (!empty($acaoSucesso['helper'])): ?>
                <div class="success-helper">
                    <?= htmlspecialchars($acaoSucesso['helper']) ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($msgErro !== ''): ?>
            <div class="msg-erro"><?= htmlspecialchars($msgErro) ?></div>
        <?php endif; ?>

        <?php if ($msgOk !== ''): ?>
            <div class="msg-ok"><?= htmlspecialchars($msgOk) ?></div>
        <?php endif; ?>

    </div>

</div>

<div class="footer-app">
    <a href="/publico/login.php">
        <i class="bi bi-chevron-left"></i>
        VOLTAR
    </a>
</div>

<script>
function setVH() {
    document.documentElement.style.setProperty('--vh', `${window.innerHeight * 0.01}px`);
}
setVH();
window.addEventListener('resize', setVH);

const tel = document.getElementById('telefone');

function formatTelefone(value) {
    const d = value.replace(/\D/g, '').slice(0, 11);

    if (!d) return '';
    if (d.length <= 2) return '(' + d;
    if (d.length <= 6) return '(' + d.slice(0,2) + ') ' + d.slice(2);
    if (d.length <= 10) return '(' + d.slice(0,2) + ') ' + d.slice(2,6) + '-' + d.slice(6);
    return '(' + d.slice(0,2) + ') ' + d.slice(2,7) + '-' + d.slice(7);
}

if (tel) {
    let lastTel = '';

    tel.addEventListener('input', e => {
        const cur = e.target.value;
        if (cur.length < lastTel.length) {
            lastTel = cur;
            return;
        }
        const formatted = formatTelefone(cur);
        lastTel = formatted;
        e.target.value = formatted;
    });
}
</script>

</body>
</html>