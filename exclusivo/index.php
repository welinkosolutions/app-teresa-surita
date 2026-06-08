<?php
declare(strict_types=1);

ini_set('display_errors','1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');
session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

$pessoa_id = (int) $_SESSION['pessoa_id'];

$usuariosExclusivos = [7160, 6168, 6607];
if (!in_array($pessoa_id, $usuariosExclusivos, true)) {
    header('Location: /interno/admin.php');
    exit;
}

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();

/* ================= BASE DE CADASTROS ================= */

$totalNovos = (int)$pdo->query("
    SELECT COUNT(*) FROM pessoas WHERE status = 'ativo'
")->fetchColumn();

$totalAntigos = (int)$pdo->query("
    SELECT COUNT(*) FROM pessoas_antigo
")->fetchColumn();

$totalValidadosAntigos = (int)$pdo->query("
    SELECT COUNT(*) FROM legacy_validados
")->fetchColumn();

$percentValidados = $totalAntigos > 0
    ? round(($totalValidadosAntigos / $totalAntigos) * 100)
    : 0;

/* ================= DEMANDAS ================= */

$dadosDemandas = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(status='aberto') as aberto,
        SUM(status='em_atendimento') as atendimento,
        SUM(status='atendido') as atendido,
        SUM(categoria='conte' AND criado_por IS NULL) as mensagens
    FROM solicitacoes
")->fetch(PDO::FETCH_ASSOC);

$totalDemandas       = (int)$dadosDemandas['total'];
$demandasAbertas     = (int)$dadosDemandas['aberto'];
$demandasAtendimento = (int)$dadosDemandas['atendimento'];
$demandasResolvidas  = (int)$dadosDemandas['atendido'];
$demandasMensagens   = (int)$dadosDemandas['mensagens'];

$percentResolucao = $totalDemandas > 0
    ? round(($demandasResolvidas / $totalDemandas) * 100)
    : 0;

/* ================= RANKING ================= */

$top1 = $pdo->query("
    SELECT nome, pontos
    FROM vw_ranking_executivo
    WHERE posicao = 1
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Painel Executivo</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{background:#f4f6f8;font-family:system-ui}
.top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.card-box{
    background:#fff;
    border-radius:16px;
    padding:20px;
    box-shadow:0 4px 12px rgba(0,0,0,.05);
    margin-bottom:16px;
}
.kpi-big{
    font-size:22px;
    font-weight:700;
}
.small-muted{
    font-size:12px;
    color:#6c757d;
}
.card-lider{
    background:linear-gradient(135deg,#0b6e7a,#169fa9);
    color:#fff;
    border-radius:16px;
    padding:20px;
}
.btn-main{
    border-radius:30px;
    padding:10px 22px;
}
</style>
</head>
<body>

<div class="container py-4">

<div class="top-bar">
    <h5 class="fw-bold m-0">🔐 Painel Executivo</h5>
    <a href="/interno/admin.php" class="btn btn-outline-secondary btn-sm">
        ← Dashboard
    </a>
</div>

<!-- ================= BASE DE CADASTROS ================= -->

<div class="card-box">

<h6 class="fw-bold mb-3">👨 Base de Cadastros</h6>

<div class="kpi-big">
Novos <?= number_format($totalNovos) ?> 
<span class="small-muted">  | Base antiga <?= number_format($totalAntigos) ?></span>
</div>

<div class="mt-2">
Validados dos antigos: <strong><?= number_format($totalValidadosAntigos) ?></strong>
</div>

<div class="small-muted">
<?= $percentValidados ?>% da base antiga validada
</div>

</div>

<!-- ================= MENSAGENS ================= -->

<div class="card-box">

<h6 class="fw-bold mb-3">🩷 Mensagens Diretas</h6>

<div class="kpi-big">
<?= number_format($demandasMensagens) ?> mensagens para Teresa
</div>

<div class="mt-3">
<a href="/exclusivo/demandas.php?status=todas&origem=mensagens"
   class="btn btn-outline-dark btn-sm">
Ver mensagens
</a>
</div>

</div>

<!-- ================= ATENDIMENTOS ================= -->

<div class="card-box">

<h6 class="fw-bold mb-3">🛟 Atendimentos e Solicitações</h6>

<div class="kpi-big">
Abertas <?= number_format($demandasAbertas) ?>
<span class="small-muted">  | Em atendimento <?= number_format($demandasAtendimento) ?></span>
</div>

<div class="mt-2">
Resolvidas <?= number_format($demandasResolvidas) ?>
<span class="small-muted">  | Total geral <?= number_format($totalDemandas) ?></span>
</div>

<div class="small-muted mt-1">
<?= $percentResolucao ?>% de resolução
</div>

</div>

<!-- ================= LÍDER ATUAL ================= -->

<?php if ($top1): ?>
<div class="card-lider mb-4">
<div class="d-flex justify-content-between align-items-center">
<div>
<div style="font-size:14px;">🥇 Líder Atual</div>
<div style="font-size:20px;font-weight:700;">
<?= htmlspecialchars($top1['nome']) ?>
</div>
<div><?= (int)$top1['pontos'] ?> pts</div>
</div>
<div style="font-size:40px;">🏆</div>
</div>
</div>
<?php endif; ?>

<!-- ================= BOTÕES ================= -->

<div class="text-center mt-4">

<a href="/exclusivo/ranking.php" class="btn btn-dark btn-main me-2">
Ver Ranking
</a>

<a href="/exclusivo/demandas.php" class="btn btn-outline-dark btn-main">
Ver Demandas
</a>

</div>

</div>
</body>
</html>