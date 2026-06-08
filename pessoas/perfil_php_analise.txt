<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$inviteEnginePath = '/home/elab/public_html/core/invite/engine.php';
if (is_file($inviteEnginePath)) {
    require_once $inviteEnginePath;
}

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

function perfil_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function perfil_dominio_atual(): string
{
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));

    return preg_replace('/:\d+$/', '', $host) ?: '';
}

function perfil_schema_banco(): string
{
    return 'elab_roraima';
}

function perfil_tenant_id_atual(PDO $pdo): int
{
    static $tenantId = 0;

    if ($tenantId > 0) {
        return $tenantId;
    }

    $dominio = perfil_dominio_atual();

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
        error_log('[perfil] Falha ao buscar tenant: ' . $e->getMessage());
        $tenantId = 0;
    }

    return $tenantId;
}

function perfil_tabela_existe(PDO $pdo, string $tabela): bool
{
    static $cache = [];

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
        $stmt->execute([perfil_schema_banco(), $tabela]);

        $cache[$tabela] = ((int) $stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        error_log('[perfil] Falha ao verificar tabela/view ' . $tabela . ': ' . $e->getMessage());
        $cache[$tabela] = false;
    }

    return $cache[$tabela];
}

function perfil_coluna_existe(PDO $pdo, string $tabela, string $coluna): bool
{
    static $cache = [];

    $key = $tabela . '.' . $coluna;

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
        $stmt->execute([perfil_schema_banco(), $tabela, $coluna]);

        $cache[$key] = ((int) $stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        error_log('[perfil] Falha ao verificar coluna ' . $tabela . '.' . $coluna . ': ' . $e->getMessage());
        $cache[$key] = false;
    }

    return $cache[$key];
}

function perfil_nome_tenant_app(PDO $pdo, int $tenantId): string
{
    if ($tenantId <= 0 || !perfil_tabela_existe($pdo, 'clientes_elab')) {
        return 'app';
    }

    try {
        $campos = [];

        if (perfil_coluna_existe($pdo, 'clientes_elab', 'nome_publico')) {
            $campos[] = 'nome_publico';
        }

        if (perfil_coluna_existe($pdo, 'clientes_elab', 'nome')) {
            $campos[] = 'nome';
        }

        if (perfil_coluna_existe($pdo, 'clientes_elab', 'dominio')) {
            $campos[] = 'dominio';
        }

        if (!$campos) {
            return 'app';
        }

        $coalesce = implode(', ', array_map(
            static fn(string $campo): string => "NULLIF({$campo}, '')",
            $campos
        ));

        $stmt = $pdo->prepare("
            SELECT COALESCE({$coalesce}) AS nome_app
            FROM clientes_elab
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$tenantId]);

        $nome = trim((string) ($stmt->fetchColumn() ?: ''));

        return $nome !== '' ? $nome : 'app';
    } catch (Throwable $e) {
        error_log('[perfil] Falha ao buscar nome da tenant: ' . $e->getMessage());

        return 'app';
    }
}

function perfil_buscar_pessoa(PDO $pdo, int $pessoaId): ?array
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
            instagram_username,
            tiktok_username,
            facebook_username,
            pode_convidar,
            quarentena_convite,
            status_validacao
        FROM pessoas
        WHERE id = ?
          AND status = 'ativo'
        LIMIT 1
    ");
    $stmt->execute([$pessoaId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function perfil_nome_exibicao(array $pessoa): string
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

function perfil_ano_entrada(array $pessoa): string
{
    $criadoEm = trim((string) ($pessoa['criado_em'] ?? ''));

    if ($criadoEm === '') {
        return date('Y');
    }

    $timestamp = strtotime($criadoEm);

    return $timestamp ? date('Y', $timestamp) : date('Y');
}

function perfil_avatar_usuario(array $pessoa): string
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

function perfil_handle_oficial(array $pessoa): string
{
    $handle = trim((string) ($pessoa['usuario_handle'] ?? ''));

    if ($handle === '') {
        return '';
    }

    return '@' . ltrim($handle, '@');
}

function perfil_normalizar_handle(string $handle): string
{
    $handle = trim($handle);
    $handle = ltrim($handle, '@');
    $handle = strtolower($handle);
    $handle = preg_replace('/[^a-z0-9._]/', '', $handle) ?: '';
    $handle = preg_replace('/\.{2,}/', '.', $handle) ?: '';
    $handle = trim($handle, '._');

    return $handle;
}

function perfil_handle_disponivel(PDO $pdo, string $handle, int $pessoaId): bool
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM pessoas
        WHERE usuario_handle = ?
          AND id <> ?
        LIMIT 1
    ");
    $stmt->execute([$handle, $pessoaId]);

    return !(bool) $stmt->fetchColumn();
}

function perfil_asset_url(string $asset, string $fallback = '/assets/statics/awards/lock.svg'): string
{
    $asset = trim($asset);

    if ($asset === '') {
        return $fallback;
    }

    if (
        str_starts_with($asset, 'http://') ||
        str_starts_with($asset, 'https://') ||
        str_starts_with($asset, '/')
    ) {
        return $asset;
    }

    return '/assets/statics/awards/' . ltrim($asset, '/');
}

function perfil_status_tag(bool $desbloqueado, bool $proximoBloqueado): string
{
    if ($desbloqueado) {
        return 'Desbl.';
    }

    if ($proximoBloqueado) {
        return 'Próx.';
    }

    return 'Bloq.';
}

function perfil_status_classe(bool $desbloqueado, bool $proximoBloqueado): string
{
    if ($desbloqueado) {
        return 'is-unlocked';
    }

    if ($proximoBloqueado) {
        return 'is-next-locked';
    }

    return 'is-locked';
}

function perfil_total_time(PDO $pdo, int $pessoaId): int
{
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM rede_indicacoes
            WHERE indicador_id = ?
        ");
        $stmt->execute([$pessoaId]);

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('[perfil] Falha ao buscar total do time: ' . $e->getMessage());

        return 0;
    }
}

function perfil_convites_pendentes(PDO $pdo, int $pessoaId): int
{
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM convites_aprovacoes
            WHERE convidador_id = ?
              AND status = 'pendente'
        ");
        $stmt->execute([$pessoaId]);

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('[perfil] Falha ao buscar convites pendentes: ' . $e->getMessage());

        return 0;
    }
}

function perfil_convites_aprovados(PDO $pdo, int $pessoaId): int
{
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM convites_aprovacoes
            WHERE convidador_id = ?
              AND status = 'aprovado'
        ");
        $stmt->execute([$pessoaId]);

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('[perfil] Falha ao buscar convites aprovados: ' . $e->getMessage());

        return 0;
    }
}

function perfil_convites_aprovados_semana(PDO $pdo, int $pessoaId): int
{
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM convites_aprovacoes
            WHERE convidador_id = ?
              AND status = 'aprovado'
              AND COALESCE(aprovado_em, criado_em) >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute([$pessoaId]);

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('[perfil] Falha ao buscar convites aprovados na semana: ' . $e->getMessage());

        return 0;
    }
}

function perfil_total_convites(PDO $pdo, int $pessoaId): int
{
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM convites_aprovacoes
            WHERE convidador_id = ?
        ");
        $stmt->execute([$pessoaId]);

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('[perfil] Falha ao buscar total de convites: ' . $e->getMessage());

        return 0;
    }
}

