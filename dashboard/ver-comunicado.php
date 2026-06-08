<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/dashboard/ver-comunicado.php
 * NOME: Ver Comunicado – App (GERAL + PARTICULAR)
 * ======================================================
 */

declare(strict_types=1);

/* ================= ERROS (DEV) ================= */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/* ================= SESSION + TIMEZONE ================= */
date_default_timezone_set('America/Boa_Vista');
session_name('ELAB_APP_SESSION');
session_start();

/* ================= CORE ================= */
$CORE = '/home/elab/public_html/core';
require_once $CORE . '/data/config.php';
require_once $CORE . '/data/data.php';

/* ================= SEGURANÇA ================= */
if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

$pessoa_id = (int)$_SESSION['pessoa_id'];
$id        = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die('Comunicado inválido');
}

/* ================= BANCO ================= */
$pdo = dbRoraima();

/* ================= BUSCA COMUNICADO ================= */
$sql = "
SELECT
    c.id,
    c.titulo,
    c.mensagem,
    c.criado_em,
    c.publico_tipo,
    c.publico_valor,
    cd.status
FROM comunicados c
LEFT JOIN comunicados_destinatarios cd
       ON cd.comunicado_id = c.id
      AND cd.pessoa_id = :pessoa_join
WHERE c.id = :id
  AND (
        (c.publico_tipo = 'pessoa' AND c.publico_valor IS NULL)
        OR cd.pessoa_id = :pessoa_where
      )
LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':id'            => $id,
    ':pessoa_join'   => $pessoa_id,
    ':pessoa_where'  => $pessoa_id
]);

$comunicado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$comunicado) {
    die('Comunicado não encontrado');
}

/* ================= MARCAR COMO LIDO (SÓ PARTICULAR) ================= */
$ehGeral = (
    $comunicado['publico_tipo'] === 'pessoa'
    && $comunicado['publico_valor'] === null
);

if ($comunicado['status'] === 'pendente' && !$ehGeral) {
    $pdo->prepare("
        UPDATE comunicados_destinatarios
        SET status = 'lido'
        WHERE comunicado_id = :c
          AND pessoa_id = :p
    ")->execute([
        ':c' => $id,
        ':p' => $pessoa_id
    ]);
}

/* ================= PAYLOAD ================= */
$payload = json_decode($comunicado['mensagem'], true) ?: [];
$texto   = $payload['texto']  ?? '';
$extras  = $payload['extras'] ?? [];

/* ================= MÍDIA ================= */
define('CRM_BASE', 'https://crm.elab.social');

$arquivo = null;
if (!empty($extras['arquivo'])) {
    $arquivo = str_starts_with($extras['arquivo'], 'http')
        ? $extras['arquivo']
        : CRM_BASE . $extras['arquivo'];
}

/* ================= HELPER ================= */
function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title><?= h($comunicado['titulo']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
.section-title{font-weight:600;margin-top:20px;margin-bottom:8px}
.info-link{display:flex;align-items:center;gap:6px;margin-bottom:6px}
</style>
</head>

<body class="container py-3">

<a href="comunicados.php" class="btn btn-sm btn-outline-secondary mb-3">← Voltar</a>

<h5><?= h($comunicado['titulo']) ?></h5>

<small class="text-muted">
<?= date('d/m/Y H:i', strtotime($comunicado['criado_em'])) ?>
<?= $comunicado['status']==='lido' ? ' • Lido' : '' ?>
</small>

<hr>

<p><?= nl2br(h($texto)) ?></p>

<?php if (!empty($extras)): ?>
<hr>
<div class="section-title">Informações</div>

<?php if (!empty($extras['telefone'])):
$tel = preg_replace('/\D/','',$extras['telefone']); ?>
<div class="info-link">📞 <a href="tel:+55<?= $tel ?>"><?= h($extras['telefone']) ?></a></div>
<?php endif; ?>

<?php if (!empty($extras['data'])):
$hr = $extras['hora'] ?? '00:00';
$start = DateTime::createFromFormat('d/m/Y H:i', $extras['data'].' '.$hr);
if ($start):
$end = (clone $start)->modify('+1 hour');
$gcal = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
      .'&text='.urlencode($comunicado['titulo'])
      .'&dates='.$start->format('Ymd\THis').'/'.$end->format('Ymd\THis')
      .'&details='.urlencode($texto);
?>
<div class="info-link">
📅 <?= h($extras['data']) ?> <?= h($hr) ?>
(<a href="<?= $gcal ?>" target="_blank">Adicionar à agenda</a>)
</div>
<?php endif; endif; ?>

<?php if (!empty($extras['maps'])): ?>
<div class="info-link">📍 <a href="<?= h($extras['maps']) ?>" target="_blank">Ver no mapa</a></div>
<?php endif; ?>

<?php if (!empty($extras['link'])): ?>
<div class="info-link">🔗 <a href="<?= h($extras['link']) ?>" target="_blank">Acessar link</a></div>
<?php endif; ?>

<?php endif; ?>

<?php if (!empty($arquivo)): ?>
<hr>
<div class="section-title">Anexo</div>

<?php if (preg_match('/\.(png|jpe?g)$/i', $arquivo)): ?>
<img src="<?= h($arquivo) ?>" style="max-width:100%;border-radius:12px" loading="lazy"
     onclick="window.open(this.src,'_blank')">
<?php elseif (preg_match('/\.(mp4|webm)$/i', $arquivo)): ?>
<video src="<?= h($arquivo) ?>" controls playsinline style="max-width:100%;border-radius:12px"></video>
<?php endif; ?>

<?php endif; ?>

</body>
</html>