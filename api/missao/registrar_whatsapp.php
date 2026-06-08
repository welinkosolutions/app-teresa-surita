<?php
declare(strict_types=1);

/*
=====================================================
ELAB SOCIAL - REGISTRAR COMPARTILHAMENTO WHATSAPP
CAMINHO: /home/elab/app.elab.social/api/missao/registrar_whatsapp.php
=====================================================
*/

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');

header('Content-Type: application/json; charset=UTF-8');

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

try {
    if (empty($_SESSION['pessoa_id'])) {
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'erro' => 'Sessão inválida.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '{}', true);

    if (!is_array($json)) {
        $json = [];
    }

    $pessoa_id = (int)$_SESSION['pessoa_id'];
    $post_id   = (int)($json['post_id'] ?? 0);
    $canal     = trim((string)($json['canal'] ?? 'whatsapp'));

    if ($post_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'erro' => 'post_id inválido.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($canal === '') {
        $canal = 'whatsapp';
    }

    if ($canal !== 'whatsapp') {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'erro' => 'Canal inválido.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = dbRoraima();

    $stmt = $pdo->prepare("
        SELECT id, instagram_media_id, permalink, ativo
        FROM social_media
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        http_response_code(404);
        echo json_encode([
            'ok' => false,
            'erro' => 'Post da missão não encontrado.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS meta_post_compartilhamentos (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            pessoa_id BIGINT NOT NULL,
            post_id BIGINT NOT NULL,
            canal ENUM('whatsapp') NOT NULL DEFAULT 'whatsapp',
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_pessoa_post (pessoa_id, post_id),
            KEY idx_post (post_id),
            KEY idx_criado_em (criado_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $pdo->prepare("
        INSERT INTO meta_post_compartilhamentos
            (pessoa_id, post_id, canal, criado_em)
        VALUES
            (?, ?, ?, NOW())
    ");
    $stmt->execute([$pessoa_id, $post_id, $canal]);

    $insertId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM meta_post_compartilhamentos
        WHERE pessoa_id = ?
          AND post_id = ?
          AND canal = 'whatsapp'
    ");
    $stmt->execute([$pessoa_id, $post_id]);

    $totalCompartilhamentos = (int)$stmt->fetchColumn();
    $whatsappAtual = min(3, $totalCompartilhamentos);
    $concluido = $whatsappAtual >= 3;

    echo json_encode([
        'ok' => true,
        'id' => $insertId,
        'post_id' => $post_id,
        'whatsapp_atual' => $whatsappAtual,
        'total_compartilhamentos' => $totalCompartilhamentos,
        'concluido' => $concluido
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'erro' => 'Erro interno ao registrar compartilhamento.',
        'detalhe' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}