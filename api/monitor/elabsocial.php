<?php
declare(strict_types=1);

/**
 * ==========================================================
 * API/MONITOR/ELABSOCIAL.PHP
 * Monitor de impacto do aplicativo no engajamento social
 * ==========================================================
 *
 * Retorna:
 * - comentários feitos por usuários Elab
 * - interações geradas por usuários Elab
 * - usuários ativos no período
 * - engajamento total das redes
 * - % de participação do app
 *
 * Query params:
 * - days=30
 * - network=instagram|facebook
 *
 * Exemplo:
 * /api/monitor/elabsocial.php?days=30
 * /api/monitor/elabsocial.php?days=30&network=instagram
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'error' => 'nao_autenticado',
        'message' => 'Sessão inválida.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

if (!function_exists('elabSocialJsonResponse')) {
    function elabSocialJsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('elabSocialPdo')) {
    function elabSocialPdo(): PDO
    {
        return dbRoraima();
    }
}

if (!function_exists('elabSocialTableExists')) {
    function elabSocialTableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
        ");
        $stmt->execute([$table]);

        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('elabSocialColumnExists')) {
    function elabSocialColumnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
        ");
        $stmt->execute([$table, $column]);

        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('elabSocialBuildDateFilter')) {
    function elabSocialBuildDateFilter(int $days, string $field = 'event_time'): string
    {
        return sprintf("%s >= NOW() - INTERVAL %d DAY", $field, $days);
    }
}

try {
    $pdo = elabSocialPdo();

    $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
    if ($days <= 0) {
        $days = 30;
    }
    if ($days > 365) {
        $days = 365;
    }

    $network = isset($_GET['network']) ? trim((string)$_GET['network']) : '';
    $network = strtolower($network);

    $allowedNetworks = ['instagram', 'facebook'];
    if ($network !== '' && !in_array($network, $allowedNetworks, true)) {
        elabSocialJsonResponse([
            'ok' => false,
            'error' => 'network_invalida',
            'message' => 'Use instagram ou facebook.',
        ], 422);
    }

    $socialEventsTable = 'social_events';
    $metricasAtuaisTable = 'metaverso_post_metricas_atuais';

    if (!elabSocialTableExists($pdo, $socialEventsTable)) {
        elabSocialJsonResponse([
            'ok' => false,
            'error' => 'tabela_ausente',
            'message' => 'Tabela social_events não encontrada.',
        ], 500);
    }

    $socialEventsHasPessoaId   = elabSocialColumnExists($pdo, $socialEventsTable, 'pessoa_id');
    $socialEventsHasEventType  = elabSocialColumnExists($pdo, $socialEventsTable, 'event_type');
    $socialEventsHasEventTime  = elabSocialColumnExists($pdo, $socialEventsTable, 'event_time');
    $socialEventsHasNetwork    = elabSocialColumnExists($pdo, $socialEventsTable, 'network');
    $socialEventsHasOrigem     = elabSocialColumnExists($pdo, $socialEventsTable, 'origem');

    if (!$socialEventsHasPessoaId || !$socialEventsHasEventType || !$socialEventsHasEventTime) {
        elabSocialJsonResponse([
            'ok' => false,
            'error' => 'estrutura_invalida',
            'message' => 'A tabela social_events precisa ter pelo menos: pessoa_id, event_type e event_time.',
        ], 500);
    }

    $where = [];
    $params = [];

    $where[] = elabSocialBuildDateFilter($days, 'event_time');

    if ($network !== '' && $socialEventsHasNetwork) {
        $where[] = 'network = :network';
        $params[':network'] = $network;
    }

    $whereSql = implode(' AND ', $where);

    /**
     * ==========================================================
     * 1) IMPACTO DIRETO DO APP
     * ==========================================================
     */
    $sqlImpacto = "
        SELECT
            COUNT(CASE WHEN pessoa_id IS NOT NULL THEN 1 END) AS interacoes_elab,
            COUNT(CASE WHEN pessoa_id IS NOT NULL AND event_type = 'comment' THEN 1 END) AS comentarios_elab,
            COUNT(CASE WHEN pessoa_id IS NOT NULL AND event_type IN ('like','reaction') THEN 1 END) AS curtidas_elab,
            COUNT(CASE WHEN pessoa_id IS NOT NULL AND event_type IN ('share','shared') THEN 1 END) AS compartilhamentos_elab,
            COUNT(CASE WHEN pessoa_id IS NOT NULL AND event_type IN ('save','saved') THEN 1 END) AS salvamentos_elab,
            COUNT(DISTINCT CASE WHEN pessoa_id IS NOT NULL THEN pessoa_id END) AS usuarios_ativos,
            COUNT(*) AS eventos_total
        FROM social_events
        WHERE {$whereSql}
    ";

    $stmtImpacto = $pdo->prepare($sqlImpacto);
    foreach ($params as $key => $value) {
        $stmtImpacto->bindValue($key, $value);
    }
    $stmtImpacto->execute();

    $impacto = $stmtImpacto->fetch(PDO::FETCH_ASSOC) ?: [];

    $interacoesElab       = (int)($impacto['interacoes_elab'] ?? 0);
    $comentariosElab      = (int)($impacto['comentarios_elab'] ?? 0);
    $curtidasElab         = (int)($impacto['curtidas_elab'] ?? 0);
    $compartilhamentosElab= (int)($impacto['compartilhamentos_elab'] ?? 0);
    $salvamentosElab      = (int)($impacto['salvamentos_elab'] ?? 0);
    $usuariosAtivos       = (int)($impacto['usuarios_ativos'] ?? 0);
    $eventosTotal         = (int)($impacto['eventos_total'] ?? 0);

    /**
     * ==========================================================
     * 2) ENGAJAMENTO TOTAL DAS REDES
     * Fonte: metaverso_post_metricas_atuais
     * ==========================================================
     */
    $engajamentoTotal = 0;
    $curtidasTotal = 0;
    $comentariosTotal = 0;
    $compartilhamentosTotal = 0;
    $salvamentosTotal = 0;
    $alcanceTotal = 0;
    $impressoesTotal = 0;
    $reproducoesTotal = 0;

    if (elabSocialTableExists($pdo, $metricasAtuaisTable)) {
        $metricasHasClienteId          = elabSocialColumnExists($pdo, $metricasAtuaisTable, 'cliente_id');
        $metricasHasNetwork            = elabSocialColumnExists($pdo, $metricasAtuaisTable, 'network');
        $metricasHasCurtidas           = elabSocialColumnExists($pdo, $metricasAtuaisTable, 'curtidas');
        $metricasHasComentarios        = elabSocialColumnExists($pdo, $metricasAtuaisTable, 'comentarios');
        $metricasHasCompartilhamentos  = elabSocialColumnExists($pdo, $metricasAtuaisTable, 'compartilhamentos');
        $metricasHasSalvamentos        = elabSocialColumnExists($pdo, $metricasAtuaisTable, 'salvamentos');
        $metricasHasAlcance            = elabSocialColumnExists($pdo, $metricasAtuaisTable, 'alcance');
        $metricasHasImpressoes         = elabSocialColumnExists($pdo, $metricasAtuaisTable, 'impressoes');
        $metricasHasReproducoes        = elabSocialColumnExists($pdo, $metricasAtuaisTable, 'reproducoes');

        $metricWhere = [];
        $metricParams = [];

        if ($network !== '' && $metricasHasNetwork) {
            $metricWhere[] = 'network = :metric_network';
            $metricParams[':metric_network'] = $network;
        }

        $metricWhereSql = $metricWhere ? ('WHERE ' . implode(' AND ', $metricWhere)) : '';

        $sqlMetricas = "
            SELECT
                " . ($metricasHasCurtidas ? "COALESCE(SUM(curtidas), 0)" : "0") . " AS curtidas_total,
                " . ($metricasHasComentarios ? "COALESCE(SUM(comentarios), 0)" : "0") . " AS comentarios_total,
                " . ($metricasHasCompartilhamentos ? "COALESCE(SUM(compartilhamentos), 0)" : "0") . " AS compartilhamentos_total,
                " . ($metricasHasSalvamentos ? "COALESCE(SUM(salvamentos), 0)" : "0") . " AS salvamentos_total,
                " . ($metricasHasAlcance ? "COALESCE(SUM(alcance), 0)" : "0") . " AS alcance_total,
                " . ($metricasHasImpressoes ? "COALESCE(SUM(impressoes), 0)" : "0") . " AS impressoes_total,
                " . ($metricasHasReproducoes ? "COALESCE(SUM(reproducoes), 0)" : "0") . " AS reproducoes_total
            FROM {$metricasAtuaisTable}
            {$metricWhereSql}
        ";

        $stmtMetricas = $pdo->prepare($sqlMetricas);
        foreach ($metricParams as $key => $value) {
            $stmtMetricas->bindValue($key, $value);
        }
        $stmtMetricas->execute();

        $metricas = $stmtMetricas->fetch(PDO::FETCH_ASSOC) ?: [];

        $curtidasTotal          = (int)($metricas['curtidas_total'] ?? 0);
        $comentariosTotal       = (int)($metricas['comentarios_total'] ?? 0);
        $compartilhamentosTotal = (int)($metricas['compartilhamentos_total'] ?? 0);
        $salvamentosTotal       = (int)($metricas['salvamentos_total'] ?? 0);
        $alcanceTotal           = (int)($metricas['alcance_total'] ?? 0);
        $impressoesTotal        = (int)($metricas['impressoes_total'] ?? 0);
        $reproducoesTotal       = (int)($metricas['reproducoes_total'] ?? 0);

        $engajamentoTotal = $curtidasTotal + $comentariosTotal + $compartilhamentosTotal + $salvamentosTotal;
    }

    /**
     * ==========================================================
     * 3) BREAKDOWN POR TIPO DE EVENTO DO APP
     * ==========================================================
     */
    $sqlBreakdown = "
        SELECT
            event_type,
            COUNT(*) AS total
        FROM social_events
        WHERE {$whereSql}
          AND pessoa_id IS NOT NULL
        GROUP BY event_type
        ORDER BY total DESC
    ";

    $stmtBreakdown = $pdo->prepare($sqlBreakdown);
    foreach ($params as $key => $value) {
        $stmtBreakdown->bindValue($key, $value);
    }
    $stmtBreakdown->execute();

    $breakdown = [];
    while ($row = $stmtBreakdown->fetch(PDO::FETCH_ASSOC)) {
        $breakdown[] = [
            'event_type' => (string)($row['event_type'] ?? ''),
            'total'      => (int)($row['total'] ?? 0),
        ];
    }

    /**
     * ==========================================================
     * 4) ORIGEM DAS AÇÕES DO APP (se existir coluna origem)
     * ==========================================================
     */
    $origens = [];

    if ($socialEventsHasOrigem) {
        $sqlOrigens = "
            SELECT
                origem,
                COUNT(*) AS total
            FROM social_events
            WHERE {$whereSql}
              AND pessoa_id IS NOT NULL
            GROUP BY origem
            ORDER BY total DESC
        ";

        $stmtOrigens = $pdo->prepare($sqlOrigens);
        foreach ($params as $key => $value) {
            $stmtOrigens->bindValue($key, $value);
        }
        $stmtOrigens->execute();

        while ($row = $stmtOrigens->fetch(PDO::FETCH_ASSOC)) {
            $origens[] = [
                'origem' => (string)($row['origem'] ?? 'desconhecida'),
                'total'  => (int)($row['total'] ?? 0),
            ];
        }
    }

    /**
     * ==========================================================
     * 5) % DE IMPACTO
     * ==========================================================
     */
    $percentualImpacto = 0.0;
    if ($engajamentoTotal > 0) {
        $percentualImpacto = round(($interacoesElab / $engajamentoTotal) * 100, 2);
    }

    $percentualComentarios = 0.0;
    if ($comentariosTotal > 0) {
        $percentualComentarios = round(($comentariosElab / $comentariosTotal) * 100, 2);
    }

    elabSocialJsonResponse([
        'ok' => true,
        'periodo' => [
            'days' => $days,
            'label' => sprintf('Últimos %d dias', $days),
        ],
        'filtros' => [
            'network' => $network !== '' ? $network : 'all',
        ],
        'resumo' => [
            'comentarios_elab' => $comentariosElab,
            'interacoes_elab' => $interacoesElab,
            'usuarios_ativos' => $usuariosAtivos,
            'eventos_rastreados_total' => $eventosTotal,
            'impacto_engajamento_percentual' => $percentualImpacto,
            'impacto_comentarios_percentual' => $percentualComentarios,
        ],
        'engajamento_redes' => [
            'engajamento_total' => $engajamentoTotal,
            'curtidas_total' => $curtidasTotal,
            'comentarios_total' => $comentariosTotal,
            'compartilhamentos_total' => $compartilhamentosTotal,
            'salvamentos_total' => $salvamentosTotal,
            'alcance_total' => $alcanceTotal,
            'impressoes_total' => $impressoesTotal,
            'reproducoes_total' => $reproducoesTotal,
        ],
        'elab' => [
            'comentarios' => $comentariosElab,
            'curtidas' => $curtidasElab,
            'compartilhamentos' => $compartilhamentosElab,
            'salvamentos' => $salvamentosElab,
            'interacoes' => $interacoesElab,
        ],
        'breakdown' => $breakdown,
        'origens' => $origens,
        'meta' => [
            'gerado_em' => date('Y-m-d H:i:s'),
            'fonte_eventos' => 'social_events',
            'fonte_metricas' => elabSocialTableExists($pdo, $metricasAtuaisTable) ? $metricasAtuaisTable : null,
        ],
    ]);
} catch (Throwable $e) {
    elabSocialJsonResponse([
        'ok' => false,
        'error' => 'erro_interno',
        'message' => $e->getMessage(),
    ], 500);
}