<?php
declare(strict_types=1);

ini_set('display_errors','1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');
session_name('ELAB_APP_SESSION');
session_start();

header('Content-Type: application/json');

/* ================= SEGURANÇA ================= */
if (empty($_SESSION['pessoa_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'erro']);
    exit;
}

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();
$pessoa_id = (int) $_SESSION['pessoa_id'];

$data = json_decode(file_get_contents('php://input'), true);
$post_id = (int) ($data['id'] ?? 0);

if ($post_id <= 0) {
    echo json_encode(['status' => 'erro']);
    exit;
}

try {

    $pdo->beginTransaction();

    /* ================= VALIDAR POST ================= */
    $stmt = $pdo->prepare("
        SELECT id, pontos_base
        FROM social_posts
        WHERE id = ?
          AND ativo = 'sim'
          AND rede IN ('instagram','multiplas')
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        $pdo->rollBack();
        echo json_encode(['status' => 'erro']);
        exit;
    }

    $pontos = (int) $post['pontos_base'];

    /* ================= VERIFICAR DUPLICIDADE ================= */
    $stmt = $pdo->prepare("
        SELECT id
        FROM social_posts_cliques_app
        WHERE post_id = ?
          AND pessoa_id = ?
          AND rede = 'whatsapp'
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$post_id, $pessoa_id]);

    if ($stmt->fetch()) {
        $pdo->commit();
        echo json_encode(['status' => 'ja_pontuado']);
        exit;
    }

    /* ================= INSERIR CLIQUE ================= */
    $stmt = $pdo->prepare("
        INSERT INTO social_posts_cliques_app
        (post_id, pessoa_id, rede, pontos_creditados, ip, user_agent, clicado_em)
        VALUES (?, ?, 'whatsapp', ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $post_id,
        $pessoa_id,
        $pontos,
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250)
    ]);

    /* ================= ATUALIZAR PONTOS (ATÔMICO) ================= */
    $stmt = $pdo->prepare("
        UPDATE pessoas
        SET pontos = pontos + ?
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$pontos, $pessoa_id]);

    $pdo->commit();

    echo json_encode(['status' => 'ok']);
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode(['status' => 'erro']);
    exit;
}