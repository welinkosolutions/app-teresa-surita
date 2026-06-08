<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['pessoa_id'])) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'error' => 'Sessão expirada.'
    ]);
    exit;
}

$pdo = dbRoraima();

$offset = max(0, (int)($_GET['offset'] ?? 0));
$limit  = (int)($_GET['limit'] ?? 20);

if ($limit <= 0) {
    $limit = 20;
}
if ($limit > 50) {
    $limit = 50;
}

function nomeRankingLoad(array $row): string
{
    $nome = trim((string)($row['nome'] ?? ''));
    $apelido = trim((string)($row['apelido'] ?? ''));
    $chamarPor = trim((string)($row['chamar_por'] ?? ''));

    if ($chamarPor === 'apelido' && $apelido !== '') {
        return $apelido;
    }

    if ($nome === '') {
        return 'Participante';
    }

    $partes = preg_split('/\s+/', $nome);
    return trim(implode(' ', array_slice($partes ?: [], 0, 2)));
}

$stmt = $pdo->prepare("
    SELECT pessoa_id, nome, apelido, chamar_por, xp_total, posicao
    FROM vw_ranking_geral
    ORDER BY posicao ASC, pessoa_id ASC
    LIMIT {$limit} OFFSET {$offset}
");
$stmt->execute();

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM vw_ranking_geral
");
$total = (int)$stmt->fetchColumn();

$html = '';

foreach ($rows as $item) {
    $html .= '
    <div class="ranking-row">
      <div class="ranking-row-left">
        <div class="ranking-row-pos">' . (int)$item['posicao'] . '</div>
        <div class="ranking-row-name">' . htmlspecialchars(nomeRankingLoad($item), ENT_QUOTES, 'UTF-8') . '</div>
      </div>

      <div class="ranking-row-score">
        ' . number_format((int)$item['xp_total'], 0, ',', '.') . '
      </div>
    </div>';
}

$nextOffset = $offset + count($rows);
$hasMore = $nextOffset < $total;

echo json_encode([
    'ok' => true,
    'html' => $html,
    'next_offset' => $nextOffset,
    'has_more' => $hasMore,
]);