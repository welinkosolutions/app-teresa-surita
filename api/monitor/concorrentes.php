<?php
declare(strict_types=1);

/*
=====================================================
ELAB SOCIAL — API MONITOR CONCORRENTES
CAMINHO:
app.elab.social/api/monitor/concorrentes.php
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
$periodo = (int) ($_GET['periodo'] ?? 30);
$limiteConcorrentes = (int) ($_GET['limite_concorrentes'] ?? 5);
$limitePosts = (int) ($_GET['limite_posts'] ?? 5);

if (!in_array($periodo, [7, 15, 30], true)) {
    $periodo = 30;
}

if ($limiteConcorrentes < 1) {
    $limiteConcorrentes = 5;
}
if ($limiteConcorrentes > 10) {
    $limiteConcorrentes = 10;
}

if ($limitePosts < 1) {
    $limitePosts = 5;
}
if ($limitePosts > 10) {
    $limitePosts = 10;
}

$periodoLabel = $periodo . ' dias';
$dataInicio = date('Y-m-d', strtotime('-' . ($periodo - 1) . ' days'));
$dataFim = date('Y-m-d');

/*
========================================
HELPERS
========================================
*/
if (!function_exists('concTextoResumo')) {
    function concTextoResumo(?string $texto, int $limite = 140): string
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

if (!function_exists('concPostTipoLabel')) {
    function concPostTipoLabel(?string $tipo): string
    {
        $tipo = strtolower(trim((string) $tipo));

        return match ($tipo) {
            'reel' => 'Reel',
            'video' => 'Vídeo',
            'story', 'stories' => 'Story',
            'carousel', 'carrossel' => 'Carrossel',
            'image', 'imagem', 'photo', 'foto' => 'Imagem',
            default => $tipo !== '' ? ucfirst($tipo) : 'Post',
        };
    }
}

if (!function_exists('concSafeInt')) {
    function concSafeInt(mixed $value): int
    {
        return (int) round((float) ($value ?? 0));
    }
}

if (!function_exists('concSafeFloat')) {
    function concSafeFloat(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }
}

if (!function_exists('concDiffPercent')) {
    function concDiffPercent(float $baseCliente, float $valorConcorrente): float
    {
        if ($baseCliente <= 0) {
            return 0.0;
        }

        return round((($valorConcorrente - $baseCliente) / $baseCliente) * 100, 2);
    }
}

/*
========================================
CLIENTE OFICIAL INSTAGRAM
========================================
*/
$clienteInstagram = [
    'cliente_id' => 1,
    'canal_id' => 0,
    'nome' => 'Teresa Surita',
    'username' => 'teresasurita',
    'network' => 'instagram',
    'seguidores_total' => 0,
    'posts_total' => 0,
    'engajamento_total' => 0,
    'comentarios_total' => 0,
    'compartilhamentos_total' => 0,
    'alcance_total' => 0,
    'impressoes_total' => 0,
    'reproducoes_total' => 0,
    'curtidas_total' => 0,
    'salvamentos_total' => 0,
    'media_por_post' => 0,
    'taxa_engajamento_por_post' => 0,
    'top_post_id' => 0,
    'pior_post_id' => 0,
];

try {
    /*
    ----------------------------------------
    CANAL OFICIAL INSTAGRAM
    ----------------------------------------
    */
    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.cliente_id,
            c.nome_exibicao,
            c.username,
            c.network
        FROM metaverso_canais c
        WHERE c.network = 'instagram'
          AND c.ativo = 'sim'
          AND c.principal = 'sim'
        ORDER BY c.id ASC
        LIMIT 1
    ");
    $stmt->execute();

    $canalInstagram = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($canalInstagram) {
        $clienteInstagram['cliente_id'] = concSafeInt($canalInstagram['cliente_id'] ?? 1);
        $clienteInstagram['canal_id'] = concSafeInt($canalInstagram['id'] ?? 0);
        $clienteInstagram['nome'] = (string) ($canalInstagram['nome_exibicao'] ?? 'Teresa Surita');
        $clienteInstagram['username'] = (string) ($canalInstagram['username'] ?? 'teresasurita');
        $clienteInstagram['network'] = (string) ($canalInstagram['network'] ?? 'instagram');

        /*
        ----------------------------------------
        SEGUIDORES MAIS RECENTES
        ----------------------------------------
        */
        $stmtSeguidores = $pdo->prepare("
            SELECT
                seguidores_fim
            FROM metaverso_analitics_resumos
            WHERE canal_id = ?
              AND network = 'instagram'
            ORDER BY processado_em DESC, data_fim DESC, id DESC
            LIMIT 1
        ");
        $stmtSeguidores->execute([
            $clienteInstagram['canal_id']
        ]);

        $resumoSeguidores = $stmtSeguidores->fetch(PDO::FETCH_ASSOC);
        if ($resumoSeguidores) {
            $clienteInstagram['seguidores_total'] = concSafeInt($resumoSeguidores['seguidores_fim'] ?? 0);
        }

        /*
        ----------------------------------------
        POSTS OFICIAIS DO CLIENTE NO PERIODO
        ----------------------------------------
        */
        $stmtClienteAgg = $pdo->prepare("
            SELECT
                COUNT(DISTINCT p.id) AS posts_total,
                COALESCE(SUM(pm.curtidas), 0) AS curtidas_total,
                COALESCE(SUM(pm.comentarios), 0) AS comentarios_total,
                COALESCE(SUM(pm.compartilhamentos), 0) AS compartilhamentos_total,
                COALESCE(SUM(pm.salvamentos), 0) AS salvamentos_total,
                COALESCE(SUM(pm.alcance), 0) AS alcance_total,
                COALESCE(SUM(pm.impressoes), 0) AS impressoes_total,
                COALESCE(SUM(pm.reproducoes), 0) AS reproducoes_total,
                COALESCE(SUM(pm.score_engajamento), 0) AS engajamento_total
            FROM metaverso_posts p
            LEFT JOIN metaverso_post_metricas_atuais pm
                ON pm.post_id = p.id
            WHERE p.canal_id = ?
              AND p.network = 'instagram'
              AND DATE(COALESCE(p.publicado_em, p.criado_em)) >= ?
              AND DATE(COALESCE(p.publicado_em, p.criado_em)) <= ?
        ");
        $stmtClienteAgg->execute([
            $clienteInstagram['canal_id'],
            $dataInicio,
            $dataFim
        ]);

        $clienteAgg = $stmtClienteAgg->fetch(PDO::FETCH_ASSOC);

        if ($clienteAgg) {
            $clienteInstagram['posts_total'] = concSafeInt($clienteAgg['posts_total'] ?? 0);
            $clienteInstagram['curtidas_total'] = concSafeInt($clienteAgg['curtidas_total'] ?? 0);
            $clienteInstagram['comentarios_total'] = concSafeInt($clienteAgg['comentarios_total'] ?? 0);
            $clienteInstagram['compartilhamentos_total'] = concSafeInt($clienteAgg['compartilhamentos_total'] ?? 0);
            $clienteInstagram['salvamentos_total'] = concSafeInt($clienteAgg['salvamentos_total'] ?? 0);
            $clienteInstagram['alcance_total'] = concSafeInt($clienteAgg['alcance_total'] ?? 0);
            $clienteInstagram['impressoes_total'] = concSafeInt($clienteAgg['impressoes_total'] ?? 0);
            $clienteInstagram['reproducoes_total'] = concSafeInt($clienteAgg['reproducoes_total'] ?? 0);
            $clienteInstagram['engajamento_total'] = concSafeInt($clienteAgg['engajamento_total'] ?? 0);

            $clienteInstagram['media_por_post'] = $clienteInstagram['posts_total'] > 0
                ? round($clienteInstagram['engajamento_total'] / $clienteInstagram['posts_total'], 2)
                : 0.0;

            $clienteInstagram['taxa_engajamento_por_post'] = $clienteInstagram['alcance_total'] > 0
                ? round(($clienteInstagram['engajamento_total'] / $clienteInstagram['alcance_total']) * 100, 2)
                : 0.0;
        }

        /*
        ----------------------------------------
        TOP POST E PIOR POST DO CLIENTE
        ----------------------------------------
        */
        $stmtTopPior = $pdo->prepare("
            SELECT
                p.id,
                COALESCE(pm.score_engajamento, 0) AS score_engajamento
            FROM metaverso_posts p
            LEFT JOIN metaverso_post_metricas_atuais pm
                ON pm.post_id = p.id
            WHERE p.canal_id = ?
              AND p.network = 'instagram'
              AND DATE(COALESCE(p.publicado_em, p.criado_em)) >= ?
              AND DATE(COALESCE(p.publicado_em, p.criado_em)) <= ?
            ORDER BY score_engajamento DESC, p.id DESC
        ");
        $stmtTopPior->execute([
            $clienteInstagram['canal_id'],
            $dataInicio,
            $dataFim
        ]);

        $postsClientePeriodo = $stmtTopPior->fetchAll(PDO::FETCH_ASSOC);

        if ($postsClientePeriodo) {
            $primeiro = $postsClientePeriodo[0];
            $ultimo = $postsClientePeriodo[count($postsClientePeriodo) - 1];

            $clienteInstagram['top_post_id'] = concSafeInt($primeiro['id'] ?? 0);
            $clienteInstagram['pior_post_id'] = concSafeInt($ultimo['id'] ?? 0);
        }
    }
} catch (Throwable $e) {
    error_log('[API_CONCORRENTES][CLIENTE_INSTAGRAM] ' . $e->getMessage());
}

/*
========================================
CONCORRENTES ATIVOS
========================================
*/
$stmt = $pdo->prepare("
    SELECT
        id,
        network,
        external_id,
        nome,
        username,
        categoria,
        cidade
    FROM metaverso_concorrentes
    WHERE ativo = 'sim'
      AND network = 'instagram'
    ORDER BY nome ASC
");
$stmt->execute();

$concorrentesBase = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$concorrentesBase) {
    echo json_encode([
        'ok' => true,
        'periodo_label' => $periodoLabel,
        'network' => 'instagram',
        'cliente_instagram' => $clienteInstagram,
        'cliente' => [
            'nome' => $clienteInstagram['nome'],
            'network' => $clienteInstagram['network'],
            'seguidores_total' => $clienteInstagram['seguidores_total'],
            'posts_total' => $clienteInstagram['posts_total'],
            'score_total' => $clienteInstagram['engajamento_total'],
            'score_medio' => $clienteInstagram['media_por_post'],
            'username' => $clienteInstagram['username'],
        ],
        'resumo' => [
            'concorrentes_monitorados' => 0,
            'lider_seguidores' => null,
            'lider_engajamento' => null,
            'mais_ativo' => null,
        ],
        'concorrentes' => [],
        'posts_destaque' => [],
        'atualizado_em' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$concorrenteIds = array_map(
    static fn(array $row): int => (int) $row['id'],
    $concorrentesBase
);

$inConcorrentes = implode(',', array_fill(0, count($concorrenteIds), '?'));

/*
========================================
SNAPSHOT MAIS RECENTE POR CONCORRENTE
========================================
*/
$snapshotsMap = [];

try {
    $sqlSnapshots = "
        SELECT s.*
        FROM metaverso_concorrente_snapshots s
        INNER JOIN (
            SELECT
                concorrente_id,
                MAX(COALESCE(coletado_em, atualizado_em, criado_em)) AS max_data
            FROM metaverso_concorrente_snapshots
            WHERE concorrente_id IN ($inConcorrentes)
            GROUP BY concorrente_id
        ) ult
            ON ult.concorrente_id = s.concorrente_id
           AND COALESCE(s.coletado_em, s.atualizado_em, s.criado_em) = ult.max_data
    ";

    $stmt = $pdo->prepare($sqlSnapshots);
    $stmt->execute($concorrenteIds);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $snap) {
        $cid = concSafeInt($snap['concorrente_id'] ?? 0);
        if ($cid > 0) {
            $snapshotsMap[$cid] = $snap;
        }
    }
} catch (Throwable $e) {
    error_log('[API_CONCORRENTES][SNAPSHOTS] ' . $e->getMessage());
}

/*
========================================
AGREGADO DE POSTS + METRICAS DO PERIODO
========================================
*/
$agregadoMap = [];

try {
    $sqlAgregado = "
        SELECT
            p.concorrente_id,
            COUNT(DISTINCT p.id) AS posts_total,
            COALESCE(SUM(m.curtidas), 0) AS curtidas_total,
            COALESCE(SUM(m.comentarios), 0) AS comentarios_total,
            COALESCE(SUM(m.compartilhamentos), 0) AS compartilhamentos_total,
            COALESCE(SUM(m.reproducoes), 0) AS reproducoes_total,
            COALESCE(SUM(m.score_engajamento), 0) AS score_total
        FROM metaverso_concorrente_posts p
        LEFT JOIN metaverso_concorrente_metricas m
            ON m.concorrente_post_id = p.id
           AND m.data_referencia >= ?
           AND m.data_referencia <= ?
        WHERE p.concorrente_id IN ($inConcorrentes)
          AND DATE(p.publicado_em) >= ?
          AND DATE(p.publicado_em) <= ?
        GROUP BY p.concorrente_id
    ";

    $params = array_merge(
        [$dataInicio, $dataFim],
        $concorrenteIds,
        [$dataInicio, $dataFim]
    );

    $stmt = $pdo->prepare($sqlAgregado);
    $stmt->execute($params);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cid = concSafeInt($row['concorrente_id'] ?? 0);
        if ($cid > 0) {
            $agregadoMap[$cid] = [
                'posts_total' => concSafeInt($row['posts_total'] ?? 0),
                'curtidas_total' => concSafeInt($row['curtidas_total'] ?? 0),
                'comentarios_total' => concSafeInt($row['comentarios_total'] ?? 0),
                'compartilhamentos_total' => concSafeInt($row['compartilhamentos_total'] ?? 0),
                'reproducoes_total' => concSafeInt($row['reproducoes_total'] ?? 0),
                'score_total' => concSafeInt($row['score_total'] ?? 0),
            ];
        }
    }
} catch (Throwable $e) {
    error_log('[API_CONCORRENTES][AGREGADO] ' . $e->getMessage());
}

/*
========================================
MONTA RANKING DE CONCORRENTES
========================================
*/
$concorrentes = [];

foreach ($concorrentesBase as $base) {
    $cid = concSafeInt($base['id'] ?? 0);
    $snap = $snapshotsMap[$cid] ?? [];
    $agg = $agregadoMap[$cid] ?? [];

    $seguidoresTotal = concSafeInt(
        $snap['seguidores_total']
        ?? $snap['followers_count']
        ?? $snap['followers_total']
        ?? $snap['seguidores']
        ?? 0
    );

    $fotoUrl = (string) (
        $snap['foto_url']
        ?? $snap['profile_picture_url']
        ?? $snap['avatar_url']
        ?? $snap['imagem_url']
        ?? ''
    );

    $postsTotalPeriodo = concSafeInt($agg['posts_total'] ?? 0);
    $scoreTotal = concSafeInt($agg['score_total'] ?? 0);
    $curtidasTotal = concSafeInt($agg['curtidas_total'] ?? 0);
    $comentariosTotal = concSafeInt($agg['comentarios_total'] ?? 0);
    $compartilhamentosTotal = concSafeInt($agg['compartilhamentos_total'] ?? 0);
    $reproducoesTotal = concSafeInt($agg['reproducoes_total'] ?? 0);

    $scoreMedio = $postsTotalPeriodo > 0
        ? round($scoreTotal / $postsTotalPeriodo, 2)
        : 0.0;

    $concorrentes[] = [
        'concorrente_id' => $cid,
        'nome' => (string) ($base['nome'] ?? ''),
        'username' => (string) ($base['username'] ?? ''),
        'network' => (string) ($base['network'] ?? 'instagram'),
        'foto_url' => $fotoUrl,

        'seguidores_total' => $seguidoresTotal,
        'posts_total' => $postsTotalPeriodo,
        'score_total' => $scoreTotal,
        'score_medio' => $scoreMedio,
        'curtidas_total' => $curtidasTotal,
        'comentarios_total' => $comentariosTotal,
        'compartilhamentos_total' => $compartilhamentosTotal,
        'reproducoes_total' => $reproducoesTotal,

        'comparativo_cliente' => [
            'seguidores_diff_percent' => concDiffPercent(
                (float) $clienteInstagram['seguidores_total'],
                (float) $seguidoresTotal
            ),
            'posts_diff_percent' => concDiffPercent(
                (float) $clienteInstagram['posts_total'],
                (float) $postsTotalPeriodo
            ),
            'engajamento_diff_percent' => concDiffPercent(
                (float) $clienteInstagram['engajamento_total'],
                (float) $scoreTotal
            ),
            'media_diff_percent' => concDiffPercent(
                (float) $clienteInstagram['media_por_post'],
                (float) $scoreMedio
            ),
        ],

        'selo' => null,
    ];
}

/*
========================================
DEFINIR LIDERES / SELOS
========================================
*/
$liderSeguidores = null;
$liderEngajamento = null;
$maisAtivo = null;

if ($concorrentes) {
    $tmp = $concorrentes;

    usort($tmp, static fn(array $a, array $b): int =>
        ($b['seguidores_total'] <=> $a['seguidores_total'])
    );
    $liderSeguidores = $tmp[0] ?? null;

    usort($tmp, static fn(array $a, array $b): int =>
        ($b['score_total'] <=> $a['score_total'])
    );
    $liderEngajamento = $tmp[0] ?? null;

    usort($tmp, static fn(array $a, array $b): int =>
        ($b['posts_total'] <=> $a['posts_total'])
    );
    $maisAtivo = $tmp[0] ?? null;

    foreach ($concorrentes as &$item) {
        if ($liderSeguidores && $item['concorrente_id'] === $liderSeguidores['concorrente_id']) {
            $item['selo'] = 'lider_seguidores';
            continue;
        }

        if ($liderEngajamento && $item['concorrente_id'] === $liderEngajamento['concorrente_id']) {
            $item['selo'] = 'lider_engajamento';
            continue;
        }

        if ($maisAtivo && $item['concorrente_id'] === $maisAtivo['concorrente_id']) {
            $item['selo'] = 'mais_ativo';
            continue;
        }

        if ($item['posts_total'] >= 3 && $item['score_medio'] > 0 && $item['score_medio'] < 120) {
            $item['selo'] = 'baixo_rendimento';
        }
    }
    unset($item);
}

/*
========================================
ORDENA TOP CONCORRENTES
========================================
*/
usort($concorrentes, static function (array $a, array $b): int {
    if ($b['score_total'] !== $a['score_total']) {
        return $b['score_total'] <=> $a['score_total'];
    }

    if ($b['seguidores_total'] !== $a['seguidores_total']) {
        return $b['seguidores_total'] <=> $a['seguidores_total'];
    }

    return strcmp((string) $a['nome'], (string) $b['nome']);
});

$concorrentesTop = array_slice($concorrentes, 0, $limiteConcorrentes);

/*
========================================
TOP POSTS DOS CONCORRENTES
========================================
*/
$postsDestaque = [];

try {
    $sqlPosts = "
        SELECT
            c.id AS concorrente_id,
            c.nome AS concorrente_nome,
            c.username AS concorrente_username,
            p.id AS post_id,
            p.post_tipo,
            p.caption,
            p.permalink,
            p.media_url_capa,
            p.publicado_em,
            COALESCE(m.curtidas, 0) AS curtidas,
            COALESCE(m.comentarios, 0) AS comentarios,
            COALESCE(m.compartilhamentos, 0) AS compartilhamentos,
            COALESCE(m.reproducoes, 0) AS reproducoes,
            COALESCE(m.score_engajamento, 0) AS score_engajamento
        FROM metaverso_concorrente_posts p
        INNER JOIN metaverso_concorrentes c
            ON c.id = p.concorrente_id
        LEFT JOIN metaverso_concorrente_metricas m
            ON m.concorrente_post_id = p.id
           AND m.data_referencia >= ?
           AND m.data_referencia <= ?
        WHERE c.ativo = 'sim'
          AND c.network = 'instagram'
          AND DATE(p.publicado_em) >= ?
          AND DATE(p.publicado_em) <= ?
        ORDER BY
            score_engajamento DESC,
            comentarios DESC,
            curtidas DESC,
            p.id DESC
        LIMIT {$limitePosts}
    ";

    $stmt = $pdo->prepare($sqlPosts);
    $stmt->execute([
        $dataInicio,
        $dataFim,
        $dataInicio,
        $dataFim
    ]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $postsDestaque[] = [
            'concorrente_id' => concSafeInt($row['concorrente_id'] ?? 0),
            'concorrente_nome' => (string) ($row['concorrente_nome'] ?? ''),
            'concorrente_username' => (string) ($row['concorrente_username'] ?? ''),
            'post_id' => concSafeInt($row['post_id'] ?? 0),
            'post_tipo' => (string) ($row['post_tipo'] ?? ''),
            'post_tipo_label' => concPostTipoLabel((string) ($row['post_tipo'] ?? '')),
            'caption_resumo' => concTextoResumo((string) ($row['caption'] ?? ''), 140),
            'permalink' => (string) ($row['permalink'] ?? ''),
            'media_url_capa' => (string) ($row['media_url_capa'] ?? ''),
            'publicado_em' => (string) ($row['publicado_em'] ?? ''),
            'curtidas' => concSafeInt($row['curtidas'] ?? 0),
            'comentarios' => concSafeInt($row['comentarios'] ?? 0),
            'compartilhamentos' => concSafeInt($row['compartilhamentos'] ?? 0),
            'reproducoes' => concSafeInt($row['reproducoes'] ?? 0),
            'score_engajamento' => concSafeInt($row['score_engajamento'] ?? 0),
        ];
    }
} catch (Throwable $e) {
    error_log('[API_CONCORRENTES][POSTS] ' . $e->getMessage());
}

/*
========================================
RESPOSTA
========================================
*/
echo json_encode([
    'ok' => true,
    'periodo_label' => $periodoLabel,
    'network' => 'instagram',
    'cliente_instagram' => $clienteInstagram,
    'cliente' => [
        'nome' => $clienteInstagram['nome'],
        'username' => $clienteInstagram['username'],
        'network' => $clienteInstagram['network'],
        'seguidores_total' => $clienteInstagram['seguidores_total'],
        'posts_total' => $clienteInstagram['posts_total'],
        'score_total' => $clienteInstagram['engajamento_total'],
        'score_medio' => $clienteInstagram['media_por_post'],
    ],
    'resumo' => [
        'concorrentes_monitorados' => count($concorrentesBase),
        'lider_seguidores' => $liderSeguidores ? [
            'concorrente_id' => $liderSeguidores['concorrente_id'],
            'nome' => $liderSeguidores['nome'],
            'username' => $liderSeguidores['username'],
            'seguidores' => $liderSeguidores['seguidores_total'],
        ] : null,
        'lider_engajamento' => $liderEngajamento ? [
            'concorrente_id' => $liderEngajamento['concorrente_id'],
            'nome' => $liderEngajamento['nome'],
            'username' => $liderEngajamento['username'],
            'score_total' => $liderEngajamento['score_total'],
        ] : null,
        'mais_ativo' => $maisAtivo ? [
            'concorrente_id' => $maisAtivo['concorrente_id'],
            'nome' => $maisAtivo['nome'],
            'username' => $maisAtivo['username'],
            'posts_periodo' => $maisAtivo['posts_total'],
        ] : null,
    ],
    'concorrentes' => $concorrentesTop,
    'posts_destaque' => $postsDestaque,
    'atualizado_em' => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

exit;