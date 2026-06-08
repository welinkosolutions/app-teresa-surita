<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';
require_once '/home/elab/public_html/core/missao/planner.php';

if (empty($_SESSION['pessoa_id'])) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'erro' => 'Sessão expirada.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$pdo = dbRoraima();
$pessoaId = (int) $_SESSION['pessoa_id'];

if (!function_exists('homeStateBuildMissaoCard')) {
    function homeStateBuildMissaoCard(?array $missaoAtual): ?array
    {
        if (empty($missaoAtual) || !is_array($missaoAtual)) {
            return null;
        }

        $payload = is_array($missaoAtual['payload'] ?? null)
            ? $missaoAtual['payload']
            : [];

        $tipoAcao = trim((string) ($payload['tipo_acao'] ?? $missaoAtual['missao_tipo'] ?? 'abrir'));
        $titulo = trim((string) ($payload['titulo'] ?? 'Missão do dia'));
        $descricao = trim((string) ($payload['descricao'] ?? 'Tem uma ação te esperando agora.'));
        $ctaLabel = trim((string) ($payload['cta_label'] ?? 'Abrir agora'));
        $imagem = trim((string) ($payload['imagem_url'] ?? ''));
        $urlDestino = trim((string) ($payload['url_destino'] ?? ''));
        $caption = trim((string) ($payload['caption'] ?? ''));
        $pontos = (int) ($payload['pontos'] ?? 0);
        $postId = (int) ($missaoAtual['post_id'] ?? 0);

        if ($imagem === '') {
            $imagem = '/assets/img/post-placeholder.jpg';
        }

        $btnClass = 'btn-missao-cta';
        $icon = 'bi-lightning-charge-fill';

        if ($tipoAcao === 'compartilhar') {
            $btnClass .= ' is-whatsapp';
            $icon = 'bi-whatsapp';
        } elseif (in_array($tipoAcao, ['post', 'comentario'], true)) {
            $btnClass .= ' is-instagram';
            $icon = 'bi-instagram';
        } elseif ($tipoAcao === 'video') {
            $btnClass .= ' is-video';
            $icon = 'bi-play-circle-fill';
        } else {
            $btnClass .= ' is-default';
            $icon = 'bi-cursor-fill';
        }

        return [
            'codigo' => (string) ($missaoAtual['missao_codigo'] ?? ''),
            'tipo_acao' => $tipoAcao,
            'titulo' => $titulo,
            'descricao' => $descricao,
            'caption' => $caption,
            'cta_label' => $ctaLabel,
            'imagem' => $imagem,
            'url_destino' => $urlDestino,
            'pontos' => $pontos,
            'post_id' => $postId,
            'btn_class' => $btnClass,
            'icon' => $icon,
            'status' => (string) ($missaoAtual['status'] ?? 'ativa'),
            'network' => (string) ($missaoAtual['network'] ?? ''),
            'missao_tipo' => (string) ($missaoAtual['missao_tipo'] ?? ''),
            'origem_regra' => (string) ($missaoAtual['origem_regra'] ?? ''),
        ];
    }
}

