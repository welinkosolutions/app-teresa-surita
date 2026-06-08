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

try {
    $pdo = dbRoraima();
    $pessoaId = (int) ($_SESSION['pessoa_id'] ?? 0);

    if ($pessoaId <= 0) {
        out_json(['ok' => false, 'error' => 'unauthorized'], 401);
    }

    $meta = 5;

    $stmt = $pdo->prepare("
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
          AND COALESCE(concluida_em, criado_em) >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
          AND COALESCE(concluida_em, criado_em) < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)
    ");
    $stmt->execute([$pessoaId]);
    $feitos = (int) $stmt->fetchColumn();

    $feitos = max(0, min($meta, $feitos));
    $faltam = max(0, $meta - $feitos);

    out_json([
        'ok' => true,
        'tipo' => 'comentarios',
        'titulo' => 'Comente 5 posts essa semana',
        'subtitulo' => $faltam > 0 ? "Faltam {$faltam} posts para concluir." : 'Missão concluída.',
        'meta' => $meta,
        'feitos' => $feitos,
        'faltam' => $faltam,
        'percent' => (int) round(($feitos / $meta) * 100),
        'source' => 'missao_historico_usuario',
    ]);
} catch (Throwable $e) {
    error_log('[ENDPOINT_COMENTARIOS] ' . $e->getMessage());
    out_json(['ok' => false, 'error' => 'server_error'], 500);
}
