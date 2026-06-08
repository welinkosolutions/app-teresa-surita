<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';
require_once '/home/elab/public_html/core/missao/bootstrap.php';
require_once '/home/elab/public_html/core/missao/planner.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function json_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function hstr(mixed $value): string
{
    return trim((string) $value);
}

function img_home_fallback(string $network, string $imagem): string
{
    $network = strtolower(trim($network));
    $imagem = trim($imagem);

    if ($imagem === '') {
        return '/assets/animations/teresa.webp';
    }

    if (str_starts_with($imagem, '/uploads/')) {
        return $imagem;
    }

    if (str_starts_with($imagem, '/assets/')) {
        return $imagem;
    }

    if (
        $network === 'facebook'
        && str_starts_with($imagem, 'https://')
        && (
            str_contains($imagem, 'fbcdn.net')
            || str_contains($imagem, 'scontent')
        )
    ) {
        return '/assets/animations/teresa.webp';
    }

    if (
        $network === 'instagram'
        && str_starts_with($imagem, 'https://')
        && (
            str_contains($imagem, 'cdninstagram.com')
            || str_contains($imagem, 'scontent')
        )
    ) {
        return '/assets/animations/teresa.webp';
    }

    return $imagem;
}

function avatar_usuario(array $pessoa): string
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