if (!function_exists('homeStateGetPessoa')) {
    function homeStateGetPessoa(PDO $pdo, int $pessoaId): array
    {
        $stmt = $pdo->prepare("
            SELECT
                id,
                nome,
                apelido,
                chamar_por,
                pontos,
                perfil,
                status_validacao
            FROM pessoas
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$pessoaId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('homeStateGetRankingPosicao')) {
    function homeStateGetRankingPosicao(PDO $pdo, int $pessoaId): int
    {
        $stmt = $pdo->prepare("
            SELECT posicao
            FROM vw_ranking_geral
            WHERE pessoa_id = ?
            LIMIT 1
        ");
        $stmt->execute([$pessoaId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }
}

if (!function_exists('homeStateGetEstado')) {
    function homeStateGetEstado(PDO $pdo, int $pessoaId): array
    {
        $stmt = $pdo->prepare("
            SELECT chave, valor
            FROM gamificacao_estado_usuario
            WHERE pessoa_id = ?
        ");
        $stmt->execute([$pessoaId]);

        $estado = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $estado[(string) $row['chave']] = (string) $row['valor'];
        }

        return $estado;
    }
}

if (!function_exists('homeStateConsumirEventosEfemeros')) {
    function homeStateConsumirEventosEfemeros(PDO $pdo, int $pessoaId, array $estado): void
    {
        $eventosConsumir = [];

        $chavesEfemeras = [
            'xp_recente',
            'subiu_ranking',
            'nova_missao',
            'combo_engajamento',
            'streak_comentarios',
            'comentario_validado',
            'novo_post',
            'novo_post_id',
            'hero_estado',
            'toast_tipo',
        ];

        foreach ($chavesEfemeras as $chave) {
            if (array_key_exists($chave, $estado) && trim((string) $estado[$chave]) !== '') {
                $eventosConsumir[] = $chave;
            }
        }

        if (!$eventosConsumir) {
            return;
        }

        $in = implode(',', array_fill(0, count($eventosConsumir), '?'));

        $sql = "
            DELETE FROM gamificacao_estado_usuario
            WHERE pessoa_id = ?
              AND chave IN ($in)
        ";

        $stmt = $pdo->prepare($sql);
        $params = array_merge([$pessoaId], $eventosConsumir);
        $stmt->execute($params);
    }
}

if (!function_exists('homeStateNomeCurto')) {
    function homeStateNomeCurto(string $nome): string
    {
        $nome = trim($nome);
        if ($nome === '') {
            return 'Alguém';
        }

        $partes = preg_split('/\s+/', $nome);
        return trim((string) ($partes[0] ?? 'Alguém'));
    }
}

if (!function_exists('homeStateBuildFaixa')) {
    function homeStateBuildFaixa(PDO $pdo, int $pessoaId, int $posicaoRanking): array
    {
        $mensagens = [];

        $stmt = $pdo->prepare("
            SELECT nome, pontos
            FROM pessoas
            WHERE status = 'ativo'
              AND id <> ?
            ORDER BY pontos DESC, id ASC
            LIMIT 5
        ");
        $stmt->execute([$pessoaId]);
        $top = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($top) {
            $lider = $top[0];
            $nomeLider = homeStateNomeCurto((string) ($lider['nome'] ?? 'Alguém'));
            $mensagens[] = $nomeLider . ' está na frente no jogo. Acelere e jogue agora!';
        }

        if ($posicaoRanking > 1) {
            $mensagens[] = 'Você está na posição ' . number_format($posicaoRanking, 0, ',', '.') . '. Bora subir agora!';
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM missao_historico_usuario
            WHERE pessoa_id = ?
              AND status = 'concluida'
              AND DATE(criado_em) = CURDATE()
        ");
        $stmt->execute([$pessoaId]);
        $missoesHoje = (int) ($stmt->fetchColumn() ?: 0);

        if ($missoesHoje > 0) {
            $mensagens[] = 'Você já fez ' . $missoesHoje . ' missão' . ($missoesHoje > 1 ? 'ões' : '') . ' hoje. Continue!';
        }

        if (!$mensagens) {
            $mensagens[] = 'A comunidade está jogando agora. Entre na missão e acelere!';
            $mensagens[] = 'Seu próximo avanço depende de você. Jogue agora!';
        }

        return [
            'ativa' => true,
            'mensagens' => array_values(array_unique($mensagens)),
            'intervalo_ms' => 4000,
        ];
    }
}

try {
    $pessoa = homeStateGetPessoa($pdo, $pessoaId);
    $xpTotal = (int) ($pessoa['pontos'] ?? 0);
    $posicaoRanking = homeStateGetRankingPosicao($pdo, $pessoaId);
    $estado = homeStateGetEstado($pdo, $pessoaId);

    $missaoAtual = missaoGerarMissaoDoDia($pdo, $pessoaId, false);
    $missaoCard = homeStateBuildMissaoCard($missaoAtual);

    $xpRecente = isset($estado['xp_recente']) ? (int) $estado['xp_recente'] : 0;
    $heroEstado = (string) ($estado['hero_estado'] ?? '');
    $toastTipo = (string) ($estado['toast_tipo'] ?? '');
    $comentarioValidado = (string) ($estado['comentario_validado'] ?? '');
    $novoPost = (string) ($estado['novo_post'] ?? '');
    $novoPostId = isset($estado['novo_post_id']) ? (int) $estado['novo_post_id'] : 0;
    $missaoPrincipalPostId = isset($estado['missao_principal_post_id']) ? (int) $estado['missao_principal_post_id'] : 0;
    $missaoPrincipalXp = isset($estado['missao_principal_xp']) ? (int) $estado['missao_principal_xp'] : 0;
    $subiuRanking = (string) ($estado['subiu_ranking'] ?? '');
    $novaMissao = (string) ($estado['nova_missao'] ?? '');
    $comboEngajamento = (string) ($estado['combo_engajamento'] ?? '');
    $streakComentarios = (string) ($estado['streak_comentarios'] ?? '');

    $payload = [
        'ok' => true,
        'server_time' => date('Y-m-d H:i:s'),
        'pessoa_id' => $pessoaId,

        'xp_total' => $xpTotal,
        'ranking_posicao' => $posicaoRanking,

        // raiz: o JS atual usa assim
        'xp_recente' => $xpRecente,
        'hero_estado' => $heroEstado,
        'toast_tipo' => $toastTipo,
        'comentario_validado' => $comentarioValidado,
        'novo_post' => $novoPost,
        'novo_post_id' => $novoPostId,
        'missao_principal_post_id' => $missaoPrincipalPostId,
        'missao_principal_xp' => $missaoPrincipalXp,
        'subiu_ranking' => $subiuRanking,
        'nova_missao' => $novaMissao,
        'combo_engajamento' => $comboEngajamento,
        'streak_comentarios' => $streakComentarios,

        // espelhado também em estado
        'estado' => [
            'xp_recente' => $xpRecente,
            'hero_estado' => $heroEstado,
            'toast_tipo' => $toastTipo,
            'comentario_validado' => $comentarioValidado,
            'novo_post' => $novoPost,
            'novo_post_id' => $novoPostId,
            'missao_principal_post_id' => $missaoPrincipalPostId,
            'missao_principal_xp' => $missaoPrincipalXp,
            'subiu_ranking' => $subiuRanking,
            'nova_missao' => $novaMissao,
            'combo_engajamento' => $comboEngajamento,
            'streak_comentarios' => $streakComentarios,
        ],

        'missao' => $missaoCard,
        'faixa' => homeStateBuildFaixa($pdo, $pessoaId, $posicaoRanking),
    ];

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // consome depois de responder
    homeStateConsumirEventosEfemeros($pdo, $pessoaId, $estado);
    exit;

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'erro' => $e->getMessage(),
        'arquivo' => $e->getFile(),
        'linha' => $e->getLine(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}