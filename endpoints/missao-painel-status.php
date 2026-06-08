<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

function out_json(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

function card_payload(string $tipo, string $titulo, int $meta, int $feitos, string $unidade): array
{
    $feitos = max(0, min($meta, $feitos));
    $faltam = max(0, $meta - $feitos);

    return [
        'tipo' => $tipo,
        'titulo' => $titulo,
        'meta' => $meta,
        'feitos' => $feitos,
        'faltam' => $faltam,
        'percent' => $meta > 0 ? (int) round(($feitos / $meta) * 100) : 0,
        'subtitulo' => $faltam > 0 ? "Faltam {$faltam} {$unidade} para concluir." : 'Missão concluída.',
    ];
}

try {
    if (empty($_SESSION['pessoa_id'])) {
        out_json(['ok' => false, 'error' => 'unauthorized'], 401);
    }

    $pdo = dbRoraima();
    $pessoaId = (int) $_SESSION['pessoa_id'];
    $meta = 5;

    $convitesFeitos = 0;
    if (table_exists($pdo, 'convites_compartilhamentos')) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM convites_compartilhamentos
            WHERE pessoa_id = ?
              AND criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute([$pessoaId]);
        $convitesFeitos = (int) $stmt->fetchColumn();
    }

    $comentariosStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM missao_historico_usuario
        WHERE pessoa_id = ?
          AND (
            missao_tipo = 'comentario'
            OR missao_codigo LIKE '%coment%'
            OR JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.tipo_acao')) = 'comentar'
          )
          AND (
            status IN ('concluida', 'executada')
            OR status_final IN ('concluida', 'executada', 'ok')
            OR concluida_em IS NOT NULL
          )
          AND COALESCE(concluida_em, criado_em) >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $comentariosStmt->execute([$pessoaId]);
    $comentariosFeitos = (int) $comentariosStmt->fetchColumn();

    $shareStmt = $pdo->prepare("
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
          AND COALESCE(concluida_em, criado_em) >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $shareStmt->execute([$pessoaId]);
    $shareFeitos = (int) $shareStmt->fetchColumn();

    $cards = [
        'convites' => card_payload('convites', 'Convide 5 pessoas para o time', $meta, $convitesFeitos, 'convites'),
        'comentarios' => card_payload('comentarios', 'Comente 5 posts essa semana', $meta, $comentariosFeitos, 'comentários'),
        'compartilhar' => card_payload('compartilhar', 'Compartilhe esse post com 5 amigos', $meta, $shareFeitos, 'compartilhamentos'),
    ];

    $totalFeitos = array_sum(array_map(static fn($c) => (int) $c['feitos'], $cards));
    $totalMeta = array_sum(array_map(static fn($c) => (int) $c['meta'], $cards));
    $totalFaltam = max(0, $totalMeta - $totalFeitos);
    $totalPercent = $totalMeta > 0 ? (int) round(($totalFeitos / $totalMeta) * 100) : 0;

    out_json([
        'ok' => true,
        'cards' => $cards,
        'summary' => [
            'feitos' => $totalFeitos,
            'faltam' => $totalFaltam,
            'percent' => $totalPercent,
        ],
    ]);
} catch (Throwable $e) {
    error_log('[missao-painel-status] ' . $e->getMessage());
    out_json(['ok' => false, 'error' => 'server_error'], 500);
}
