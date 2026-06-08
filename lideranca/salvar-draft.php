<?php
declare(strict_types=1);

session_name('ELAB_APP_SESSION');
session_start();

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();
$pessoa_id = (int)$_SESSION['pessoa_id'];

$dados = json_decode(file_get_contents('php://input'), true);

$stmt = $pdo->prepare("
INSERT INTO demandas_draft (pessoa_id,dados_json)
VALUES (?,?)
ON DUPLICATE KEY UPDATE dados_json=VALUES(dados_json)
");
$stmt->execute([$pessoa_id,json_encode($dados)]);

echo json_encode(['ok'=>true]);
