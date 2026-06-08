<?php
declare(strict_types=1);

/**
 * ======================================================
 * CAMINHO: /home/elab/app.elab.social/api/feed/_feed_helpers.php
 * NOME: Helpers API Feed Comunidade V2
 *
 * DESCRIÇÃO:
 * - Centraliza sessão, conexão, JSON e montagem do feed
 * - Usado por /api/feed/todos.php, amigos.php e novos.php
 * - Exclui do feed de usuário comum pessoas vinculadas à tenant/campanha
 * - Mantém metaverso_posts como conteúdo oficial da campanha
 * - Oferece diversificação por pessoa para evitar feed repetitivo
 * ======================================================
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

function feedJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function feedRequireLogin(): int
{
    if (empty($_SESSION['pessoa_id'])) {
        feedJson([
            'ok' => false,
            'erro' => 'nao_autenticado',
            'mensagem' => 'Sessão expirada. Faça login novamente.',
        ], 401);
    }

    return (int) $_SESSION['pessoa_id'];
}

function feedTenantId(PDO $pdo, int $pessoaId): int
{
    if (!empty($_SESSION['tenant_cliente_id'])) {
        return (int) $_SESSION['tenant_cliente_id'];
    }

    if (!empty($_SESSION['cliente_id'])) {
        return (int) $_SESSION['cliente_id'];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT tenant_cliente_id
            FROM pessoas
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$pessoaId]);
        $tenant = (int) $stmt->fetchColumn();

        if ($tenant > 0) {
            return $tenant;
        }
    } catch (Throwable $e) {
        error_log('[FEED_API_TENANT] ' . $e->getMessage());
    }

    /*
     * Fallback atual do ambiente:
     * - social_events está com tenant_cliente_id fixo = 1 na view
     * - game_eventos aparece usando tenant 2 em testes
     */
    return 2;
}

function feedLimit(string $feed): int
{
    $limit = (int) ($_GET['limit'] ?? 10);

    if ($limit <= 0) {
        $limit = 10;
    }

    if ($limit > 20) {
        $limit = 20;
    }

    return $limit;
}

function feedMaxPorSessao(string $feed): int
{
    if ($feed === 'novos') {
        return 30;
    }

    return 50;
}

function feedCursorPublicadoEm(): ?string
{
    $cursor = trim((string) ($_GET['cursor_publicado_em'] ?? ''));

    if ($cursor === '') {
        return null;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $cursor)) {
        return null;
    }

    return $cursor;
}

function feedCursorOrigemId(): int
{
    return max(0, (int) ($_GET['cursor_origem_id'] ?? 0));
}

/**
 * Retorna os IDs de pessoas que representam a própria tenant/campanha.
 *
 * Exemplo atual:
 * - metaverso_clientes.id = 1
 * - metaverso_clientes.pessoa_id = 7160
 * - pessoa 7160 = Teresa Surita
 *
 * Essas pessoas não devem aparecer como atividade comum do feed.
 * Elas só devem aparecer como conteúdo oficial via metaverso_posts.
 */
