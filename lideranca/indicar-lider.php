<?php
/**
 * ==========================================================
 * ARQUIVO: app.elab.social/lideranca/indicar-lider.php
 * FUNÇÃO: Ponte legada para transferência oficial
 * AJUSTE:
 * - evita duplicidade com transferir.php
 * - mantém compatibilidade com links antigos
 * ==========================================================
 */

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

$demandaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($demandaId > 0) {
    header('Location: /lideranca/transferir.php?id=' . $demandaId);
    exit;
}

header('Location: /lideranca/demandas.php');
exit;