<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/dashboard/conte.php
 * NOME: Conte para Teresa – Canal Pessoal
 * ======================================================
 */

declare(strict_types=1);
date_default_timezone_set('America/Boa_Vista');

/* ================= SESSION ================= */
session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

$pessoa_id = (int)$_SESSION['pessoa_id'];

/* ================= CORE ================= */
$CORE = '/home/elab/public_html/core';
require_once $CORE . '/data/config.php';
require_once $CORE . '/data/data.php';

$pdo = dbRoraima();

/* ================= SUBMIT ================= */
$ok = false;
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tipo = trim($_POST['tipo'] ?? '');
    $texto = trim($_POST['mensagem'] ?? '');

    $lat = $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
    $lng = $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;

    if ($tipo === '' || $texto === '') {
        $erro = 'Preencha todos os campos obrigatórios.';
    } else {

        $stmt = $pdo->prepare("
            INSERT INTO solicitacoes (
                pessoa_id,
                tipo,
                categoria,
                descricao,
                latitude,
                longitude,
                status,
                criado_em
            ) VALUES (
                :pessoa,
                :tipo,
                'conte',
                :descricao,
                :lat,
                :lng,
                'aberto',
                NOW()
            )
        ");

        $stmt->execute([
            ':pessoa'    => $pessoa_id,
            ':tipo'      => $tipo,
            ':descricao' => $texto,
            ':lat'       => $lat,
            ':lng'       => $lng
        ]);

        $ok = true;
    }
}

function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Conte para Teresa</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{background:#f6f7fb}
.card{border-radius:18px}
textarea{resize:none}
</style>
</head>

<body class="container py-4">

<a href="/dashboard/index.php" class="btn btn-sm btn-outline-secondary mb-3">← Voltar</a>

<div class="card">
<div class="card-body">

<h5 class="mb-3">💬 Conte para Teresa</h5>

<?php if ($ok): ?>
<div class="alert alert-success">
Sua mensagem foi enviada com sucesso.<br>
Ela será lida com atenção ❤️
</div>
<?php else: ?>

<?php if ($erro): ?>
<div class="alert alert-danger"><?= h($erro) ?></div>
<?php endif; ?>

<form method="post">

<label class="fw-semibold">Tipo *</label>
<select name="tipo" class="form-select mb-3" required>
    <option value="">Selecione</option>
    <option value="mensagem">Mensagem</option>
    <option value="reclamacao">Reclamação</option>
    <option value="sugestao">Sugestão</option>
    <option value="solicitacao">Solicitação</option>
</select>

<label class="fw-semibold">Mensagem *</label>
<textarea name="mensagem"
          rows="6"
          class="form-control mb-3"
          placeholder="Escreva aqui o que você gostaria de dizer..."
          required></textarea>

<input type="hidden" name="latitude">
<input type="hidden" name="longitude">

<button class="btn btn-success w-100">
Enviar mensagem
</button>

</form>
<?php endif; ?>

</div>
</div>

<script>
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(p=>{
        document.querySelector('[name=latitude]').value = p.coords.latitude;
        document.querySelector('[name=longitude]').value = p.coords.longitude;
    });
}
</script>

</body>
</html>