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

$periodo = $_GET['periodo'] ?? 'geral'; // geral | 7d | 30d
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
 * Ranking de usuários
 * ===============================
 *
 * score_usuario =
 *   (total_comentarios * 10)
 * + bônus de atividade recente
 */

$sql = "
    SELECT 
        username,
        COUNT(*) AS total_comentarios,
        SUM(
            CASE
                WHEN publicado_em >= NOW() - INTERVAL 3 DAY THEN 15
                WHEN publicado_em >= NOW() - INTERVAL 7 DAY THEN 10
                WHEN publicado_em >= NOW() - INTERVAL 15 DAY THEN 5
                ELSE 0
            END
        ) AS bonus_recente,
        (
            (COUNT(*) * 10)
            +
            SUM(
                CASE
                    WHEN publicado_em >= NOW() - INTERVAL 3 DAY THEN 15
                    WHEN publicado_em >= NOW() - INTERVAL 7 DAY THEN 10
                    WHEN publicado_em >= NOW() - INTERVAL 15 DAY THEN 5
                    ELSE 0
                END
            )
        ) AS score_usuario
    FROM meta_comentarios
    WHERE ativo = 'sim'
    {$filtroData}
    GROUP BY username
    ORDER BY score_usuario DESC
    LIMIT :limit
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();

$ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * ===============================
 * Formatar posição no ranking
 * ===============================
 */

$posicao = 1;
foreach ($ranking as &$usuario) {
    $usuario['posicao'] = $posicao++;
}

/**
 * ===============================
 * Retorno
 * ===============================
 */

echo json_encode([
    'success' => true,
    'periodo' => $periodo,
    'total_resultados' => count($ranking),
    'ranking' => $ranking
], JSON_PRETTY_PRINT);