function nivel_por_xp(PDO $pdo, int $pessoaId, int $xpTotal): array
{
    $nivel = 1;
    $nome = 'Iniciante';
    $xpAtual = $xpTotal;

    try {
        $tenantId = function_exists('tenant_resolver_id_atual') ? tenant_resolver_id_atual($pdo) : null;

        if ($tenantId !== null) {
            $stmt = $pdo->prepare("
                SELECT nivel_atual, xp_total
                FROM vw_game_usuario_estado_resumo
                WHERE tenant_cliente_id = ?
                  AND pessoa_id = ?
                LIMIT 1
            ");
            $stmt->execute([$tenantId, $pessoaId]);
            $estado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($estado) {
                $nivel = max(1, (int) ($estado['nivel_atual'] ?? 1));
                $xpAtual = max($xpAtual, (int) ($estado['xp_total'] ?? 0));
            }
        }

        $stmt = $pdo->prepare("
            SELECT nome
            FROM game_niveis
            WHERE nivel = ?
              AND ativo = 'sim'
            LIMIT 1
        ");
        $stmt->execute([$nivel]);
        $nomeBanco = trim((string) ($stmt->fetchColumn() ?: ''));

        if ($nomeBanco !== '') {
            $nome = $nomeBanco;
        }
    } catch (Throwable $e) {
        error_log('[MISSAO_ATUAL_API_NIVEL] ' . $e->getMessage());
    }

    $proximo = match (true) {
        $xpAtual < 10 => 10,
        $xpAtual < 30 => 30,
        $xpAtual < 60 => 60,
        $xpAtual < 100 => 100,
        $xpAtual < 160 => 160,
        $xpAtual < 250 => 250,
        $xpAtual < 400 => 400,
        $xpAtual < 700 => 700,
        $xpAtual < 1000 => 1000,
        default => (int) (ceil(($xpAtual + 1) / 500) * 500),
    };

    return [
        'numero' => $nivel,
        'nome' => $nome,
        'xp_atual' => $xpAtual,
        'xp_proximo' => $proximo,
        'xp_faltante' => max(0, $proximo - $xpAtual),
        'percent' => max(4, min(100, (int) round(($xpAtual / max(1, $proximo)) * 100))),
    ];
}

try {
    $pdo = dbRoraima();

    $pessoaId = (int) ($_SESSION['pessoa_id'] ?? $_SESSION['pessoa_logada_id'] ?? $_SESSION['usuario_id'] ?? 0);

    if ($pessoaId <= 0) {
        json_out([
            'ok' => false,
            'error' => 'unauthorized',
        ], 401);
    }

    $codigoAtual = hstr($_GET['codigo_atual'] ?? '');
    $estadoAtualId = (int) ($_GET['estado_id_atual'] ?? 0);

    try {
        $stmtExpire = $pdo->prepare("
            UPDATE missao_estado_usuario
            SET status = 'expirada',
                atualizada_em = NOW()
            WHERE pessoa_id = ?
              AND status = 'ativa'
              AND expira_em IS NOT NULL
              AND expira_em < NOW()
        ");
        $stmtExpire->execute([$pessoaId]);

        $stmtSyncDone = $pdo->prepare("
            UPDATE missao_estado_usuario meu
            SET meu.status = 'concluida',
                meu.concluida_em = COALESCE(meu.concluida_em, NOW()),
                meu.evento_conclusao_tipo = COALESCE(meu.evento_conclusao_tipo, 'historico_sync'),
                meu.atualizada_em = NOW()
            WHERE meu.pessoa_id = ?
              AND meu.status = 'ativa'
              AND EXISTS (
                SELECT 1
                FROM missao_historico_usuario h
                WHERE h.pessoa_id = meu.pessoa_id
                  AND h.network = meu.network
                  AND h.post_id = meu.post_id
                  AND (
                    h.missao_estado_id_origem = meu.id
                    OR h.missao_codigo = meu.missao_codigo
                    OR h.missao_tipo = meu.missao_tipo
                  )
                  AND (
                    h.status IN ('concluida', 'executada')
                    OR h.status_final IN ('concluida', 'executada', 'ok')
                    OR h.concluida_em IS NOT NULL
                  )
                  AND h.criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)
              )
        ");
        $stmtSyncDone->execute([$pessoaId]);
    } catch (Throwable $e) {
        error_log('[MISSAO_ATUAL_PREPARE] ' . $e->getMessage());
    }

    $cols = [];
    $colStmt = $pdo->query("SHOW COLUMNS FROM pessoas");
    foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        $cols[strtolower((string) $col['Field'])] = (string) $col['Field'];
    }

    $selectPessoa = ['id'];

    foreach (['nome', 'nome_exibicao', 'apelido', 'pontos', 'xp_total', 'sexo'] as $candidate) {
        if (isset($cols[$candidate])) {
            $selectPessoa[] = $cols[$candidate];
        }
    }

    $selectPessoa = array_values(array_unique($selectPessoa));

    $stmtPessoa = $pdo->prepare("
        SELECT " . implode(', ', array_map(static fn($c) => '`' . str_replace('`', '``', $c) . '`', $selectPessoa)) . "
        FROM pessoas
        WHERE id = ?
        LIMIT 1
    ");
    $stmtPessoa->execute([$pessoaId]);
    $pessoa = $stmtPessoa->fetch(PDO::FETCH_ASSOC);

    if (!$pessoa) {
        json_out([
            'ok' => false,
            'error' => 'pessoa_not_found',
        ], 404);
    }

    $xpTotal = (int) ($pessoa['pontos'] ?? $pessoa['xp_total'] ?? 0);

    $nomeExibicao = trim((string) ($pessoa['nome_exibicao'] ?? ''));
    if ($nomeExibicao === '') {
        $nomeExibicao = trim((string) ($pessoa['apelido'] ?? ''));
    }
    if ($nomeExibicao === '') {
        $nomeExibicao = trim((string) ($pessoa['nome'] ?? ''));
    }

    $nivel = nivel_por_xp($pdo, $pessoaId, $xpTotal);

    $stmt = $pdo->prepare("
        SELECT
            id,
            missao_codigo,
            missao_tipo,
            network,
            post_id,
            acao,
            payload_json,
            expira_em,
            atualizada_em
        FROM missao_estado_usuario
        WHERE pessoa_id = ?
          AND status = 'ativa'
          AND payload_json IS NOT NULL
          AND JSON_VALID(payload_json)
          AND (disponivel_em IS NULL OR disponivel_em <= NOW())
          AND (expira_em IS NULL OR expira_em > NOW())
        ORDER BY prioridade DESC, atualizada_em DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([$pessoaId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        try {
            if (function_exists('missaoGerarMissaoDoDia')) {
                missaoGerarMissaoDoDia($pdo, $pessoaId, true);

                $stmt->execute([$pessoaId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) {
            error_log('[MISSAO_ATUAL_GERAR_PROXIMA] ' . $e->getMessage());
        }

        if (!$row) {
            json_out([
                'ok' => true,
                'changed' => true,
                'missao' => null,
                'xp_total' => $xpTotal,
                'nivel' => $nivel,
                'avatar' => avatar_usuario($pessoa),
                'nome' => $nomeExibicao,
            ]);
        }
    }

    $payload = json_decode((string) $row['payload_json'], true);
    if (!is_array($payload)) {
        $payload = [];
    }

    $network = strtolower(hstr($row['network'] ?? ($payload['network'] ?? 'instagram')));
    $acao = strtolower(hstr($row['acao'] ?? ($payload['tipo_acao'] ?? 'comentar')));

    $redeLabel = $network === 'facebook' ? 'Facebook' : ($network === 'whatsapp' ? 'WhatsApp' : 'Instagram');

    $actionLabel = match ($acao) {
        'compartilhar' => 'Compartilhe',
        'curtir' => 'Curta',
        'video' => 'Assista',
        default => 'Comente',
    };

    $overlay = match (true) {
        $acao === 'compartilhar' && $network === 'whatsapp' => 'COMPARTILHAR NO WHATSAPP',
        $acao === 'compartilhar' => 'COMPARTILHAR',
        $acao === 'curtir' => 'CURTIR NO ' . mb_strtoupper($redeLabel, 'UTF-8'),
        $acao === 'video' => 'ASSISTIR',
        default => 'COMENTAR NO ' . mb_strtoupper($redeLabel, 'UTF-8'),
    };

    $imagemOriginal = hstr($payload['imagem_url'] ?? $payload['imagem'] ?? '');
    $imagem = img_home_fallback($network, $imagemOriginal);

    $pontos = (int) ($payload['pontos'] ?? 0);
    if ($pontos <= 0) {
        $pontos = 25;
    }

    $missao = [
        'estado_id' => (int) $row['id'],
        'codigo' => (string) $row['missao_codigo'],
        'tipo' => (string) $row['missao_tipo'],
        'network' => $network,
        'rede_label' => $redeLabel,
        'network_class' => match ($network) {
            'facebook' => 'is-facebook',
            'whatsapp' => 'is-whatsapp',
            default => 'is-instagram',
        },
        'rede_icon' => $network === 'facebook'
            ? '/assets/feed/statics/002-facebook.svg'
            : '/assets/feed/statics/001-instagram.svg',
        'post_id' => (int) ($row['post_id'] ?? 0),
        'acao' => $acao,
        'titulo' => 'Apoie a Teresa neste post!',
        'instrucao' => $actionLabel . ' no ' . $redeLabel . ' e ganhe +' . $pontos . ' pontos',
        'overlay' => $overlay,
        'url_destino' => hstr($payload['url_destino'] ?? ''),
        'imagem_url' => $imagem,
        'caption' => hstr($payload['caption'] ?? $payload['descricao'] ?? ''),
        'pontos' => $pontos,
        'expira_em' => (string) ($row['expira_em'] ?? ''),
        'atualizada_em' => (string) ($row['atualizada_em'] ?? ''),
    ];

    $changed = true;

    if ($estadoAtualId > 0 && $estadoAtualId === (int) $row['id']) {
        $changed = false;
    } elseif ($estadoAtualId <= 0 && $codigoAtual !== '' && $codigoAtual === (string) $row['missao_codigo']) {
        $changed = false;
    }

    json_out([
        'ok' => true,
        'changed' => $changed,
        'missao' => $missao,
        'xp_total' => $xpTotal,
        'nivel' => $nivel,
        'avatar' => avatar_usuario($pessoa),
        'nome' => $nomeExibicao,
    ]);
} catch (Throwable $e) {
    error_log('[MISSAO_ATUAL_API] ' . $e->getMessage());

    json_out([
        'ok' => false,
        'error' => 'server_error',
    ], 500);
}
