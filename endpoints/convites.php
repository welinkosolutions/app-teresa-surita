<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

function out_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

try {
    $pdo = dbRoraima();
    $pessoaId = (int) ($_SESSION['pessoa_id'] ?? 0);

    if ($pessoaId <= 0) {
        out_json(['ok' => false, 'error' => 'unauthorized'], 401);
    }

    $meta = 5;
    $feitos = 0;
    $source = 'fallback';

    if (table_exists($pdo, 'convites')) {
        $cols = [
            'pessoa_id' => column_exists($pdo, 'convites', 'pessoa_id'),
            'convidante_id' => column_exists($pdo, 'convites', 'convidante_id'),
            'criado_em' => column_exists($pdo, 'convites', 'criado_em'),
            'created_at' => column_exists($pdo, 'convites', 'created_at'),
        ];

        $ownerCol = $cols['pessoa_id'] ? 'pessoa_id' : ($cols['convidante_id'] ? 'convidante_id' : null);
        $dateCol = $cols['criado_em'] ? 'criado_em' : ($cols['created_at'] ? 'created_at' : null);

        if ($ownerCol && $dateCol) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM convites
                WHERE {$ownerCol} = ?
                  AND {$dateCol} >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                  AND {$dateCol} < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)
            ");
            $stmt->execute([$pessoaId]);
            $feitos = (int) $stmt->fetchColumn();
            $source = 'convites';
        }
    }

    if ($source === 'fallback' && table_exists($pdo, 'rede_indicacoes')) {
        $cols = [
            'pessoa_id' => column_exists($pdo, 'rede_indicacoes', 'pessoa_id'),
            'indicador_id' => column_exists($pdo, 'rede_indicacoes', 'indicador_id'),
            'criado_em' => column_exists($pdo, 'rede_indicacoes', 'criado_em'),
            'created_at' => column_exists($pdo, 'rede_indicacoes', 'created_at'),
        ];

        $ownerCol = $cols['pessoa_id'] ? 'pessoa_id' : ($cols['indicador_id'] ? 'indicador_id' : null);
        $dateCol = $cols['criado_em'] ? 'criado_em' : ($cols['created_at'] ? 'created_at' : null);

        if ($ownerCol && $dateCol) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM rede_indicacoes
                WHERE {$ownerCol} = ?
                  AND {$dateCol} >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                  AND {$dateCol} < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)
            ");
            $stmt->execute([$pessoaId]);
            $feitos = (int) $stmt->fetchColumn();
            $source = 'rede_indicacoes';
        }
    }

    $feitos = max(0, min($meta, $feitos));
    $faltam = max(0, $meta - $feitos);

    out_json([
        'ok' => true,
        'tipo' => 'convites',
        'titulo' => 'Convide 5 pessoas para o time',
        'subtitulo' => $faltam > 0 ? "Faltam {$faltam} convites para concluir." : 'Missão concluída.',
        'meta' => $meta,
        'feitos' => $feitos,
        'faltam' => $faltam,
        'percent' => (int) round(($feitos / $meta) * 100),
        'source' => $source,
    ]);
} catch (Throwable $e) {
    error_log('[ENDPOINT_CONVITES] ' . $e->getMessage());
    out_json(['ok' => false, 'error' => 'server_error'], 500);
}
