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
    $source = 'missao_historico_usuario';

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM missao_historico_usuario
        WHERE pessoa_id = ?
          AND (
            missao_codigo LIKE '%compart%'
            OR missao_tipo LIKE '%compart%'
            OR JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.tipo_acao')) = 'compartilhar'
          )
          AND (
            status IN ('concluida', 'executada')
            OR status_final IN ('concluida', 'executada', 'ok')
            OR concluida_em IS NOT NULL
          )
          AND COALESCE(concluida_em, criado_em) >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
          AND COALESCE(concluida_em, criado_em) < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)
    ");
    $stmt->execute([$pessoaId]);
    $feitos = (int) $stmt->fetchColumn();

    if ($feitos <= 0 && table_exists($pdo, 'missao_compartilhamentos')) {
        $ownerCol = column_exists($pdo, 'missao_compartilhamentos', 'pessoa_id') ? 'pessoa_id' : null;
        $dateCol = column_exists($pdo, 'missao_compartilhamentos', 'criado_em') ? 'criado_em' : (column_exists($pdo, 'missao_compartilhamentos', 'created_at') ? 'created_at' : null);

        if ($ownerCol && $dateCol) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM missao_compartilhamentos
                WHERE {$ownerCol} = ?
                  AND {$dateCol} >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                  AND {$dateCol} < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)
            ");
            $stmt->execute([$pessoaId]);
            $feitos = (int) $stmt->fetchColumn();
            $source = 'missao_compartilhamentos';
        }
    }

    $feitos = max(0, min($meta, $feitos));
    $faltam = max(0, $meta - $feitos);

    out_json([
        'ok' => true,
        'tipo' => 'compartilhar',
        'titulo' => 'Compartilhe 5 posts essa semana',
        'subtitulo' => $faltam > 0 ? "Faltam {$faltam} posts para concluir." : 'Missão concluída.',
        'meta' => $meta,
        'feitos' => $feitos,
        'faltam' => $faltam,
        'percent' => (int) round(($feitos / $meta) * 100),
        'source' => $source,
    ]);
} catch (Throwable $e) {
    error_log('[ENDPOINT_COMPARTILHAR] ' . $e->getMessage());
    out_json(['ok' => false, 'error' => 'server_error'], 500);
}
