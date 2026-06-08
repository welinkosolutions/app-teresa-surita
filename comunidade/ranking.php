<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$gameEstadoServicePath = '/home/elab/public_html/core/games/GameEstadoService.php';
if (is_file($gameEstadoServicePath)) {
    require_once $gameEstadoServicePath;
}

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

$pdo = dbRoraima();
$pessoaId = (int) $_SESSION['pessoa_id'];

function ranking_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function ranking_schema_banco(): string
{
    return 'elab_roraima';
}

function ranking_dominio_atual(): string
{
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));

    return preg_replace('/:\d+$/', '', $host) ?: '';
}

function ranking_tabela_existe(PDO $pdo, string $tabela): bool
{
    static $cache = [];

    $tabela = trim($tabela);

    if ($tabela === '') {
        return false;
    }

    if (array_key_exists($tabela, $cache)) {
        return $cache[$tabela];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([ranking_schema_banco(), $tabela]);

        $cache[$tabela] = ((int) $stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        error_log('[ranking] Falha ao verificar tabela/view ' . $tabela . ': ' . $e->getMessage());
        $cache[$tabela] = false;
    }

    return $cache[$tabela];
}

function ranking_coluna_existe(PDO $pdo, string $tabela, string $coluna): bool
{
    static $cache = [];

    $tabela = trim($tabela);
    $coluna = trim($coluna);
    $key = $tabela . '.' . $coluna;

    if ($tabela === '' || $coluna === '') {
        return false;
    }

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([ranking_schema_banco(), $tabela, $coluna]);

        $cache[$key] = ((int) $stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        error_log('[ranking] Falha ao verificar coluna ' . $tabela . '.' . $coluna . ': ' . $e->getMessage());
        $cache[$key] = false;
    }

    return $cache[$key];
}

function ranking_tenant_id_atual(PDO $pdo): int
{
    static $tenantId = 0;

    if ($tenantId > 0) {
        return $tenantId;
    }

    $dominio = ranking_dominio_atual();

    if ($dominio === '') {
        return 0;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id
            FROM clientes_elab
            WHERE dominio = ?
              AND status = 'ativo'
            LIMIT 1
        ");
        $stmt->execute([$dominio]);

        $tenantId = (int) ($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        error_log('[ranking] Falha ao buscar tenant: ' . $e->getMessage());
        $tenantId = 0;
    }

    return $tenantId;
}

function ranking_buscar_pessoa(PDO $pdo, int $pessoaId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            id,
            nome,
            apelido,
            usuario_handle,
            chamar_por,
            sexo,
            criado_em,
            status
        FROM pessoas
        WHERE id = ?
          AND status = 'ativo'
        LIMIT 1
    ");
    $stmt->execute([$pessoaId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function ranking_nome_exibicao(array $pessoa): string
{
    $nome = trim((string) ($pessoa['nome'] ?? 'Participante'));
    $apelido = trim((string) ($pessoa['apelido'] ?? ''));
    $chamarPor = (string) ($pessoa['chamar_por'] ?? 'nome');

    if ($chamarPor === 'apelido' && $apelido !== '') {
        return $apelido;
    }

    if ($nome === '') {
        return 'Participante';
    }

    $partes = preg_split('/\s+/', $nome);
    $curto = implode(' ', array_slice($partes ?: [], 0, 2));

    return $curto !== '' ? $curto : $nome;
}

function ranking_nome_podio(array $pessoa): string
{
    $nome = ranking_nome_exibicao($pessoa);
    $partes = preg_split('/\s+/', trim($nome));

    if (!$partes || count($partes) <= 1) {
        return $nome;
    }

    $primeiro = $partes[0] ?? '';
    $ultimo = $partes[count($partes) - 1] ?? '';

    if ($primeiro === '' || $ultimo === '') {
        return $nome;
    }

    return $primeiro . ' ' . mb_substr($ultimo, 0, 1, 'UTF-8') . '.';
}

function ranking_avatar_usuario(array $pessoa): string
{
    $sexo = strtoupper(trim((string) ($pessoa['sexo'] ?? '')));

    if ($sexo === 'M') {
        return '/assets/animations/users/user-man.webp?v=2';
    }

    if ($sexo === 'F') {
        return '/assets/animations/users/user-woman.webp?v=2';
    }

    return '/assets/animations/users/user.webp?v=2';
}

function ranking_periodo_atual(): string
{
    $periodo = strtolower(trim((string) ($_GET['periodo'] ?? 'semana')));
    $permitidos = ['semana', 'mes', 'geral'];

    return in_array($periodo, $permitidos, true) ? $periodo : 'semana';
}

function ranking_periodo_titulo(string $periodo): string
{
    return match ($periodo) {
        'mes' => 'Ranking do mês',
        'geral' => 'Ranking geral',
        default => 'Ranking da semana',
    };
}

function ranking_niveis_fallback(): array
{
    return [
        ['nivel' => 1, 'nome' => 'Iniciante', 'xp_minimo' => 0, 'icone_url' => '/assets/statics/awards/001-startup.svg'],
        ['nivel' => 2, 'nome' => 'Participante', 'xp_minimo' => 10, 'icone_url' => '/assets/statics/awards/007-coin.svg'],
        ['nivel' => 3, 'nome' => 'Liderança', 'xp_minimo' => 30, 'icone_url' => '/assets/statics/awards/008-shield.svg'],
        ['nivel' => 4, 'nome' => 'Ativador', 'xp_minimo' => 75, 'icone_url' => '/assets/statics/awards/006-energy-bar.svg'],
        ['nivel' => 5, 'nome' => 'Competidor', 'xp_minimo' => 150, 'icone_url' => '/assets/statics/awards/009-swords.svg'],
        ['nivel' => 6, 'nome' => 'Bronze', 'xp_minimo' => 300, 'icone_url' => '/assets/statics/awards/016-star.svg'],
        ['nivel' => 7, 'nome' => 'Prata', 'xp_minimo' => 600, 'icone_url' => '/assets/statics/awards/002-7.svg'],
        ['nivel' => 8, 'nome' => 'Ouro', 'xp_minimo' => 1200, 'icone_url' => '/assets/statics/awards/022-rating.svg'],
        ['nivel' => 9, 'nome' => 'Elite', 'xp_minimo' => 2500, 'icone_url' => '/assets/statics/awards/029-vip.svg'],
        ['nivel' => 10, 'nome' => 'Lendário', 'xp_minimo' => 10000, 'icone_url' => '/assets/statics/awards/023-shooting-star.svg'],
    ];
}

function ranking_buscar_niveis_banco(PDO $pdo): array
{
    $fallback = ranking_niveis_fallback();

    if (!ranking_tabela_existe($pdo, 'game_niveis')) {
        return $fallback;
    }

    try {
        $where = '1=1';

        if (ranking_coluna_existe($pdo, 'game_niveis', 'ativo')) {
            $where .= " AND ativo = 'sim'";
        }

        $stmt = $pdo->query("
            SELECT *
            FROM game_niveis
            WHERE {$where}
            ORDER BY nivel ASC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            return $fallback;
        }

        $niveis = [];

        foreach ($rows as $row) {
            $nivel = (int) ($row['nivel'] ?? $row['id'] ?? 0);

            if ($nivel <= 0) {
                continue;
            }

            $niveis[] = [
                'nivel' => $nivel,
                'nome' => (string) ($row['nome'] ?? ('Nível ' . $nivel)),
                'descricao' => (string) ($row['descricao'] ?? ''),
                'xp_minimo' => (int) ($row['xp_minimo'] ?? $row['xp_requerido'] ?? 0),
                'icone_url' => (string) ($row['icone_url'] ?? ''),
            ];
        }

        return $niveis ?: $fallback;
    } catch (Throwable $e) {
        error_log('[ranking] Falha ao buscar game_niveis: ' . $e->getMessage());

        return $fallback;
    }
}

function ranking_preparar_nivel_atual(array $niveis, int $nivelAtual, int $xpTotal): array
{
    $porNivel = [];

    foreach ($niveis as $nivel) {
        $n = (int) ($nivel['nivel'] ?? 0);

        if ($n > 0) {
            $porNivel[$n] = $nivel;
        }
    }

    ksort($porNivel);

    if (!$porNivel) {
        $porNivel = [
            1 => ['nivel' => 1, 'nome' => 'Iniciante', 'xp_minimo' => 0],
            2 => ['nivel' => 2, 'nome' => 'Participante', 'xp_minimo' => 10],
        ];
    }

    $nivelAtual = max(1, $nivelAtual);

    if (!isset($porNivel[$nivelAtual])) {
        foreach ($porNivel as $n => $nivel) {
            if ($xpTotal >= (int) ($nivel['xp_minimo'] ?? 0)) {
                $nivelAtual = (int) $n;
            }
        }
    }

    $atual = $porNivel[$nivelAtual] ?? reset($porNivel);
    $proximo = $porNivel[$nivelAtual + 1] ?? $atual;

    $xpBase = (int) ($atual['xp_minimo'] ?? 0);
    $xpProximo = (int) ($proximo['xp_minimo'] ?? $xpBase);
    $isMax = $nivelAtual >= max(array_keys($porNivel));

    if ($xpProximo <= $xpBase && !$isMax) {
        $xpProximo = $xpBase + 1;
    }

    $xpNoNivel = max(0, $xpTotal - $xpBase);
    $xpNecessarioNivel = max(1, $xpProximo - $xpBase);
    $percentual = $isMax ? 100 : min(100, max(0, (int) round(($xpNoNivel / $xpNecessarioNivel) * 100)));

    return [
        'nivel_atual' => $nivelAtual,
        'nome' => (string) ($atual['nome'] ?? 'Iniciante'),
        'xp_base' => $xpBase,
        'xp_proximo' => $xpProximo,
        'falta_xp' => $isMax ? 0 : max(0, $xpProximo - $xpTotal),
        'percentual' => $percentual,
        'is_max' => $isMax,
    ];
}

function ranking_obter_ou_criar_estado_usuario(PDO $pdo, int $tenantId, int $pessoaId): array
{
    $estadoPadrao = [
        'moedas_saldo' => 0,
        'moedas_total_ganhas' => 0,
        'xp_total' => 0,
        'nivel_atual' => 1,
        'atualizado_em' => null,
    ];

    if ($tenantId <= 0 || $pessoaId <= 0) {
        return $estadoPadrao;
    }

    try {
        if (class_exists('GameEstadoService')) {
            $estadoService = new GameEstadoService($pdo);
            $estado = $estadoService->obterOuCriarEstado($tenantId, $pessoaId);

            if (is_array($estado)) {
                return array_merge($estadoPadrao, $estado);
            }
        }
    } catch (Throwable $e) {
        error_log('[ranking] GameEstadoService falhou ao obter/criar estado: ' . $e->getMessage());
    }

    if (!ranking_tabela_existe($pdo, 'game_usuario_estado')) {
        return $estadoPadrao;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                moedas_saldo,
                moedas_total_ganhas,
                xp_total,
                nivel_atual,
                atualizado_em
            FROM game_usuario_estado
            WHERE tenant_cliente_id = ?
              AND pessoa_id = ?
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $pessoaId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return array_merge($estadoPadrao, $row);
        }
    } catch (Throwable $e) {
        error_log('[ranking] Falha ao buscar estado game do usuario: ' . $e->getMessage());
    }

    return $estadoPadrao;
}

function ranking_buscar_ranking_base(PDO $pdo, int $tenantId, array $niveis): array
{
    $temTenantPessoas = ranking_coluna_existe($pdo, 'pessoas', 'tenant_cliente_id');
    $temEstadoGame = ranking_tabela_existe($pdo, 'game_usuario_estado');

    $params = [];

    $where = "
        p.status = 'ativo'
    ";

    if (ranking_tabela_existe($pdo, 'metaverso_clientes')) {
        $where .= "
            AND NOT EXISTS (
                SELECT 1
                FROM metaverso_clientes mc
                WHERE mc.status = 'ativo'
                  AND mc.pessoa_id IS NOT NULL
                  AND mc.pessoa_id = p.id
            )
        ";
    }

    if ($temTenantPessoas && $tenantId > 0) {
        $where .= " AND p.tenant_cliente_id = ? ";
        $params[] = $tenantId;
    }

    if (!$temEstadoGame) {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    p.id,
                    p.nome,
                    p.apelido,
                    p.chamar_por,
                    p.sexo,
                    0 AS estado_moedas_saldo,
                    0 AS estado_moedas_total_ganhas,
                    0 AS estado_xp_total,
                    1 AS estado_nivel_atual,
                    NULL AS estado_atualizado_em
                FROM pessoas p
                WHERE {$where}
                ORDER BY p.id ASC
                LIMIT 1000
            ");
            $stmt->execute($params);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[ranking] Falha ao buscar ranking sem estado game: ' . $e->getMessage());
            $rows = [];
        }
    } else {
        $joinEstado = "
            LEFT JOIN game_usuario_estado e
                   ON e.pessoa_id = p.id
        ";

        if ($tenantId > 0) {
            $joinEstado .= " AND e.tenant_cliente_id = ? ";
            array_unshift($params, $tenantId);
        }

        try {
            $stmt = $pdo->prepare("
                SELECT
                    p.id,
                    p.nome,
                    p.apelido,
                    p.chamar_por,
                    p.sexo,
                    COALESCE(e.moedas_saldo, 0) AS estado_moedas_saldo,
                    COALESCE(e.moedas_total_ganhas, 0) AS estado_moedas_total_ganhas,
                    COALESCE(e.xp_total, 0) AS estado_xp_total,
                    COALESCE(e.nivel_atual, 1) AS estado_nivel_atual,
                    e.atualizado_em AS estado_atualizado_em
                FROM pessoas p
                {$joinEstado}
                WHERE {$where}
                ORDER BY
                    estado_xp_total DESC,
                    estado_moedas_total_ganhas DESC,
                    estado_moedas_saldo DESC,
                    p.nome ASC,
                    p.id ASC
                LIMIT 1000
            ");
            $stmt->execute($params);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[ranking] Falha ao buscar ranking base pelo game_usuario_estado: ' . $e->getMessage());
            $rows = [];
        }
    }

    $unicos = [];

    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);

        if ($id <= 0 || isset($unicos[$id])) {
            continue;
        }

        $moedasSaldo = (int) ($row['estado_moedas_saldo'] ?? 0);
        $moedasTotal = (int) ($row['estado_moedas_total_ganhas'] ?? 0);
        $xpTotal = (int) ($row['estado_xp_total'] ?? 0);
        $nivelAtualEstado = max(1, (int) ($row['estado_nivel_atual'] ?? 1));

        $nivelGame = ranking_preparar_nivel_atual($niveis, $nivelAtualEstado, $xpTotal);

        $item = $row;
        $item['pessoa_id'] = $id;
        $item['nome_exibicao'] = ranking_nome_exibicao($row);
        $item['nome_podio'] = ranking_nome_podio($row);
        $item['avatar'] = ranking_avatar_usuario($row);
        $item['moedas'] = max(0, $moedasSaldo);
        $item['moedas_total'] = max(0, $moedasTotal);
        $item['xp_total'] = max(0, $xpTotal);
        $item['nivel_atual'] = (int) ($nivelGame['nivel_atual'] ?? $nivelAtualEstado);
        $item['nivel_nome'] = (string) ($nivelGame['nome'] ?? 'Iniciante');
        $item['nivel_percentual'] = (int) ($nivelGame['percentual'] ?? 0);
        $item['posicao'] = 0;

        $unicos[$id] = $item;
    }

    $ranking = array_values($unicos);

    usort($ranking, static function (array $a, array $b): int {
        $xpB = (int) ($b['xp_total'] ?? 0);
        $xpA = (int) ($a['xp_total'] ?? 0);

        if ($xpB !== $xpA) {
            return $xpB <=> $xpA;
        }

        $moedasTotalB = (int) ($b['moedas_total'] ?? 0);
        $moedasTotalA = (int) ($a['moedas_total'] ?? 0);

        if ($moedasTotalB !== $moedasTotalA) {
            return $moedasTotalB <=> $moedasTotalA;
        }

        $moedasB = (int) ($b['moedas'] ?? 0);
        $moedasA = (int) ($a['moedas'] ?? 0);

        if ($moedasB !== $moedasA) {
            return $moedasB <=> $moedasA;
        }

        $nomeA = (string) ($a['nome_exibicao'] ?? '');
        $nomeB = (string) ($b['nome_exibicao'] ?? '');

        $cmpNome = strcasecmp($nomeA, $nomeB);

        if ($cmpNome !== 0) {
            return $cmpNome;
        }

        return ((int) ($a['pessoa_id'] ?? 0)) <=> ((int) ($b['pessoa_id'] ?? 0));
    });

    foreach ($ranking as $index => &$item) {
        $item['posicao'] = $index + 1;
    }

    unset($item);

    return $ranking;
}

function ranking_encontrar_usuario(array $ranking, int $pessoaId): ?array
{
    foreach ($ranking as $item) {
        if ((int) ($item['pessoa_id'] ?? 0) === $pessoaId) {
            return $item;
        }
    }

    return null;
}

function ranking_alvo_acima(array $ranking, ?array $usuarioRank): ?array
{
    if (!$usuarioRank) {
        return null;
    }

    $posicao = (int) ($usuarioRank['posicao'] ?? 0);

    if ($posicao <= 1) {
        return null;
    }

    foreach ($ranking as $item) {
        if ((int) ($item['posicao'] ?? 0) === ($posicao - 1)) {
            return $item;
        }
    }

    return null;
}

function ranking_calcular_progresso(?array $usuarioRank, ?array $alvoAcima): int
{
    if (!$usuarioRank) {
        return 5;
    }

    if (!$alvoAcima) {
        return 100;
    }

    $meuXp = max(0, (int) ($usuarioRank['xp_total'] ?? 0));
    $xpAlvo = max(1, (int) ($alvoAcima['xp_total'] ?? 1));

    if ($xpAlvo <= 0) {
        return 5;
    }

    $progresso = (int) round(($meuXp / $xpAlvo) * 100);

    return min(98, max(5, $progresso));
}

function ranking_formatar_numero(int $valor): string
{
    return number_format($valor, 0, ',', '.');
}

function ranking_posicao_label(int $posicao): string
{
    return $posicao > 0 ? ranking_formatar_numero($posicao) . 'º' : '-';
}

$pessoa = ranking_buscar_pessoa($pdo, $pessoaId);

if (!$pessoa) {
    header('Location: /index.php');
    exit;
}

$tenantClienteId = ranking_tenant_id_atual($pdo);
$periodo = ranking_periodo_atual();
$periodoTitulo = ranking_periodo_titulo($periodo);

$niveis = array_slice(ranking_buscar_niveis_banco($pdo), 0, 10);
$ranking = ranking_buscar_ranking_base($pdo, $tenantClienteId, $niveis);

$top3 = array_slice($ranking, 0, 3);
$listaRanking = array_slice($ranking, 3, 7);

$usuarioRank = ranking_encontrar_usuario($ranking, $pessoaId);

if (!$usuarioRank) {
    $estadoUsuario = ranking_obter_ou_criar_estado_usuario($pdo, $tenantClienteId, $pessoaId);

    $moedasSaldo = (int) ($estadoUsuario['moedas_saldo'] ?? 0);
    $moedasTotal = (int) ($estadoUsuario['moedas_total_ganhas'] ?? 0);
    $xpTotal = (int) ($estadoUsuario['xp_total'] ?? 0);
    $nivelAtualEstado = max(1, (int) ($estadoUsuario['nivel_atual'] ?? 1));

    $nivelGame = ranking_preparar_nivel_atual($niveis, $nivelAtualEstado, $xpTotal);

    $usuarioRank = [
        'pessoa_id' => $pessoaId,
        'nome_exibicao' => ranking_nome_exibicao($pessoa),
        'nome_podio' => ranking_nome_podio($pessoa),
        'avatar' => ranking_avatar_usuario($pessoa),
        'moedas' => max(0, $moedasSaldo),
        'moedas_total' => max(0, $moedasTotal),
        'xp_total' => max(0, $xpTotal),
        'nivel_atual' => (int) ($nivelGame['nivel_atual'] ?? $nivelAtualEstado),
        'nivel_nome' => (string) ($nivelGame['nome'] ?? 'Iniciante'),
        'posicao' => count($ranking) + 1,
    ];
}

$alvoAcima = ranking_alvo_acima($ranking, $usuarioRank);
$progressoUltrapassagem = ranking_calcular_progresso($usuarioRank, $alvoAcima);

$faltamXp = 0;
$textoUltrapassagem = 'Complete missões para começar a subir no ranking.';

if ($usuarioRank && $alvoAcima) {
    $faltamXp = max(1, ((int) ($alvoAcima['xp_total'] ?? 0)) - ((int) ($usuarioRank['xp_total'] ?? 0)) + 1);
    $textoUltrapassagem = 'Faltam <strong>' . ranking_formatar_numero($faltamXp) . ' XP</strong> para passar ' . ranking_h((string) ($alvoAcima['nome_podio'] ?? 'alguém')) . '.';
} elseif ($usuarioRank && (int) ($usuarioRank['posicao'] ?? 0) === 1) {
    $textoUltrapassagem = 'Você está no topo. Continue defendendo sua posição!';
}

$podioVazio = count($top3) === 0;

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Ranking da Comunidade | elab.social</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <link rel="stylesheet" href="/assets/css/footer-v2.css">
    <link rel="stylesheet" href="/assets/css/comunidade-ranking-v2.css?v=1">
</head>
<body class="ranking-page-body">

<main class="ranking-page">

    <header class="ranking-page-header">
        <h1 class="ranking-page-title">Ranking da Comunidade</h1>
        <p class="ranking-page-subtitle"><?= ranking_h($periodoTitulo) ?> · moedas, XP e níveis em jogo.</p>
    </header>

    <nav class="ranking-tabs" aria-label="Período do ranking">
        <a class="ranking-tab <?= $periodo === 'semana' ? 'is-active' : '' ?>" href="/comunidade/ranking.php?periodo=semana">Semana</a>
        <a class="ranking-tab <?= $periodo === 'mes' ? 'is-active' : '' ?>" href="/comunidade/ranking.php?periodo=mes">Mês</a>
        <a class="ranking-tab <?= $periodo === 'geral' ? 'is-active' : '' ?>" href="/comunidade/ranking.php?periodo=geral">Geral</a>
    </nav>

    <section class="ranking-hero" aria-label="Pódio da comunidade">
        <div class="ranking-hero-glow"></div>

        <?php if ($podioVazio): ?>
            <div class="ranking-empty">
                <strong>O ranking está começando.</strong>
                <span>Complete missões, ganhe moedas e seja um dos primeiros a aparecer no pódio.</span>
            </div>
        <?php else: ?>
            <?php
            $primeiro = $top3[0] ?? null;
            $segundo = $top3[1] ?? null;
            $terceiro = $top3[2] ?? null;
            ?>

            <div class="ranking-podium">
                <?php if ($segundo): ?>
                    <article class="ranking-podium-card ranking-podium-card--second">
                        <img
                            class="ranking-podium-asset ranking-podium-asset--second"
                            src="/assets/podium/podio2.webp?v=1"
                            alt=""
                            width="40"
                            height="40"
                            loading="eager"
                        >

                        <div class="ranking-avatar-frame">
                            <img class="ranking-avatar" src="<?= ranking_h((string) $segundo['avatar']) ?>" alt="">
                        </div>

                        <span class="ranking-level-chip">Nível <?= ranking_formatar_numero((int) $segundo['nivel_atual']) ?></span>

                        <div class="ranking-podium-base">
                            <strong class="ranking-podium-name"><?= ranking_h((string) $segundo['nome_podio']) ?></strong>
                            <span class="ranking-podium-metric"><?= ranking_formatar_numero((int) $segundo['moedas']) ?> moedas</span>
                            <span class="ranking-podium-xp"><?= ranking_formatar_numero((int) $segundo['xp_total']) ?> XP</span>
                        </div>
                    </article>
                <?php else: ?>
                    <article class="ranking-podium-card ranking-podium-card--second">
                        <div class="ranking-podium-base">
                            <strong class="ranking-podium-name">2º lugar</strong>
                            <span class="ranking-podium-metric">Em breve</span>
                        </div>
                    </article>
                <?php endif; ?>

                <?php if ($primeiro): ?>
                    <article class="ranking-podium-card ranking-podium-card--first">
                        <img
                            class="ranking-podium-asset ranking-podium-asset--first"
                            src="/assets/podium/crown.webp?v=1"
                            alt=""
                            width="50"
                            height="auto"
                            loading="eager"
                        >

                        <div class="ranking-avatar-frame">
                            <img class="ranking-avatar" src="<?= ranking_h((string) $primeiro['avatar']) ?>" alt="">
                        </div>

                        <span class="ranking-level-chip">Nível <?= ranking_formatar_numero((int) $primeiro['nivel_atual']) ?></span>

                        <div class="ranking-podium-base">
                            <strong class="ranking-podium-name"><?= ranking_h((string) $primeiro['nome_podio']) ?></strong>
                            <span class="ranking-podium-metric"><?= ranking_formatar_numero((int) $primeiro['moedas']) ?> moedas</span>
                            <span class="ranking-podium-xp"><?= ranking_formatar_numero((int) $primeiro['xp_total']) ?> XP</span>
                        </div>
                    </article>
                <?php endif; ?>

                <?php if ($terceiro): ?>
                    <article class="ranking-podium-card ranking-podium-card--third">
                        <img
                            class="ranking-podium-asset ranking-podium-asset--third"
                            src="/assets/podium/podio3.webp?v=1"
                            alt=""
                            width="40"
                            height="40"
                            loading="eager"
                        >

                        <div class="ranking-avatar-frame">
                            <img class="ranking-avatar" src="<?= ranking_h((string) $terceiro['avatar']) ?>" alt="">
                        </div>

                        <span class="ranking-level-chip">Nível <?= ranking_formatar_numero((int) $terceiro['nivel_atual']) ?></span>

                        <div class="ranking-podium-base">
                            <strong class="ranking-podium-name"><?= ranking_h((string) $terceiro['nome_podio']) ?></strong>
                            <span class="ranking-podium-metric"><?= ranking_formatar_numero((int) $terceiro['moedas']) ?> moedas</span>
                            <span class="ranking-podium-xp"><?= ranking_formatar_numero((int) $terceiro['xp_total']) ?> XP</span>
                        </div>
                    </article>
                <?php else: ?>
                    <article class="ranking-podium-card ranking-podium-card--third">
                        <div class="ranking-podium-base">
                            <strong class="ranking-podium-name">3º lugar</strong>
                            <span class="ranking-podium-metric">Em breve</span>
                        </div>
                    </article>
                <?php endif; ?>
            </div>

            <div class="ranking-podium-line"></div>

            <div class="ranking-chase">
                <div class="ranking-chase-avatar">
                    <img src="<?= ranking_h((string) ($usuarioRank['avatar'] ?? ranking_avatar_usuario($pessoa))) ?>" alt="">
                </div>

                <div class="ranking-chase-main">
                    <p class="ranking-chase-text"><?= $textoUltrapassagem ?></p>

                    <div class="ranking-progress" style="--ranking-progress-width: <?= max(5, min(100, $progressoUltrapassagem)) ?>%;">
                        <div class="ranking-progress-fill"></div>
                    </div>
                </div>

                <div class="ranking-chase-avatar">
                    <img src="<?= ranking_h((string) ($alvoAcima['avatar'] ?? ($primeiro['avatar'] ?? ranking_avatar_usuario($pessoa)))) ?>" alt="">
                </div>
            </div>
        <?php endif; ?>
    </section>

    <section class="ranking-content" aria-label="Lista do ranking">
        <?php if (!$listaRanking): ?>
            <div class="ranking-empty">
                <strong>A comunidade ainda está aquecendo.</strong>
                <span>Assim que mais pessoas pontuarem, a lista completa aparece aqui.</span>
            </div>
        <?php else: ?>
            <div class="ranking-list">
                <?php foreach ($listaRanking as $index => $item): ?>
                    <?php $isCurrentUser = (int) ($item['pessoa_id'] ?? 0) === $pessoaId; ?>

                    <article
                        class="ranking-list-card <?= $isCurrentUser ? 'is-current-user' : '' ?>"
                        style="--ranking-row-index: <?= (int) $index ?>;"
                    >
                        <div class="ranking-position"><?= ranking_formatar_numero((int) $item['posicao']) ?>.</div>

                        <div class="ranking-list-avatar">
                            <img src="<?= ranking_h((string) $item['avatar']) ?>" alt="">
                        </div>

                        <div class="ranking-list-info">
                            <div class="ranking-list-name">
                                <span><?= ranking_h((string) $item['nome_exibicao']) ?></span>
                                <?php if ($isCurrentUser): ?>
                                    <em class="ranking-you-pill">Você</em>
                                <?php endif; ?>
                            </div>

                            <div class="ranking-list-meta">
                                <span>Nível <?= ranking_formatar_numero((int) $item['nivel_atual']) ?></span>
                                <span><?= ranking_h((string) $item['nivel_nome']) ?></span>
                            </div>
                        </div>

                        <div class="ranking-list-score">
                            <strong class="ranking-list-coins"><?= ranking_formatar_numero((int) $item['moedas']) ?> moedas</strong>
                            <span class="ranking-list-xp"><?= ranking_formatar_numero((int) $item['xp_total']) ?> XP</span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <article class="ranking-user-card" aria-label="Sua posição no ranking">
            <div class="ranking-user-position">
                <?= ranking_posicao_label((int) ($usuarioRank['posicao'] ?? 0)) ?>
            </div>

            <div class="ranking-user-avatar">
                <img src="<?= ranking_h((string) ($usuarioRank['avatar'] ?? ranking_avatar_usuario($pessoa))) ?>" alt="">
            </div>

            <div class="ranking-user-info">
                <strong class="ranking-user-kicker">Você</strong>
                <span class="ranking-user-description">
                    Você está em <?= ranking_posicao_label((int) ($usuarioRank['posicao'] ?? 0)) ?> lugar.
                </span>

                <div class="ranking-user-metrics">
                    <strong class="ranking-user-coins"><?= ranking_formatar_numero((int) ($usuarioRank['moedas'] ?? 0)) ?> moedas</strong>
                    <span class="ranking-user-level">Nível <?= ranking_formatar_numero((int) ($usuarioRank['nivel_atual'] ?? 1)) ?></span>
                    <span class="ranking-user-xp"><?= ranking_formatar_numero((int) ($usuarioRank['xp_total'] ?? 0)) ?> XP</span>
                </div>
            </div>

            <div class="ranking-user-arrow" aria-hidden="true">↑</div>
        </article>
    </section>

</main>

<?php
$footerPath = __DIR__ . '/../assets/footer/menu.php';

if (is_file($footerPath)) {
    require_once $footerPath;
}
?>

</body>
</html>