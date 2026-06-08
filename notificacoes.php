<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/notificacoes.php
 * NOME: Notificações — Caixa do Usuário
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

$pessoa_id = (int) $_SESSION['pessoa_id'];

/* ================= CORE ================= */
$CORE = '/home/elab/public_html/core';
require_once $CORE . '/data/config.php';
require_once $CORE . '/data/data.php';

$pdo = db();

/* ================= BUSCA ================= */
$sql = "
SELECT 
    l.id,
    l.mensagem,
    l.canal,
    l.referencia_id,
    l.criado_em,
    nl.id AS lido
FROM logs_comunicacoes l
LEFT JOIN notificacoes_lidas nl
       ON nl.notificacao_id = l.id
      AND nl.pessoa_id = ?
WHERE l.pessoa_id = ?
  AND l.canal = 'app'
ORDER BY l.criado_em DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$pessoa_id, $pessoa_id]);
$notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= CONTADOR ================= */
$naoLidas = array_filter($notificacoes, fn($n) => !$n['lido']);

/* ================= LABEL ================= */
function labelOrigem(?string $msg): string {
    $msg = strtolower($msg ?? '');
    return match (true) {
        str_contains($msg,'ranking') => '🏆 Ranking',
        str_contains($msg,'suporte') => '🛠️ Suporte',
        str_contains($msg,'comunicado') => '📢 Comunicado',
        default => '🔔 Sistema'
    };
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Notificações</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
.nao-lida {
    background:#eef6ff;
    font-weight:600;
}
.badge-origem {
    font-size:.75rem;
}
</style>
</head>

<body class="container py-4">

<h4 class="mb-2">🔔 Notificações</h4>

<p class="text-muted mb-3">
<?= count($naoLidas) ?> não lida(s)
</p>

<?php if (!$notificacoes): ?>
<p class="text-muted">Nenhuma notificação.</p>
<?php endif; ?>

<div class="list-group">
<?php foreach ($notificacoes as $n): ?>
<a href="javascript:void(0)"
   onclick="marcarLida(<?= (int)$n['id'] ?>)"
   class="list-group-item list-group-item-action <?= $n['lido'] ? '' : 'nao-lida' ?>">

<div class="d-flex justify-content-between">
<span><?= htmlspecialchars($n['mensagem']) ?></span>
<span class="badge bg-secondary badge-origem">
<?= labelOrigem($n['mensagem']) ?>
</span>
</div>

<small class="text-muted">
<?= date('d/m/Y H:i', strtotime($n['criado_em'])) ?>
<?= $n['lido'] ? '• Lida' : '• Nova' ?>
</small>

</a>
<?php endforeach; ?>
</div>

<a href="app.php" class="btn btn-light w-100 mt-4">← Voltar</a>

<script>
function marcarLida(id){
    fetch('lido-notificacao.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'id='+id
    }).then(()=>location.reload());
}
</script>

</body>
</html>
