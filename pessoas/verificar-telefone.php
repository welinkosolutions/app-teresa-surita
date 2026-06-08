<?php
declare(strict_types=1);

date_default_timezone_set('America/Boa_Vista');

ini_set('display_errors','1');
error_reporting(E_ALL);

header('Content-Type: application/json');

/*
========================================
CORE
========================================
*/

$CORE = '/home/elab/public_html/core';

require_once $CORE.'/data/config.php';
require_once $CORE.'/data/data.php';

$pdo = dbRoraima();

/*
========================================
INPUT
========================================
*/

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$telefone = preg_replace('/\D+/', '', $data['telefone'] ?? '');

/*
========================================
VALIDAÇÃO
========================================
*/

if(strlen($telefone) !== 11){

    echo json_encode([
        'existe' => false,
        'valido' => false
    ]);
    exit;

}

/*
========================================
VERIFICAR TELEFONE
========================================
*/

$stmt = $pdo->prepare("
SELECT id
FROM pessoas
WHERE telefone = ?
LIMIT 1
");

$stmt->execute([$telefone]);

$existe = (bool)$stmt->fetchColumn();

/*
========================================
RESPOSTA
========================================
*/

echo json_encode([
    'existe' => $existe,
    'valido' => true
]);