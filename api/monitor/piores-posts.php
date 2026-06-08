<?php
declare(strict_types=1);

/*
=====================================================
ELAB SOCIAL — API MONITOR PIORES POSTS
CAMINHO:
app.elab.social/api/monitor/piores-posts.php
=====================================================
*/

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['pessoa_id'])) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'erro' => 'Sessão inválida.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$pdo = dbRoraima();
$pessoaId = (int) $_SESSION['pessoa_id'];

/*
========================================
WHITELIST DE ACESSO
========================================
*/
$idsMonitorRedes = [
    6607,
    6169,
    7160,
];

if (!in_array($pessoaId, $idsMonitorRedes, true)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'erro' => 'Sem permissão para visualizar este monitor.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/*
========================================
PARAMETROS
========================================
*/
$rede = strtolower(trim((string) ($_GET['rede'] ?? 'todos')));
$ordem = strtolower(trim((string) ($_GET['ordem'] ?? 'score')));
$limite = (int) ($_GET['limite'] ?? 3);

if (!in_array($rede, ['todos', 'instagram', 'facebook'], true)) {
    $rede = 'todos';
}

if ($limite < 1) {
    $limite = 3;
}
if ($limite > 3) {
    $limite = 3;
}

$ordensPermitidas = [
    'score' => 'm.score_engajamento ASC, m.comentarios ASC, m.curtidas ASC, m.post_id ASC',
    'comentarios' => 'm.comentarios ASC, m.score_engajamento ASC, m.curtidas ASC, m.post_id ASC',
    'curtidas' => 'm.curtidas ASC, m.score_engajamento ASC, m.comentarios ASC, m.post_id ASC',
    'alcance' => 'm.alcance ASC, m.score_engajamento ASC, m.comentarios ASC, m.post_id ASC',
    'compartilhamentos' => 'm.compartilhamentos ASC, m.score_engajamento ASC, m.comentarios ASC, m.post_id ASC',
    'reproducoes' => 'm.reproducoes ASC, m.score_engajamento ASC, m.comentarios ASC, m.post_id ASC',
];

if (!isset($ordensPermitidas[$ordem])) {
    $ordem = 'score';
}

$sqlOrder = $ordensPermitidas[$ordem];

/*
========================================
HELPERS
========================================
*/
if (!function_exists('ppTextoResumo')) {
    function ppTextoResumo(?string $texto, int $limite = 140): string
    {
        $texto = trim((string) $texto);

        if ($texto === '') {
            return '';
        }

        $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

        if (mb_strlen($texto, 'UTF-8') <= $limite) {
            return $texto;
        }

        return rtrim(mb_substr($texto, 0, $limite, 'UTF-8')) . '...';
    }
}

if (!function_exists('ppPostTipoLabel')) {
    function ppPostTipoLabel(?string $tipo): string
    {
        $tipo = strtolower(trim((string) $tipo));

        return match ($tipo) {
            'reel' => 'Reel',
            'video' => 'Vídeo',
            'story' => 'Story',
            'stories' => 'Story',
            'carousel', 'carrossel' => 'Carrossel',
            'image', 'imagem', 'photo', 'foto' => 'Imagem',
            default => $tipo !== '' ? ucfirst($tipo) : 'Post',
        };
    }
}

if (!function_exists('ppNetworkLabel')) {
    function ppNetworkLabel(?string $network): string
    {
        $network = strtolower(trim((string) $network));

        return match ($network) {
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            default => 'Rede social',
        };
    }
}

/*
========================================
CONSULTA
- pega posts ativos dos ultimos 30 dias
- traz um lote maior para permitir deduplicacao
- deduplicacao final sera feita no PHP por post_id
========================================
*/
$whereNetwork = '';
$params = [];

if ($rede !== 'todos') {
    $whereNetwork = ' AND p.network = :network ';
    $params[':network'] = $rede;
}

/*
Buscamos mais linhas que o limite final para conseguir
filtrar repetidos no PHP e ainda retornar 3 posts unicos.
*/
$limiteBusca = max($limite * 10, 20);

$sql = "
    SELECT
        m.post_id,
        COALESCE(m.curtidas, 0) AS curtidas,
        COALESCE(m.comentarios, 0) AS comentarios,
        COALESCE(m.comentarios_unicos, 0) AS comentarios_unicos,
        COALESCE(m.compartilhamentos, 0) AS compartilhamentos,
        COALESCE(m.salvamentos, 0) AS salvamentos,
        COALESCE(m.alcance, 0) AS alcance,
        COALESCE(m.impressoes, 0) AS impressoes,
        COALESCE(m.reproducoes, 0) AS reproducoes,
        COALESCE(m.score_engajamento, 0) AS score_engajamento,
        m.ultimo_evento_em,
        m.atualizado_em,

        p.network,
        p.post_tipo,
        p.caption,
        p.permalink,
        p.media_url_capa,
        p.publicado_em,
        p.status
    FROM metaverso_post_metricas_atuais m
    INNER JOIN metaverso_posts p
        ON p.id = m.post_id
    WHERE p.status = 'ativo'
      AND p.publicado_em IS NOT NULL
      AND p.publicado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      {$whereNetwork}
    ORDER BY {$sqlOrder}
    LIMIT {$limiteBusca}
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $posts = [];
    $postIdsUsados = [];

    foreach ($rows as $row) {
        $postId = (int) ($row['post_id'] ?? 0);

        if ($postId <= 0) {
            continue;
        }

        if (isset($postIdsUsados[$postId])) {
            continue;
        }

        $postIdsUsados[$postId] = true;

        $posts[] = [
            'post_id' => $postId,
            'network' => (string) ($row['network'] ?? ''),
            'network_label' => ppNetworkLabel((string) ($row['network'] ?? '')),
            'post_tipo' => (string) ($row['post_tipo'] ?? ''),
            'post_tipo_label' => ppPostTipoLabel((string) ($row['post_tipo'] ?? '')),
            'caption' => (string) ($row['caption'] ?? ''),
            'caption_resumo' => ppTextoResumo((string) ($row['caption'] ?? ''), 140),
            'permalink' => (string) ($row['permalink'] ?? ''),
            'media_url_capa' => (string) ($row['media_url_capa'] ?? ''),
            'publicado_em' => (string) ($row['publicado_em'] ?? ''),
            'curtidas' => (int) ($row['curtidas'] ?? 0),
            'comentarios' => (int) ($row['comentarios'] ?? 0),
            'comentarios_unicos' => (int) ($row['comentarios_unicos'] ?? 0),
            'compartilhamentos' => (int) ($row['compartilhamentos'] ?? 0),
            'salvamentos' => (int) ($row['salvamentos'] ?? 0),
            'alcance' => (int) ($row['alcance'] ?? 0),
            'impressoes' => (int) ($row['impressoes'] ?? 0),
            'reproducoes' => (int) ($row['reproducoes'] ?? 0),
            'score_engajamento' => (int) ($row['score_engajamento'] ?? 0),
            'ultimo_evento_em' => (string) ($row['ultimo_evento_em'] ?? ''),
            'atualizado_em' => (string) ($row['atualizado_em'] ?? ''),
        ];

        if (count($posts) >= $limite) {
            break;
        }
    }

    echo json_encode([
        'ok' => true,
        'periodo_label' => '30 dias',
        'rede' => $rede,
        'ordem' => $ordem,
        'limite' => $limite,
        'total' => count($posts),
        'posts' => $posts,
        'atualizado_em' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    error_log('[API_PIORES_POSTS] ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'erro' => 'Falha ao carregar os piores posts.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}