<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/endpoint/busca_vinculo.php
 * NOME: Autocomplete – Vínculo / Local de Trabalho
 * DESCRIÇÃO:
 * - Retorna locais de trabalho já cadastrados
 * - Usado no cadastro por convite
 * - Apenas leitura
 * - Seguro para base grande
 * ======================================================
 */

declare(strict_types=1);
date_default_timezone_set('America/Boa_Vista');

header('Content-Type: application/json');

/* ================= ERROS (DEV) ================= */
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

/* ================= CORE ================= */
$CORE = '/home/elab/public_html/core';
require_once $CORE.'/data/config.php';
require_once $CORE.'/data/data.php';

/* ================= BANCO ================= */
$pdo = dbRoraima();
if (!$pdo instanceof PDO) {
    echo json_encode([]);
    exit;
}

/* ================= QUERY ================= */
$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

/* ================= BUSCA ================= */
/*
 Regra:
 - DISTINCT para não repetir
 - LIKE com índice aproveitável (prefix)
 - LIMIT curto para UX
*/
$stmt = $pdo->prepare("
    SELECT DISTINCT local_trabalho
    FROM pessoas
    WHERE local_trabalho IS NOT NULL
      AND local_trabalho <> ''
      AND local_trabalho LIKE ?
    ORDER BY local_trabalho
    LIMIT 10
");

$stmt->execute([$q.'%']);

$result = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $result[] = $row['local_trabalho'];
}

echo json_encode($result);
exit;