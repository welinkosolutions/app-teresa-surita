<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/exclusivo/aniversarios.php
 * NOME: Dashboard Executivo – Aniversários
 * ======================================================
 */

declare(strict_types=1);
date_default_timezone_set('America/Boa_Vista');

ini_set('display_errors','1');
error_reporting(E_ALL);

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

/* ======================================================
   BASE TOTAL
====================================================== */

$totalNova = (int)$pdo->query("
    SELECT COUNT(*)
    FROM pessoas
    WHERE status='ativo'
      AND data_nascimento IS NOT NULL
")->fetchColumn();

$totalAntiga = (int)$pdo->query("
    SELECT COUNT(*)
    FROM pessoas_antigo
    WHERE data_nascimento IS NOT NULL
      AND data_nascimento != '0001-01-01'
")->fetchColumn();

$totalGeral = $totalNova + $totalAntiga;

/* ======================================================
   ANIVERSÁRIOS HOJE
====================================================== */

$hojeNova = (int)$pdo->query("
    SELECT COUNT(*)
    FROM pessoas
    WHERE status='ativo'
      AND DATE_FORMAT(data_nascimento,'%m-%d') = DATE_FORMAT(CURDATE(),'%m-%d')
")->fetchColumn();

$hojeAntigo = (int)$pdo->query("
    SELECT COUNT(*)
    FROM pessoas_antigo
    WHERE data_nascimento IS NOT NULL
      AND data_nascimento != '0001-01-01'
      AND DATE_FORMAT(data_nascimento,'%m-%d') = DATE_FORMAT(CURDATE(),'%m-%d')
")->fetchColumn();

$aniversariosHoje = $hojeNova + $hojeAntigo;

/* ======================================================
   CONTATOS REALIZADOS HOJE
====================================================== */

$contatadosHoje = (int)$pdo->query("
    SELECT COUNT(*)
    FROM aniversarios_contatos
    WHERE DATE(contatado_em) = CURDATE()
")->fetchColumn();

$pendentesHoje = max(0, $aniversariosHoje - $contatadosHoje);

/* ======================================================
   PRÓXIMOS 7 DIAS
====================================================== */

$semanaNova = (int)$pdo->query("
    SELECT COUNT(*)
    FROM pessoas
    WHERE status='ativo'
      AND data_nascimento IS NOT NULL
      AND STR_TO_DATE(
            CONCAT(YEAR(CURDATE()), '-', DATE_FORMAT(data_nascimento,'%m-%d')),
            '%Y-%m-%d'
          )
          BETWEEN CURDATE()
          AND DATE_ADD(CURDATE(), INTERVAL 6 DAY)
")->fetchColumn();

$semanaAntigo = (int)$pdo->query("
    SELECT COUNT(*)
    FROM pessoas_antigo
    WHERE data_nascimento IS NOT NULL
      AND data_nascimento != '0001-01-01'
      AND STR_TO_DATE(
            CONCAT(YEAR(CURDATE()), '-', DATE_FORMAT(data_nascimento,'%m-%d')),
            '%Y-%m-%d'
          )
          BETWEEN CURDATE()
          AND DATE_ADD(CURDATE(), INTERVAL 6 DAY)
")->fetchColumn();

$aniversariosSemana = $semanaNova + $semanaAntigo;

/* ======================================================
   FILTROS LEGACY
====================================================== */

$familiaresAntigo = (int)$pdo->query("
    SELECT COUNT(DISTINCT pa.id)
    FROM pessoas_antigo pa
    JOIN vinculos_antigo v 
        ON v.pessoa_raw_id = pa.pessoa_raw_id
    WHERE pa.data_nascimento IS NOT NULL
      AND pa.data_nascimento != '0001-01-01'
      AND (
            v.descricao_raw LIKE '%FAMIL%'
         OR v.descricao_raw LIKE '%AMIGO%'
      )
")->fetchColumn();

$autoridadesAntigo = (int)$pdo->query("
    SELECT COUNT(DISTINCT pa.id)
    FROM pessoas_antigo pa
    JOIN vinculos_antigo v 
        ON v.pessoa_raw_id = pa.pessoa_raw_id
    WHERE pa.data_nascimento IS NOT NULL
      AND pa.data_nascimento != '0001-01-01'
      AND v.descricao_raw LIKE '%AUTORIDADE%'
")->fetchColumn();

$importantesAntigo = (int)$pdo->query("
    SELECT COUNT(DISTINCT pa.id)
    FROM pessoas_antigo pa
    JOIN vinculos_antigo v 
        ON v.pessoa_raw_id = pa.pessoa_raw_id
    WHERE pa.data_nascimento IS NOT NULL
      AND pa.data_nascimento != '0001-01-01'
      AND (
            v.descricao_raw LIKE '%IMPORTANTE%'
         OR v.descricao_raw LIKE '%CABELOS DE PRATA%'
      )
")->fetchColumn();

?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Dashboard de Aniversários</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{background:#f4f6f9;font-family:system-ui}
.card-box{
    background:#fff;
    border-radius:16px;
    padding:20px;
    box-shadow:0 6px 18px rgba(0,0,0,.05);
    margin-bottom:18px;
}
.kpi-big{
    font-size:28px;
    font-weight:800;
}
.btn-pill{
    border-radius:30px;
    padding:8px 18px;
}
</style>
</head>

<body>

<div class="container py-4" style="max-width:700px">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold m-0">🎂 Dashboard de Aniversários</h5>
    <a href="/interno/admin.php" class="btn btn-outline-secondary btn-sm">
        ← Dashboard
    </a>
</div>

<div class="card-box text-center">
    <div class="kpi-big"><?= number_format($totalGeral) ?></div>
    <div>Total de Aniversariantes Cadastrados</div>
    <div class="text-muted small mt-1">
        Base Antiga: <?= number_format($totalAntiga) ?> |
        Base Nova: <?= number_format($totalNova) ?>
    </div>
</div>

<div class="card-box text-center">
    <div class="kpi-big text-success"><?= number_format($aniversariosHoje) ?></div>
    <div><?= date('d/m') ?> – Aniversários de Hoje</div>

    <div class="mt-2 small">
        <span class="text-success fw-bold"><?= $contatadosHoje ?></span> já contactados |
        <span class="text-danger fw-bold"><?= $pendentesHoje ?></span> pendentes
    </div>
</div>

<div class="card-box text-center">
    <div class="kpi-big text-primary"><?= number_format($aniversariosSemana) ?></div>
    <div>
        Próximos 7 dias:
        <?= date('d/m') ?> a <?= date('d/m', strtotime('+6 days')) ?>
    </div>
</div>

<div class="card-box d-flex justify-content-between align-items-center">
    <div>
        <strong><?= $familiaresAntigo ?></strong><br>
        Familiares & Amigos (Legacy)
    </div>
    <a href="/exclusivo/lista-aniversarios.php?filtro=familia"
       class="btn btn-primary btn-pill">Ver Lista</a>
</div>

<div class="card-box d-flex justify-content-between align-items-center">
    <div>
        <strong><?= $autoridadesAntigo ?></strong><br>
        Autoridades (Legacy)
    </div>
    <a href="/exclusivo/lista-aniversarios.php?filtro=autoridades"
       class="btn btn-dark btn-pill">Ver Lista</a>
</div>

<div class="card-box d-flex justify-content-between align-items-center">
    <div>
        <strong><?= $importantesAntigo ?></strong><br>
        Pessoas Importantes (Legacy)
    </div>
    <a href="/exclusivo/lista-aniversarios.php?filtro=importantes"
       class="btn btn-success btn-pill">Ver Lista</a>
</div>

<div class="card-box d-flex justify-content-between align-items-center">
    <div>
        <strong><?= number_format($totalGeral) ?></strong><br>
        Lista Geral
    </div>
    <a href="/exclusivo/lista-aniversarios.php"
       class="btn btn-outline-secondary btn-pill">Ver Todos</a>
</div>

</div>
</body>
</html>