function perfil_links_gerados_semana(PDO $pdo, int $pessoaId): int
{
    if ($pessoaId <= 0) {
        return 0;
    }

    try {
        if (perfil_tabela_existe($pdo, 'convites_compartilhamentos')) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM convites_compartilhamentos
                WHERE pessoa_id = ?
                  AND criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$pessoaId]);

            return (int) $stmt->fetchColumn();
        }

        if (perfil_tabela_existe($pdo, 'convites')) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM convites
                WHERE convidador_id = ?
                  AND criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$pessoaId]);

            return (int) $stmt->fetchColumn();
        }
    } catch (Throwable $e) {
        error_log('[perfil] Falha ao buscar links gerados na semana: ' . $e->getMessage());
    }

    return 0;
}

function perfil_links_gerados_total(PDO $pdo, int $pessoaId): int
{
    if ($pessoaId <= 0) {
        return 0;
    }

    try {
        if (perfil_tabela_existe($pdo, 'convites_compartilhamentos')) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM convites_compartilhamentos
                WHERE pessoa_id = ?
            ");
            $stmt->execute([$pessoaId]);

            return (int) $stmt->fetchColumn();
        }

        if (perfil_tabela_existe($pdo, 'convites')) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM convites
                WHERE convidador_id = ?
            ");
            $stmt->execute([$pessoaId]);

            return (int) $stmt->fetchColumn();
        }
    } catch (Throwable $e) {
        error_log('[perfil] Falha ao buscar links totais: ' . $e->getMessage());
    }

    return 0;
}


function perfil_formatar_tempo_reativacao(int $segundos): string
{
    $segundos = max(0, $segundos);

    $horas = intdiv($segundos, 3600);
    $minutos = intdiv($segundos % 3600, 60);
    $segundosRestantes = $segundos % 60;

    return str_pad((string) $horas, 2, '0', STR_PAD_LEFT)
        . ':'
        . str_pad((string) $minutos, 2, '0', STR_PAD_LEFT)
        . ':'
        . str_pad((string) $segundosRestantes, 2, '0', STR_PAD_LEFT);
}

