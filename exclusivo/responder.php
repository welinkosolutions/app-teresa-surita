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

$id = (int)($_POST['id'] ?? 0);
$mensagem = trim($_POST['mensagem'] ?? '');
$visibilidade = $_POST['visibilidade'] ?? 'publico';

if ($id <= 0 || $mensagem === '') {
    header('Location: demandas.php');
    exit;
}

if (!in_array($visibilidade, ['publico','interno'], true)) {
    $visibilidade = 'publico';
}

/* ===== BUSCA STATUS ATUAL ===== */

$stmtCheck = $pdo->prepare("
    SELECT status
    FROM solicitacoes
    WHERE id = ?
    LIMIT 1
");
$stmtCheck->execute([$id]);

$demanda = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if (!$demanda) {
    header('Location: demandas.php');
    exit;
}

$statusAnterior = $demanda['status'];

/* ===== INSERE RESPOSTA ===== */

$stmtResposta = $pdo->prepare("
    INSERT INTO demandas_respostas
    (solicitacao_id, autor_tipo, autor_id, mensagem, visibilidade, tipo)
    VALUES (?, 'admin', ?, ?, ?, 'resposta')
");

$stmtResposta->execute([
    $id,
    $pessoa_id,
    $mensagem,
    $visibilidade
]);

/* ===== ALTERA STATUS PARA EM ATENDIMENTO SE ESTAVA ABERTO ===== */

if ($statusAnterior === 'aberto') {

    $stmtUpdate = $pdo->prepare("
        UPDATE solicitacoes
        SET status = 'em_atendimento',
            atualizado_em = NOW()
        WHERE id = ?
    ");
    $stmtUpdate->execute([$id]);

    /* REGISTRA EVENTO NO FEED */

    $stmtEvento = $pdo->prepare("
        INSERT INTO demandas_respostas
        (solicitacao_id, autor_tipo, autor_id, mensagem, visibilidade, tipo)
        VALUES (?, 'sistema', NULL, ?, 'interno', 'evento')
    ");

    $stmtEvento->execute([
        $id,
        'Status alterado automaticamente de ABERTO para EM ATENDIMENTO'
    ]);
}

header("Location: demanda-ver.php?id=".$id);
exit;