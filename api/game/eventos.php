<?php
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    http_response_code(403);
    exit;
}

$pessoa_id = (int)$_SESSION['pessoa_id'];
$pdo = dbRoraima();

$stmt = $pdo->prepare("
    SELECT id, tipo, valor
    FROM gamificacao_eventos_usuario
    WHERE pessoa_id = ?
      AND exibido = 'nao'
    ORDER BY id ASC
");

$stmt->execute([$pessoa_id]);
$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* marcar como exibido */
if ($eventos) {
    $ids = array_column($eventos, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));

    $upd = $pdo->prepare("
        UPDATE gamificacao_eventos_usuario
        SET exibido = 'sim'
        WHERE id IN ($in)
    ");
    $upd->execute($ids);
}

echo json_encode($eventos);