function perfil_missao_convite_semanal(PDO $pdo, int $pessoaId, int $meta = 5, int $cooldownHoras = 3): array
{
    $meta = max(1, $meta);
    $cooldownSegundos = max(1, $cooldownHoras) * 3600;

    $base = [
        'meta' => $meta,
        'progresso' => 0,
        'percentual' => 0,
        'concluida' => false,
        'reativa_segundos' => 0,
        'reativa_texto' => '',
        'convites_semana' => 0,
    ];

    if ($pessoaId <= 0 || !perfil_tabela_existe($pdo, 'convites_compartilhamentos')) {
        return $base;
    }

    try {
        $agoraTs = (int) ($pdo->query("SELECT UNIX_TIMESTAMP(NOW())")->fetchColumn() ?: time());

        $stmtTotalSemana = $pdo->prepare("
            SELECT COUNT(*)
            FROM convites_compartilhamentos
            WHERE pessoa_id = ?
              AND criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmtTotalSemana->execute([$pessoaId]);

        $base['convites_semana'] = (int) $stmtTotalSemana->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT
                id,
                criado_em,
                UNIX_TIMESTAMP(criado_em) AS criado_ts
            FROM convites_compartilhamentos
            WHERE pessoa_id = ?
              AND criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY criado_em ASC, id ASC
        ");
        $stmt->execute([$pessoaId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $batch = [];
        $liberadoAPartirDe = 0;

        foreach ($rows as $row) {
            $ts = (int) ($row['criado_ts'] ?? 0);

            if ($ts <= 0) {
                continue;
            }

            if ($ts < $liberadoAPartirDe) {
                continue;
            }

            $batch[] = $ts;

            if (count($batch) >= $meta) {
                $concluidaEm = $batch[$meta - 1];
                $reativaEm = $concluidaEm + $cooldownSegundos;

                if ($agoraTs < $reativaEm) {
                    $restante = max(0, $reativaEm - $agoraTs);
                    $restante = min($restante, $cooldownSegundos);

                    return [
                        'meta' => $meta,
                        'progresso' => $meta,
                        'percentual' => 100,
                        'concluida' => true,
                        'reativa_segundos' => $restante,
                        'reativa_texto' => perfil_formatar_tempo_reativacao($restante),
                        'convites_semana' => $base['convites_semana'],
                    ];
                }

                $liberadoAPartirDe = $reativaEm;
                $batch = [];
            }
        }

        $progresso = min($meta, count($batch));

        return [
            'meta' => $meta,
            'progresso' => $progresso,
            'percentual' => min(100, (int) round(($progresso / $meta) * 100)),
            'concluida' => false,
            'reativa_segundos' => 0,
            'reativa_texto' => '',
            'convites_semana' => $base['convites_semana'],
        ];
    } catch (Throwable $e) {
        error_log('[perfil] Falha ao calcular missão semanal de convite: ' . $e->getMessage());

        return $base;
    }
}

function perfil_buscar_estado_game(PDO $pdo, int $tenantId, int $pessoaId, int $moedasFallback = 0): array
{
    $moedasFallback = max(0, $moedasFallback);

    $estado = [
        'moedas_saldo' => $moedasFallback,
        'moedas_total_ganhas' => $moedasFallback,
        'xp_total' => (int) floor($moedasFallback / 100),
        'nivel_atual' => 1,
        'ofensiva_dias' => 0,
        'ofensiva_comentarios_dias' => 0,
    ];

    try {
        if (perfil_tabela_existe($pdo, 'vw_game_usuario_estado_resumo')) {
            $where = 'pessoa_id = ?';
            $params = [$pessoaId];

            if ($tenantId > 0) {
                $where = 'tenant_cliente_id = ? AND pessoa_id = ?';
                $params = [$tenantId, $pessoaId];
            }

            $stmt = $pdo->prepare("
                SELECT *
                FROM vw_game_usuario_estado_resumo
                WHERE {$where}
                ORDER BY atualizado_em DESC
                LIMIT 1
            ");
            $stmt->execute($params);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                foreach ($estado as $key => $value) {
                    if (array_key_exists($key, $row)) {
                        $estado[$key] = is_numeric($row[$key]) ? (int) $row[$key] : $row[$key];
                    }
                }

                return $estado;
            }
        }
    } catch (Throwable $e) {
        error_log('[perfil] Falha ao buscar vw_game_usuario_estado_resumo: ' . $e->getMessage());
    }

    try {
        if ($tenantId > 0 && class_exists('GameEstadoService')) {
            $estadoService = new GameEstadoService($pdo);
            $estadoGame = $estadoService->obterOuCriarEstado($tenantId, $pessoaId);

            if (is_array($estadoGame)) {
                $estado = array_merge($estado, $estadoGame);
            }
        }
    } catch (Throwable $e) {
        error_log('[perfil] GameEstadoService falhou: ' . $e->getMessage());
    }

    if (!perfil_tabela_existe($pdo, 'game_usuario_estado')) {
        return $estado;
    }

    try {
        $where = 'pessoa_id = ?';
        $params = [$pessoaId];

        if (perfil_coluna_existe($pdo, 'game_usuario_estado', 'tenant_cliente_id') && $tenantId > 0) {
            $where = 'tenant_cliente_id = ? AND pessoa_id = ?';
            $params = [$tenantId, $pessoaId];
        }

        $stmt = $pdo->prepare("
            SELECT *
            FROM game_usuario_estado
            WHERE {$where}
            ORDER BY atualizado_em DESC
            LIMIT 1
        ");
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            foreach ($estado as $key => $value) {
                if (array_key_exists($key, $row)) {
                    $estado[$key] = is_numeric($row[$key]) ? (int) $row[$key] : $row[$key];
                }
            }
        }
    } catch (Throwable $e) {
        error_log('[perfil] Falha ao buscar game_usuario_estado: ' . $e->getMessage());
    }

    return $estado;
}

function perfil_niveis_fallback(): array
{
    return [
        ['nivel' => 1, 'nome' => 'Iniciante', 'xp_minimo' => 0, 'icone_url' => '/assets/statics/awards/001-startup.svg'],
        ['nivel' => 2, 'nome' => 'Jogador', 'xp_minimo' => 10, 'icone_url' => '/assets/statics/awards/007-coin.svg'],
        ['nivel' => 3, 'nome' => 'Líder', 'xp_minimo' => 30, 'icone_url' => '/assets/statics/awards/008-shield.svg'],
        ['nivel' => 4, 'nome' => 'Ativador', 'xp_minimo' => 75, 'icone_url' => '/assets/statics/awards/006-energy-bar.svg'],
        ['nivel' => 5, 'nome' => 'Competidor', 'xp_minimo' => 150, 'icone_url' => '/assets/statics/awards/009-swords.svg'],
        ['nivel' => 6, 'nome' => 'Bronze', 'xp_minimo' => 300, 'icone_url' => '/assets/statics/awards/016-star.svg'],
        ['nivel' => 7, 'nome' => 'Prata', 'xp_minimo' => 600, 'icone_url' => '/assets/statics/awards/002-7.svg'],
        ['nivel' => 8, 'nome' => 'Ouro', 'xp_minimo' => 1200, 'icone_url' => '/assets/statics/awards/022-rating.svg'],
        ['nivel' => 9, 'nome' => 'Elite', 'xp_minimo' => 2500, 'icone_url' => '/assets/statics/awards/029-vip.svg'],
        ['nivel' => 10, 'nome' => 'Lendário', 'xp_minimo' => 10000, 'icone_url' => '/assets/statics/awards/023-shooting-star.svg'],
    ];
}

function perfil_buscar_niveis_banco(PDO $pdo): array
{
    $fallback = perfil_niveis_fallback();

    if (!perfil_tabela_existe($pdo, 'game_niveis')) {
        return $fallback;
    }

    try {
        $where = '1=1';

        if (perfil_coluna_existe($pdo, 'game_niveis', 'ativo')) {
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
        error_log('[perfil] Falha ao buscar game_niveis: ' . $e->getMessage());

        return $fallback;
    }
}

function perfil_preparar_nivel_atual(array $niveis, int $nivelAtual, int $xpTotal): array
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
            2 => ['nivel' => 2, 'nome' => 'Jogador', 'xp_minimo' => 10],
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

function perfil_medalhas_fallback(): array
{
    return [
        ['codigo' => 'med_1_convidado', 'titulo' => '1 Convidado!', 'subtitulo' => 'Primeiro amigo convidado.', 'asset' => '/assets/statics/awards/011-like-instagram-heart.svg', 'status' => 'locked', 'nova' => false],
        ['codigo' => 'med_time_ativo', 'titulo' => 'Time ativo', 'subtitulo' => '5 amigos ativos.', 'asset' => '/assets/statics/awards/015-satisfied.svg', 'status' => 'locked', 'nova' => false],
        ['codigo' => 'med_top_10', 'titulo' => 'Top 10', 'subtitulo' => 'Entrou no Top 10.', 'asset' => '/assets/statics/awards/010-lucky.svg', 'status' => 'locked', 'nova' => false],
    ];
}

function perfil_conquistas_fallback(): array
{
    return [
        ['codigo' => 'primeiro_amigo_convidado', 'titulo' => 'Primeiro amigo convidado', 'subtitulo' => 'Primeiro amigo convidado.', 'asset' => '/assets/statics/awards/011-like-instagram-heart.svg', 'status' => 'locked', 'nova' => false],
        ['codigo' => 'primeiro_amigo_ativado', 'titulo' => 'Primeiro amigo ativado', 'subtitulo' => 'Primeiro amigo ativou cadastro.', 'asset' => '/assets/statics/awards/051-friend.svg', 'status' => 'locked', 'nova' => false],
        ['codigo' => 'cinco_amigos_ativos', 'titulo' => '5 amigos ativos', 'subtitulo' => 'Usuário possui 5 amigos ativos.', 'asset' => '/assets/statics/awards/019-five.svg', 'status' => 'locked', 'nova' => false],
    ];
}

function perfil_criterio_valor_atual(array $criterio, array $metricas): int
{
    $tipo = strtolower(trim((string) ($criterio['criterio_tipo'] ?? '')));
    $network = strtolower(trim((string) ($criterio['network'] ?? '')));

    if ($tipo === '') {
        return 0;
    }

    if ($tipo === 'rede_ativada') {
        return !empty($metricas['redes_ativas'][$network]) ? 1 : 0;
    }

    if ($tipo === 'redes_ativadas_total') {
        return (int) ($metricas['redes_ativadas_total'] ?? 0);
    }

    if ($tipo === 'convites_total') {
        return (int) ($metricas['convites_total'] ?? 0);
    }

    if ($tipo === 'amigos_ativos_total') {
        return (int) ($metricas['amigos_ativos_total'] ?? 0);
    }

    if ($tipo === 'links_compartilhados_total' || $tipo === 'posts_compartilhados_total') {
        return (int) ($metricas['links_compartilhados_total'] ?? 0);
    }

    if ($tipo === 'ofensiva_comentarios_dias') {
        return (int) ($metricas['ofensiva_comentarios_dias'] ?? 0);
    }

    if ($tipo === 'xp_total') {
        return (int) ($metricas['xp_total'] ?? 0);
    }

    if ($tipo === 'moedas_total' || $tipo === 'moedas_total_ganhas') {
        return (int) ($metricas['moedas_total'] ?? 0);
    }

    if ($tipo === 'nivel_atual') {
        return (int) ($metricas['nivel_atual'] ?? 0);
    }

    return (int) ($metricas[$tipo] ?? 0);
}

function perfil_criterio_desbloqueado(array $criterio, array $metricas): bool
{
    $criterioValor = (int) ($criterio['criterio_valor'] ?? 0);

    if ($criterioValor <= 0) {
        return false;
    }

    return perfil_criterio_valor_atual($criterio, $metricas) >= $criterioValor;
}

function perfil_icone_conquista_fallback(array $row): string
{
    $icone = trim((string) ($row['icone_url'] ?? ''));

    if ($icone !== '') {
        return $icone;
    }

    $codigo = strtolower((string) ($row['codigo'] ?? ''));
    $network = strtolower((string) ($row['network'] ?? ''));

    if ($network === 'instagram' || str_contains($codigo, 'instagram') || str_contains($codigo, 'insta')) {
        return '/assets/statics/awards/001-instagram.svg';
    }

    if ($network === 'facebook' || str_contains($codigo, 'facebook') || str_contains($codigo, 'face')) {
        return '/assets/statics/awards/002-facebook.svg';
    }

    if ($network === 'tiktok' || str_contains($codigo, 'tiktok')) {
        return '/assets/statics/awards/003-tiktok.svg';
    }

    if (str_contains($codigo, 'comentario') || str_contains($codigo, 'comentarios')) {
        return '/assets/statics/awards/014-feedback.svg';
    }

    if (str_contains($codigo, 'curtida') || str_contains($codigo, 'like')) {
        return '/assets/statics/awards/012-like.svg';
    }

    if (str_contains($codigo, 'amigo') || str_contains($codigo, 'convite')) {
        return '/assets/statics/awards/051-friend.svg';
    }

    if (str_contains($codigo, 'ranking') || str_contains($codigo, 'top')) {
        return '/assets/statics/awards/020-podium.svg';
    }

    if (
        str_contains($codigo, 'nivel') ||
        str_contains($codigo, 'bronze') ||
        str_contains($codigo, 'ouro') ||
        str_contains($codigo, 'lendario')
    ) {
        return '/assets/statics/awards/022-rating.svg';
    }

    return '/assets/statics/awards/lock.svg';
}

function perfil_limitar_game_cards(array $itens, int $limite = 10): array
{
    $visiveis = array_slice($itens, 0, $limite);

    $total = count($itens);
    $totalDesbloqueadas = 0;

    foreach ($itens as $item) {
        if (($item['status'] ?? 'locked') === 'unlocked') {
            $totalDesbloqueadas++;
        }
    }

    $totalBloqueadas = max(0, $total - $totalDesbloqueadas);
    $totalOcultas = max(0, $total - count($visiveis));

    return [
        'itens' => $visiveis,
        'total' => $total,
        'total_desbloqueadas' => $totalDesbloqueadas,
        'total_bloqueadas' => $totalBloqueadas,
        'total_ocultas' => $totalOcultas,
    ];
}

function perfil_buscar_medalhas_banco(PDO $pdo, int $tenantId, int $pessoaId, array $metricas): array
{
    $fallback = perfil_medalhas_fallback();

    if (!perfil_tabela_existe($pdo, 'vw_game_medalhas_catalogo')) {
        error_log('[perfil] View vw_game_medalhas_catalogo nao encontrada. Usando fallback.');
        return perfil_limitar_game_cards($fallback, 10);
    }

    $temUsuarioResumo = perfil_tabela_existe($pdo, 'vw_game_usuario_medalhas_resumo');

    try {
        $joinUsuario = '';
        $selectUsuario = "
            NULL AS usuario_medalha_id,
            NULL AS desbloqueada_em,
            'sim' AS visualizada
        ";

        $params = [];

        if ($temUsuarioResumo) {
            $selectUsuario = "
                vum.usuario_medalha_id,
                vum.desbloqueada_em,
                vum.visualizada
            ";

            $joinUsuario = "
                LEFT JOIN vw_game_usuario_medalhas_resumo vum
                       ON vum.medalha_id = vm.medalha_id
                      AND vum.pessoa_id = ?
            ";

            $params[] = $pessoaId;

            if ($tenantId > 0) {
                $joinUsuario .= " AND vum.tenant_cliente_id = ? ";
                $params[] = $tenantId;
            }
        }

        $sql = "
            SELECT
                vm.medalha_id,
                vm.conquista_id,
                vm.codigo,
                vm.nome,
                vm.descricao,
                vm.raridade,
                vm.icone_url,
                vm.ordem,
                vm.criterio_tipo,
                vm.criterio_valor,
                vm.conquista_network,
                {$selectUsuario}
            FROM vw_game_medalhas_catalogo vm
            {$joinUsuario}
            WHERE vm.ativo = 'sim'
            ORDER BY
                CASE WHEN vm.ordem IS NULL THEN 1 ELSE 0 END ASC,
                vm.ordem ASC,
                vm.medalha_id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            error_log('[perfil] View vw_game_medalhas_catalogo retornou zero linhas. Usando fallback.');
            return perfil_limitar_game_cards($fallback, 10);
        }

        $resultado = [];

        foreach ($rows as $row) {
            $desbloqueadaTabela = !empty($row['usuario_medalha_id']);

            $desbloqueadaCriterio = perfil_criterio_desbloqueado([
                'criterio_tipo' => (string) ($row['criterio_tipo'] ?? ''),
                'criterio_valor' => (int) ($row['criterio_valor'] ?? 0),
                'network' => (string) ($row['conquista_network'] ?? ''),
            ], $metricas);

            $desbloqueada = $desbloqueadaTabela || $desbloqueadaCriterio;
            $visualizada = strtolower((string) ($row['visualizada'] ?? 'sim'));
            $descricao = trim((string) ($row['descricao'] ?? ''));
            $iconeUrl = trim((string) ($row['icone_url'] ?? ''));

            $resultado[] = [
                'codigo' => (string) ($row['codigo'] ?? ''),
                'titulo' => (string) ($row['nome'] ?? 'Medalha'),
                'subtitulo' => $descricao !== '' ? $descricao : ($desbloqueada ? 'Desbloqueada.' : 'Continue jogando para desbloquear.'),
                'asset' => $iconeUrl !== '' ? $iconeUrl : '/assets/statics/awards/lock.svg',
                'raridade' => (string) ($row['raridade'] ?? 'comum'),
                'status' => $desbloqueada ? 'unlocked' : 'locked',
                'nova' => $desbloqueadaTabela && $visualizada !== 'sim',
            ];
        }

        return perfil_limitar_game_cards($resultado, 10);
    } catch (Throwable $e) {
        error_log('[perfil] Falha ao buscar medalhas pela view: ' . $e->getMessage());

        return perfil_limitar_game_cards($fallback, 10);
    }
}

function perfil_buscar_conquistas_banco(PDO $pdo, int $tenantId, int $pessoaId, array $metricas): array
{
    $fallback = perfil_conquistas_fallback();

    if (!perfil_tabela_existe($pdo, 'vw_game_conquistas_catalogo')) {
        error_log('[perfil] View vw_game_conquistas_catalogo nao encontrada. Usando fallback.');
        return perfil_limitar_game_cards($fallback, 10);
    }

    $temUsuarioConquistas = perfil_tabela_existe($pdo, 'game_usuario_conquistas');
    $temTenantUsuario = $temUsuarioConquistas && perfil_coluna_existe($pdo, 'game_usuario_conquistas', 'tenant_cliente_id');
    $temVisualizada = $temUsuarioConquistas && perfil_coluna_existe($pdo, 'game_usuario_conquistas', 'visualizada');

    try {
        $join = '';
        $selectUsuario = "
            NULL AS usuario_conquista_id,
            NULL AS desbloqueada_em,
            'sim' AS visualizada
        ";

        $params = [];

        if ($temUsuarioConquistas) {
            $selectUsuario = "
                guc.id AS usuario_conquista_id,
                guc.desbloqueada_em,
                " . ($temVisualizada ? "guc.visualizada" : "'sim'") . " AS visualizada
            ";

            $join = "
                LEFT JOIN game_usuario_conquistas guc
                       ON guc.conquista_id = vc.conquista_id
                      AND guc.pessoa_id = ?
            ";

            $params[] = $pessoaId;

            if ($temTenantUsuario && $tenantId > 0) {
                $join .= " AND guc.tenant_cliente_id = ? ";
                $params[] = $tenantId;
            }
        }

        $sql = "
            SELECT
                vc.conquista_id,
                vc.codigo,
                vc.nome,
                vc.descricao,
                vc.icone_url,
                vc.categoria,
                vc.criterio_tipo,
                vc.criterio_valor,
                vc.network,
                {$selectUsuario}
            FROM vw_game_conquistas_catalogo vc
            {$join}
            WHERE vc.ativo = 'sim'
            ORDER BY vc.conquista_id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            error_log('[perfil] View vw_game_conquistas_catalogo retornou zero linhas. Usando fallback.');
            return perfil_limitar_game_cards($fallback, 10);
        }

        $resultado = [];

        foreach ($rows as $row) {
            $desbloqueadaTabela = !empty($row['usuario_conquista_id']);

            $desbloqueadaCriterio = perfil_criterio_desbloqueado([
                'criterio_tipo' => (string) ($row['criterio_tipo'] ?? ''),
                'criterio_valor' => (int) ($row['criterio_valor'] ?? 0),
                'network' => (string) ($row['network'] ?? ''),
            ], $metricas);

            $desbloqueada = $desbloqueadaTabela || $desbloqueadaCriterio;
            $visualizada = strtolower((string) ($row['visualizada'] ?? 'sim'));
            $descricao = trim((string) ($row['descricao'] ?? ''));

            $resultado[] = [
                'codigo' => (string) ($row['codigo'] ?? ''),
                'titulo' => (string) ($row['nome'] ?? 'Conquista'),
                'subtitulo' => $descricao !== '' ? $descricao : ($desbloqueada ? 'Desbloqueada.' : 'Continue jogando para desbloquear.'),
                'asset' => perfil_icone_conquista_fallback($row),
                'categoria' => (string) ($row['categoria'] ?? ''),
                'criterio_tipo' => (string) ($row['criterio_tipo'] ?? ''),
                'criterio_valor' => (int) ($row['criterio_valor'] ?? 0),
                'status' => $desbloqueada ? 'unlocked' : 'locked',
                'nova' => $desbloqueadaTabela && $visualizada !== 'sim',
            ];
        }

        return perfil_limitar_game_cards($resultado, 10);
    } catch (Throwable $e) {
        error_log('[perfil] Falha ao buscar conquistas pela view: ' . $e->getMessage());

        return perfil_limitar_game_cards($fallback, 10);
    }
}

$pessoa = perfil_buscar_pessoa($pdo, $pessoaId);

if (!$pessoa) {
    header('Location: /index.php');
    exit;
}

$handleErro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_usuario_handle'])) {
    $handleBruto = (string) ($_POST['usuario_handle'] ?? '');
    $handleNovo = perfil_normalizar_handle($handleBruto);

    if ($handleNovo === '') {
        $handleErro = 'Digite um usuário válido.';
    } elseif (strlen($handleNovo) < 3) {
        $handleErro = 'Seu usuário precisa ter pelo menos 3 caracteres.';
    } elseif (strlen($handleNovo) > 50) {
        $handleErro = 'Seu usuário pode ter no máximo 50 caracteres.';
    } elseif (!preg_match('/^[a-z0-9](?:[a-z0-9._]*[a-z0-9])?$/', $handleNovo)) {
        $handleErro = 'Use apenas letras, números, ponto ou underline.';
    } elseif (!perfil_handle_disponivel($pdo, $handleNovo, $pessoaId)) {
        $handleErro = 'Esse usuário já está em uso. Tente outro.';
    } else {
        $stmt = $pdo->prepare("
            UPDATE pessoas
            SET usuario_handle = ?,
                atualizado_em = NOW()
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$handleNovo, $pessoaId]);

        header('Location: /pessoas/perfil.php?handle_ok=1');
        exit;
    }
}

