<?php
declare(strict_types=1);

/**
 * ======================================================
 * CAMINHO: /home/elab/app.elab.social/api/feed/novos.php
 * NOME: API Feed Comunidade V2 — Novos
 *
 * DESCRIÇÃO:
 * - Retorna somente itens ainda não visualizados pela pessoa logada
 * - Fonte: vw_comunidade_feed_unificado
 * - Usa comunidade_visualizacoes para filtrar novidades
 * - Exclui pessoas vinculadas à tenant/campanha como atividade comum
 * - Mantém metaverso_posts como conteúdo oficial da campanha
 * - Diversifica o lote para evitar repetição excessiva da mesma pessoa
 * - Suporta cursor para paginação infinita controlada
 * ======================================================
 */

require_once __DIR__ . '/_feed_helpers.php';

$pessoaId = feedRequireLogin();
$pdo = dbRoraima();

$feed = 'novos';
$tenantClienteId = feedTenantId($pdo, $pessoaId);
$limit = feedLimit($feed);
$maxPorSessao = feedMaxPorSessao($feed);

$cursorPublicadoEm = feedCursorPublicadoEm();
$cursorOrigemId = feedCursorOrigemId();

try {
    $whereCursor = '';

    /*
     * Buscamos mais itens brutos do que o limite final para permitir:
     * - remover pessoa vinculada à tenant/campanha
     * - diversificar por pessoa
     * - ainda devolver um lote útil ao front
     */
    $limitBusca = max($limit + 1, $limit * 6);

    $params = [
        ':tenant_cliente_id_a' => $tenantClienteId,
        ':tenant_cliente_id_b' => $tenantClienteId,
        ':tenant_cliente_id_c' => $tenantClienteId,
        ':tenant_cliente_id_d' => $tenantClienteId,
        ':tenant_cliente_id_e' => $tenantClienteId,
        ':pessoa_id_a' => $pessoaId,
        ':pessoa_id_b' => $pessoaId,
    ];

    if ($cursorPublicadoEm !== null && $cursorOrigemId > 0) {
     $whereCursor = "
    AND (
        f.publicado_em < :cursor_publicado_em_a
        OR (
            f.publicado_em = :cursor_publicado_em_b
            AND f.origem_id < :cursor_origem_id
        )
    )
";

$params[':cursor_publicado_em_a'] = $cursorPublicadoEm;
$params[':cursor_publicado_em_b'] = $cursorPublicadoEm;
$params[':cursor_origem_id'] = $cursorOrigemId;
    }

    /*
     * Regra importante:
     * - pessoas vinculadas em metaverso_clientes representam a própria campanha/tenant
     * - elas NÃO aparecem como atividade comum em social_events/game_eventos
     * - elas continuam aparecendo por metaverso_posts, como conteúdo oficial
     */
    $sql = "
        SELECT
            f.*,

            'sim' AS novo,

            CASE
                WHEN ca.status = 'concluido' THEN 'sim'
                ELSE 'nao'
            END AS acao_concluida,

            COALESCE(ci.total_interacoes, 0) AS total_interacoes,
            COALESCE(ci.total_gostei, 0) AS total_gostei,
            COALESCE(ci.total_comemorei, 0) AS total_comemorei,
            COALESCE(ci.total_apoiei, 0) AS total_apoiei,
            COALESCE(ci.total_curti, 0) AS total_curti

        FROM vw_comunidade_feed_unificado f

        LEFT JOIN comunidade_visualizacoes cv
            ON cv.tenant_cliente_id = :tenant_cliente_id_a
           AND cv.pessoa_id = :pessoa_id_a
           AND cv.origem_tipo = f.origem_tipo
           AND cv.origem_id = f.origem_id

        LEFT JOIN comunidade_acoes ca
            ON ca.tenant_cliente_id = :tenant_cliente_id_b
           AND ca.pessoa_id = :pessoa_id_b
           AND ca.origem_tipo = f.origem_tipo
           AND ca.origem_id = f.origem_id
           AND ca.status = 'concluido'

        LEFT JOIN (
            SELECT
                tenant_cliente_id,
                origem_tipo,
                origem_id,
                COUNT(*) AS total_interacoes,
                SUM(CASE WHEN tipo = 'gostei' THEN 1 ELSE 0 END) AS total_gostei,
                SUM(CASE WHEN tipo = 'comemorei' THEN 1 ELSE 0 END) AS total_comemorei,
                SUM(CASE WHEN tipo = 'apoiei' THEN 1 ELSE 0 END) AS total_apoiei,
                SUM(CASE WHEN tipo = 'curti' THEN 1 ELSE 0 END) AS total_curti
            FROM comunidade_interacoes
            WHERE tenant_cliente_id = :tenant_cliente_id_c
            GROUP BY tenant_cliente_id, origem_tipo, origem_id
        ) ci
            ON ci.tenant_cliente_id = :tenant_cliente_id_d
           AND ci.origem_tipo = f.origem_tipo
           AND ci.origem_id = f.origem_id

        WHERE f.tenant_cliente_id IN (0, 1, :tenant_cliente_id_e)
          AND cv.id IS NULL

          AND NOT (
              f.network IN ('instagram', 'facebook')
              AND COALESCE(f.link_url, '') = ''
          )

          AND NOT EXISTS (
              SELECT 1
              FROM metaverso_clientes mc
              WHERE mc.status = 'ativo'
                AND mc.pessoa_id IS NOT NULL
                AND mc.pessoa_id = f.pessoa_id
                AND f.origem_tipo <> 'metaverso_posts'
          )

          {$whereCursor}

        ORDER BY
            f.publicado_em DESC,
            f.origem_id DESC

        LIMIT " . (int) ($limitBusca + 1);

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }

    $stmt->execute();

    $rowsBrutas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $temMaisBruto = count($rowsBrutas) > $limitBusca;

    if ($temMaisBruto) {
        $rowsBrutas = array_slice($rowsBrutas, 0, $limitBusca);
    }

    /*
     * Diversificação:
     * - máximo 2 cards da mesma pessoa por lote
     * - posts oficiais não contam como repetição
     */
    $rows = feedDiversificarRows($rowsBrutas, $limit, 2);

    $items = feedNormalizarItens($rows);
    $nextCursor = feedUltimoCursor($items);

    /*
     * Se ainda havia mais registros brutos do que o lote final,
     * mantemos has_more true para o front buscar a próxima página.
     */
    $hasMore = $temMaisBruto || count($rowsBrutas) > count($rows);

    feedJson([
        'ok' => true,
        'feed' => $feed,
        'tenant_cliente_id' => $tenantClienteId,
        'pessoa_id' => $pessoaId,
        'limit' => $limit,
        'max_por_sessao' => $maxPorSessao,
        'has_more' => $hasMore,
        'next_cursor' => $nextCursor,
        'items' => $items,
    ]);
} catch (Throwable $e) {
    feedLogErro('novos', $e);

    feedJson([
        'ok' => false,
        'erro' => 'erro_feed_novos',
        'mensagem' => 'Não foi possível carregar as novidades agora.',
    ], 500);
}