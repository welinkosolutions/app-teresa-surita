<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/suporte-ver.php
 * NOME: Suporte — Visualizar Ticket
 * DESCRIÇÃO:
 * - Exibe conversa do ticket
 * - Valida pertencimento do ticket ao usuário
 * - Mostra SLA em tempo real
 * ======================================================
 */

declare(strict_types=1);
date_default_timezone_set('America/Boa_Vista');

/* ================= SESSION ================= */
session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    header('Location: index.php');
    exit;
}

$pessoa_id   = (int) $_SESSION['pessoa_id'];
$protocoloId = (int) ($_GET['id'] ?? 0);

if ($protocoloId <= 0) {
    die('Ticket inválido.');
}

/* ================= CORE ================= */
$CORE = '/home/elab/public_html/core';
require_once $CORE . '/data/config.php';
require_once $CORE . '/data/data.php';

$pdo = db();

/* ================= BUSCA DO TICKET ================= */
$stmt = $pdo->prepare("
SELECT 
    p.id,
    p.assunto,
    p.status,
    p.criado_em,
    TIMESTAMPDIFF(MINUTE, p.criado_em, NOW()) AS sla_minutos
FROM protocolo p
WHERE p.id = ?
  AND p.pessoa_id = ?
LIMIT 1
");
$stmt->execute([$protocoloId, $pessoa_id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    die('Acesso negado ou ticket inexistente.');
}

/* ================= MENSAGENS ================= */
$stmt = $pdo->prepare("
SELECT 
    autor,
    mensagem,
    criado_em
FROM protocolo_mensagens
WHERE protocolo_id = ?
ORDER BY criado_em ASC
");
$stmt->execute([$protocoloId]);
$mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= SLA VISUAL ================= */
$sla = (int) $ticket['sla_minutos'];

if ($ticket['status'] === 'concluido') {
    $slaLabel = '🟢 Finalizado';
} elseif ($sla < 30) {
    $slaLabel = '🟢 Dentro do SLA';
} elseif ($sla < 120) {
    $slaLabel = '🟡 Atenção';
} else {
    $slaLabel = '🔴 SLA Estourado';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Ticket #<?= $ticket['id'] ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
.msg-usuario {
    background:#e8f0ff;
    border-radius:8px;
    padding:10px;
}
.msg-suporte {
    background:#f1f1f1;
    border-radius:8px;
    padding:10px;
}
</style>
</head>

<body class="container py-4">

<a href="/dashboard/suporte.php" class="btn btn-sm btn-light mb-3">← Voltar</a>

<h5>🎫 Ticket #<?= $ticket['id'] ?></h5>

<p class="mb-1"><strong>Assunto:</strong> <?= htmlspecialchars($ticket['assunto']) ?></p>
<p class="mb-1"><strong>Status:</strong> <?= htmlspecialchars($ticket['status']) ?></p>
<p class="mb-3"><strong>SLA:</strong> <?= $slaLabel ?> (<?= $sla ?> min)</p>

<hr>

<?php if (!$mensagens): ?>
<p class="text-muted">Nenhuma mensagem registrada.</p>
<?php endif; ?>

<?php foreach ($mensagens as $m): ?>
<div class="mb-3 <?= $m['autor'] === 'usuario' ? 'msg-usuario' : 'msg-suporte' ?>">
    <small class="text-muted">
        <?= $m['autor'] === 'usuario' ? '👤 Você' : '🛠️ Suporte' ?> ·
        <?= date('d/m/Y H:i', strtotime($m['criado_em'])) ?>
    </small>
    <div><?= nl2br(htmlspecialchars($m['mensagem'])) ?></div>
</div>
<?php endforeach; ?>

</body>
</html>