$tenantClienteId = perfil_tenant_id_atual($pdo);
$tenantNomeApp = perfil_nome_tenant_app($pdo, $tenantClienteId);

$nomeExibicao = perfil_nome_exibicao($pessoa);
$anoEntrada = perfil_ano_entrada($pessoa);
$avatarUsuario = perfil_avatar_usuario($pessoa);
$handleOficial = perfil_handle_oficial($pessoa);

$estadoGame = perfil_buscar_estado_game($pdo, $tenantClienteId, $pessoaId, 0);

$moedas = (int) ($estadoGame['moedas_saldo'] ?? 0);
$moedasTotal = (int) ($estadoGame['moedas_total_ganhas'] ?? 0);
$xpTotal = (int) ($estadoGame['xp_total'] ?? 0);
$nivelAtualEstado = max(1, (int) ($estadoGame['nivel_atual'] ?? 1));
$ofensivaComentariosDias = (int) ($estadoGame['ofensiva_comentarios_dias'] ?? 0);

$niveis = array_slice(perfil_buscar_niveis_banco($pdo), 0, 10);
$nivelGame = perfil_preparar_nivel_atual($niveis, $nivelAtualEstado, $xpTotal);

$nivelAtual = (int) $nivelGame['nivel_atual'];
$nivelNome = (string) ($nivelGame['nome'] ?? 'Iniciante');
$xpProximoNivel = (int) ($nivelGame['xp_proximo'] ?? 10);
$faltaXp = (int) ($nivelGame['falta_xp'] ?? 0);
$percentualNivel = (int) ($nivelGame['percentual'] ?? 0);