function feedPessoasTenantIds(PDO $pdo): array
{
    static $cache = null;

    if (is_array($cache)) {
        return $cache;
    }

    try {
        $stmt = $pdo->query("
            SELECT pessoa_id
            FROM metaverso_clientes
            WHERE status = 'ativo'
              AND pessoa_id IS NOT NULL
        ");

        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $ids = array_values(array_unique(array_filter(
            $ids,
            static fn (int $id): bool => $id > 0
        )));

        $cache = $ids;

        return $cache;
    } catch (Throwable $e) {
        error_log('[FEED_API_TENANT_PESSOAS] ' . $e->getMessage());

        $cache = [];

        return [];
    }
}

/**
 * Gera um filtro SQL para excluir atividades comuns da tenant.
 *
 * Uso:
 * $filtroTenant = feedFiltroExcluirPessoasTenant($pdo, 'v');
 *
 * SQL:
 * {$filtroTenant['sql']}
 *
 * Params:
 * array_merge($params, $filtroTenant['params'])
 *
 * Regra:
 * - Exclui social_events/game_eventos/etc quando pessoa_id pertence à tenant
 * - NÃO exclui metaverso_posts, porque ali é post oficial da campanha
 */
function feedFiltroExcluirPessoasTenant(PDO $pdo, string $alias = 'v'): array
{
    $ids = feedPessoasTenantIds($pdo);

    if (!$ids) {
        return [
            'sql' => '',
            'params' => [],
        ];
    }

    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'v';
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    return [
        'sql' => "
            AND NOT (
                {$alias}.origem_tipo <> 'metaverso_posts'
                AND {$alias}.pessoa_id IN ({$placeholders})
            )
        ",
        'params' => $ids,
    ];
}

/**
 * Diversifica os registros do feed para evitar repetição de uma mesma pessoa.
 *
 * Recomendação nas APIs:
 * - buscar um lote bruto maior: $limitBusca = $limit * 5
 * - aplicar feedDiversificarRows($rows, $limit, 2)
 *
 * Posts oficiais/metaverso não contam como repetição de pessoa.
 */
function feedDiversificarRows(array $rows, int $limit, int $maxPorPessoa = 2): array
{
    $limit = max(1, $limit);
    $maxPorPessoa = max(1, $maxPorPessoa);

    $saida = [];
    $contadorPessoa = [];

    foreach ($rows as $row) {
        if (count($saida) >= $limit) {
            break;
        }

        $origemTipo = (string) ($row['origem_tipo'] ?? '');
        $grupo = (string) ($row['grupo'] ?? '');
        $pessoaId = isset($row['pessoa_id']) ? (int) $row['pessoa_id'] : 0;

        /*
         * Posts oficiais sempre podem entrar.
         * Eles representam a campanha, não uma ação de usuário comum.
         */
        if ($origemTipo === 'metaverso_posts' || $grupo === 'metaverso' || $pessoaId <= 0) {
            $saida[] = $row;
            continue;
        }

        $contadorPessoa[$pessoaId] = ($contadorPessoa[$pessoaId] ?? 0) + 1;

        if ($contadorPessoa[$pessoaId] > $maxPorPessoa) {
            continue;
        }

        $saida[] = $row;
    }

    return $saida;
}

function feedNomeExibicao(array $item): string
{
    $nome = trim((string) ($item['nome'] ?? ''));
    $apelido = trim((string) ($item['apelido'] ?? ''));
    $chamarPor = trim((string) ($item['chamar_por'] ?? ''));

    if ($chamarPor === 'apelido' && $apelido !== '') {
        return $apelido;
    }

    if ($nome === '') {
        return '';
    }

    $partes = preg_split('/\s+/', $nome);
    $curto = trim(implode(' ', array_slice($partes ?: [], 0, 2)));

    return $curto !== '' ? $curto : $nome;
}

function feedAvatarTexto(array $item): string
{
    $grupo = (string) ($item['grupo'] ?? '');
    $tipo = (string) ($item['tipo'] ?? '');
    $network = (string) ($item['network'] ?? '');

    if ($grupo === 'metaverso') {
        if ($network === 'instagram' || $tipo === 'instagram') {
            return '◎';
        }

        if ($network === 'facebook' || $tipo === 'facebook') {
            return 'f';
        }

        return '📣';
    }

    if ($grupo === 'game') {
        if ($tipo === 'moedas') {
            return '🪙';
        }

        if ($tipo === 'xp') {
            return '⚡';
        }

        if ($tipo === 'nivel_up') {
            return '🚀';
        }

        if ($tipo === 'medalha' || $tipo === 'conquista') {
            return '🏅';
        }

        return '🎮';
    }

    $nome = feedNomeExibicao($item);

    if ($nome !== '') {
        return mb_strtoupper(mb_substr($nome, 0, 1, 'UTF-8'), 'UTF-8');
    }

    return '🙂';
}

function feedNormalizarItem(array $row): array
{
    $origemTipo = (string) ($row['origem_tipo'] ?? '');
    $origemId = (int) ($row['origem_id'] ?? 0);
    $grupo = (string) ($row['grupo'] ?? '');
    $tipo = (string) ($row['tipo'] ?? '');
    $network = (string) ($row['network'] ?? '');
    $nomeExibicao = feedNomeExibicao($row);

    $titulo = trim((string) ($row['titulo'] ?? ''));
    $texto = trim((string) ($row['texto'] ?? ''));

    if ($titulo === '') {
        $titulo = 'Novidade';
    }

    if ($texto === '') {
        $texto = 'Algo novo aconteceu na comunidade.';
    }

    $publicadoEm = (string) ($row['publicado_em'] ?? '');

    return [
        'id' => $origemTipo . ':' . $origemId,
        'origem_tipo' => $origemTipo,
        'origem_id' => $origemId,

        'tenant_cliente_id' => (int) ($row['tenant_cliente_id'] ?? 0),
        'pessoa_id' => isset($row['pessoa_id']) && $row['pessoa_id'] !== null
            ? (int) $row['pessoa_id']
            : null,

        'grupo' => $grupo,
        'tipo' => $tipo,

        'titulo' => $titulo,
        'texto' => $texto,

        'nome' => (string) ($row['nome'] ?? ''),
        'apelido' => (string) ($row['apelido'] ?? ''),
        'nome_exibicao' => $nomeExibicao,
        'perfil' => (string) ($row['perfil'] ?? ''),

        'avatar_texto' => feedAvatarTexto($row),

        'network' => $network,
        'external_post_id' => (string) ($row['external_post_id'] ?? ''),

        'link_url' => (string) ($row['link_url'] ?? ''),
        'cta_label' => (string) ($row['cta_label'] ?? ''),

        'recompensa_moedas' => (int) ($row['recompensa_moedas'] ?? 0),
        'recompensa_xp' => (int) ($row['recompensa_xp'] ?? 0),

        'lottie_url' => (string) ($row['lottie_url'] ?? ''),
        'som_url' => (string) ($row['som_url'] ?? ''),
        'animacao_tipo' => (string) ($row['animacao_tipo'] ?? 'fade_up'),

        'publicado_em' => $publicadoEm,

        'novo' => (string) ($row['novo'] ?? 'nao'),
        'acao_concluida' => (string) ($row['acao_concluida'] ?? 'nao'),

        'total_interacoes' => (int) ($row['total_interacoes'] ?? 0),
        'total_gostei' => (int) ($row['total_gostei'] ?? 0),
        'total_comemorei' => (int) ($row['total_comemorei'] ?? 0),
        'total_apoiei' => (int) ($row['total_apoiei'] ?? 0),
        'total_curti' => (int) ($row['total_curti'] ?? 0),

        'cursor' => [
            'publicado_em' => $publicadoEm,
            'origem_id' => $origemId,
        ],
    ];
}

function feedNormalizarItens(array $rows): array
{
    return array_map(
        static fn (array $row): array => feedNormalizarItem($row),
        $rows
    );
}

function feedUltimoCursor(array $items): ?array
{
    if (!$items) {
        return null;
    }

    $ultimo = end($items);

    if (!is_array($ultimo) || empty($ultimo['cursor']) || !is_array($ultimo['cursor'])) {
        return null;
    }

    return $ultimo['cursor'];
}

function feedLogErro(string $contexto, Throwable $e): void
{
    error_log('[COMUNIDADE_FEED_API][' . $contexto . '] ' . $e->getMessage());
}