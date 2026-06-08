<?php
/**
 * ============================================================
 * CAMINHO: app.elab.social/reset-pin.php
 * NOME: Reset de PIN
 * DESCRIÇÃO:
 * Valida token temporário e redefine o PIN do usuário.
 * ============================================================
 */

declare(strict_types=1);

/* ============================================================
   ERROS (DEV)
============================================================ */
ini_set('display_errors', '1');
error_reporting(E_ALL);

/* ============================================================
   LOG
============================================================ */
$LOG_PATH = '/home/elab/logs/app-reset-pin.log';

function app_log(string $msg): void {
    global $LOG_PATH;
    file_put_contents(
        $LOG_PATH,
        date('Y-m-d H:i:s') . " | {$msg}\n",
        FILE_APPEND
    );
}

/* ============================================================
   TIMEZONE + SESSION
============================================================ */
date_default_timezone_set('America/Boa_Vista');
session_start();

/* ============================================================
   CORE
============================================================ */
$CORE = '/home/elab/public_html/core';
require_once $CORE . '/data/config.php';
require_once $CORE . '/data/data.php';

/* ============================================================
   TOKEN (GET)
============================================================ */
$token = $_GET['token'] ?? '';

if (!$token || strlen($token) < 20) {
    http_response_code(403);
    exit('Token inválido.');
}

/* ============================================================
   VALIDAR TOKEN
============================================================ */
$stmt = $pdo->prepare("
    SELECT id, telefone, expira_em, invalidado_em
    FROM panic_temp_links
    WHERE token = :token
    LIMIT 1
");
$stmt->execute(['token' => $token]);
$link = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    !$link ||
    $link['invalidado_em'] !== null ||
    strtotime($link['expira_em']) < time()
) {
    http_response_code(403);
    exit('Token expirado ou inválido.');
}

/* ============================================================
   NOVO PIN (POST)
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pin = $_POST['pin'] ?? '';
    $confirm = $_POST['confirmar_pin'] ?? '';

    if (!preg_match('/^\d{4}$/', $pin)) {
        $erro = 'PIN deve conter 4 dígitos.';
    } elseif ($pin !== $confirm) {
        $erro = 'Os PINs não conferem.';
    } else {

        $pin_hash = password_hash($pin, PASSWORD_DEFAULT);

        /* =========================
           ATUALIZA PIN
        ========================== */
        $stmt = $pdo->prepare("
            UPDATE pessoas
            SET pin = :pin
            WHERE telefone = :telefone
            LIMIT 1
        ");
        $stmt->execute([
            'pin'      => $pin_hash,
            'telefone' => $link['telefone']
        ]);

        /* =========================
           INVALIDA TOKEN
        ========================== */
        $pdo->prepare("
            UPDATE panic_temp_links
            SET invalidado_em = NOW(), acessado_em = NOW()
            WHERE id = :id
        ")->execute(['id' => $link['id']]);

        /* =========================
           LOG SEGURANÇA
        ========================== */
        $pdo->prepare("
            INSERT INTO seguranca_eventos
                (tipo, pessoa_telefone, ip, detalhes)
            VALUES
                ('falha_pin', :telefone, :ip, 'PIN redefinido')
        ")->execute([
            'telefone' => $link['telefone'],
            'ip'       => $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        app_log("PIN redefinido com sucesso para {$link['telefone']}");

        header('Location: login.php?reset=ok');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Redefinir PIN</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body {
    margin:0;
    height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#0f172a;
    font-family:system-ui;
}
.box {
    background:#020617;
    color:#e5e7eb;
    padding:24px;
    border-radius:14px;
    width:100%;
    max-width:340px;
    box-shadow:0 20px 60px rgba(0,0,0,.6);
}
h1 {
    font-size:18px;
    margin-bottom:10px;
}
input {
    width:100%;
    padding:12px;
    margin-top:10px;
    border-radius:10px;
    border:none;
    font-size:16px;
}
button {
    width:100%;
    margin-top:14px;
    padding:12px;
    border:none;
    border-radius:10px;
    font-weight:700;
    background:#22c55e;
    color:#022c22;
}
.erro {
    color:#f87171;
    margin-top:10px;
    font-size:14px;
}
</style>
</head>

<body>

<div class="box">
    <h1>🔐 Redefinir PIN</h1>

    <?php if (!empty($erro)): ?>
        <div class="erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="password" name="pin" placeholder="Novo PIN (4 dígitos)" maxlength="4" required>
        <input type="password" name="confirmar_pin" placeholder="Confirmar PIN" maxlength="4" required>
        <button type="submit">Salvar novo PIN</button>
    </form>
</div>

</body>
</html>