$totalTime = perfil_total_time($pdo, $pessoaId);
$convitesPendentes = perfil_convites_pendentes($pdo, $pessoaId);
$convitesAprovados = perfil_convites_aprovados($pdo, $pessoaId);
$convitesAprovadosSemana = perfil_convites_aprovados_semana($pdo, $pessoaId);
$convitesTotal = perfil_total_convites($pdo, $pessoaId);
$linksGeradosSemana = perfil_links_gerados_semana($pdo, $pessoaId);
$linksGeradosTotal = perfil_links_gerados_total($pdo, $pessoaId);

$cooldownConviteHoras = 2;
$cooldownConviteSegundos = $cooldownConviteHoras * 3600;

$missaoConviteSemanal = perfil_missao_convite_semanal($pdo, $pessoaId, 5, $cooldownConviteHoras);

$metaAtivadosSemana = (int) ($missaoConviteSemanal['meta'] ?? 5);
$ativadosSemana = (int) ($missaoConviteSemanal['progresso'] ?? 0);
$ativadosSemanaLimitado = min($ativadosSemana, $metaAtivadosSemana);
$ativadosSemanaFaltam = max(0, $metaAtivadosSemana - $ativadosSemana);
$percentualAtivadosSemana = (int) ($missaoConviteSemanal['percentual'] ?? 0);
$missaoConviteConcluida = (bool) ($missaoConviteSemanal['concluida'] ?? false);
$missaoConviteReativaTexto = (string) ($missaoConviteSemanal['reativa_texto'] ?? '');
$missaoConviteReativaSegundos = (int) ($missaoConviteSemanal['reativa_segundos'] ?? 0);

$segundosPorConviteMissao = (int) max(1, floor($cooldownConviteSegundos / max(1, $metaAtivadosSemana)));

