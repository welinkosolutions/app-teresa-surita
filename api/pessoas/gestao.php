<?php
declare(strict_types=1);

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
        'erro' => 'Sessão inválida.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();
$pessoaId = (int)($_SESSION['pessoa_id'] ?? 0);

$tenantClienteId = 0;
if (isset($_SESSION['tenant_cliente_id'])) {
    $tenantClienteId = (int)$_SESSION['tenant_cliente_id'];
} elseif (isset($_SESSION['cliente_id'])) {
    $tenantClienteId = (int)$_SESSION['cliente_id'];
} elseif (isset($_SESSION['tenant_id'])) {
    $tenantClienteId = (int)$_SESSION['tenant_id'];
}

function acessoEspecialAtivo(PDO $pdo, int $tenantClienteId, int $pessoaId, string $recurso): bool
{
    if ($tenantClienteId > 0) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM acessos_especiais
            WHERE tenant_cliente_id = ?
              AND pessoa_id = ?
              AND recurso = ?
              AND status = 'ativo'
            LIMIT 1
        ");
        $stmt->execute([$tenantClienteId, $pessoaId, $recurso]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id
            FROM acessos_especiais
            WHERE pessoa_id = ?
              AND recurso = ?
              AND status = 'ativo'
            LIMIT 1
        ");
        $stmt->execute([$pessoaId, $recurso]);
    }

    return (bool)$stmt->fetchColumn();
}

try {
    $stmtPerfil = $pdo->prepare("
        SELECT perfil
        FROM pessoas
        WHERE id = ?
        LIMIT 1
    ");
    $stmtPerfil->execute([$pessoaId]);
    $perfil = trim((string)($stmtPerfil->fetchColumn() ?? 'pessoa'));

    $temAcesso = acessoEspecialAtivo($pdo, $tenantClienteId, $pessoaId, 'gestao_pessoas')
        || in_array($perfil, ['admin', 'gestor_lideres'], true);

    if (!$temAcesso) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'erro' => 'Acesso não autorizado.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmtCadastros = $pdo->query("
        SELECT
            COUNT(*) AS total_cadastros,
            SUM(CASE WHEN criado_em >= (NOW() - INTERVAL 1 DAY) THEN 1 ELSE 0 END) AS cadastros_24h,
            SUM(CASE WHEN criado_em >= (NOW() - INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS cadastros_7d
        FROM pessoas
    ");
    $cadastros = $stmtCadastros->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmtAniversarios = $pdo->query("
        SELECT
            SUM(
                CASE
                    WHEN data_nascimento IS NOT NULL
                     AND DATE_FORMAT(data_nascimento, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')
                    THEN 1 ELSE 0
                END
            ) AS aniversariantes_dia,
            SUM(
                CASE
                    WHEN data_nascimento IS NOT NULL
                     AND MONTH(data_nascimento) = MONTH(CURDATE())
                    THEN 1 ELSE 0
                END
            ) AS aniversariantes_mes
        FROM pessoas
    ");
    $aniversarios = $stmtAniversarios->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmtConvites = $pdo->query("
        SELECT COUNT(*) AS total_convites_enviados
        FROM convites_aprovacoes
    ");
    $convites = $stmtConvites->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmtComentarios = $pdo->query("
        SELECT COALESCE(SUM(comentarios), 0) AS comentarios_30d
        FROM metaverso_post_metricas_atuais
        WHERE atualizado_em >= (NOW() - INTERVAL 30 DAY)
    ");
    $comentarios = $stmtComentarios->fetch(PDO::FETCH_ASSOC) ?: [];

    $meses = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Março',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro',
    ];

    $mesAtualNumero = (int)date('n');
    $mesAtualNome = $meses[$mesAtualNumero] ?? '';

    echo json_encode([
        'ok' => true,
        'dados' => [
            'total_cadastros' => (int)($cadastros['total_cadastros'] ?? 0),
            'cadastros_24h' => (int)($cadastros['cadastros_24h'] ?? 0),
            'cadastros_7d' => (int)($cadastros['cadastros_7d'] ?? 0),
            'aniversariantes_dia' => (int)($aniversarios['aniversariantes_dia'] ?? 0),
            'aniversariantes_mes' => (int)($aniversarios['aniversariantes_mes'] ?? 0),
            'mes_atual_nome' => $mesAtualNome,
            'mes_atual_numero' => $mesAtualNumero,
            'total_convites_enviados' => (int)($convites['total_convites_enviados'] ?? 0),
            'comentarios_30d' => (int)($comentarios['comentarios_30d'] ?? 0),
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('[API_GESTAO_PESSOAS] ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'erro' => 'Erro interno ao carregar Pulso da Base.'
    ], JSON_UNESCAPED_UNICODE);
}