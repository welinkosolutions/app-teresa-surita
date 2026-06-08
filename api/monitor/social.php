<?php
declare(strict_types=1);

/*
=====================================================
ELAB SOCIAL — API MONITOR REDES
CAMINHO:
app.elab.social/api/monitor/social.php
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
HELPERS
========================================
*/
if (!function_exists('mrPeriodoRange')) {
    function mrPeriodoRange(string $janela): array
    {
        $hoje = new DateTimeImmutable('today');

        return match ($janela) {
            '24h' => [
                'inicio' => $hoje->format('Y-m-d'),
                'fim'    => $hoje->format('Y-m-d'),
            ],
            '7d' => [
                'inicio' => $hoje->modify('-6 days')->format('Y-m-d'),
                'fim'    => $hoje->format('Y-m-d'),
            ],
            '30d' => [
                'inicio' => $hoje->modify('-29 days')->format('Y-m-d'),
                'fim'    => $hoje->format('Y-m-d'),
            ],
            default => [
                'inicio' => $hoje->format('Y-m-d'),
                'fim'    => $hoje->format('Y-m-d'),
            ],
        };
    }
}

if (!function_exists('mrBuscarConexoesAtivas')) {
    function mrBuscarConexoesAtivas(PDO $pdo): array
    {
        $stmt = $pdo->query("
            SELECT network, status
            FROM metaverso_conexoes
            WHERE network IN ('instagram', 'facebook')
        ");

        $saida = [
            'instagram' => false,
            'facebook' => false,
        ];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $network = (string) ($row['network'] ?? '');
            $status = strtolower(trim((string) ($row['status'] ?? '')));

            if (isset($saida[$network]) && $status === 'ativo') {
                $saida[$network] = true;
            }
        }

        return $saida;
    }
}