$slotsMissaoPreenchidos = $missaoConviteConcluida
    ? min($metaAtivadosSemana, max(0, (int) ceil($missaoConviteReativaSegundos / $segundosPorConviteMissao)))
    : $ativadosSemanaLimitado;

$percentualBarraMissao = $missaoConviteConcluida
    ? min(100, max(0, (int) round(($missaoConviteReativaSegundos / max(1, $cooldownConviteSegundos)) * 100)))
    : $percentualAtivadosSemana;

$linksGeradosSemana = (int) ($missaoConviteSemanal['convites_semana'] ?? $linksGeradosSemana);

$seguidores = 0;

$statusValidacao = strtolower(trim((string) ($pessoa['status_validacao'] ?? '')));
$temCadastroParaAtivar = in_array($statusValidacao, ['pendente', 'aguardando', 'aguardando_ativacao'], true);

$podeConvidar = (string) ($pessoa['pode_convidar'] ?? 'nao') === 'sim'
    && (string) ($pessoa['quarentena_convite'] ?? 'nao') !== 'sim';

$temAmigosParaAprovar = $convitesPendentes > 0;
$linkAprovarAmigos = '/pessoas/aprovar-pessoa.php';

$linkConvitePublico = '';
$codigoConvitePublico = '';

try {
    if (function_exists('inviteObterOuCriarLinkPublico')) {
        $linkPublicoConvite = inviteObterOuCriarLinkPublico($pdo, $pessoaId);
        $codigoConvitePublico = trim((string) ($linkPublicoConvite['codigo_convite_publico'] ?? ''));
        $linkConvitePublico = trim((string) ($linkPublicoConvite['url_curta'] ?? ''));

        if ($linkConvitePublico === '' && $codigoConvitePublico !== '') {
            $linkConvitePublico = 'https://app.elab.social/i/' . rawurlencode($codigoConvitePublico);
        }
    }
} catch (Throwable $e) {
    error_log('[perfil] Falha ao obter link público de convite: ' . $e->getMessage());
}

$redesAtivas = [
    'facebook' => trim((string) ($pessoa['facebook_username'] ?? '')) !== '',
    'instagram' => trim((string) ($pessoa['instagram_username'] ?? '')) !== '',
    'tiktok' => trim((string) ($pessoa['tiktok_username'] ?? '')) !== '',
];

$metricasGame = [
    'redes_ativas' => $redesAtivas,
    'redes_ativadas_total' => count(array_filter($redesAtivas)),
    'convites_total' => $convitesTotal,
    'amigos_ativos_total' => $totalTime,
    'links_compartilhados_total' => $linksGeradosTotal,
    'ofensiva_comentarios_dias' => $ofensivaComentariosDias,
    'xp_total' => $xpTotal,
    'moedas_total' => $moedasTotal,
    'nivel_atual' => $nivelAtual,
];

$numbersBase = '/assets/animations/numbers/';
$medalhasResumo = perfil_buscar_medalhas_banco($pdo, $tenantClienteId, $pessoaId, $metricasGame);
$conquistasResumo = perfil_buscar_conquistas_banco($pdo, $tenantClienteId, $pessoaId, $metricasGame);

$medalhas = $medalhasResumo['itens'] ?? [];
$conquistas = $conquistasResumo['itens'] ?? [];

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Perfil | elab.social</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <link rel="stylesheet" href="/assets/css/footer-v2.css?v=5">
    <link rel="stylesheet" href="/assets/css/pessoas-perfil.css?v=71">
</head>
<body class="perfil-page-body">

