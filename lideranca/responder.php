<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('America/Boa_Vista');

session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

$id = (int)($_POST['id'] ?? $_GET['id'] ?? $_POST['demanda_id'] ?? 0);

if ($id > 0) {
    header('Location: /lideranca/ver-demanda.php?id=' . $id, true, 301);
    exit;
}

header('Location: /lideranca/demandas.php', true, 301);
exit;