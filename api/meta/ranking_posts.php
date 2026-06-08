<?php

declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

header('Content-Type: application/json');

$pdo = dbRoraima();

/**
 * ===============================
 * Parâmetros
 * ===============================
 */

$periodo = $_GET['periodo'] ?? 'geral';   // geral | 7d | 30d
$limit   = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

if ($limit <= 0 || $limit > 50) {
    $limit = 10;
}

/**
 * ===============================
 * Filtro por período
 * ===============================
 */

$filtroData = '';

if ($periodo === '7d') {
    $filtroData = "AND publicado_em >= NOW() - INTERVAL 7 DAY";
} elseif ($periodo === '30d') {
    $filtroData = "AND publicado_em >= NOW() - INTERVAL 30 DAY";
}

/**
 * ===============================
 * Consulta Ranking
 * ===============================
 */

$sql = "
    SELECT 
        instagram_media_id,
        caption,
        total_comentarios,
        score_engajamento,
        publicado_em
    FROM meta_posts
    WHERE ativo = 'sim'
    {$filtroData}
    ORDER BY score_engajamento DESC
    LIMIT :limit
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();

$ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * ===============================
 * Post de Maior Impacto
 * ===============================
 */

$topPost = $ranking[0] ?? null;

/**
 * ===============================
 * Retorno
 * ===============================
 */

echo json_encode([
    'success' => true,
    'periodo' => $periodo,
    'total_resultados' => count($ranking),
    'post_maior_impacto' => $topPost,
    'ranking' => $ranking
], JSON_PRETTY_PRINT);