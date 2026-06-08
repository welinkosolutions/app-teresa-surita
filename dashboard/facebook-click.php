<?php
declare(strict_types=1);

ini_set('display_errors','1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');
session_name('ELAB_APP_SESSION');
session_start();

header('Content-Type: application/json');

if (empty($_SESSION['pessoa_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Sessão inválida']);
    exit;
}

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();
$pessoa_id = (int) $_SESSION['pessoa_id'];

/* ================= INPUT ================= */
$data = json_decode(file_get_contents('php://input'), true);
$post_id = (int)($data['id'] ?? 0);

if ($post_id <= 0) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Post inválido']);
    exit;
}

try {

    $pdo->beginTransaction();

    /* ================= VERIFICAR DUPLICIDADE (48H) ================= */
    $stmt = $pdo->prepare("
        SELECT id
        FROM social_posts_cliques_app
        WHERE pessoa_id = ?
          AND post_id = ?
          AND clicado_em >= (NOW() - INTERVAL 48 HOUR)
        LIMIT 1
    ");
    $stmt->execute([$pessoa_id, $post_id]);

    if ($stmt->fetch()) {
        $pdo->rollBack();
        echo json_encode(['status' => 'ja_pontuado']);
        exit;
    }

    /* ================= BUSCAR POST ================= */
    $stmt = $pdo->prepare("
        SELECT pontos_base, ativo
        FROM social_posts
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post || $post['ativo'] !== 'sim') {
        $pdo->rollBack();
        echo json_encode(['status' => 'erro', 'mensagem' => 'Post inativo']);
        exit;
    }

    $pontos = (int)$post['pontos_base'];

    if ($pontos <= 0) {
        $pdo->rollBack();
        echo json_encode(['status' => 'erro', 'mensagem' => 'Pontuação inválida']);
        exit;
    }

    /* ================= INSERIR REGISTRO ================= */
    $stmt = $pdo->prepare("
        INSERT INTO social_posts_cliques_app
        (pessoa_id, post_id, clicado_em)
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$pessoa_id, $post_id]);

    /* ================= ATUALIZAR PONTOS ================= */
    $stmt = $pdo->prepare("
        UPDATE pessoas
        SET pontos = pontos + ?
        WHERE id = ?
    ");
    $stmt->execute([$pontos, $pessoa_id]);

    $pdo->commit();

    echo json_encode([
        'status' => 'ok',
        'pontos' => $pontos
    ]);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro interno'
    ]);
}
