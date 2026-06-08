<?php
declare(strict_types=1);

/**
 * ======================================================
 * CAMINHO: /home/elab/app.elab.social/api/feed/interagir.php
 * NOME: API Feed Comunidade V2 — Interagir
 *
 * DESCRIÇÃO:
 * - Registra interação do usuário em um item do feed
 * - Usado pelo botão lateral de apoio/celebração
 * - Compatível com a tabela atual comunidade_interacoes
 * - Evita duplicidade pela UNIQUE KEY:
 *   tenant_cliente_id + pessoa_id + origem_tipo + origem_id + tipo
 * ======================================================
 */

require_once __DIR__ . '/_feed_helpers.php';

$pessoaId = feedRequireLogin();
$pdo = dbRoraima();
$tenantClienteId = feedTenantId($pdo, $pessoaId);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    feedJson([
        'ok' => false,
        'erro' => 'metodo_invalido',
        'mensagem' => 'Método inválido.',
    ], 405);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '{}', true);

if (!is_array($payload)) {
    $payload = $_POST;
}

$origemTipo = trim((string) ($payload['origem_tipo'] ?? ''));
$origemId = (int) ($payload['origem_id'] ?? 0);
$tipo = trim((string) ($payload['tipo'] ?? 'gostei'));

$origensPermitidas = [
    'game_eventos',
    'metaverso_posts',
    'social_events',
];

$tiposPermitidos = [
    'gostei',
    'comemorei',
    'apoiei',
    'curti',
    'vi',
];

if (!in_array($origemTipo, $origensPermitidas, true) || $origemId <= 0) {
    feedJson([
        'ok' => false,
        'erro' => 'dados_invalidos',
        'mensagem' => 'Item inválido.',
        'debug' => [
            'origem_tipo' => $origemTipo,
            'origem_id' => $origemId,
        ],
    ], 422);
}

if (!in_array($tipo, $tiposPermitidos, true)) {
    $tipo = 'gostei';
}

try {
    /*
     * Confirma que o item existe no feed.
     * Isso evita gravar interação em origem inexistente.
     */
    $stmt = $pdo->prepare("
        SELECT origem_id
        FROM vw_comunidade_feed_unificado
        WHERE origem_tipo = ?
          AND origem_id = ?
        LIMIT 1
    ");
    $stmt->execute([$origemTipo, $origemId]);

    if (!$stmt->fetchColumn()) {
        feedJson([
            'ok' => false,
            'erro' => 'item_nao_encontrado',
            'mensagem' => 'Item não encontrado no feed.',
            'debug' => [
                'origem_tipo' => $origemTipo,
                'origem_id' => $origemId,
            ],
        ], 404);
    }

    /*
     * A tabela atual não tem atualizado_em.
     * A chave única já impede duplicidade do mesmo tipo.
     */
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO comunidade_interacoes
            (
                tenant_cliente_id,
                pessoa_id,
                origem_tipo,
                origem_id,
                tipo,
                criado_em
            )
        VALUES
            (?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $tenantClienteId,
        $pessoaId,
        $origemTipo,
        $origemId,
        $tipo,
    ]);

    $gravou = $stmt->rowCount() > 0;

    /*
     * Total geral de apoios/interações exibido no botão lateral.
     */
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM comunidade_interacoes
        WHERE tenant_cliente_id = ?
          AND origem_tipo = ?
          AND origem_id = ?
          AND tipo IN ('gostei', 'comemorei', 'apoiei', 'curti')
    ");
    $stmt->execute([
        $tenantClienteId,
        $origemTipo,
        $origemId,
    ]);

    $totalInteracoes = (int) $stmt->fetchColumn();

    /*
     * Totais quebrados por tipo, caso o front/API queira usar depois.
     */
    $stmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN tipo = 'gostei' THEN 1 ELSE 0 END) AS total_gostei,
            SUM(CASE WHEN tipo = 'comemorei' THEN 1 ELSE 0 END) AS total_comemorei,
            SUM(CASE WHEN tipo = 'apoiei' THEN 1 ELSE 0 END) AS total_apoiei,
            SUM(CASE WHEN tipo = 'curti' THEN 1 ELSE 0 END) AS total_curti
        FROM comunidade_interacoes
        WHERE tenant_cliente_id = ?
          AND origem_tipo = ?
          AND origem_id = ?
    ");
    $stmt->execute([
        $tenantClienteId,
        $origemTipo,
        $origemId,
    ]);

    $totais = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    feedJson([
        'ok' => true,
        'origem_tipo' => $origemTipo,
        'origem_id' => $origemId,
        'tipo' => $tipo,
        'gravou' => $gravou ? 'sim' : 'nao',
        'ja_existia' => $gravou ? 'nao' : 'sim',
        'total_interacoes' => $totalInteracoes,
        'total_gostei' => (int) ($totais['total_gostei'] ?? 0),
        'total_comemorei' => (int) ($totais['total_comemorei'] ?? 0),
        'total_apoiei' => (int) ($totais['total_apoiei'] ?? 0),
        'total_curti' => (int) ($totais['total_curti'] ?? 0),
    ]);
} catch (Throwable $e) {
    feedLogErro('interagir', $e);

    feedJson([
        'ok' => false,
        'erro' => 'erro_interagir',
        'mensagem' => 'Não foi possível registrar sua interação agora.',
        'debug' => [
            'origem_tipo' => $origemTipo,
            'origem_id' => $origemId,
            'tipo' => $tipo,
            'tenant_cliente_id' => $tenantClienteId,
            'pessoa_id' => $pessoaId,
            'erro_tecnico' => $e->getMessage(),
        ],
    ], 500);
}