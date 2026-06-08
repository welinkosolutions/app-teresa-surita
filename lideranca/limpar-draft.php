<?php
declare(strict_types=1);
session_name('ELAB_APP_SESSION');
session_start();

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();
$pessoa_id = (int)$_SESSION['pessoa_id'];

$stmt = $pdo->prepare("DELETE FROM demandas_draft WHERE pessoa_id=?");
$stmt->execute([$pessoa_id]);

echo json_encode(['ok'=>true]);
