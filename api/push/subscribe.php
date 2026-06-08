<?php
declare(strict_types=1);

session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    http_response_code(403);
    exit;
}

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();
$pessoa_id = (int) $_SESSION['pessoa_id'];

$data = json_decode(file_get_contents('php://input'), true);

if (
    empty($data['endpoint']) ||
    empty($data['keys']['p256dh']) ||
    empty($data['keys']['auth'])
) {
    http_response_code(400);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO app_push_subscriptions
    (pessoa_id, endpoint, p256dh, auth)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE criado_em = NOW()
");

$stmt->execute([
    $pessoa_id,
    $data['endpoint'],
    $data['keys']['p256dh'],
    $data['keys']['auth']
]);

echo json_encode(['status' => 'ok']);