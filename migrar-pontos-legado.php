<?php
declare(strict_types=1);

/**
 * TEMPORARIO - Migracao de pontos legados para game.
 *
 * Caminho:
 * /home/elab/app.elab.social/migrar-pontos-legado.php
 *
 * IMPORTANTE:
 * Apagar este arquivo depois da migracao.
 *
 * Tenant padrao:
 * 2 = Teresa Surita / app.elab.social
 *
 * Dry-run:
 * /migrar-pontos-legado.php?chave=MIGRAR_ELAB_2026
 *
 * Executar sem zerar pessoas.pontos:
 * /migrar-pontos-legado.php?chave=MIGRAR_ELAB_2026&executar=1&confirmar=MIGRAR_PONTOS_LEGADO
 *
 * Executar e zerar pessoas.pontos:
 * /migrar-pontos-legado.php?chave=MIGRAR_ELAB_2026&executar=1&confirmar=MIGRAR_PONTOS_LEGADO&zerar_pontos=1
 *
 * Forcar outro tenant:
 * /migrar-pontos-legado.php?chave=MIGRAR_ELAB_2026&tenant_id=2
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

$chave = trim((string) ($_GET['chave'] ?? $_POST['chave'] ?? ''));

if ($chave !== 'MIGRAR_ELAB_2026') {
    http_response_code(403);
    echo "Acesso negado.\n";
    echo "Use a chave temporaria para rodar este script.\n";
    exit;
}

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();

function migracao_param(string $nome, mixed $padrao = null): mixed
{
    return $_GET[$nome] ?? $_POST[$nome] ?? $padrao;
}

function migracao_bool_param(string $nome): bool
{
    $valor = strtolower(trim((string) migracao_param($nome, '0')));

    return in_array($valor, ['1', 'sim', 'true', 'yes'], true);
}

function migracao_log(string $mensagem = ''): void
{
    echo $mensagem . PHP_EOL;
}

function migracao_schema_banco(): string
{
    return 'elab_roraima';
}

function migracao_tabela_existe(PDO $pdo, string $tabela): bool
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
        $stmt->execute([migracao_schema_banco(), $tabela]);

        $cache[$tabela] = ((int) $stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        error_log('[migrar-pontos-legado-temp] Falha ao verificar tabela ' . $tabela . ': ' . $e->getMessage());
        $cache[$tabela] = false;
    }

    return $cache[$tabela];
}

function migracao_coluna_existe(PDO $pdo, string $tabela, string $coluna): bool
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
        $stmt->execute([migracao_schema_banco(), $tabela, $coluna]);

        $cache[$key] = ((int) $stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        error_log('[migrar-pontos-legado-temp] Falha ao verificar coluna ' . $tabela . '.' . $coluna . ': ' . $e->getMessage());
        $cache[$key] = false;
    }

    return $cache[$key];
}

function migracao_resolver_nivel_por_xp(PDO $pdo, int $xpTotal): int
{
    if (!migracao_tabela_existe($pdo, 'game_niveis')) {
        return 1;
    }

    try {
        $where = 'xp_minimo <= ?';

        if (migracao_coluna_existe($pdo, 'game_niveis', 'ativo')) {
            $where .= " AND ativo = 'sim'";
        }

        $stmt = $pdo->prepare("
            SELECT nivel
            FROM game_niveis
            WHERE {$where}
            ORDER BY xp_minimo DESC
            LIMIT 1
        ");
        $stmt->execute([$xpTotal]);

        return max(1, (int) ($stmt->fetchColumn() ?: 1));
    } catch (Throwable $e) {
        error_log('[migrar-pontos-legado-temp] Falha ao resolver nivel por XP: ' . $e->getMessage());

        return 1;
    }
}

function migracao_buscar_estado(PDO $pdo, int $tenantId, int $pessoaId): ?array
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM game_usuario_estado
        WHERE tenant_cliente_id = ?
          AND pessoa_id = ?
        ORDER BY atualizado_em DESC
        LIMIT 1
    ");
    $stmt->execute([$tenantId, $pessoaId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function migracao_ledger_moedas_existe(PDO $pdo, int $tenantId, int $pessoaId): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM game_moedas_ledger
        WHERE tenant_cliente_id = ?
          AND pessoa_id = ?
          AND evento_codigo = 'migracao_pontos_legado'
          AND origem_tipo = 'pessoas.pontos'
        LIMIT 1
    ");
    $stmt->execute([$tenantId, $pessoaId]);

    return (bool) $stmt->fetchColumn();
}

function migracao_inserir_estado(PDO $pdo, int $tenantId, int $pessoaId, int $moedas, int $xpTotal, int $nivel): void
{
    $stmt = $pdo->prepare("
        INSERT INTO game_usuario_estado
        (
            tenant_cliente_id,
            pessoa_id,
            moedas_saldo,
            moedas_total_ganhas,
            moedas_total_perdidas,
            xp_total,
            nivel_atual,
            ofensiva_dias,
            ofensiva_comentarios_dias,
            status,
            ultimo_evento_em,
            criado_em,
            atualizado_em
        )
        VALUES
        (?, ?, ?, ?, 0, ?, ?, 0, 0, 'ativo', NOW(), NOW(), NOW())
    ");

    $stmt->execute([
        $tenantId,
        $pessoaId,
        $moedas,
        $moedas,
        $xpTotal,
        $nivel,
    ]);
}

function migracao_atualizar_estado(
    PDO $pdo,
    int $tenantId,
    int $pessoaId,
    int $novoSaldo,
    int $novoTotalGanhas,
    int $novoXpTotal,
    int $novoNivel
): void {
    $stmt = $pdo->prepare("
        UPDATE game_usuario_estado
        SET
            moedas_saldo = ?,
            moedas_total_ganhas = ?,
            xp_total = ?,
            nivel_atual = ?,
            status = 'ativo',
            ultimo_evento_em = NOW(),
            atualizado_em = NOW()
        WHERE tenant_cliente_id = ?
          AND pessoa_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $novoSaldo,
        $novoTotalGanhas,
        $novoXpTotal,
        $novoNivel,
        $tenantId,
        $pessoaId,
    ]);
}

function migracao_registrar_ledger_moedas(PDO $pdo, int $tenantId, int $pessoaId, int $pontosLegado, array $metadata): void
{
    $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $stmt = $pdo->prepare("
        INSERT INTO game_moedas_ledger
        (
            tenant_cliente_id,
            pessoa_id,
            tipo_movimento,
            quantidade,
            evento_codigo,
            origem_tipo,
            origem_id,
            network,
            post_id,
            descricao,
            metadata_json,
            criado_em
        )
        VALUES
        (?, ?, 'credito', ?, 'migracao_pontos_legado', 'pessoas.pontos', ?, NULL, NULL, 'Migração dos pontos legados para moedas V2', ?, NOW())
    ");

    $stmt->execute([
        $tenantId,
        $pessoaId,
        $pontosLegado,
        (string) $pessoaId,
        $metadataJson,
    ]);
}

function migracao_registrar_ledger_xp(PDO $pdo, int $tenantId, int $pessoaId, int $xpGanho, int $pontosLegado, array $metadata): void
{
    if ($xpGanho <= 0) {
        return;
    }

    $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $stmt = $pdo->prepare("
        INSERT INTO game_xp_ledger
        (
            tenant_cliente_id,
            pessoa_id,
            xp,
            moedas_base,
            evento_codigo,
            origem_tipo,
            origem_id,
            descricao,
            metadata_json,
            criado_em
        )
        VALUES
        (?, ?, ?, ?, 'migracao_pontos_legado', 'pessoas.pontos', ?, 'Migração dos pontos legados para XP V2', ?, NOW())
    ");

    $stmt->execute([
        $tenantId,
        $pessoaId,
        $xpGanho,
        $pontosLegado,
        (string) $pessoaId,
        $metadataJson,
    ]);
}

function migracao_zerar_pontos_pessoa(PDO $pdo, int $pessoaId): void
{
    $stmt = $pdo->prepare("
        UPDATE pessoas
        SET pontos = 0,
            atualizado_em = NOW()
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$pessoaId]);
}

function migracao_buscar_pessoas(PDO $pdo): array
{
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.nome,
            p.pontos
        FROM pessoas p
        WHERE p.status = 'ativo'
          AND COALESCE(p.pontos, 0) > 0
        ORDER BY p.pontos DESC, p.id ASC
    ");
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$executar = migracao_bool_param('executar');
$confirmar = trim((string) migracao_param('confirmar', ''));
$zerarPontos = migracao_bool_param('zerar_pontos');

$tenantParam = (int) migracao_param('tenant_id', 0);
$tenantFiltro = $tenantParam > 0 ? $tenantParam : 2;

if ($tenantFiltro <= 0) {
    echo "Tenant nao identificado.\n";
    exit;
}

$dryRun = !$executar;

if ($executar && $confirmar !== 'MIGRAR_PONTOS_LEGADO') {
    echo "Confirmacao invalida.\n";
    echo "Use confirmar=MIGRAR_PONTOS_LEGADO para executar.\n";
    exit;
}

$obrigatorias = [
    'pessoas',
    'game_usuario_estado',
    'game_moedas_ledger',
    'game_xp_ledger',
];

foreach ($obrigatorias as $tabela) {
    if (!migracao_tabela_existe($pdo, $tabela)) {
        echo "Tabela obrigatoria ausente: {$tabela}\n";
        exit;
    }
}

if (!migracao_coluna_existe($pdo, 'pessoas', 'pontos')) {
    echo "Coluna obrigatoria ausente: pessoas.pontos\n";
    exit;
}

migracao_log('============================================================');
migracao_log('MIGRACAO TEMPORARIA DE PONTOS LEGADOS PARA GAME');
migracao_log('============================================================');
migracao_log('Modo: ' . ($dryRun ? 'DRY-RUN (nao grava)' : 'EXECUCAO REAL'));
migracao_log('Tenant filtro: ' . (string) $tenantFiltro);
migracao_log('Zerar pessoas.pontos apos migrar: ' . ($zerarPontos ? 'SIM' : 'NAO'));
migracao_log('Data: ' . date('Y-m-d H:i:s'));
migracao_log('============================================================');
migracao_log();

$pessoas = migracao_buscar_pessoas($pdo);

$totalPessoas = count($pessoas);
$totalPontosLegado = 0;
$totalXpPrevisto = 0;
$totalMigradas = 0;
$totalIgnoradas = 0;
$totalErros = 0;
$totalZeradas = 0;

migracao_log('Pessoas encontradas com pontos legado > 0: ' . number_format($totalPessoas, 0, ',', '.'));
migracao_log();

foreach ($pessoas as $pessoa) {
    $pessoaId = (int) ($pessoa['id'] ?? 0);
    $nome = trim((string) ($pessoa['nome'] ?? 'Participante'));
    $pontosLegado = max(0, (int) ($pessoa['pontos'] ?? 0));
    $tenantId = $tenantFiltro;

    if ($pessoaId <= 0 || $pontosLegado <= 0) {
        $totalIgnoradas++;
        continue;
    }

    $xpLegado = intdiv($pontosLegado, 100);

    $totalPontosLegado += $pontosLegado;
    $totalXpPrevisto += $xpLegado;

    try {
        $jaMigrado = migracao_ledger_moedas_existe($pdo, $tenantId, $pessoaId);

        if ($jaMigrado) {
            migracao_log("[IGNORADO] pessoa_id={$pessoaId} tenant={$tenantId} ja possui ledger migracao_pontos_legado.");

            if (!$dryRun && $zerarPontos) {
                migracao_zerar_pontos_pessoa($pdo, $pessoaId);
                $totalZeradas++;
                migracao_log("          pessoas.pontos zerado porque zerar_pontos=1.");
            }

            $totalIgnoradas++;
            continue;
        }

        $estadoAntes = migracao_buscar_estado($pdo, $tenantId, $pessoaId);

        $saldoAntes = $estadoAntes ? (int) ($estadoAntes['moedas_saldo'] ?? 0) : 0;
        $totalGanhasAntes = $estadoAntes ? (int) ($estadoAntes['moedas_total_ganhas'] ?? 0) : 0;
        $xpAntes = $estadoAntes ? (int) ($estadoAntes['xp_total'] ?? 0) : 0;
        $nivelAntes = $estadoAntes ? (int) ($estadoAntes['nivel_atual'] ?? 1) : 1;

        $novoSaldo = max(0, $saldoAntes + $pontosLegado);
        $novoTotalGanhas = max(0, $totalGanhasAntes + $pontosLegado);
        $novoXpTotal = intdiv($novoTotalGanhas, 100);
        $xpGanho = max(0, $novoXpTotal - $xpAntes);
        $novoNivel = migracao_resolver_nivel_por_xp($pdo, $novoXpTotal);

        $metadata = [
            'script' => 'migrar-pontos-legado.php',
            'pessoa_id' => $pessoaId,
            'tenant_cliente_id' => $tenantId,
            'pontos_legado' => $pontosLegado,
            'saldo_antes' => $saldoAntes,
            'moedas_total_ganhas_antes' => $totalGanhasAntes,
            'xp_total_antes' => $xpAntes,
            'nivel_antes' => $nivelAntes,
            'novo_saldo' => $novoSaldo,
            'novo_total_ganhas' => $novoTotalGanhas,
            'novo_xp_total' => $novoXpTotal,
            'xp_migrado' => $xpGanho,
            'novo_nivel' => $novoNivel,
            'zerar_pessoas_pontos' => $zerarPontos,
            'executado_em' => date('c'),
        ];

        migracao_log(
            ($dryRun ? '[DRY-RUN]' : '[MIGRAR]') .
            " pessoa_id={$pessoaId} tenant={$tenantId} nome=\"{$nome}\" legado=" .
            number_format($pontosLegado, 0, ',', '.') .
            " moedas | saldo {$saldoAntes} -> {$novoSaldo} | total_ganhas {$totalGanhasAntes} -> {$novoTotalGanhas} | XP {$xpAntes} -> {$novoXpTotal} | nivel {$nivelAntes} -> {$novoNivel}"
        );

        if (!$dryRun) {
            $pdo->beginTransaction();

            try {
                if ($estadoAntes) {
                    migracao_atualizar_estado(
                        $pdo,
                        $tenantId,
                        $pessoaId,
                        $novoSaldo,
                        $novoTotalGanhas,
                        $novoXpTotal,
                        $novoNivel
                    );
                } else {
                    migracao_inserir_estado(
                        $pdo,
                        $tenantId,
                        $pessoaId,
                        $pontosLegado,
                        $novoXpTotal,
                        $novoNivel
                    );
                }

                migracao_registrar_ledger_moedas($pdo, $tenantId, $pessoaId, $pontosLegado, $metadata);
                migracao_registrar_ledger_xp($pdo, $tenantId, $pessoaId, $xpGanho, $pontosLegado, $metadata);

                if ($zerarPontos) {
                    migracao_zerar_pontos_pessoa($pdo, $pessoaId);
                    $totalZeradas++;
                }

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        $totalMigradas++;
    } catch (Throwable $e) {
        $totalErros++;
        error_log('[migrar-pontos-legado-temp] Erro pessoa_id=' . $pessoaId . ': ' . $e->getMessage());
        migracao_log("[ERRO] pessoa_id={$pessoaId} " . $e->getMessage());
    }
}

migracao_log();
migracao_log('============================================================');
migracao_log('RESUMO');
migracao_log('============================================================');
migracao_log('Pessoas encontradas: ' . number_format($totalPessoas, 0, ',', '.'));
migracao_log('Migradas/previstas: ' . number_format($totalMigradas, 0, ',', '.'));
migracao_log('Ignoradas: ' . number_format($totalIgnoradas, 0, ',', '.'));
migracao_log('Erros: ' . number_format($totalErros, 0, ',', '.'));
migracao_log('Pontos legado somados: ' . number_format($totalPontosLegado, 0, ',', '.'));
migracao_log('XP legado previsto bruto: ' . number_format($totalXpPrevisto, 0, ',', '.'));
migracao_log('pessoas.pontos zerados: ' . number_format($totalZeradas, 0, ',', '.'));
migracao_log('Modo final: ' . ($dryRun ? 'DRY-RUN' : 'EXECUTADO'));
migracao_log('============================================================');

if ($dryRun) {
    migracao_log();
    migracao_log('Para executar de verdade sem zerar pessoas.pontos:');
    migracao_log('/migrar-pontos-legado.php?chave=MIGRAR_ELAB_2026&executar=1&confirmar=MIGRAR_PONTOS_LEGADO');
    migracao_log();
    migracao_log('Para executar e zerar pessoas.pontos:');
    migracao_log('/migrar-pontos-legado.php?chave=MIGRAR_ELAB_2026&executar=1&confirmar=MIGRAR_PONTOS_LEGADO&zerar_pontos=1');
    migracao_log();
    migracao_log('Depois da migracao, apague este arquivo temporario.');
}