<main class="perfil-page">

    <section class="perfil-hero">
        <div class="perfil-hero-glow perfil-hero-glow-one"></div>
        <div class="perfil-hero-glow perfil-hero-glow-two"></div>

        <div class="perfil-hero-top">
            <div class="perfil-title-wrap">
                <span class="perfil-title-kicker">MEU PERFIL</span>
                <h1><?= perfil_h($nomeExibicao) ?></h1>
            </div>

            <div class="perfil-hero-actions">
                <button type="button" class="perfil-icon-btn" data-perfil-share aria-label="Compartilhar perfil">⇧</button>
                <a href="/pessoas/editar.php" class="perfil-icon-btn" aria-label="Editar cadastro">
                    ⚙
                </a>
            </div>
        </div>

        <div class="perfil-character-wrap">
            <img
                class="perfil-character"
                src="<?= perfil_h($avatarUsuario) ?>"
                alt=""
                width="270"
                height="232"
                loading="eager"
            >
        </div>

        <div class="perfil-level-floating" aria-label="Nível atual">
            <span>Nível</span>
            <strong><?= number_format($nivelAtual, 0, ',', '.') ?></strong>
        </div>
    </section>

    <section class="perfil-content">

        <div class="perfil-identity">
            <?php if ($handleOficial !== ''): ?>
                <div class="perfil-handle">
                    <?= perfil_h($handleOficial) ?> · MUDANDO O MUNDO DESDE <?= perfil_h($anoEntrada) ?>
                </div>
            <?php else: ?>
                <button type="button" class="perfil-create-handle-btn" data-perfil-open-handle>
                    @ CRIE SEU USUÁRIO EXCLUSIVO
                </button>

                <div class="perfil-since">
                    MUDANDO O MUNDO DESDE <?= perfil_h($anoEntrada) ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($handleErro !== ''): ?>
            <div class="perfil-alert"><?= perfil_h($handleErro) ?></div>
        <?php endif; ?>

        <section class="perfil-social-stats" aria-label="Resumo social">
            <div class="perfil-social-item">
                <strong><?= perfil_h($nivelNome) ?></strong>
                <span>Nível</span>
            </div>

            <div class="perfil-social-item">
                <strong><?= number_format($totalTime, 0, ',', '.') ?></strong>
                <span>Time</span>
            </div>

            <div class="perfil-social-item">
                <strong><?= number_format($seguidores, 0, ',', '.') ?></strong>
                <span>Seguidores</span>
            </div>
        </section>

        <section class="perfil-level-card">
            <div class="perfil-level-card-top">
                <div>
                    <h2>Nível: <?= perfil_h($nivelNome) ?></h2>
                    <?php if ((bool) ($nivelGame['is_max'] ?? false)): ?>
                        <p>Você chegou no topo da jornada.</p>
                    <?php else: ?>
                        <p>Faltam só <strong><?= number_format($faltaXp, 0, ',', '.') ?> XP</strong> para você subir.</p>
                    <?php endif; ?>
                </div>

                <div class="perfil-level-badge"><?= perfil_h($nivelNome) ?></div>
            </div>

            <div class="perfil-xp-track" aria-label="Progresso de XP">
                <div class="perfil-xp-fill" style="width: <?= max(0, min(100, $percentualNivel)) ?>%;"></div>
            </div>

            <div class="perfil-xp-row">
                <span><?= number_format($xpTotal, 0, ',', '.') ?> XP</span>
                <span><?= number_format($xpProximoNivel, 0, ',', '.') ?> XP</span>
            </div>
        </section>

        <?php if ($temCadastroParaAtivar): ?>
            <a class="perfil-invite-button perfil-activate-button" href="<?= perfil_h($linkAprovarAmigos) ?>">
                <span class="perfil-invite-icon">✓</span>
                <span>ATIVAR CADASTRO</span>
            </a>

            <p class="perfil-invite-note">Complete sua ativação para liberar convites, moedas e progressão.</p>
        <?php elseif ($temAmigosParaAprovar): ?>
            <a class="perfil-invite-button perfil-approve-button" href="<?= perfil_h($linkAprovarAmigos) ?>">
                <span class="perfil-invite-icon">!</span>
                <span>APROVAR AMIGOS</span>
            </a>

            <p class="perfil-invite-note">Você tem amigos aguardando aprovação para ativar seu time.</p>
        <?php elseif ($podeConvidar): ?>
            <button
                type="button"
                class="perfil-invite-button"
                data-perfil-invite
                data-url="<?= perfil_h($linkConvitePublico) ?>"
                data-tenant="<?= perfil_h($tenantNomeApp) ?>"
            >
                <span class="perfil-invite-icon">✦</span>
                <span>CONVIDAR AMIGO</span>
            </button>

            <p class="perfil-invite-note">Convide pelo WhatsApp, ganhe moedas e fortaleça seu time.</p>
        <?php else: ?>
            <button type="button" class="perfil-invite-button is-locked">
                <span class="perfil-invite-icon">🔒</span>
                <span>CONVITES BLOQUEADOS</span>
            </button>

            <p class="perfil-invite-note">Continue jogando para liberar convites.</p>
        <?php endif; ?>

        <section class="perfil-section">
            <div class="perfil-section-title">
                <div>
                    <h2>VISÃO GERAL</h2>
                    <p>Seu painel rápido do jogo.</p>
                </div>
            </div>

            <div class="perfil-overview-grid">
                <article class="perfil-overview-card">
                    <span class="perfil-overview-icon fire">🔥</span>
                    <div>
                        <strong><?= number_format($ofensivaComentariosDias, 0, ',', '.') ?></strong>
                        <small>dias de ofensiva</small>
                    </div>
                </article>

                <article class="perfil-overview-card">
                    <span class="perfil-overview-icon trophy">🏆</span>
                    <div>
                        <strong><?= perfil_h($nivelNome) ?></strong>
                        <small>nível atual</small>
                    </div>
                </article>

                <article class="perfil-overview-card">
                    <span class="perfil-overview-icon coin">🪙</span>
                    <div>
                        <strong><?= number_format($moedas, 0, ',', '.') ?></strong>
                        <small>moedas</small>
                    </div>
                </article>

                <article class="perfil-overview-card">
                    <span class="perfil-overview-icon bolt">⚡</span>
                    <div>
                        <strong><?= number_format($xpTotal, 0, ',', '.') ?></strong>
                        <small>XP total</small>
                    </div>
                </article>
            </div>
        </section>

        <section
            class="perfil-section perfil-weekly-invite-section"
            data-missao-convite-semanal
            data-meta="<?= (int) $metaAtivadosSemana ?>"
            data-concluida="<?= $missaoConviteConcluida ? '1' : '0' ?>"
            data-reativa-segundos="<?= (int) $missaoConviteReativaSegundos ?>"
            data-cooldown-total="<?= (int) $cooldownConviteSegundos ?>"
        >
            <div class="perfil-section-title">
                <div>
                    <h2>MISSÃO SEMANAL</h2>
                    <p>
                        <?php if ($missaoConviteConcluida): ?>
                            Missão concluída.
                            <span class="perfil-weekly-reactivation">
                                Reativa em <strong class="perfil-weekly-reactivation-time" data-missao-reativa-tempo><?= perfil_h($missaoConviteReativaTexto) ?></strong>.
                            </span>
                        <?php else: ?>
                            Convide <?= number_format($ativadosSemanaFaltam, 0, ',', '.') ?> pessoas para completar a missão.
                        <?php endif; ?>
                    </p>
                </div>

                <span class="perfil-weekly-pill <?= $missaoConviteConcluida ? 'is-complete' : '' ?>">
                    <?php if ($missaoConviteConcluida): ?>
                        <?= number_format($metaAtivadosSemana, 0, ',', '.') ?>/<?= number_format($metaAtivadosSemana, 0, ',', '.') ?> concluído
                    <?php else: ?>
                        <?= number_format($ativadosSemanaLimitado, 0, ',', '.') ?>/<?= number_format($metaAtivadosSemana, 0, ',', '.') ?>
                    <?php endif; ?>
                </span>
            </div>

            <div class="perfil-weekly-card">
                <div class="perfil-weekly-top">
                    <div>
                        <strong>Convide 5 pessoas para o time</strong>
                        <span>
                            <?php if ($missaoConviteConcluida): ?>
                                5/5 concluído
                            <?php else: ?>
                                Faltam <?= number_format($ativadosSemanaFaltam, 0, ',', '.') ?> convites para concluir.
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <div class="perfil-weekly-track">
                    <div class="perfil-weekly-fill" data-missao-fill style="width: <?= $percentualBarraMissao ?>%;"></div>
                </div>

                <div class="perfil-invite-slots" aria-label="Progresso dos ativados da semana">
                    <?php for ($i = 1; $i <= $metaAtivadosSemana; $i++): ?>
                        <?php if ($i <= $slotsMissaoPreenchidos): ?>
                            <button
                                type="button"
                                class="is-done"
                                <?= $missaoConviteConcluida ? 'disabled' : '' ?>
                                aria-label="Convite <?= $i ?> concluído"
                            >
                                <img src="<?= perfil_h($numbersBase . $i . '.webp') ?>" alt="" loading="lazy">
                            </button>
                        <?php elseif (!$missaoConviteConcluida && $podeConvidar && $linkConvitePublico !== ''): ?>
                            <button
                                type="button"
                                class="is-pending is-action"
                                data-perfil-invite
                                data-url="<?= perfil_h($linkConvitePublico) ?>"
                                data-tenant="<?= perfil_h($tenantNomeApp) ?>"
                                aria-label="Convidar amigo para completar o ativado <?= $i ?>"
                            >
                                <span><?= $i ?></span>
                            </button>
                        <?php else: ?>
                            <button type="button" class="is-pending" aria-label="Ativado <?= $i ?> pendente">
                                <span><?= $i ?></span>
                            </button>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="perfil-team-mini">
                <div>
                    <strong><?= number_format($linksGeradosSemana, 0, ',', '.') ?></strong>
                    <span>Convites enviados</span>
                </div>

                <div>
                    <strong><?= number_format($convitesPendentes, 0, ',', '.') ?></strong>
                    <span>Amigos para aprovar</span>
                </div>

                <div>
                    <strong><?= number_format($totalTime, 0, ',', '.') ?></strong>
                    <span>Total de ativados</span>
                </div>
            </div>
        </section>

        <section class="perfil-section">
            <div class="perfil-section-title with-arrow">
                <div>
                    <h2>NÍVEIS</h2>
                    <p>Sua jornada até o Lendário.</p>
                </div>

                <button type="button" data-scroll-next="#perfilNiveis" aria-label="Avançar níveis">›</button>
            </div>

            <div id="perfilNiveis" class="perfil-levels-row perfil-carousel" data-perfil-carousel>
                <?php foreach ($niveis as $nivel): ?>
                    <?php
                    $nivelNumero = (int) ($nivel['nivel'] ?? 0);
                    $nivelStatus = $nivelNumero === $nivelAtual
                        ? 'is-current'
                        : ($nivelNumero < $nivelAtual ? 'is-unlocked' : 'is-locked');

                    $nivelTexto = $nivelNumero === $nivelAtual
                        ? 'Atual'
                        : ($nivelNumero < $nivelAtual ? 'Desbloqueado' : number_format((int) ($nivel['xp_minimo'] ?? 0), 0, ',', '.') . ' XP');
                    ?>

                    <article class="perfil-level-item <?= perfil_h($nivelStatus) ?>">
                        <div class="perfil-level-item-icon">
                            <img src="<?= perfil_h(perfil_asset_url((string) ($nivel['icone_url'] ?? ''), '/assets/statics/awards/lock.svg')) ?>" alt="">
                        </div>

                        <span class="perfil-level-item-kicker">Nível <?= number_format($nivelNumero, 0, ',', '.') ?></span>
                        <strong><?= perfil_h((string) ($nivel['nome'] ?? 'Nível')) ?></strong>
                        <small><?= perfil_h($nivelTexto) ?></small>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="perfil-section">
            <div class="perfil-section-title with-arrow">
                <div>
                    <h2>MEDALHAS</h2>
                    <p>
                        <?= number_format((int) ($medalhasResumo['total_desbloqueadas'] ?? 0), 0, ',', '.') ?>
                        desbloqueadas de
                        <?= number_format((int) ($medalhasResumo['total'] ?? 0), 0, ',', '.') ?>.
                    </p>
                </div>

                <button type="button" data-scroll-next="#perfilMedalhas" aria-label="Avançar medalhas">›</button>
            </div>

            <div id="perfilMedalhas" class="perfil-awards-row perfil-carousel" data-perfil-carousel>
                <?php $primeiraMedalhaBloqueadaMarcada = false; ?>

                <?php foreach ($medalhas as $medalha): ?>
                    <?php
                    $unlocked = (string) ($medalha['status'] ?? 'locked') === 'unlocked';
                    $proximoBloqueado = !$unlocked && !$primeiraMedalhaBloqueadaMarcada;

                    if ($proximoBloqueado) {
                        $primeiraMedalhaBloqueadaMarcada = true;
                    }

                    $statusClasse = perfil_status_classe($unlocked, $proximoBloqueado);
                    $statusTag = perfil_status_tag($unlocked, $proximoBloqueado);
                    ?>

                    <article class="perfil-award-card <?= perfil_h($statusClasse) ?>">
                        <span class="perfil-mini-tag <?= perfil_h($statusClasse) ?>"><?= perfil_h($statusTag) ?></span>

                        <div class="perfil-award-orb">
                            <img src="<?= perfil_h(perfil_asset_url((string) ($medalha['asset'] ?? ''), '/assets/statics/awards/lock.svg')) ?>" alt="">
                        </div>

                        <strong><?= perfil_h((string) ($medalha['titulo'] ?? 'Medalha')) ?></strong>
                        <small><?= perfil_h((string) ($medalha['subtitulo'] ?? 'Em breve.')) ?></small>
                    </article>
                <?php endforeach; ?>

                <?php if ((int) ($medalhasResumo['total_ocultas'] ?? 0) > 0): ?>
                    <article class="perfil-award-card is-locked is-summary">
                        <span class="perfil-mini-tag is-locked">Bloq.</span>

                        <div class="perfil-award-orb">
                            <img src="/assets/statics/awards/lock.svg" alt="">
                        </div>

                        <strong>+<?= number_format((int) ($medalhasResumo['total_ocultas'] ?? 0), 0, ',', '.') ?></strong>
                        <small>medalhas para desbloquear jogando.</small>
                    </article>
                <?php endif; ?>
            </div>
        </section>

        <section class="perfil-section perfil-last-section">
            <div class="perfil-section-title with-arrow">
                <div>
                    <h2>CONQUISTAS</h2>
                    <p>
                        <?= number_format((int) ($conquistasResumo['total_desbloqueadas'] ?? 0), 0, ',', '.') ?>
                        desbloqueadas de
                        <?= number_format((int) ($conquistasResumo['total'] ?? 0), 0, ',', '.') ?>.
                    </p>
                </div>

                <button type="button" data-scroll-next="#perfilConquistas" aria-label="Avançar conquistas">›</button>
            </div>

            <div id="perfilConquistas" class="perfil-achievements-row perfil-carousel" data-perfil-carousel>
                <?php $primeiraConquistaBloqueadaMarcada = false; ?>

                <?php foreach ($conquistas as $conquista): ?>
                    <?php
                    $unlocked = (string) ($conquista['status'] ?? 'locked') === 'unlocked';
                    $proximoBloqueado = !$unlocked && !$primeiraConquistaBloqueadaMarcada;

                    if ($proximoBloqueado) {
                        $primeiraConquistaBloqueadaMarcada = true;
                    }

                    $statusClasse = perfil_status_classe($unlocked, $proximoBloqueado);
                    $statusTag = perfil_status_tag($unlocked, $proximoBloqueado);
                    ?>

                    <article class="perfil-achievement-card <?= perfil_h($statusClasse) ?>">
                        <span class="perfil-achievement-tag <?= perfil_h($statusClasse) ?>">
                            <?= perfil_h($statusTag) ?>
                        </span>

                        <img src="<?= perfil_h(perfil_asset_url((string) ($conquista['asset'] ?? ''), '/assets/statics/awards/lock.svg')) ?>" alt="">

                        <strong><?= perfil_h((string) ($conquista['titulo'] ?? 'Conquista')) ?></strong>
                        <small><?= perfil_h((string) ($conquista['subtitulo'] ?? 'Em breve.')) ?></small>
                    </article>
                <?php endforeach; ?>

                <?php if ((int) ($conquistasResumo['total_ocultas'] ?? 0) > 0): ?>
                    <article class="perfil-achievement-card is-locked is-summary">
                        <span class="perfil-achievement-tag is-locked">Bloq.</span>

                        <img src="/assets/statics/awards/lock.svg" alt="">

                        <strong>+<?= number_format((int) ($conquistasResumo['total_ocultas'] ?? 0), 0, ',', '.') ?></strong>
                        <small>conquistas para liberar jogando.</small>
                    </article>
                <?php endif; ?>
            </div>
        </section>

    </section>

</main>

<div class="perfil-modal-backdrop" data-perfil-handle-modal hidden>
    <div class="perfil-modal-card" role="dialog" aria-modal="true" aria-labelledby="perfilHandleTitle">
        <button type="button" class="perfil-modal-close" data-perfil-close-handle aria-label="Fechar">×</button>

        <h2 id="perfilHandleTitle">Crie seu usuário</h2>
        <p>Escolha um @ curto, exclusivo e fácil de compartilhar.</p>

        <form method="post" class="perfil-handle-form">
            <input
                type="text"
                name="usuario_handle"
                inputmode="text"
                autocomplete="off"
                placeholder="seu.usuario"
                maxlength="50"
                required
            >

            <button type="submit" name="salvar_usuario_handle" value="1">
                Salvar usuário
            </button>
        </form>
    </div>
</div>

<?php
$footerPath = __DIR__ . '/../assets/footer/menu.php';

if (is_file($footerPath)) {
    require_once $footerPath;
}
?>

<script src="/assets/js/perfil.js?v=72" defer></script>
</body>
</html>