if (!function_exists('mrBuscarResumoPeriodo')) {
    function mrBuscarResumoPeriodo(PDO $pdo, string $inicio, string $fim): array
    {
        $stmt = $pdo->prepare("
            SELECT
                network,
                COALESCE(SUM(comentarios_total), 0) AS comentarios_total,
                COALESCE(SUM(engajamentos_total), 0) AS engajamentos_total,
                COALESCE(SUM(seguidores_ganhos), 0) AS seguidores_ganhos,
                COALESCE(SUM(seguidores_perdidos), 0) AS seguidores_perdidos,
                COALESCE(SUM(alcance_total), 0) AS alcance_total,
                COALESCE(SUM(views_perfil), 0) AS views_perfil,
                COALESCE(SUM(views_pagina), 0) AS views_pagina,
                COALESCE(SUM(media_views_total), 0) AS media_views_total,
                COALESCE(SUM(reacoes_total), 0) AS curtidas_total,
                COALESCE(SUM(compartilhamentos_total), 0) AS compartilhamentos_total
            FROM metaverso_canal_metricas_historico
            WHERE periodo = 'day'
              AND network IN ('instagram', 'facebook')
              AND data_fim BETWEEN :inicio AND :fim
            GROUP BY network
            ORDER BY network ASC
        ");
        $stmt->execute([
            ':inicio' => $inicio,
            ':fim'    => $fim,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $base = [
            'instagram' => [
                'comentarios_total' => 0,
                'engajamentos_total' => 0,
                'seguidores_ganhos' => 0,
                'seguidores_perdidos' => 0,
                'seguidores_saldo' => 0,
                'alcance_total' => 0,
                'visualizacoes_total' => 0,
                'curtidas_total' => 0,
                'compartilhamentos_total' => 0,
            ],
            'facebook' => [
                'comentarios_total' => 0,
                'engajamentos_total' => 0,
                'seguidores_ganhos' => 0,
                'seguidores_perdidos' => 0,
                'seguidores_saldo' => 0,
                'alcance_total' => 0,
                'visualizacoes_total' => 0,
                'curtidas_total' => 0,
                'compartilhamentos_total' => 0,
            ],
        ];

        foreach ($rows as $row) {
            $network = (string) ($row['network'] ?? '');
            if (!isset($base[$network])) {
                continue;
            }

            $ganhos = (int) ($row['seguidores_ganhos'] ?? 0);
            $perdidos = (int) ($row['seguidores_perdidos'] ?? 0);
            $viewsPerfil = (int) ($row['views_perfil'] ?? 0);
            $viewsPagina = (int) ($row['views_pagina'] ?? 0);
            $mediaViews = (int) ($row['media_views_total'] ?? 0);

            $base[$network] = [
                'comentarios_total' => (int) ($row['comentarios_total'] ?? 0),
                'engajamentos_total' => (int) ($row['engajamentos_total'] ?? 0),
                'seguidores_ganhos' => $ganhos,
                'seguidores_perdidos' => $perdidos,
                'seguidores_saldo' => $ganhos - $perdidos,
                'alcance_total' => (int) ($row['alcance_total'] ?? 0),
                'visualizacoes_total' => $viewsPerfil + $viewsPagina + $mediaViews,
                'curtidas_total' => (int) ($row['curtidas_total'] ?? 0),
                'compartilhamentos_total' => (int) ($row['compartilhamentos_total'] ?? 0),
            ];
        }

        return [
            'instagram' => $base['instagram'],
            'facebook' => $base['facebook'],
            'total' => [
                'comentarios_total' => $base['instagram']['comentarios_total'] + $base['facebook']['comentarios_total'],
                'engajamentos_total' => $base['instagram']['engajamentos_total'] + $base['facebook']['engajamentos_total'],
                'seguidores_ganhos' => $base['instagram']['seguidores_ganhos'] + $base['facebook']['seguidores_ganhos'],
                'seguidores_perdidos' => $base['instagram']['seguidores_perdidos'] + $base['facebook']['seguidores_perdidos'],
                'seguidores_saldo' => $base['instagram']['seguidores_saldo'] + $base['facebook']['seguidores_saldo'],
                'alcance_total' => $base['instagram']['alcance_total'] + $base['facebook']['alcance_total'],
                'visualizacoes_total' => $base['instagram']['visualizacoes_total'] + $base['facebook']['visualizacoes_total'],
                'curtidas_total' => $base['instagram']['curtidas_total'] + $base['facebook']['curtidas_total'],
                'compartilhamentos_total' => $base['instagram']['compartilhamentos_total'] + $base['facebook']['compartilhamentos_total'],
            ],
        ];
    }
}

if (!function_exists('mrBuscarTopEPiorPost')) {
    function mrBuscarTopEPiorPost(PDO $pdo, string $inicio, string $fim): array
    {
        $stmt = $pdo->prepare("
            SELECT
                network,
                top_post_id,
                pior_post_id,
                data_inicio,
                data_fim,
                processado_em
            FROM metaverso_analitics_resumos
            WHERE network IN ('instagram', 'facebook')
              AND data_inicio = :inicio
              AND data_fim = :fim
            ORDER BY processado_em DESC, id DESC
        ");
        $stmt->execute([
            ':inicio' => $inicio,
            ':fim'    => $fim,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $saida = [
            'instagram' => [
                'top_post_id' => 0,
                'pior_post_id' => 0,
            ],
            'facebook' => [
                'top_post_id' => 0,
                'pior_post_id' => 0,
            ],
        ];

        foreach ($rows as $row) {
            $network = (string) ($row['network'] ?? '');
            if (!isset($saida[$network])) {
                continue;
            }

            if ($saida[$network]['top_post_id'] === 0) {
                $saida[$network]['top_post_id'] = (int) ($row['top_post_id'] ?? 0);
            }

            if ($saida[$network]['pior_post_id'] === 0) {
                $saida[$network]['pior_post_id'] = (int) ($row['pior_post_id'] ?? 0);
            }
        }

        return $saida;
    }
}

if (!function_exists('mrBuscarSeguidoresAtuais')) {
    function mrBuscarSeguidoresAtuais(PDO $pdo): array
    {
        $saida = [
            'instagram' => 0,
            'facebook' => 0,
        ];

        /*
        Preferencia 1:
        ultimo resumo processado por canal/rede com seguidores_fim
        */
        try {
            $stmt = $pdo->query("
                SELECT
                    network,
                    seguidores_fim,
                    processado_em,
                    data_fim,
                    id
                FROM metaverso_analitics_resumos
                WHERE network IN ('instagram', 'facebook')
                  AND seguidores_fim IS NOT NULL
                ORDER BY processado_em DESC, data_fim DESC, id DESC
            ");

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $network = (string) ($row['network'] ?? '');
                if (!isset($saida[$network])) {
                    continue;
                }

                if ($saida[$network] === 0) {
                    $saida[$network] = (int) ($row['seguidores_fim'] ?? 0);
                }
            }
        } catch (Throwable $e) {
            error_log('[API_MONITOR_SOCIAL][SEGUIDORES_ATUAIS][RESUMOS] ' . $e->getMessage());
        }

        /*
        Preferencia 2:
        ultimo snapshot diario de historico, caso o resumo nao tenha seguidores_fim
        */
        if ($saida['instagram'] === 0 || $saida['facebook'] === 0) {
            try {
                $stmt = $pdo->query("
                    SELECT
                        h.network,
                        h.seguidores_fim,
                        h.data_fim,
                        h.id
                    FROM metaverso_canal_metricas_historico h
                    INNER JOIN (
                        SELECT
                            network,
                            MAX(id) AS max_id
                        FROM metaverso_canal_metricas_historico
                        WHERE network IN ('instagram', 'facebook')
                          AND periodo = 'day'
                          AND seguidores_fim IS NOT NULL
                        GROUP BY network
                    ) ult
                        ON ult.max_id = h.id
                ");

                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $network = (string) ($row['network'] ?? '');
                    if (!isset($saida[$network])) {
                        continue;
                    }

                    if ($saida[$network] === 0) {
                        $saida[$network] = (int) ($row['seguidores_fim'] ?? 0);
                    }
                }
            } catch (Throwable $e) {
                error_log('[API_MONITOR_SOCIAL][SEGUIDORES_ATUAIS][HISTORICO] ' . $e->getMessage());
            }
        }

        return $saida;
    }
}

if (!function_exists('mrCalcularTaxaEngajamento')) {
    function mrCalcularTaxaEngajamento(int $engajamentoTotal, int $alcanceTotal): float
    {
        if ($alcanceTotal <= 0) {
            return 0.0;
        }

        return round(($engajamentoTotal / $alcanceTotal) * 100, 2);
    }
}

try {
    $range24 = mrPeriodoRange('24h');
    $range7  = mrPeriodoRange('7d');
    $range30 = mrPeriodoRange('30d');

    $resumo24 = mrBuscarResumoPeriodo($pdo, $range24['inicio'], $range24['fim']);
    $resumo7  = mrBuscarResumoPeriodo($pdo, $range7['inicio'], $range7['fim']);
    $resumo30 = mrBuscarResumoPeriodo($pdo, $range30['inicio'], $range30['fim']);

    $posts24 = mrBuscarTopEPiorPost($pdo, $range24['inicio'], $range24['fim']);
    $posts7  = mrBuscarTopEPiorPost($pdo, $range7['inicio'], $range7['fim']);
    $posts30 = mrBuscarTopEPiorPost($pdo, $range30['inicio'], $range30['fim']);

    $redesConectadas = mrBuscarConexoesAtivas($pdo);
    $seguidoresAtuais = mrBuscarSeguidoresAtuais($pdo);

    $alcanceTotal30 = (int) $resumo30['total']['alcance_total'];
    $engajamentoTotal30 = (int) $resumo30['total']['engajamentos_total'];
    $visualizacoes30 = (int) $resumo30['total']['visualizacoes_total'];
    $curtidas30 = (int) $resumo30['total']['curtidas_total'];
    $comentarios30 = (int) $resumo30['total']['comentarios_total'];
    $compartilhamentos30 = (int) $resumo30['total']['compartilhamentos_total'];
    $interacoes30 = $engajamentoTotal30;
    $taxaEngajamento30 = mrCalcularTaxaEngajamento($engajamentoTotal30, $alcanceTotal30);

    echo json_encode([
        'ok' => true,

        /*
        ========================================
        RESUMO EXECUTIVO 30 DIAS
        ========================================
        */
        'resumo_executivo' => [
            'alcance_total' => $alcanceTotal30,
            'engajamento_total' => $engajamentoTotal30,
            'visualizacoes' => $visualizacoes30,
            'taxa_engajamento' => $taxaEngajamento30,
            'interacoes' => $interacoes30,
            'curtidas' => $curtidas30,
            'comentarios' => $comentarios30,
            'compartilhamentos' => $compartilhamentos30,
        ],

        /*
        ========================================
        CAMPOS DE APOIO LEGADOS
        ========================================
        */
        'alcance_total' => $alcanceTotal30,
        'engajamento_total' => $engajamentoTotal30,
        'visualizacoes' => $visualizacoes30,
        'taxa_engajamento' => $taxaEngajamento30,
        'interacoes' => $interacoes30,
        'curtidas' => $curtidas30,
        'comentarios_total_30d' => $comentarios30,
        'compartilhamentos' => $compartilhamentos30,
        'redes_conectadas' => $redesConectadas,
        'periodo_label' => 'Últimos 30 dias',

        /*
        ========================================
        CONSOLIDADO
        ========================================
        */
        'seguidores' => [
            '24h' => $resumo24['total']['seguidores_saldo'],
            '7d'  => $resumo7['total']['seguidores_saldo'],
            '30d' => $resumo30['total']['seguidores_saldo'],
            'total_atual' => $seguidoresAtuais['instagram'] + $seguidoresAtuais['facebook'],
            'ganhos_30d' => $resumo30['total']['seguidores_ganhos'],
            'perdidos_30d' => $resumo30['total']['seguidores_perdidos'],
            'saldo_30d' => $resumo30['total']['seguidores_saldo'],
        ],

        'comentarios' => [
            '24h' => $resumo24['total']['comentarios_total'],
            '7d'  => $resumo7['total']['comentarios_total'],
            '30d' => $resumo30['total']['comentarios_total'],
        ],

        'engajamento' => [
            '24h' => $resumo24['total']['engajamentos_total'],
            '7d'  => $resumo7['total']['engajamentos_total'],
            '30d' => $resumo30['total']['engajamentos_total'],
        ],

        /*
        ========================================
        REDES
        ========================================
        */
        'redes' => [
            'instagram' => [
                'comentarios' => [
                    '24h' => $resumo24['instagram']['comentarios_total'],
                    '7d'  => $resumo7['instagram']['comentarios_total'],
                    '30d' => $resumo30['instagram']['comentarios_total'],
                ],
                'seguidores' => [
                    '24h' => $resumo24['instagram']['seguidores_saldo'],
                    '7d'  => $resumo7['instagram']['seguidores_saldo'],
                    '30d' => $resumo30['instagram']['seguidores_saldo'],
                    'total_atual' => $seguidoresAtuais['instagram'],
                    'ganhos_30d' => $resumo30['instagram']['seguidores_ganhos'],
                    'perdidos_30d' => $resumo30['instagram']['seguidores_perdidos'],
                    'saldo_30d' => $resumo30['instagram']['seguidores_saldo'],
                ],
                'engajamento' => [
                    '24h' => $resumo24['instagram']['engajamentos_total'],
                    '7d'  => $resumo7['instagram']['engajamentos_total'],
                    '30d' => $resumo30['instagram']['engajamentos_total'],
                ],
                'alcance' => [
                    '24h' => $resumo24['instagram']['alcance_total'],
                    '7d'  => $resumo7['instagram']['alcance_total'],
                    '30d' => $resumo30['instagram']['alcance_total'],
                ],
                'visualizacoes' => [
                    '24h' => $resumo24['instagram']['visualizacoes_total'],
                    '7d'  => $resumo7['instagram']['visualizacoes_total'],
                    '30d' => $resumo30['instagram']['visualizacoes_total'],
                ],
                'curtidas' => [
                    '24h' => $resumo24['instagram']['curtidas_total'],
                    '7d'  => $resumo7['instagram']['curtidas_total'],
                    '30d' => $resumo30['instagram']['curtidas_total'],
                ],
                'compartilhamentos' => [
                    '24h' => $resumo24['instagram']['compartilhamentos_total'],
                    '7d'  => $resumo7['instagram']['compartilhamentos_total'],
                    '30d' => $resumo30['instagram']['compartilhamentos_total'],
                ],
            ],
            'facebook' => [
                'comentarios' => [
                    '24h' => $resumo24['facebook']['comentarios_total'],
                    '7d'  => $resumo7['facebook']['comentarios_total'],
                    '30d' => $resumo30['facebook']['comentarios_total'],
                ],
                'seguidores' => [
                    '24h' => $resumo24['facebook']['seguidores_saldo'],
                    '7d'  => $resumo7['facebook']['seguidores_saldo'],
                    '30d' => $resumo30['facebook']['seguidores_saldo'],
                    'total_atual' => $seguidoresAtuais['facebook'],
                    'ganhos_30d' => $resumo30['facebook']['seguidores_ganhos'],
                    'perdidos_30d' => $resumo30['facebook']['seguidores_perdidos'],
                    'saldo_30d' => $resumo30['facebook']['seguidores_saldo'],
                ],
                'engajamento' => [
                    '24h' => $resumo24['facebook']['engajamentos_total'],
                    '7d'  => $resumo7['facebook']['engajamentos_total'],
                    '30d' => $resumo30['facebook']['engajamentos_total'],
                ],
                'alcance' => [
                    '24h' => $resumo24['facebook']['alcance_total'],
                    '7d'  => $resumo7['facebook']['alcance_total'],
                    '30d' => $resumo30['facebook']['alcance_total'],
                ],
                'visualizacoes' => [
                    '24h' => $resumo24['facebook']['visualizacoes_total'],
                    '7d'  => $resumo7['facebook']['visualizacoes_total'],
                    '30d' => $resumo30['facebook']['visualizacoes_total'],
                ],
                'curtidas' => [
                    '24h' => $resumo24['facebook']['curtidas_total'],
                    '7d'  => $resumo7['facebook']['curtidas_total'],
                    '30d' => $resumo30['facebook']['curtidas_total'],
                ],
                'compartilhamentos' => [
                    '24h' => $resumo24['facebook']['compartilhamentos_total'],
                    '7d'  => $resumo7['facebook']['compartilhamentos_total'],
                    '30d' => $resumo30['facebook']['compartilhamentos_total'],
                ],
            ],
        ],

        /*
        ========================================
        POSTS / FAIXAS
        ========================================
        */
        'posts' => [
            '24h' => $posts24,
            '7d'  => $posts7,
            '30d' => $posts30,
        ],

        'faixas' => [
            '24h' => $range24,
            '7d'  => $range7,
            '30d' => $range30,
        ],

        'atualizado_em' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
} catch (Throwable $e) {
    error_log('[API_MONITOR_SOCIAL] ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'erro' => 'Não foi possível carregar o monitor agora.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}