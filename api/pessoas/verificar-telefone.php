<?php
declare(strict_types=1);

header('Content-Type: application/json');

$CORE='/home/elab/public_html/core';

require_once $CORE.'/data/config.php';
require_once $CORE.'/data/data.php';

$pdo = dbRoraima();

$data = json_decode(file_get_contents("php://input"), true);

$telefone = preg_replace('/\D+/', '', $data['telefone'] ?? '');

if(!$telefone){
    echo json_encode(['existe'=>false]);
    exit;
}

$stmt = $pdo->prepare("
SELECT id
FROM pessoas
WHERE telefone = ?
LIMIT 1
");

$stmt->execute([$telefone]);

echo json_encode([
'existe' => $stmt->fetchColumn() ? true : false
]);