<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

function out_json(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if (empty($_SESSION['pessoa_id'])) {
        out_json(['ok' => false, 'error' => 'unauthorized'], 401);
    }

    $pdo = dbRoraima();
    $pessoaId = (int) $_SESSION['pessoa_id'];

    $stmt = $pdo->prepare("
        SELECT
            mp.id,
            mp.network,
            mp.caption,
            mp.permalink,
            mp.publicado_em,
            mp.atualizado_em,
            mps.titulo AS missao_titulo,
            mps.descricao AS missao_descricao
        FROM missao_post_semanal mps
        INNER JOIN metaverso_posts mp
                ON mp.id = mps.metaverso_post_id
        WHERE mps.status = 'ativo'
          AND CURDATE() BETWEEN mps.semana_inicio AND mps.semana_fim
          AND mp.status = 'ativo'
          AND mp.network IN ('instagram', 'facebook')
          AND mp.permalink IS NOT NULL
          AND TRIM(mp.permalink) <> ''
        ORDER BY mps.atualizado_em DESC, mps.id DESC
        LIMIT 1
    ");
    $stmt->execute();
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        $stmt = $pdo->prepare("
            SELECT
                id,
                network,
                caption,
                permalink,
                publicado_em,
                atualizado_em,
                NULL AS missao_titulo,
                NULL AS missao_descricao
            FROM metaverso_posts
            WHERE status = 'ativo'
              AND network IN ('instagram', 'facebook')
              AND permalink IS NOT NULL
              AND TRIM(permalink) <> ''
            ORDER BY publicado_em DESC, atualizado_em DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute();
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$post) {
        out_json([
            'ok' => false,
            'error' => 'post_not_found',
            'message' => 'Nenhum post disponível para compartilhar agora.',
        ], 404);
    }

    $postId = (int) ($post['id'] ?? 0);
    $network = strtolower(trim((string) ($post['network'] ?? '')));
    $url = trim((string) ($post['permalink'] ?? ''));
    $caption = trim((string) ($post['caption'] ?? ''));

    if ($postId <= 0 || $url === '') {
        out_json(['ok' => false, 'error' => 'invalid_post'], 422);
    }

    /*
     * GET serve apenas para diagnosticar o post escolhido.
     * POST registra progresso.
     */
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        out_json([
            'ok' => true,
            'mode' => 'preview',
            'url' => $url,
            'caption' => $caption,
            'network' => $network,
            'network_label' => $network === 'facebook' ? 'Facebook' : 'Instagram',
            'post_id' => $postId,
        ]);
    }

    $payload = [
        'tipo_acao' => 'compartilhar',
        'origem' => 'missao_painel',
        'canal' => 'whatsapp',
        'network' => $network,
        'post_id' => $postId,
        'url_destino' => $url,
        'caption' => $caption,
    ];

    $stmtHist = $pdo->prepare("
        INSERT INTO missao_historico_usuario
            (
                pessoa_id,
                origem,
                missao_codigo,
                missao_tipo,
                network,
                post_id,
                status,
                origem_regra,
                payload_json,
                criado_em,
                status_final,
                motivo_transicao,
                evento_gatilho_tipo,
                evento_gatilho_id,
                concluida_em
            )
        VALUES
            (
                ?,
                'automatica',
                'compartilhar_post_semana',
                'compartilhar',
                ?,
                ?,
                'concluida',
                'missao_painel',
                ?,
                NOW(),
                'concluida',
                'share_whatsapp',
                'share_post',
                ?,
                NOW()
            )
    ");

    $stmtHist->execute([
        $pessoaId,
        $network,
        $postId,
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        (string) $postId,
    ]);

    out_json([
        'ok' => true,
        'mode' => 'registered',
        'url' => $url,
        'caption' => $caption,
        'network' => $network,
        'network_label' => $network === 'facebook' ? 'Facebook' : 'Instagram',
        'post_id' => $postId,
        'historico_id' => (int) $pdo->lastInsertId(),
    ]);
} catch (Throwable $e) {
    error_log('[compartilhar-post] ' . $e->getMessage());

    out_json([
        'ok' => false,
        'error' => 'server_error',
        'debug' => $e->getMessage(),
    ], 500);
}
