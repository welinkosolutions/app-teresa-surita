<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';
require_once '/home/elab/public_html/core/invite/engine.php';
require_once '/home/elab/public_html/core/gamificacao/ranking_engine.php';
require_once '/home/elab/public_html/core/missao/bootstrap.php';
require_once '/home/elab/public_html/core/missao/planner.php';
require_once '/home/elab/public_html/core/missao/share.php';

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

$pdo = dbRoraima();
$pessoa_id = (int) $_SESSION['pessoa_id'];

if (!function_exists('missaoGerarMissaoDoDia')) {
    die('core/missao/bootstrap.php não carregou corretamente ou função missaoGerarMissaoDoDia() não existe');
}

function home_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (!function_exists('tenant_resolver_dominio_atual')) {
    function tenant_resolver_dominio_atual(): string
    {
        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
        return preg_replace('/:\d+$/', '', $host) ?: '';
    }
}

if (!function_exists('tenant_resolver_id_atual')) {
    function tenant_resolver_id_atual(PDO $pdo): int
    {
        static $tenantId = 0;

        if ($tenantId > 0) {
            return $tenantId;
        }

        $dominio = tenant_resolver_dominio_atual();

        if ($dominio === '') {
            throw new RuntimeException('Domínio atual não identificado para resolver tenant.');
        }

        $stmt = $pdo->prepare("
            SELECT id
            FROM clientes_elab
            WHERE dominio = ?
              AND status = 'ativo'
            LIMIT 1
        ");
        $stmt->execute([$dominio]);

        $tenantId = (int) ($stmt->fetchColumn() ?? 0);

        if ($tenantId <= 0) {
            throw new RuntimeException("Tenant não encontrado para o domínio: {$dominio}");
        }

        return $tenantId;
    }
}

if (!function_exists('tenant_config_get')) {
    function tenant_config_get(string $chave, mixed $default = null): mixed
    {
        global $pdo;

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('PDO global não disponível para tenant_config_get().');
        }

        static $cache = [];
        static $tenantIdCache = 0;

        if ($tenantIdCache <= 0) {
            $tenantIdCache = tenant_resolver_id_atual($pdo);
        }

        $cacheKey = $tenantIdCache . '|' . $chave;

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $stmt = $pdo->prepare("
            SELECT valor, tipo
            FROM clientes_elab_config
            WHERE tenant_cliente_id = ?
              AND chave = ?
            LIMIT 1
        ");
        $stmt->execute([$tenantIdCache, $chave]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $cache[$cacheKey] = $default;
            return $default;
        }

        $valor = $row['valor'];
        $tipo = strtolower(trim((string) ($row['tipo'] ?? 'string')));

        $resultado = match ($tipo) {
            'int' => (int) $valor,
            'float' => (float) $valor,
            'bool' => in_array(strtolower((string) $valor), ['1', 'true', 'sim', 'yes', 'on'], true),
            'json' => json_decode((string) $valor, true),
            default => (string) $valor,
        };

        $cache[$cacheKey] = $resultado;
        return $resultado;
    }
}

if (!function_exists('tenantCfgStrict')) {
    function tenantCfgStrict(string $key): string
    {
        $value = tenant_config_get($key, null);

        if ($value === null) {
            throw new RuntimeException("Config obrigatória do tenant ausente: {$key}");
        }

        $value = trim((string) $value);

        if ($value === '') {
            throw new RuntimeException("Config obrigatória do tenant vazia: {$key}");
        }

        return $value;
    }
}

try {
    $tenantTimezone = tenantCfgStrict('tenant.timezone');
    date_default_timezone_set($tenantTimezone);

    $tenantBrand = [
        'app_name' => tenantCfgStrict('branding.app_name'),
        'app_title' => tenantCfgStrict('branding.app_title'),
        'theme_color' => tenantCfgStrict('branding.theme_color'),
        'favicon_url' => tenantCfgStrict('branding.favicon_url'),
        'apple_touch_icon_url' => tenantCfgStrict('branding.apple_touch_icon_url'),
        'post_placeholder_url' => tenantCfgStrict('branding.post_placeholder_url'),

        'primary_color' => tenantCfgStrict('branding.primary_color'),
        'secondary_color' => tenantCfgStrict('branding.secondary_color'),

        'instagram_start' => tenantCfgStrict('branding.instagram_start'),
        'instagram_mid' => tenantCfgStrict('branding.instagram_mid'),
        'instagram_end' => tenantCfgStrict('branding.instagram_end'),

        'facebook_start' => tenantCfgStrict('branding.facebook_start'),
        'facebook_end' => tenantCfgStrict('branding.facebook_end'),

        'whatsapp_start' => tenantCfgStrict('branding.whatsapp_start'),
        'whatsapp_end' => tenantCfgStrict('branding.whatsapp_end'),

        'missao_card_title_comentario' => tenantCfgStrict('missao.card_title_comentario'),
        'missao_card_title_post' => tenantCfgStrict('missao.card_title_post'),
        'missao_card_title_compartilhar' => tenantCfgStrict('missao.card_title_compartilhar'),
        'missao_card_title_video' => tenantCfgStrict('missao.card_title_video'),

        'missao_cta_comentario' => tenantCfgStrict('missao.cta_comentario'),
        'missao_cta_post' => tenantCfgStrict('missao.cta_post'),
        'missao_cta_compartilhar' => tenantCfgStrict('missao.cta_compartilhar'),
        'missao_cta_video' => tenantCfgStrict('missao.cta_video'),

        'missao_urgency_comment' => tenantCfgStrict('missao.urgency_comment'),
        'missao_urgency_post' => tenantCfgStrict('missao.urgency_post'),
        'missao_urgency_share' => tenantCfgStrict('missao.urgency_share'),
        'missao_urgency_video' => tenantCfgStrict('missao.urgency_video'),

        'missao_narrative_comment' => tenantCfgStrict('missao.narrative_comment'),
        'missao_narrative_post' => tenantCfgStrict('missao.narrative_post'),
        'missao_narrative_share' => tenantCfgStrict('missao.narrative_share'),
        'missao_narrative_video' => tenantCfgStrict('missao.narrative_video'),

        'missao_default_description_prefix' => tenantCfgStrict('missao.default_description_prefix'),
        'missao_default_description_empty' => tenantCfgStrict('missao.default_description_empty'),

        'tenant_app_url' => tenantCfgStrict('tenant.app_url'),

        'feature_instagram_enabled' => tenant_config_get('feature.instagram_enabled', true),
        'feature_facebook_enabled' => tenant_config_get('feature.facebook_enabled', true),
        'feature_whatsapp_share_enabled' => tenant_config_get('feature.whatsapp_share_enabled', true),
    ];
} catch (Throwable $e) {
    http_response_code(500);
    die('Tenant inválido ou incompleto: ' . home_h($e->getMessage()));
}

$tenantIdAtual = tenant_resolver_id_atual($pdo);

$featureInstagramEnabled = (bool) $tenantBrand['feature_instagram_enabled'];
$featureFacebookEnabled = (bool) $tenantBrand['feature_facebook_enabled'];
$featureWhatsappShareEnabled = (bool) $tenantBrand['feature_whatsapp_share_enabled'];

/*
========================================
USUÁRIO
========================================
*/
$stmt = $pdo->prepare("
    SELECT
        id,
        nome,
        apelido,
        chamar_por,
        sexo,
        instagram,
        instagram_username,
        instagram_confirmado,
        instagram_user,
        facebook,
        facebook_username,
        facebook_confirmado,
        facebook_user_id,
        usa_instagram,
        usa_facebook,
        perfil,
        pontos,
        status_validacao
    FROM pessoas
    WHERE id = ?
      AND status = 'ativo'
    LIMIT 1
");
$stmt->execute([$pessoa_id]);
$pessoa = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

if (!$pessoa) {
    header('Location: /index.php');
    exit;
}

$nomeCompleto = trim((string) ($pessoa['nome'] ?? ''));
$nomeExibicao = '';

if (($pessoa['chamar_por'] ?? '') === 'apelido' && !empty($pessoa['apelido'])) {
    $nomeExibicao = trim((string) $pessoa['apelido']);
} else {
    $partes = preg_split('/\s+/', $nomeCompleto);
    $nomeExibicao = (string) ($partes[0] ?? '');
}

if ($nomeExibicao === '') {
    $nomeExibicao = 'Usuário';
}

function home_avatar_usuario(array $pessoa): string
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

$avatarUsuario = home_avatar_usuario($pessoa);

$usaInstagram = (($pessoa['usa_instagram'] ?? 'sim') !== 'nao');
$usaFacebook = (($pessoa['usa_facebook'] ?? 'sim') !== 'nao');

$temInstagram = !empty($pessoa['instagram_username']);
$temFacebook = !empty($pessoa['facebook_username']);

$temRedeElegivelMissao =
    ($featureInstagramEnabled && $usaInstagram && $temInstagram) ||
    ($featureFacebookEnabled && $usaFacebook && $temFacebook);

/*
========================================
NÍVEL SIMPLES DA HOME
========================================
*/
$xpTotal = (int) ($pessoa['pontos'] ?? 0);

$nivelAtual = 1;
$nivelNome = 'Iniciante';

try {
    $stmt = $pdo->prepare("
        SELECT nivel_atual, xp_total
        FROM vw_game_usuario_estado_resumo
        WHERE tenant_cliente_id = ?
          AND pessoa_id = ?
        ORDER BY atualizado_em DESC
        LIMIT 1
    ");
    $stmt->execute([$tenantIdAtual, $pessoa_id]);
    $estadoGame = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($estadoGame) {
        $nivelAtual = max(1, (int) ($estadoGame['nivel_atual'] ?? 1));
        $xpTotal = max($xpTotal, (int) ($estadoGame['xp_total'] ?? 0));
    }
} catch (Throwable $e) {
    error_log('[home-v2] Falha ao buscar estado game: ' . $e->getMessage());
}

try {
    $stmt = $pdo->prepare("
        SELECT nome
        FROM game_niveis
        WHERE nivel = ?
          AND ativo = 'sim'
        LIMIT 1
    ");
    $stmt->execute([$nivelAtual]);
    $nivelNomeBanco = trim((string) ($stmt->fetchColumn() ?: ''));

    if ($nivelNomeBanco !== '') {
        $nivelNome = $nivelNomeBanco;
    }
} catch (Throwable $e) {
    error_log('[home-v2] Falha ao buscar nome do nível: ' . $e->getMessage());
}

/*
========================================
MISSÃO ATUAL - MESMO MIOLO DA DASHBOARD
========================================
*/
$missaoAtual = null;

if ($temRedeElegivelMissao) {
    $missaoAtual = missaoGerarMissaoDoDia($pdo, $pessoa_id, false);

    if (!empty($missaoAtual) && is_array($missaoAtual)) {
        $missaoNetworkBruta = strtolower(trim((string) ($missaoAtual['network'] ?? '')));

        $missaoPermitida =
            ($missaoNetworkBruta === 'instagram' && $featureInstagramEnabled && $usaInstagram && $temInstagram) ||
            ($missaoNetworkBruta === 'facebook' && $featureFacebookEnabled && $usaFacebook && $temFacebook);

        $missaoExpirou = false;
        $missaoExpiraRaw = trim((string) ($missaoAtual['expira_em'] ?? ''));

        if ($missaoExpiraRaw !== '') {
            $tsExpira = strtotime($missaoExpiraRaw);
            if ($tsExpira && $tsExpira < time()) {
                $missaoExpirou = true;
            }
        }

        if (!$missaoPermitida || $missaoExpirou) {
            $missaoAtual = null;
        }
    }
}

$missaoCard = null;
$missaoExpiraEm = date('Y-m-d 23:59:59');
$missaoNarrativa = '';
$missaoUrgenciaLabel = '⏳ Missão ativa hoje';

if (!empty($missaoAtual) && is_array($missaoAtual)) {
    $payloadMissao = is_array($missaoAtual['payload'] ?? null)
        ? $missaoAtual['payload']
        : [];

    $missaoTipoAcao = trim((string) ($payloadMissao['tipo_acao'] ?? $missaoAtual['missao_tipo'] ?? 'abrir'));
    $missaoTituloCard = trim((string) ($payloadMissao['titulo'] ?? 'Missão do dia'));
    $missaoDescricaoCard = trim((string) ($payloadMissao['descricao'] ?? ''));
    $missaoCtaLabel = trim((string) ($payloadMissao['cta_label'] ?? 'Abrir agora'));
    $missaoImagem = trim((string) ($payloadMissao['imagem_url'] ?? ''));
    $missaoUrlDestino = trim((string) ($payloadMissao['url_destino'] ?? ''));
    $missaoCaption = trim((string) ($payloadMissao['caption'] ?? ''));
    $missaoPontos = (int) ($payloadMissao['pontos'] ?? 0);
    $missaoPostIdNovo = (int) ($missaoAtual['post_id'] ?? 0);

    $missaoNetwork = strtolower(trim((string) ($missaoAtual['network'] ?? $payloadMissao['post']['network'] ?? 'instagram')));

    if (!in_array($missaoNetwork, ['instagram', 'facebook'], true)) {
        $missaoNetwork = 'instagram';
    }

    if ($missaoImagem === '') {
        $missaoImagem = $tenantBrand['post_placeholder_url'];
    }

    $expiraPayload = trim((string) ($payloadMissao['expira_em'] ?? $payloadMissao['expires_at'] ?? ''));
    if ($expiraPayload !== '') {
        $tsExpira = strtotime($expiraPayload);
        if ($tsExpira) {
            $missaoExpiraEm = date('Y-m-d H:i:s', $tsExpira);
        }
    }

    if ($missaoTipoAcao === 'comentario') {
        $missaoTituloCard = $tenantBrand['missao_card_title_comentario'];
        $missaoCtaLabel = $tenantBrand['missao_cta_comentario'];
        $missaoUrgenciaLabel = $tenantBrand['missao_urgency_comment'];
        $missaoNarrativa = $tenantBrand['missao_narrative_comment'];
    } elseif ($missaoTipoAcao === 'post') {
        $missaoTituloCard = $tenantBrand['missao_card_title_post'];
        $missaoCtaLabel = $tenantBrand['missao_cta_post'];
        $missaoUrgenciaLabel = $tenantBrand['missao_urgency_post'];
        $missaoNarrativa = $tenantBrand['missao_narrative_post'];
    } elseif ($missaoTipoAcao === 'compartilhar') {
        $missaoTituloCard = $tenantBrand['missao_card_title_compartilhar'];
        $missaoCtaLabel = $tenantBrand['missao_cta_compartilhar'];
        $missaoUrgenciaLabel = $tenantBrand['missao_urgency_share'];
        $missaoNarrativa = $tenantBrand['missao_narrative_share'];
    } elseif ($missaoTipoAcao === 'video') {
        $missaoTituloCard = $tenantBrand['missao_card_title_video'];
        $missaoCtaLabel = $tenantBrand['missao_cta_video'];
        $missaoUrgenciaLabel = $tenantBrand['missao_urgency_video'];
        $missaoNarrativa = $tenantBrand['missao_narrative_video'];
    }

    if ($missaoDescricaoCard === '') {
        $missaoDescricaoCard = trim(
            $tenantBrand['missao_default_description_prefix'] . ' ' .
            $tenantBrand['missao_default_description_empty']
        );
    } else {
        $missaoDescricaoCard = trim(
            $tenantBrand['missao_default_description_prefix'] . ' ' . $missaoDescricaoCard
        );
    }

    $missaoBotaoClasse = 'btn-missao-cta';
    $missaoIcone = 'bi-lightning-charge-fill';

    if ($missaoTipoAcao === 'compartilhar') {
        $missaoBotaoClasse .= ' is-whatsapp';
        $missaoIcone = 'bi-whatsapp';
    } elseif (in_array($missaoTipoAcao, ['post', 'comentario'], true)) {
        if ($missaoNetwork === 'facebook') {
            $missaoBotaoClasse .= ' is-facebook';
            $missaoIcone = 'bi-facebook';
        } else {
            $missaoBotaoClasse .= ' is-instagram';
            $missaoIcone = 'bi-instagram';
        }
    } elseif ($missaoTipoAcao === 'video') {
        $missaoBotaoClasse .= ' is-video';
        $missaoIcone = 'bi-play-circle-fill';
    } else {
        $missaoBotaoClasse .= ' is-default';
        $missaoIcone = 'bi-cursor-fill';
    }

    $missaoCard = [
        'codigo' => (string) ($missaoAtual['missao_codigo'] ?? ''),
        'tipo_acao' => $missaoTipoAcao,
        'network' => $missaoNetwork,
        'titulo' => $missaoTituloCard,
        'descricao' => $missaoDescricaoCard,
        'caption' => $missaoCaption,
        'cta_label' => $missaoCtaLabel,
        'imagem' => $missaoImagem,
        'url_destino' => $missaoUrlDestino,
        'pontos' => $missaoPontos,
        'post_id' => $missaoPostIdNovo,
        'btn_class' => $missaoBotaoClasse,
        'icon' => $missaoIcone,
        'expira_em' => $missaoExpiraEm,
        'urgencia_label' => $missaoUrgenciaLabel,
        'narrativa' => $missaoNarrativa,
    ];
}

$temMissaoReal = !empty($missaoCard);

if (!$temMissaoReal) {
    $missaoCard = [
        'codigo' => '',
        'tipo_acao' => 'abrir',
        'network' => 'instagram',
        'titulo' => 'MISSÃO DE HOJE',
        'descricao' => $temRedeElegivelMissao
            ? 'Nenhuma missão nova disponível agora. Volte em instantes para continuar pontuando.'
            : 'Cadastre seu Instagram ou Facebook para liberar missões com pontos.',
        'caption' => 'A próxima missão aparece aqui assim que estiver disponível.',
        'cta_label' => $temRedeElegivelMissao ? 'VER MISSÕES' : 'CADASTRAR REDE',
        'imagem' => $tenantBrand['post_placeholder_url'],
        'url_destino' => $temRedeElegivelMissao ? '/comunidade/missao.php' : '/dashboard/index.php',
        'pontos' => 0,
        'post_id' => 0,
        'btn_class' => 'btn-missao-cta is-default',
        'icon' => 'bi-cursor-fill',
        'expira_em' => $missaoExpiraEm,
        'urgencia_label' => $temRedeElegivelMissao ? '⏳ Aguardando nova missão' : '🔒 Rede social pendente',
        'narrativa' => '',
    ];
}

$overlayTitulo = trim((string) ($missaoCard['cta_label'] ?? 'CLIQUE AQUI'));
if ($overlayTitulo === '') {
    $overlayTitulo = 'CLIQUE AQUI';
}

$overlaySubtitulo = $temMissaoReal
    ? trim((string) ($missaoCard['urgencia_label'] ?? 'Missão ativa hoje'))
    : trim((string) ($missaoCard['urgencia_label'] ?? 'Aguardando missão'));

if ($overlaySubtitulo === '') {
    $overlaySubtitulo = 'Missão ativa hoje';
}

$missaoImagemRaw = trim((string) ($missaoCard['imagem'] ?? ''));
$missaoImagemFinal = $missaoImagemRaw !== ''
    ? $missaoImagemRaw
    : (string) ($tenantBrand['post_placeholder_url'] ?? '');

if ($missaoImagemFinal === '') {
    $missaoImagemFinal = '/assets/animations/missao-default.webp';
}

$missaoImagemProxy = $missaoImagemFinal;

if (
    $missaoImagemFinal !== '' &&
    str_starts_with($missaoImagemFinal, 'https://') &&
    (
        str_contains($missaoImagemFinal, 'cdninstagram.com') ||
        str_contains($missaoImagemFinal, 'fbcdn.net') ||
        str_contains($missaoImagemFinal, 'scontent')
    )
) {
    $missaoImagemProxy = '/inicial/img-missao-proxy.php?u=' . rtrim(strtr(base64_encode($missaoImagemFinal), '+/', '-_'), '=');
}

$homeV19XpAtual = (int) (
    $xpAtual
    ?? $usuarioXp
    ?? $gamificacaoXp
    ?? $perfilUsuario['xp_atual']
    ?? $perfilUsuario['xp']
    ?? $pessoa['xp']
    ?? 9
);

$homeV19XpProximo = (int) (
    $xpProximoNivel
    ?? $proximoNivelXp
    ?? $gamificacaoXpProximo
    ?? $perfilUsuario['xp_proximo_nivel']
    ?? 10
);

if ($homeV19XpProximo <= 0) {
    $homeV19XpProximo = 10;
}

if ($homeV19XpAtual < 0) {
    $homeV19XpAtual = 0;
}

$homeV19XpFalta = max(0, $homeV19XpProximo - $homeV19XpAtual);
$homeV19XpPercent = max(3, min(100, (int) round(($homeV19XpAtual / max(1, $homeV19XpProximo)) * 100)));

$homeV19RankingPosicao = (int) (
    $rankingPosicao
    ?? $minhaPosicaoRanking
    ?? $posicaoRanking
    ?? $perfilUsuario['ranking_posicao']
    ?? 29
);

if ($homeV19RankingPosicao <= 0) {
    $homeV19RankingPosicao = 29;
}

$homeV19NivelProximoNome = (string) (
    $proximoNivelNome
    ?? $nivelProximoNome
    ?? $perfilUsuario['proximo_nivel_nome']
    ?? 'Participante'
);

$homeV19ProgressLabel = $homeV19XpFalta > 0
    ? 'Falta ' . $homeV19XpFalta . ' XP para virar ' . $homeV19NivelProximoNome
    : 'Você já pode subir de nível';

$homeV20Pontos = (int) ($missaoCard['pontos'] ?? ($payloadMissao['pontos'] ?? 0));
if ($homeV20Pontos <= 0) {
    $homeV20Pontos = 25;
}

$homeV20TipoAcao = strtolower(trim((string) ($missaoCard['tipo_acao'] ?? $missaoCard['acao'] ?? 'comentar')));

$homeV20Titulo = 'Desafio de hoje';
$homeV20Subtitulo = 'Ajude esse post a ganhar força';
$homeV20OverlayTitulo = 'COMENTE';
$homeV20OverlaySubtitulo = 'E GANHE +' . $homeV20Pontos;
$homeV20Cta = 'Comentar e ganhar pontos';
$homeV20Nota = 'Uma ação rápida para movimentar a comunidade e subir no jogo.';

if ($homeV20TipoAcao === 'compartilhar') {
    $homeV20Subtitulo = 'Compartilhe e espalhe essa mensagem';
    $homeV20OverlayTitulo = 'COMPARTILHE';
    $homeV20OverlaySubtitulo = 'E GANHE +' . $homeV20Pontos;
    $homeV20Cta = 'Compartilhar e ganhar pontos';
    $homeV20Nota = 'Espalhe a mensagem, fortaleça a rede e avance no ranking.';
} elseif ($homeV20TipoAcao === 'curtir') {
    $homeV20Subtitulo = 'Dê força para esse conteúdo crescer';
    $homeV20OverlayTitulo = 'CURTA';
    $homeV20OverlaySubtitulo = 'E GANHE +' . $homeV20Pontos;
    $homeV20Cta = 'Curtir e ganhar pontos';
    $homeV20Nota = 'Toque, participe e ajude esse conteúdo a chegar mais longe.';
}

$homeV21RedeLabel = strtolower((string) ($missaoCard['network'] ?? 'instagram')) === 'facebook'
    ? 'Facebook'
    : 'Instagram';

$homeV21TipoAcao = strtolower(trim((string) ($missaoCard['tipo_acao'] ?? $missaoCard['acao'] ?? 'comentar')));

$homeV21Verbo = 'comente';
if ($homeV21TipoAcao === 'compartilhar') {
    $homeV21Verbo = 'compartilhe';
} elseif ($homeV21TipoAcao === 'curtir') {
    $homeV21Verbo = 'curta';
}

$homeV21Pontos = (int) ($homeV20Pontos ?? $missaoCard['pontos'] ?? $payloadMissao['pontos'] ?? 25);
if ($homeV21Pontos <= 0) {
    $homeV21Pontos = 25;
}

$homeV21Instrucao = 'Clique aqui e ' . $homeV21Verbo . ' no ' . $homeV21RedeLabel . ', ajude a engajar e ganhe +' . $homeV21Pontos . ' pontos.';
$homeV21OverlayTitulo = 'CLIQUE AQUI';
$homeV21OverlaySubtitulo = ucfirst($homeV21Verbo) . ' no ' . $homeV21RedeLabel . ' e ganhe +' . $homeV21Pontos;

$homeV21NivelNome = (string) (
    $nivelAtualNome
    ?? $nivelNome
    ?? $perfilUsuario['nivel_nome']
    ?? $pessoa['nivel_nome']
    ?? 'Iniciante'
);

$homeV21XpAtual = (int) ($homeV19XpAtual ?? 9);
$homeV21XpProximo = (int) ($homeV19XpProximo ?? 10);
$homeV21XpFalta = max(0, $homeV21XpProximo - $homeV21XpAtual);
$homeV21XpPercent = max(3, min(100, (int) ($homeV19XpPercent ?? round(($homeV21XpAtual / max(1, $homeV21XpProximo)) * 100))));

$homeV22RedeLabel = strtolower((string) ($missaoCard['network'] ?? 'instagram')) === 'facebook'
    ? 'Facebook'
    : 'Instagram';

$homeV22TipoAcao = strtolower(trim((string) ($missaoCard['tipo_acao'] ?? $missaoCard['acao'] ?? 'comentar')));

$homeV22Verbo = 'Comente';
if ($homeV22TipoAcao === 'compartilhar') {
    $homeV22Verbo = 'Compartilhe';
} elseif ($homeV22TipoAcao === 'curtir') {
    $homeV22Verbo = 'Curta';
}

$homeV22Pontos = (int) ($homeV21Pontos ?? $homeV20Pontos ?? $missaoCard['pontos'] ?? $payloadMissao['pontos'] ?? 25);
if ($homeV22Pontos <= 0) {
    $homeV22Pontos = 25;
}

$homeV22TituloMissao = 'Apoiar no ' . $homeV22RedeLabel;
$homeV22OverlayTitulo = 'CLIQUE AQUI';
$homeV22OverlaySubtitulo = $homeV22Verbo . ' e ganhe +' . $homeV22Pontos;

$homeV22Caption = trim((string) ($missaoCard['caption'] ?? $payloadMissao['caption'] ?? $missaoCard['descricao'] ?? ''));
if ($homeV22Caption === '') {
    $homeV22Caption = 'Abra a missão, participe e ajude esse conteúdo a ganhar força.';
}

$homeV23NetworkRaw = strtolower((string) ($missaoCard['network'] ?? $payloadMissao['network'] ?? 'instagram'));

$homeV23RedeLabel = $homeV23NetworkRaw === 'facebook'
    ? 'Facebook'
    : 'Instagram';

$homeV23RedeIcon = $homeV23NetworkRaw === 'facebook'
    ? 'f'
    : '◎';

$homeV23RedeClass = $homeV23NetworkRaw === 'facebook'
    ? 'is-facebook'
    : 'is-instagram';

$homeV23TituloMissao = 'Apoiar no ' . $homeV23RedeLabel;

$homeV23TipoAcao = strtolower(trim((string) ($missaoCard['tipo_acao'] ?? $missaoCard['acao'] ?? 'comentar')));

$homeV23Verbo = 'Comente';
if ($homeV23TipoAcao === 'compartilhar') {
    $homeV23Verbo = 'Compartilhe';
} elseif ($homeV23TipoAcao === 'curtir') {
    $homeV23Verbo = 'Curta';
}

$homeV23Pontos = (int) ($homeV22Pontos ?? $homeV21Pontos ?? $homeV20Pontos ?? $missaoCard['pontos'] ?? $payloadMissao['pontos'] ?? 25);
if ($homeV23Pontos <= 0) {
    $homeV23Pontos = 25;
}

$homeV23OverlayTitulo = 'CLIQUE';
$homeV23OverlaySubtitulo = $homeV23Verbo . ' e ganhe +' . $homeV23Pontos;

$homeV23NivelNome = (string) (
    $homeV21NivelNome
    ?? $nivelAtualNome
    ?? $nivelNome
    ?? $perfilUsuario['nivel_nome']
    ?? $pessoa['nivel_nome']
    ?? 'Iniciante'
);

$homeV23XpAtual = (int) ($homeV21XpAtual ?? $homeV19XpAtual ?? 9);
$homeV23XpProximo = (int) ($homeV21XpProximo ?? $homeV19XpProximo ?? 10);
if ($homeV23XpProximo <= 0) {
    $homeV23XpProximo = 10;
}

$homeV23XpFalta = max(0, $homeV23XpProximo - $homeV23XpAtual);
$homeV23XpPercent = max(3, min(100, (int) ($homeV21XpPercent ?? $homeV19XpPercent ?? round(($homeV23XpAtual / max(1, $homeV23XpProximo)) * 100))));

$homeV24NetworkRaw = strtolower((string) ($missaoCard['network'] ?? $payloadMissao['network'] ?? 'instagram'));

$homeV24RedeLabel = $homeV24NetworkRaw === 'facebook' ? 'Facebook' : 'Instagram';
$homeV24RedeIcon = $homeV24NetworkRaw === 'facebook'
    ? '/assets/feed/statics/002-facebook.svg'
    : '/assets/feed/statics/001-instagram.svg';

$homeV24Action = strtolower(trim((string) ($missaoCard['tipo_acao'] ?? $missaoCard['acao'] ?? 'comentar')));

$homeV24ActionLabel = 'Comente';
$homeV24ActionIcon = '/assets/feed/animateds/curte-porfavorzinho.webp';

if ($homeV24Action === 'compartilhar') {
    $homeV24ActionLabel = 'Compartilhe';
    $homeV24ActionIcon = '/assets/feed/animateds/novo-post-facebook.webp';
} elseif ($homeV24Action === 'curtir') {
    $homeV24ActionLabel = 'Curta';
    $homeV24ActionIcon = '/assets/feed/animateds/coracao-feliz-dancando.webp';
}

$homeV24Pontos = (int) (
    $homeV23Pontos
    ?? $homeV22Pontos
    ?? $homeV21Pontos
    ?? $homeV20Pontos
    ?? $missaoCard['pontos']
    ?? $payloadMissao['pontos']
    ?? 25
);
if ($homeV24Pontos <= 0) {
    $homeV24Pontos = 25;
}

$homeV24Titulo = 'Apoiar no ' . $homeV24RedeLabel;
$homeV24Instrucao = $homeV24ActionLabel . ' no ' . $homeV24RedeLabel . ' e ganhe +' . $homeV24Pontos . ' pontos';

$homeV24Caption = trim((string) ($missaoCard['caption'] ?? $payloadMissao['caption'] ?? $missaoCard['descricao'] ?? ''));
if ($homeV24Caption === '') {
    $homeV24Caption = 'Abra a missão, participe e ajude esse conteúdo a ganhar força.';
}

$homeV24NivelNome = (string) (
    $homeV23NivelNome
    ?? $homeV21NivelNome
    ?? $nivelAtualNome
    ?? $nivelNome
    ?? $perfilUsuario['nivel_nome']
    ?? $pessoa['nivel_nome']
    ?? 'Iniciante'
);

$homeV24XpAtual = (int) ($homeV23XpAtual ?? $homeV21XpAtual ?? $homeV19XpAtual ?? 9);
$homeV24XpProximo = (int) ($homeV23XpProximo ?? $homeV21XpProximo ?? $homeV19XpProximo ?? 10);
if ($homeV24XpProximo <= 0) {
    $homeV24XpProximo = 10;
}
$homeV24XpPercent = max(3, min(100, (int) ($homeV23XpPercent ?? $homeV21XpPercent ?? $homeV19XpPercent ?? round(($homeV24XpAtual / max(1, $homeV24XpProximo)) * 100))));

$homeGameNetworkRaw = strtolower((string) ($missaoCard['network'] ?? $payloadMissao['network'] ?? 'instagram'));

$homeGameRedeLabel = $homeGameNetworkRaw === 'facebook' ? 'Facebook' : 'Instagram';
$homeGameRedeIcon = $homeGameNetworkRaw === 'facebook'
    ? '/assets/feed/statics/002-facebook.svg'
    : '/assets/feed/statics/001-instagram.svg';

$homeGameAction = strtolower(trim((string) ($missaoCard['tipo_acao'] ?? $missaoCard['acao'] ?? 'comentar')));

$homeGameActionLabel = 'Comente';
$homeGameActionVerb = 'comentar';
$homeGameActionAsset = '/assets/feed/animateds/curte-porfavorzinho.webp';

if ($homeGameAction === 'compartilhar') {
    $homeGameActionLabel = 'Compartilhe';
    $homeGameActionVerb = 'compartilhar';
    $homeGameActionAsset = '/assets/feed/animateds/novo-post-facebook.webp';
} elseif ($homeGameAction === 'curtir') {
    $homeGameActionLabel = 'Curta';
    $homeGameActionVerb = 'curtir';
    $homeGameActionAsset = '/assets/feed/animateds/coracao-feliz-dancando.webp';
}

$homeGamePontos = (int) (
    $missaoCard['pontos']
    ?? $payloadMissao['pontos']
    ?? 25
);

if ($homeGamePontos <= 0) {
    $homeGamePontos = 25;
}

$homeGameCaption = trim((string) ($missaoCard['caption'] ?? $payloadMissao['caption'] ?? $missaoCard['descricao'] ?? ''));
if ($homeGameCaption === '') {
    $homeGameCaption = 'Participe agora e ajude esse conteúdo a ganhar força.';
}

$homeGameNivelNome = (string) (
    $nivelAtualNome
    ?? $nivelNome
    ?? $perfilUsuario['nivel_nome']
    ?? $pessoa['nivel_nome']
    ?? 'Iniciante'
);

$homeGameXpAtual = (int) (
    $xpAtual
    ?? $usuarioXp
    ?? $gamificacaoXp
    ?? $perfilUsuario['xp_atual']
    ?? $perfilUsuario['xp']
    ?? $pessoa['xp']
    ?? 9
);

$homeGameXpProximo = (int) (
    $xpProximoNivel
    ?? $proximoNivelXp
    ?? $gamificacaoXpProximo
    ?? $perfilUsuario['xp_proximo_nivel']
    ?? 10
);

if ($homeGameXpProximo <= 0) {
    $homeGameXpProximo = 10;
}

$homeGameXpPercent = max(3, min(100, (int) round(($homeGameXpAtual / max(1, $homeGameXpProximo)) * 100)));

$homeCssUrl = '/assets/css/home.css?v=76';
$homeJsUrl = '/assets/js/home.js?v=68';

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Inicial | <?= home_h($tenantBrand['app_name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="<?= home_h($tenantBrand['theme_color']) ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">

    <link rel="icon" href="<?= home_h($tenantBrand['favicon_url']) ?>">
    <link rel="apple-touch-icon" href="<?= home_h($tenantBrand['apple_touch_icon_url']) ?>">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/footer-v2.css?v=5">

    <style>
        :root {
            --home-bg: #f8fafc;
            --home-ink: #111827;
            --home-muted: #7b8498;
            --home-green: #16a34a;
            --home-green-2: #22c55e;
            --home-line: #e7edf5;
            --home-card: #ffffff;
            --home-red: #ef4444;
            --home-shadow: 0 26px 70px rgba(15, 23, 42, .13);
            --home-soft-shadow: 0 16px 42px rgba(15, 23, 42, .10);

            --instagram-start: <?= home_h($tenantBrand['instagram_start']) ?>;
            --instagram-mid: <?= home_h($tenantBrand['instagram_mid']) ?>;
            --instagram-end: <?= home_h($tenantBrand['instagram_end']) ?>;
            --facebook-start: <?= home_h($tenantBrand['facebook_start']) ?>;
            --facebook-end: <?= home_h($tenantBrand['facebook_end']) ?>;
            --whatsapp-start: <?= home_h($tenantBrand['whatsapp_start']) ?>;
            --whatsapp-end: <?= home_h($tenantBrand['whatsapp_end']) ?>;
        }

        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html,
        body {
            min-height: 100%;
            margin: 0;
            background:
                radial-gradient(circle at top left, rgba(34, 197, 94, .10), transparent 32%),
                linear-gradient(180deg, #fbfdff 0%, #f8fafc 100%);
            color: var(--home-ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
        }

        body {
            padding-bottom: calc(145px + env(safe-area-inset-bottom));
            overflow-x: hidden;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .home-v2-page {
            width: min(100%, 430px);
            min-height: 100svh;
            margin: 0 auto;
            padding: 18px 18px 150px;
        }

        .home-v2-header {
            display: flex;
            align-items: center;
            gap: 13px;
            margin: 2px 0 18px;
            padding: 4px 2px 0;
        }

        .home-v2-avatar-frame {
            width: 68px;
            height: 68px;
            padding: 3px;
            flex: 0 0 auto;
            border-radius: 999px;
            background: linear-gradient(135deg, #16a34a, #5ee28d);
            box-shadow: 0 10px 26px rgba(22, 163, 74, .18);
        }

        .home-v2-avatar {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
            border-radius: 999px;
            border: 4px solid #fff;
            background: #fff;
        }

        .home-v2-greeting {
            min-width: 0;
            flex: 1;
        }

        .home-v2-greeting h1 {
            margin: 0;
            max-width: 100%;
            font-size: clamp(28px, 7.5vw, 38px);
            line-height: .96;
            letter-spacing: -.065em;
            font-weight: 1000;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .home-v2-wave {
            display: inline-block;
            margin-left: 4px;
            font-size: .58em;
            letter-spacing: 0;
            transform: translateY(-3px);
            transform-origin: center;
        }

        .home-v2-level {
            width: max-content;
            max-width: 100%;
            margin-top: 7px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(22, 163, 74, .10);
            color: #7f8a9f;
            font-size: 13px;
            line-height: 1;
            font-weight: 1000;
            letter-spacing: -.025em;
            box-shadow: inset 0 0 0 1px rgba(22, 163, 74, .10);
        }

        .home-v2-level strong {
            color: var(--home-green);
        }

        .home-v2-mission-card {
            width: 100%;
            background: var(--home-card);
            border: 1px solid rgba(231, 237, 245, .95);
            border-radius: 30px;
            padding: 18px 14px 16px;
            box-shadow: var(--home-shadow);
        }

        .home-v2-mission-title {
            display: flex;
            align-items: center;
            gap: 9px;
            margin: 0 0 16px;
            color: var(--home-green);
            font-size: clamp(21px, 5.7vw, 29px);
            line-height: .96;
            font-weight: 1000;
            letter-spacing: -.055em;
            text-transform: uppercase;
        }

        .home-v2-mission-title-text {
            display: block;
            min-width: 0;
            max-width: 100%;
            text-wrap: balance;
        }

        .home-v2-target {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 999px;
            background: #e9fbef;
            box-shadow: inset 0 0 0 1px rgba(34, 197, 94, .16);
            font-size: 27px;
        }

        .home-v2-post {
            position: relative;
            display: block;
            min-height: clamp(390px, 56svh, 455px);
            overflow: hidden;
            border-radius: 28px;
            background: #111827;
            box-shadow: 0 20px 44px rgba(17, 24, 39, .23);
            isolation: isolate;
        }

        .home-v2-post-real-bg {
            position: absolute;
            inset: 0;
            z-index: 0;
            background:
                radial-gradient(circle at 50% 40%, rgba(255,255,255,.22), transparent 30%),
                linear-gradient(135deg, #74d9ff 0%, #bff6dc 52%, #fff0a6 100%);
        }

        .home-v2-post-real-bg img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
            object-position: center;
            transform: scale(1.02);
        }

        .home-v2-post-shade {
            position: absolute;
            inset: 0;
            z-index: 2;
            background:
                linear-gradient(180deg, rgba(17,24,39,.20) 0%, rgba(17,24,39,.52) 34%, rgba(17,24,39,.68) 100%),
                radial-gradient(circle at center, rgba(0,0,0,.12), rgba(0,0,0,.42));
            pointer-events: none;
        }

        .home-v2-post.is-fallback .home-v2-post-real-bg::before {
            content: "⚡";
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            color: rgba(255,255,255,.95);
            font-size: 128px;
            filter: drop-shadow(0 16px 28px rgba(0,0,0,.22));
        }

        .home-v2-post-bg {
            position: absolute;
            inset: 0;
            z-index: 0;
            background:
                linear-gradient(180deg, #eff4f8 0 17%, transparent 17%),
                linear-gradient(180deg, transparent 0 70%, #eff4f8 70% 100%),
                linear-gradient(135deg, #83ddff 0%, #b5f2dd 45%, #fff0a6 100%);
            opacity: .92;
        }

        

        .home-v2-post-head {
            position: absolute;
            z-index: 5;
            top: 16px;
            left: 18px;
            right: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #fff;
            opacity: .96;
        }

        .home-v2-account {
            display: flex;
            align-items: center;
            gap: 9px;
            min-width: 0;
            font-size: 19px;
            font-weight: 1000;
            letter-spacing: -.05em;
        }

        .home-v2-network-icon {
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 12px;
            color: #fff;
            font-size: 21px;
            background: radial-gradient(circle at 30% 110%, var(--instagram-start) 0 18%, var(--instagram-mid) 44%, var(--instagram-end) 100%);
        }

        .home-v2-network-icon.is-facebook {
            background: linear-gradient(135deg, var(--facebook-start), var(--facebook-end));
        }

        .home-v2-network-icon.is-whatsapp {
            background: linear-gradient(135deg, var(--whatsapp-start), var(--whatsapp-end));
        }

        .home-v2-account-name {
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            color: #fff;
            text-shadow: 0 3px 12px rgba(0,0,0,.35);
        }

        .home-v2-check {
            color: #38bdf8;
            font-size: 14px;
            text-shadow: 0 3px 10px rgba(0,0,0,.35);
        }

        .home-v2-dots {
            font-size: 30px;
            font-weight: 1000;
            transform: rotate(90deg);
            letter-spacing: -.18em;
            margin-right: 5px;
        }

        .home-v2-post-image { display: none; }

        .home-v2-post-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .home-v2-post-image.is-fallback {
            background:
                radial-gradient(circle at 50% 42%, rgba(255,255,255,.42) 0 22%, transparent 23%),
                radial-gradient(circle at 20% 85%, rgba(34,197,94,.28), transparent 28%),
                radial-gradient(circle at 80% 18%, rgba(59,130,246,.28), transparent 30%),
                linear-gradient(135deg, #78d9ff 0%, #c7f9dd 52%, #fff0a6 100%);
        }

        .home-v2-post-image.is-fallback::before {
            content: "⚡";
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            font-size: 122px;
            filter: drop-shadow(0 14px 28px rgba(0,0,0,.18));
        }

        .home-v2-post-image.is-fallback::after {
            content: "Missão ativa";
            position: absolute;
            left: 18px;
            right: 18px;
            bottom: 18px;
            padding: 10px 12px;
            border-radius: 16px;
            background: rgba(255,255,255,.84);
            color: #111827;
            text-align: center;
            font-weight: 1000;
            font-size: 18px;
            letter-spacing: -.04em;
        }

        .home-v2-post-foot {
            position: absolute;
            z-index: 5;
            left: 22px;
            right: 22px;
            bottom: 18px;
            color: #fff;
            opacity: .76;
            text-shadow: 0 3px 12px rgba(0,0,0,.38);
        }

        .home-v2-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 30px;
            line-height: 1;
            margin-bottom: 10px;
        }

        .home-v2-actions-left {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .home-v2-caption {
            color: #fff;
            font-size: 17px;
            line-height: 1.12;
            letter-spacing: -.04em;
            font-weight: 850;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .home-v2-caption strong {
            font-weight: 1000;
        }

        .home-v2-caption span {
            display: block;
            margin-top: 6px;
            color: #fbbf24;
            font-weight: 1000;
        }

        .home-v2-overlay {
            position: absolute;
            inset: 0;
            z-index: 6;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 22px;
            text-align: center;
            color: #fff;
        }

        .home-v2-overlay h2 {
            margin: 0;
            font-size: clamp(34px, 9.6vw, 56px);
            line-height: .90;
            letter-spacing: -.07em;
            font-weight: 1000;
            text-transform: uppercase;
            text-shadow: 0 10px 24px rgba(0, 0, 0, .32);
        }

        .home-v2-overlay p {
            margin: 13px 0 0;
            font-size: clamp(20px, 5.4vw, 30px);
            line-height: 1.02;
            letter-spacing: -.055em;
            font-weight: 1000;
            text-shadow: 0 10px 24px rgba(0, 0, 0, .30);
        }

        .home-v2-hand {
            width: 82px;
            height: 82px;
            margin: 16px auto 0;
            position: relative;
            display: grid;
            place-items: center;
            font-size: 72px;
            filter: drop-shadow(0 10px 22px rgba(0,0,0,.28));
        }

        .home-v2-hand::after {
            content: "";
            position: absolute;
            width: 68px;
            height: 68px;
            border-radius: 999px;
            border: 7px solid rgba(190, 255, 218, .56);
            animation: homePulse 1.45s ease-out infinite;
        }

        @keyframes homePulse {
            0% {
                transform: scale(.72);
                opacity: .90;
            }

            100% {
                transform: scale(1.28);
                opacity: 0;
            }
        }

        .home-v2-note {
            margin: 12px auto 0;
            max-width: 330px;
            text-align: center;
            color: #8390a4;
            font-size: 12px;
            line-height: 1.34;
            font-weight: 900;
        }

        .home-v2-real-cta-wrap {
            margin-top: 14px;
        }

        .home-v2-real-cta,
        .home-v2-real-cta-button {
            width: 100%;
            min-height: 50px;
            border: 0;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: #fff;
            font-size: 15px;
            font-weight: 1000;
            cursor: pointer;
            box-shadow: 0 16px 34px rgba(15, 23, 42, .18);
            background: linear-gradient(135deg, #16a34a, #22c55e);
        }

        .home-v2-real-cta.is-instagram,
        .home-v2-real-cta-button.is-instagram {
            background: radial-gradient(circle at 30% 110%, var(--instagram-start) 0 18%, var(--instagram-mid) 44%, var(--instagram-end) 100%);
        }

        .home-v2-real-cta.is-facebook,
        .home-v2-real-cta-button.is-facebook {
            background: linear-gradient(135deg, var(--facebook-start), var(--facebook-end));
        }

        .home-v2-real-cta.is-whatsapp,
        .home-v2-real-cta-button.is-whatsapp {
            background: linear-gradient(135deg, var(--whatsapp-start), var(--whatsapp-end));
        }

        .home-v2-timer {
            margin: 10px auto 0;
            width: max-content;
            max-width: 100%;
            padding: 8px 14px;
            border-radius: 999px;
            background: #eefbf2;
            color: #15803d;
            font-size: 12px;
            font-weight: 1000;
        }

        .home-v2-timer.is-urgente {
            background: #fff7ed;
            color: #c2410c;
        }

        .home-v2-timer.is-expired {
            background: #fef2f2;
            color: #b91c1c;
        }

        @media (max-width: 390px) {
            .home-v2-page {
                padding: 16px 12px 145px;
                width: 100%;
            }

            .home-v2-header {
                display: flex;
                gap: 11px;
                margin: 0 0 16px;
                padding-top: 2px;
            }

            .home-v2-avatar-frame {
                width: 60px;
                height: 60px;
                padding: 3px;
            }

            .home-v2-avatar {
                border-width: 4px;
            }

            .home-v2-greeting h1 {
                font-size: clamp(27px, 7.6vw, 34px);
                line-height: .96;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .home-v2-wave {
                display: inline-block;
                margin-left: 3px;
                margin-top: 0;
                font-size: .56em;
            }

            .home-v2-level {
                font-size: 12px;
                margin-top: 6px;
                padding: 6px 9px;
            }

            .home-v2-mission-card {
                border-radius: 28px;
                padding: 16px 12px 14px;
            }

            .home-v2-mission-title {
                font-size: clamp(21px, 6vw, 25px);
                margin-bottom: 14px;
                gap: 8px;
            }

            .home-v2-target {
                width: 38px;
                height: 38px;
                font-size: 22px;
            }

            .home-v2-post {
                border-radius: 24px;
                min-height: min(445px, 56svh);
            }

            .home-v2-post-head {
                top: 14px;
                left: 16px;
                right: 16px;
            }

            .home-v2-account {
                font-size: 17px;
            }

            .home-v2-network-icon {
                width: 34px;
                height: 34px;
                font-size: 18px;
            }

            .home-v2-dots {
                font-size: 24px;
            }

            .home-v2-post-image {
                left: 18px;
                right: 18px;
                top: 70px;
                height: 54%;
                border-radius: 20px;
            }

            .home-v2-overlay {
                padding: 18px;
            }

            .home-v2-overlay h2 {
                font-size: clamp(34px, 10vw, 46px);
                line-height: .9;
            }

            .home-v2-overlay p {
                font-size: clamp(20px, 5.8vw, 26px);
                margin-top: 12px;
            }

            .home-v2-hand {
                width: 74px;
                height: 74px;
                font-size: 64px;
                margin-top: 12px;
            }

            .home-v2-hand::after {
                width: 60px;
                height: 60px;
                border-width: 6px;
            }

            .home-v2-post-foot {
                left: 18px;
                right: 18px;
                bottom: 14px;
            }

            .home-v2-actions {
                font-size: 26px;
                margin-bottom: 8px;
            }

            .home-v2-actions-left {
                gap: 16px;
            }

            .home-v2-caption {
                font-size: 15px;
                -webkit-line-clamp: 2;
            }

            .home-v2-real-cta-wrap {
                margin-top: 12px;
            }

            .home-v2-real-cta,
            .home-v2-real-cta-button {
                min-height: 48px;
                border-radius: 16px;
                font-size: 14px;
            }

            .home-v2-timer {
                font-size: 11px;
                padding: 7px 12px;
            }

            .home-v2-note {
                font-size: 11px;
                line-height: 1.25;
            }
        }
        }
    
        /* ===============================
           V18 - Refinamento visual premium
           Mantem core real da missao
        =============================== */

        .home-v2-page {
            width: min(100%, 410px);
            padding-top: 14px;
            padding-bottom: 170px;
        }

        .home-v2-header {
            max-width: 372px;
            margin: 0 auto 16px;
            padding: 0 2px;
            gap: 10px;
        }

        .home-v2-avatar-frame {
            width: 58px;
            height: 58px;
            padding: 3px;
            box-shadow: 0 10px 24px rgba(22, 163, 74, .14);
        }

        .home-v2-avatar {
            border-width: 3px;
        }

        .home-v2-greeting h1 {
            font-size: clamp(25px, 6.8vw, 34px);
            letter-spacing: -.055em;
            line-height: .96;
        }

        .home-v2-wave {
            font-size: .50em;
            margin-left: 2px;
            transform: translateY(-3px);
        }

        .home-v2-level {
            margin-top: 5px;
            padding: 5px 9px;
            font-size: 11px;
            background: rgba(22, 163, 74, .105);
            box-shadow: inset 0 0 0 1px rgba(22, 163, 74, .12);
        }

        .home-v2-mission-card {
            max-width: 372px;
            margin: 0 auto;
            border-radius: 28px;
            padding: 14px 12px 14px;
            box-shadow:
                0 22px 55px rgba(15, 23, 42, .10),
                0 7px 18px rgba(15, 23, 42, .05);
        }

        .home-v2-mission-title {
            min-height: 38px;
            gap: 8px;
            margin-bottom: 12px;
            font-size: clamp(22px, 5.8vw, 28px);
            line-height: .92;
            letter-spacing: -.052em;
        }

        .home-v2-target {
            width: 38px;
            height: 38px;
            font-size: 22px;
            box-shadow: 0 8px 18px rgba(34, 197, 94, .12);
        }

        .home-v2-mission-title-text {
            line-height: .92;
        }

        .home-v2-post {
            min-height: 410px;
            border-radius: 25px;
            box-shadow:
                0 18px 36px rgba(15, 23, 42, .22),
                0 4px 10px rgba(15, 23, 42, .10);
        }

        .home-v2-post-real-bg img {
            transform: scale(1.01);
            object-position: center;
        }

        .home-v2-post-shade {
            background:
                linear-gradient(180deg, rgba(15,23,42,.14) 0%, rgba(15,23,42,.43) 38%, rgba(15,23,42,.62) 100%),
                radial-gradient(circle at center, rgba(0,0,0,.05), rgba(0,0,0,.34));
        }

        .home-v2-post-head {
            top: 15px;
            left: 16px;
            right: 16px;
        }

        .home-v2-account {
            gap: 8px;
            font-size: 17px;
        }

        .home-v2-network-icon {
            width: 34px;
            height: 34px;
            font-size: 18px;
            box-shadow: 0 8px 18px rgba(0,0,0,.18);
        }

        .home-v2-dots {
            font-size: 25px;
            letter-spacing: -7px;
            opacity: .88;
        }

        .home-v2-overlay {
            padding: 18px 18px 44px;
            align-items: center;
        }

        .home-v2-overlay h2 {
            font-size: clamp(36px, 9.2vw, 52px);
            line-height: .91;
            letter-spacing: -.065em;
            text-shadow:
                0 5px 18px rgba(0,0,0,.32),
                0 1px 0 rgba(0,0,0,.12);
        }

        .home-v2-overlay p {
            margin-top: 12px;
            font-size: clamp(19px, 5.2vw, 27px);
            line-height: 1;
            text-shadow: 0 4px 14px rgba(0,0,0,.34);
        }

        .home-v2-hand {
            width: 70px;
            height: 70px;
            margin-top: 12px;
            font-size: 60px;
            filter: drop-shadow(0 12px 18px rgba(0,0,0,.24));
        }

        .home-v2-hand::after {
            width: 56px;
            height: 56px;
            border-width: 5px;
            opacity: .28;
        }

        .home-v2-post-foot {
            left: 18px;
            right: 18px;
            bottom: 14px;
            opacity: .88;
        }

        .home-v2-actions {
            font-size: 25px;
            margin-bottom: 7px;
        }

        .home-v2-actions-left {
            gap: 15px;
        }

        .home-v2-caption {
            font-size: 14px;
            line-height: 1.12;
            letter-spacing: -.035em;
            -webkit-line-clamp: 2;
        }

        .home-v2-caption span {
            margin-top: 4px;
            font-size: 14px;
        }

        .home-v2-timer {
            margin-top: 10px;
            padding: 7px 13px;
            border-radius: 999px;
            font-size: 11px;
            box-shadow:
                0 10px 22px rgba(22, 163, 74, .10),
                inset 0 0 0 1px rgba(22, 163, 74, .10);
        }

        .home-v2-real-cta-wrap {
            margin-top: 10px;
        }

        .home-v2-real-cta,
        .home-v2-real-cta-button {
            min-height: 48px;
            border-radius: 16px;
            font-size: 13px;
            letter-spacing: -.02em;
            box-shadow:
                0 16px 28px rgba(22, 163, 74, .24),
                inset 0 -2px 0 rgba(0,0,0,.10);
        }

        .home-v2-real-cta i,
        .home-v2-real-cta-button i {
            font-size: 14px;
        }

        .home-v2-note {
            margin-top: 10px;
            max-width: 290px;
            font-size: 10.5px;
            line-height: 1.25;
            color: #94a3b8;
        }

        @media (max-width: 430px) {
            .home-v2-page {
                width: 100%;
                padding: 13px 14px 160px;
            }

            .home-v2-header,
            .home-v2-mission-card {
                max-width: 100%;
            }

            .home-v2-header {
                margin-bottom: 14px;
            }

            .home-v2-mission-card {
                border-radius: 27px;
                padding: 13px 11px 13px;
            }

            .home-v2-post {
                min-height: min(408px, 54svh);
                border-radius: 24px;
            }

            .home-v2-overlay h2 {
                font-size: clamp(34px, 9.8vw, 45px);
            }

            .home-v2-overlay p {
                font-size: clamp(18px, 5.5vw, 24px);
            }

            .home-v2-note {
                display: none;
            }
        }

        @media (max-width: 370px) {
            .home-v2-page {
                padding-left: 10px;
                padding-right: 10px;
            }

            .home-v2-avatar-frame {
                width: 52px;
                height: 52px;
            }

            .home-v2-greeting h1 {
                font-size: clamp(23px, 7.2vw, 30px);
            }

            .home-v2-post {
                min-height: 386px;
            }

            .home-v2-overlay h2 {
                font-size: 34px;
            }

            .home-v2-hand {
                width: 62px;
                height: 62px;
                font-size: 54px;
            }
        }

    
        /* ===============================
           V19 - Bloco compacto de avanço
        =============================== */

        .home-v2-advance-card {
            margin: 11px auto 0;
            width: 100%;
            max-width: 330px;
            padding: 11px 12px 12px;
            border-radius: 18px;
            background:
                linear-gradient(135deg, rgba(255,255,255,.96), rgba(240,253,244,.92)),
                radial-gradient(circle at 100% 0%, rgba(34,197,94,.14), transparent 34%);
            border: 1px solid rgba(226, 232, 240, .95);
            box-shadow:
                0 14px 26px rgba(15, 23, 42, .07),
                inset 0 1px 0 rgba(255,255,255,.88);
        }

        .home-v2-advance-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 7px;
        }

        .home-v2-advance-kicker {
            color: #64748b;
            font-size: 10px;
            font-weight: 1000;
            letter-spacing: .035em;
            text-transform: uppercase;
        }

        .home-v2-advance-rank {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
            padding: 5px 8px;
            border-radius: 999px;
            background: rgba(250, 204, 21, .16);
            color: #92400e;
            font-size: 11px;
            font-weight: 1000;
            line-height: 1;
            box-shadow: inset 0 0 0 1px rgba(250, 204, 21, .18);
        }

        .home-v2-advance-main {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .home-v2-advance-main strong {
            display: block;
            color: #0f172a;
            font-size: 12.5px;
            line-height: 1.12;
            letter-spacing: -.035em;
            font-weight: 1000;
        }

        .home-v2-advance-main small {
            display: block;
            margin-top: 3px;
            color: #94a3b8;
            font-size: 10.5px;
            line-height: 1;
            font-weight: 900;
        }

        .home-v2-advance-bar {
            position: relative;
            width: 100%;
            height: 8px;
            margin-top: 9px;
            border-radius: 999px;
            overflow: hidden;
            background: #e8eef5;
            box-shadow: inset 0 1px 2px rgba(15, 23, 42, .08);
        }

        .home-v2-advance-bar span {
            position: absolute;
            inset: 0 auto 0 0;
            border-radius: inherit;
            background: linear-gradient(90deg, #16a34a 0%, #22c55e 56%, #facc15 100%);
            box-shadow: 0 5px 12px rgba(34, 197, 94, .26);
        }

        @media (max-width: 430px) {
            .home-v2-advance-card {
                max-width: 100%;
                margin-top: 10px;
                padding: 10px 11px 11px;
                border-radius: 17px;
            }

            .home-v2-advance-main strong {
                font-size: 12px;
            }

            .home-v2-advance-main small {
                font-size: 10px;
            }
        }

        @media (max-height: 760px) and (max-width: 430px) {
            .home-v2-advance-card {
                padding: 9px 10px;
            }

            .home-v2-advance-head {
                margin-bottom: 5px;
            }

            .home-v2-advance-bar {
                height: 7px;
                margin-top: 7px;
            }
        }

    
        /* ===============================
           V20 - Game copy + narrativa
        =============================== */

        .home-v2-mission-title {
            align-items: center;
            min-height: 48px;
            margin-bottom: 12px;
        }

        .home-v2-mission-title-text {
            display: flex;
            flex-direction: column;
            gap: 3px;
            line-height: 1;
            letter-spacing: 0;
        }

        .home-v2-mission-title-text strong {
            display: block;
            color: var(--home-green);
            font-size: clamp(24px, 6vw, 31px);
            line-height: .92;
            letter-spacing: -.06em;
            font-weight: 1000;
            text-transform: none;
        }

        .home-v2-mission-title-text small {
            display: block;
            color: #7b8799;
            font-size: 11px;
            line-height: 1.1;
            letter-spacing: -.02em;
            font-weight: 900;
            text-transform: none;
        }

        .home-v2-target {
            background:
                radial-gradient(circle at 30% 25%, rgba(255,255,255,.92), rgba(255,255,255,.70)),
                linear-gradient(135deg, rgba(34,197,94,.13), rgba(14,165,233,.10));
        }

        .home-v2-post {
            min-height: 420px;
        }

        .home-v2-post-shade {
            background:
                linear-gradient(180deg, rgba(15,23,42,.10) 0%, rgba(15,23,42,.36) 36%, rgba(15,23,42,.66) 100%),
                radial-gradient(circle at center, rgba(0,0,0,.02), rgba(0,0,0,.32));
        }

        .home-v2-overlay {
            padding-bottom: 58px;
        }

        .home-v2-overlay h2 {
            font-size: clamp(40px, 10.2vw, 58px);
            line-height: .88;
            letter-spacing: -.075em;
        }

        .home-v2-overlay p {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: max-content;
            max-width: 92%;
            margin: 12px auto 0;
            padding: 8px 13px;
            border-radius: 999px;
            background: rgba(255,255,255,.18);
            border: 1px solid rgba(255,255,255,.18);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            font-size: clamp(18px, 5vw, 25px);
            line-height: 1;
            color: #fff;
            box-shadow: 0 10px 22px rgba(0,0,0,.16);
        }

        .home-v2-hand {
            margin-top: 10px;
            width: 66px;
            height: 66px;
            font-size: 58px;
        }

        .home-v2-caption {
            font-size: 13.5px;
            opacity: .94;
        }

        .home-v2-caption span {
            display: inline-flex;
            width: max-content;
            margin-top: 5px;
            padding: 4px 8px;
            border-radius: 999px;
            background: rgba(250,204,21,.14);
            color: #fde68a;
            font-size: 12px;
            text-shadow: none;
        }

        .home-v2-timer {
            margin-top: 11px;
            background:
                linear-gradient(135deg, rgba(236,253,245,.96), rgba(240,253,244,.88));
            color: #047857;
            font-size: 11px;
            font-weight: 1000;
        }

        .home-v2-timer::before {
            content: "⏳ ";
        }

        .home-v2-real-cta,
        .home-v2-real-cta-button {
            min-height: 50px;
            border-radius: 17px;
            font-size: 13px;
            text-transform: none;
            letter-spacing: -.025em;
        }

        .home-v2-real-cta::after,
        .home-v2-real-cta-button::after {
            content: "";
            position: absolute;
            inset: 1px;
            border-radius: inherit;
            background: linear-gradient(180deg, rgba(255,255,255,.18), rgba(255,255,255,0));
            pointer-events: none;
        }

        .home-v2-note {
            max-width: 305px;
            margin-top: 9px;
            color: #8491a5;
            font-size: 10.5px;
            line-height: 1.25;
            font-weight: 900;
        }

        .home-v2-advance-card {
            margin-top: 10px;
            transform: translateY(0);
        }

        @media (max-width: 430px) {
            .home-v2-mission-title {
                min-height: 44px;
                gap: 9px;
            }

            .home-v2-mission-title-text strong {
                font-size: clamp(23px, 6.4vw, 28px);
            }

            .home-v2-mission-title-text small {
                font-size: 10.5px;
            }

            .home-v2-post {
                min-height: min(426px, 55svh);
            }

            .home-v2-overlay {
                padding: 18px 18px 58px;
            }

            .home-v2-overlay h2 {
                font-size: clamp(39px, 11vw, 50px);
            }

            .home-v2-overlay p {
                font-size: clamp(18px, 5.4vw, 23px);
                padding: 7px 12px;
            }

            .home-v2-note {
                display: none;
            }
        }

        @media (max-height: 760px) and (max-width: 430px) {
            .home-v2-post {
                min-height: 398px;
            }

            .home-v2-overlay h2 {
                font-size: clamp(35px, 10vw, 45px);
            }

            .home-v2-hand {
                width: 60px;
                height: 60px;
                font-size: 52px;
            }
        }

    
        /* ===============================
           V21 - Header curto + missão CTA
        =============================== */

        .home-v2-page {
            padding-top: 12px;
            padding-bottom: 190px;
        }

        .home-v2-header {
            max-width: 372px;
            margin-bottom: 8px;
        }

        .home-v2-player-strip {
            width: 100%;
            max-width: 372px;
            margin: 0 auto 12px;
            padding: 10px 12px 11px;
            border-radius: 20px;
            background:
                linear-gradient(135deg, rgba(255,255,255,.94), rgba(240,253,244,.90)),
                radial-gradient(circle at 100% 0%, rgba(250,204,21,.16), transparent 38%);
            border: 1px solid rgba(226,232,240,.95);
            box-shadow: 0 14px 32px rgba(15,23,42,.07);
        }

        .home-v2-player-strip-main {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .home-v2-player-strip-main div {
            display: flex;
            align-items: baseline;
            gap: 5px;
            min-width: 0;
        }

        .home-v2-player-strip-main span {
            color: #64748b;
            font-size: 11px;
            font-weight: 1000;
            letter-spacing: -.02em;
        }

        .home-v2-player-strip-main strong {
            color: #16a34a;
            font-size: 15px;
            line-height: 1;
            font-weight: 1000;
            letter-spacing: -.045em;
        }

        .home-v2-player-strip-main em {
            flex: 0 0 auto;
            padding: 5px 8px;
            border-radius: 999px;
            background: rgba(22,163,74,.10);
            color: #047857;
            font-style: normal;
            font-size: 10px;
            line-height: 1;
            font-weight: 1000;
        }

        .home-v2-player-strip-bar {
            position: relative;
            height: 8px;
            margin-top: 8px;
            overflow: hidden;
            border-radius: 999px;
            background: #e8eef5;
            box-shadow: inset 0 1px 2px rgba(15,23,42,.08);
        }

        .home-v2-player-strip-bar i {
            position: absolute;
            inset: 0 auto 0 0;
            border-radius: inherit;
            background: linear-gradient(90deg, #16a34a 0%, #22c55e 58%, #facc15 100%);
            box-shadow: 0 5px 14px rgba(34,197,94,.28);
        }

        .home-v2-player-strip small {
            display: block;
            margin-top: 6px;
            color: #94a3b8;
            font-size: 10px;
            line-height: 1;
            font-weight: 900;
        }

        .home-v2-carousel-hint {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 0 0 10px;
            padding: 0 2px;
        }

        .home-v2-carousel-hint span {
            color: #94a3b8;
            font-size: 10px;
            font-weight: 1000;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .home-v2-carousel-hint div {
            width: 34px;
            height: 6px;
            border-radius: 999px;
            background: #e8eef5;
            overflow: hidden;
        }

        .home-v2-carousel-hint i {
            display: block;
            width: 100%;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #16a34a, #22c55e);
        }

        .home-v2-mission-card {
            padding-top: 12px;
        }

        .home-v2-mission-title {
            align-items: flex-start;
            margin-bottom: 12px;
            min-height: auto;
        }

        .home-v2-mission-title-text strong {
            font-size: clamp(24px, 6.3vw, 30px);
        }

        .home-v2-mission-title-text small {
            max-width: 278px;
            margin-top: 2px;
            color: #7f8a9f;
            font-size: 11.5px;
            line-height: 1.18;
            font-weight: 950;
            letter-spacing: -.03em;
        }

        .home-v2-post {
            min-height: 446px;
            cursor: pointer;
        }

        .home-v2-post::before {
            content: "Toque para abrir";
            position: absolute;
            z-index: 8;
            left: 50%;
            bottom: 18px;
            transform: translateX(-50%);
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,.14);
            border: 1px solid rgba(255,255,255,.16);
            color: rgba(255,255,255,.92);
            font-size: 10px;
            font-weight: 1000;
            text-transform: uppercase;
            letter-spacing: .04em;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            pointer-events: none;
        }

        .home-v2-overlay {
            padding-bottom: 70px;
        }

        .home-v2-overlay h2 {
            font-size: clamp(38px, 10.5vw, 54px);
            line-height: .88;
        }

        .home-v2-overlay p {
            max-width: 82%;
            white-space: normal;
            text-align: center;
            line-height: 1.05;
            font-size: clamp(15px, 4.4vw, 20px);
        }

        .home-v2-timer,
        .home-v2-real-cta-wrap,
        .home-v2-real-cta,
        .home-v2-real-cta-button {
            display: none !important;
        }

        .home-v2-note {
            display: none !important;
        }

        .home-v2-advance-card {
            display: none !important;
        }

        @media (max-width: 430px) {
            .home-v2-page {
                padding-top: 10px;
                padding-bottom: 185px;
            }

            .home-v2-player-strip {
                max-width: 100%;
                margin-bottom: 11px;
            }

            .home-v2-post {
                min-height: min(452px, 59svh);
            }

            .home-v2-mission-title-text small {
                max-width: 245px;
                font-size: 11px;
            }

            .home-v2-overlay h2 {
                font-size: clamp(36px, 10.8vw, 48px);
            }

            .home-v2-overlay p {
                font-size: clamp(14px, 4.5vw, 18px);
            }
        }

        @media (max-height: 760px) and (max-width: 430px) {
            .home-v2-post {
                min-height: 420px;
            }

            .home-v2-player-strip {
                padding-top: 9px;
                padding-bottom: 9px;
            }
        }

    
        /* ===============================
           V22 - Card limpo sem falso Instagram
        =============================== */

        .home-v2-mission-title-text strong {
            font-size: clamp(25px, 6.8vw, 32px);
            letter-spacing: -.065em;
        }

        .home-v2-mission-title-text small {
            max-width: 270px;
            color: #8b95a7;
            font-size: 11.5px;
            line-height: 1.18;
        }

        .home-v2-post {
            min-height: 438px;
            border-radius: 29px;
        }

        /* Remove cabeçalho falso tipo Instagram */
        .home-v2-post-head {
            display: none !important;
        }

        /* Remove ícones falsos de like/comment/share/save e legenda interna */
        .home-v2-post-foot {
            display: none !important;
        }

        /* Remove aviso redundante */
        .home-v2-post::before {
            display: none !important;
            content: none !important;
        }

        .home-v2-post-shade {
            background:
                linear-gradient(180deg, rgba(15,23,42,.08) 0%, rgba(15,23,42,.30) 35%, rgba(15,23,42,.58) 100%),
                radial-gradient(circle at center, rgba(0,0,0,.00), rgba(0,0,0,.26));
        }

        .home-v2-overlay {
            padding: 26px 22px;
            justify-content: center;
        }

        .home-v2-overlay h2 {
            font-size: clamp(42px, 11vw, 60px);
            line-height: .88;
            letter-spacing: -.08em;
            text-shadow: 0 7px 20px rgba(0,0,0,.32);
        }

        .home-v2-overlay p {
            max-width: 82%;
            padding: 9px 15px;
            margin-top: 13px;
            border-radius: 999px;
            background: rgba(15, 23, 42, .34);
            border: 1px solid rgba(255,255,255,.16);
            color: #fff;
            font-size: clamp(17px, 4.8vw, 24px);
            line-height: 1.05;
            text-align: center;
            box-shadow: 0 12px 24px rgba(0,0,0,.18);
        }

        .home-v2-hand {
            margin-top: 12px;
            width: 68px;
            height: 68px;
            font-size: 60px;
        }

        .home-v2-post-summary {
            width: 100%;
            margin: 12px auto 0;
            padding: 12px 13px;
            border-radius: 18px;
            background:
                linear-gradient(135deg, rgba(255,255,255,.97), rgba(248,250,252,.94));
            border: 1px solid rgba(226,232,240,.92);
            box-shadow: 0 12px 26px rgba(15,23,42,.06);
        }

        .home-v2-post-summary span {
            display: block;
            margin-bottom: 4px;
            color: #16a34a;
            font-size: 10px;
            line-height: 1;
            font-weight: 1000;
            text-transform: uppercase;
            letter-spacing: .045em;
        }

        .home-v2-post-summary p {
            margin: 0;
            color: #334155;
            font-size: 12.5px;
            line-height: 1.25;
            font-weight: 900;
            letter-spacing: -.025em;
        }

        /* Mantém removidos: botão, timer e avanço redundantes */
        .home-v2-timer,
        .home-v2-real-cta-wrap,
        .home-v2-real-cta,
        .home-v2-real-cta-button,
        .home-v2-note,
        .home-v2-advance-card {
            display: none !important;
        }

        @media (max-width: 430px) {
            .home-v2-post {
                min-height: min(438px, 57svh);
            }

            .home-v2-overlay h2 {
                font-size: clamp(39px, 11vw, 52px);
            }

            .home-v2-overlay p {
                max-width: 88%;
                font-size: clamp(16px, 4.8vw, 20px);
            }

            .home-v2-post-summary {
                padding: 11px 12px;
                margin-top: 11px;
            }
        }

        @media (max-height: 760px) and (max-width: 430px) {
            .home-v2-post {
                min-height: 405px;
            }

            .home-v2-post-summary p {
                font-size: 12px;
                line-height: 1.18;
            }
        }

    
        /* ===============================
           V23 - Header curto perfil + icone rede
        =============================== */

        .home-v2-page {
            padding-top: 9px;
            padding-bottom: 182px;
        }

        .home-v2-header {
            max-width: 390px;
            margin: 0 auto 12px;
            display: grid;
            grid-template-columns: 62px minmax(0, 1fr);
            align-items: center;
            column-gap: 10px;
            row-gap: 8px;
        }

        .home-v2-avatar-frame {
            width: 58px;
            height: 58px;
            grid-row: span 2;
        }

        .home-v2-greeting {
            min-width: 0;
        }

        .home-v2-greeting h1 {
            font-size: clamp(27px, 7vw, 35px);
            line-height: .92;
            letter-spacing: -.065em;
            white-space: nowrap;
        }

        .home-v2-level {
            display: none !important;
        }

        .home-v2-header-progress {
            grid-column: 2;
            width: 100%;
            max-width: 235px;
            padding: 7px 9px 8px;
            border-radius: 14px;
            background:
                linear-gradient(135deg, rgba(255,255,255,.92), rgba(240,253,244,.86));
            border: 1px solid rgba(187,247,208,.72);
            box-shadow: 0 10px 22px rgba(15,23,42,.055);
        }

        .home-v2-header-progress-copy {
            display: flex;
            align-items: center;
            gap: 4px;
            min-width: 0;
            white-space: nowrap;
        }

        .home-v2-header-progress-copy span {
            color: #64748b;
            font-size: 9.5px;
            font-weight: 1000;
        }

        .home-v2-header-progress-copy strong {
            color: #16a34a;
            font-size: 11px;
            line-height: 1;
            font-weight: 1000;
            letter-spacing: -.03em;
        }

        .home-v2-header-progress-copy em {
            margin-left: auto;
            color: #94a3b8;
            font-size: 9px;
            line-height: 1;
            font-style: normal;
            font-weight: 1000;
        }

        .home-v2-header-progress-bar {
            position: relative;
            height: 6px;
            margin-top: 5px;
            overflow: hidden;
            border-radius: 999px;
            background: #e8eef5;
        }

        .home-v2-header-progress-bar i {
            position: absolute;
            inset: 0 auto 0 0;
            border-radius: inherit;
            background: linear-gradient(90deg, #16a34a 0%, #22c55e 58%, #facc15 100%);
        }

        .home-v2-player-strip {
            display: none !important;
        }

        .home-v2-mission-card {
            max-width: 390px;
            border-radius: 27px;
            padding: 14px 11px 13px;
        }

        .home-v2-carousel-hint {
            display: none !important;
        }

        .home-v2-mission-title {
            gap: 8px;
            margin-bottom: 11px;
        }

        .home-v2-target {
            width: 35px;
            height: 35px;
            font-size: 20px;
            flex: 0 0 auto;
        }

        .home-v2-mission-title-text {
            min-width: 0;
        }

        .home-v2-mission-title-text strong {
            display: flex;
            align-items: center;
            gap: 7px;
            min-width: 0;
            color: #16a34a;
            font-size: clamp(22px, 6vw, 28px);
            line-height: .95;
            letter-spacing: -.062em;
            white-space: nowrap;
        }

        .home-v2-social-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 25px;
            height: 25px;
            border-radius: 8px;
            color: #fff;
            font-size: 15px;
            line-height: 1;
            font-weight: 1000;
            box-shadow: 0 8px 16px rgba(15,23,42,.12);
        }

        .home-v2-social-badge.is-instagram {
            background: radial-gradient(circle at 30% 110%, #feda75 0%, #fa7e1e 25%, #d62976 52%, #962fbf 74%, #4f5bd5 100%);
        }

        .home-v2-social-badge.is-facebook {
            background: linear-gradient(135deg, #1877f2, #0f5fd1);
            font-family: Arial, sans-serif;
            font-size: 18px;
        }

        .home-v2-mission-title-text small {
            max-width: 260px;
            margin-top: 3px;
            color: #7b8799;
            font-size: 10.5px;
            line-height: 1.15;
            font-weight: 950;
            letter-spacing: -.025em;
        }

        .home-v2-post {
            min-height: 405px;
            border-radius: 26px;
        }

        .home-v2-overlay {
            padding: 24px 19px;
        }

        .home-v2-overlay h2 {
            font-size: clamp(38px, 10vw, 52px);
            line-height: .88;
        }

        .home-v2-overlay p {
            max-width: 78%;
            padding: 8px 13px;
            font-size: clamp(15px, 4.4vw, 20px);
            line-height: 1.05;
        }

        .home-v2-hand {
            width: 62px;
            height: 62px;
            font-size: 54px;
            margin-top: 10px;
        }

        .home-v2-post-summary {
            margin-top: 10px;
            padding: 10px 12px;
            border-radius: 16px;
        }

        .home-v2-post-summary span {
            font-size: 9.5px;
        }

        .home-v2-post-summary p {
            font-size: 11.5px;
            line-height: 1.22;
        }

        @media (max-width: 430px) {
            .home-v2-page {
                padding-left: 13px;
                padding-right: 13px;
            }

            .home-v2-header,
            .home-v2-mission-card {
                max-width: 100%;
            }

            .home-v2-header {
                grid-template-columns: 58px minmax(0, 1fr);
                margin-bottom: 11px;
            }

            .home-v2-greeting h1 {
                font-size: clamp(27px, 8vw, 34px);
            }

            .home-v2-header-progress {
                max-width: 220px;
            }

            .home-v2-mission-title-text strong {
                font-size: clamp(21px, 6.4vw, 27px);
            }

            .home-v2-post {
                min-height: min(407px, 52svh);
            }

            .home-v2-overlay h2 {
                font-size: clamp(36px, 10.5vw, 48px);
            }
        }

        @media (max-height: 760px) and (max-width: 430px) {
            .home-v2-post {
                min-height: 382px;
            }

            .home-v2-post-summary {
                padding: 9px 11px;
            }
        }

    
        /* ===============================
           V24 - Visual real do app, sem Canva
        =============================== */

        .home-v2-page {
            width: min(100%, 430px);
            padding: 12px 16px 185px;
        }

        .home-v2-header {
            max-width: 398px;
            margin: 0 auto 12px;
            display: grid;
            grid-template-columns: 64px minmax(0, 1fr);
            align-items: center;
            column-gap: 11px;
            row-gap: 7px;
        }

        .home-v2-avatar-frame {
            grid-row: span 2;
            width: 60px;
            height: 60px;
            padding: 3px;
            border-radius: 999px;
            background: #fff;
            box-shadow: 0 12px 30px rgba(34, 197, 94, .14);
        }

        .home-v2-greeting h1 {
            font-size: clamp(28px, 7.2vw, 36px);
            line-height: .93;
            letter-spacing: -.07em;
            white-space: nowrap;
        }

        .home-v2-wave {
            font-size: .44em;
            transform: translateY(-5px);
        }

        .home-v2-level,
        .home-v2-header-progress,
        .home-v2-player-strip {
            display: none !important;
        }

        .home-v24-header-progress {
            grid-column: 2;
            width: 100%;
            max-width: 232px;
            padding: 7px 9px 8px;
            border-radius: 16px;
            background: rgba(255,255,255,.72);
            border: 1px solid rgba(187,247,208,.72);
            box-shadow: 0 10px 22px rgba(15,23,42,.055);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .home-v24-header-progress-top {
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        .home-v24-header-progress-top span {
            color: #64748b;
            font-size: 9.5px;
            font-weight: 1000;
        }

        .home-v24-header-progress-top strong {
            color: #16a34a;
            font-size: 11.5px;
            line-height: 1;
            font-weight: 1000;
            letter-spacing: -.035em;
        }

        .home-v24-header-progress-top em {
            margin-left: auto;
            color: #94a3b8;
            font-size: 9px;
            line-height: 1;
            font-style: normal;
            font-weight: 1000;
        }

        .home-v24-header-progress-bar {
            position: relative;
            height: 6px;
            margin-top: 5px;
            overflow: hidden;
            border-radius: 999px;
            background: #e8eef5;
        }

        .home-v24-header-progress-bar i {
            position: absolute;
            inset: 0 auto 0 0;
            border-radius: inherit;
            background: linear-gradient(90deg, #16a34a 0%, #22c55e 58%, #facc15 100%);
        }

        .home-v2-mission-card {
            max-width: 398px;
            margin: 0 auto;
            padding: 13px 12px 14px;
            border-radius: 30px;
            background: rgba(255,255,255,.94);
            border: 1px solid rgba(226,232,240,.95);
            box-shadow:
                0 20px 54px rgba(15,23,42,.10),
                inset 0 1px 0 rgba(255,255,255,.92);
        }

        .home-v2-carousel-hint {
            display: none !important;
        }

        .home-v2-mission-title {
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr);
            align-items: center;
            gap: 9px;
            margin-bottom: 11px;
            min-height: auto;
        }

        .home-v2-target {
            width: 34px;
            height: 34px;
            font-size: 19px;
            box-shadow: 0 8px 18px rgba(34,197,94,.10);
        }

        .home-v2-mission-title-text {
            min-width: 0;
        }

        .home-v24-title-line {
            display: flex !important;
            align-items: center;
            gap: 7px;
            min-width: 0;
            color: #0f172a !important;
            font-size: clamp(18px, 5vw, 24px) !important;
            line-height: 1 !important;
            letter-spacing: -.055em !important;
            font-weight: 1000 !important;
            white-space: nowrap;
        }

        .home-v24-title-line > span:last-child {
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .home-v24-social-icon {
            flex: 0 0 auto;
            width: 30px;
            height: 30px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            box-shadow: 0 8px 18px rgba(15,23,42,.10);
        }

        .home-v24-social-icon img {
            width: 22px;
            height: 22px;
            display: block;
            object-fit: contain;
        }

        .home-v2-mission-title-text small {
            display: block;
            margin-top: 3px;
            max-width: 260px;
            color: #8b95a7;
            font-size: 10.5px;
            line-height: 1.14;
            font-weight: 950;
            letter-spacing: -.025em;
        }

        .home-v2-post {
            min-height: 386px;
            border-radius: 26px;
            overflow: hidden;
            box-shadow:
                0 16px 34px rgba(15,23,42,.18),
                0 4px 9px rgba(15,23,42,.08);
            cursor: pointer;
        }

        .home-v2-post-head,
        .home-v2-post-foot,
        .home-v2-post::before {
            display: none !important;
            content: none !important;
        }

        .home-v2-post-shade {
            background:
                linear-gradient(180deg, rgba(15,23,42,.02) 0%, rgba(15,23,42,.22) 40%, rgba(15,23,42,.52) 100%),
                radial-gradient(circle at center, rgba(0,0,0,0), rgba(0,0,0,.22));
        }

        .home-v2-overlay {
            justify-content: center;
            padding: 24px 20px;
        }

        .home-v24-action-orb {
            width: 82px;
            height: 82px;
            margin-bottom: 7px;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(circle at 35% 25%, rgba(255,255,255,.95), rgba(255,255,255,.72)),
                linear-gradient(135deg, rgba(34,197,94,.16), rgba(250,204,21,.12));
            border: 1px solid rgba(255,255,255,.42);
            box-shadow:
                0 18px 34px rgba(0,0,0,.22),
                inset 0 1px 0 rgba(255,255,255,.85);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }

        .home-v24-action-orb img {
            width: 64px;
            height: 64px;
            object-fit: contain;
            display: block;
        }

        .home-v2-overlay h2 {
            font-size: clamp(32px, 8.5vw, 44px);
            line-height: .9;
            letter-spacing: -.065em;
            text-shadow: 0 8px 20px rgba(0,0,0,.26);
        }

        .home-v2-overlay p {
            width: auto;
            max-width: 78%;
            margin-top: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(15,23,42,.34);
            border: 1px solid rgba(255,255,255,.16);
            color: #fff;
            font-size: clamp(14px, 4vw, 18px);
            line-height: 1.05;
            font-weight: 1000;
            text-align: center;
            box-shadow: 0 12px 24px rgba(0,0,0,.16);
        }

        .home-v2-hand {
            display: none !important;
        }

        .home-v2-post-summary {
            margin-top: 10px;
            padding: 10px 12px;
            border-radius: 18px;
            background: linear-gradient(135deg, rgba(255,255,255,.98), rgba(248,250,252,.95));
            border: 1px solid rgba(226,232,240,.95);
            box-shadow: 0 12px 26px rgba(15,23,42,.055);
        }

        .home-v2-post-summary span {
            display: block;
            margin-bottom: 4px;
            color: #16a34a;
            font-size: 9.5px;
            line-height: 1;
            font-weight: 1000;
            text-transform: uppercase;
            letter-spacing: .045em;
        }

        .home-v2-post-summary p {
            margin: 0;
            color: #334155;
            font-size: 11.5px;
            line-height: 1.22;
            font-weight: 900;
            letter-spacing: -.025em;
        }

        .home-v2-timer,
        .home-v2-real-cta-wrap,
        .home-v2-real-cta,
        .home-v2-real-cta-button,
        .home-v2-note,
        .home-v2-advance-card {
            display: none !important;
        }

        @media (max-width: 430px) {
            .home-v2-page {
                padding-left: 13px;
                padding-right: 13px;
            }

            .home-v2-header,
            .home-v2-mission-card {
                max-width: 100%;
            }

            .home-v2-post {
                min-height: min(386px, 50svh);
            }

            .home-v24-title-line {
                font-size: clamp(18px, 5.4vw, 23px) !important;
            }

            .home-v2-overlay h2 {
                font-size: clamp(30px, 8.8vw, 40px);
            }

            .home-v24-action-orb {
                width: 76px;
                height: 76px;
            }

            .home-v24-action-orb img {
                width: 60px;
                height: 60px;
            }
        }

        @media (max-height: 760px) and (max-width: 430px) {
            .home-v2-post {
                min-height: 356px;
            }

            .home-v24-action-orb {
                width: 68px;
                height: 68px;
                border-radius: 22px;
            }

            .home-v24-action-orb img {
                width: 54px;
                height: 54px;
            }
        }

    
        /* ===============================
           V25 - RESET LIMPO
           Remove excesso visual e volta para padrão app
        =============================== */

        .home-v2-page {
            width: min(100%, 430px) !important;
            padding: 12px 16px 180px !important;
        }

        /* HEADER LIMPO */
        .home-v2-header {
            max-width: 398px !important;
            margin: 0 auto 14px !important;
            display: grid !important;
            grid-template-columns: 58px minmax(0, 1fr) !important;
            align-items: center !important;
            column-gap: 11px !important;
            row-gap: 5px !important;
            background: transparent !important;
            box-shadow: none !important;
            padding: 0 !important;
        }

        .home-v2-avatar-frame {
            width: 56px !important;
            height: 56px !important;
            padding: 3px !important;
            border-radius: 999px !important;
            background: #fff !important;
            box-shadow: 0 10px 24px rgba(34, 197, 94, .13) !important;
        }

        .home-v2-avatar {
            border-radius: 999px !important;
            border: 3px solid #22c55e !important;
        }

        .home-v2-greeting h1 {
            font-size: clamp(27px, 7.4vw, 35px) !important;
            line-height: .92 !important;
            letter-spacing: -.07em !important;
            color: #0f172a !important;
            white-space: nowrap !important;
        }

        .home-v2-wave {
            font-size: .42em !important;
            transform: translateY(-5px) !important;
        }

        .home-v2-level {
            display: inline-flex !important;
            width: fit-content !important;
            margin-top: 5px !important;
            padding: 5px 10px !important;
            border-radius: 999px !important;
            background: rgba(22, 163, 74, .12) !important;
            color: #16a34a !important;
            font-size: 11px !important;
            font-weight: 1000 !important;
            box-shadow: inset 0 0 0 1px rgba(34, 197, 94, .15) !important;
        }

        .home-v2-header-progress,
        .home-v24-header-progress,
        .home-v2-player-strip {
            display: none !important;
        }

        /* CARD PRINCIPAL */
        .home-v2-mission-card {
            max-width: 398px !important;
            margin: 0 auto !important;
            padding: 14px 12px 13px !important;
            border-radius: 28px !important;
            background: #fff !important;
            border: 1px solid rgba(226, 232, 240, .95) !important;
            box-shadow:
                0 18px 46px rgba(15, 23, 42, .10),
                inset 0 1px 0 rgba(255,255,255,.9) !important;
        }

        .home-v2-carousel-hint {
            display: none !important;
        }

        /* TITULO DA MISSAO */
        .home-v2-mission-title {
            display: grid !important;
            grid-template-columns: 34px minmax(0, 1fr) !important;
            align-items: center !important;
            gap: 9px !important;
            margin-bottom: 11px !important;
            min-height: auto !important;
        }

        .home-v2-target {
            width: 34px !important;
            height: 34px !important;
            font-size: 19px !important;
            background: rgba(240, 253, 244, .9) !important;
            box-shadow: 0 8px 18px rgba(34, 197, 94, .10) !important;
        }

        .home-v2-mission-title-text {
            min-width: 0 !important;
        }

        .home-v24-title-line,
        .home-v2-mission-title-text strong {
            display: flex !important;
            align-items: center !important;
            gap: 7px !important;
            color: #0f172a !important;
            font-size: clamp(19px, 5.2vw, 24px) !important;
            line-height: 1 !important;
            letter-spacing: -.055em !important;
            font-weight: 1000 !important;
            white-space: nowrap !important;
        }

        .home-v24-social-icon {
            width: 28px !important;
            height: 28px !important;
            border-radius: 9px !important;
            background: #fff !important;
            box-shadow: 0 7px 16px rgba(15, 23, 42, .10) !important;
        }

        .home-v24-social-icon img {
            width: 21px !important;
            height: 21px !important;
        }

        .home-v2-mission-title-text small {
            display: block !important;
            margin-top: 4px !important;
            color: #7b8799 !important;
            font-size: 10.5px !important;
            line-height: 1.15 !important;
            font-weight: 950 !important;
            letter-spacing: -.02em !important;
        }

        /* IMAGEM LIMPA */
        .home-v2-post {
            min-height: 0 !important;
            height: auto !important;
            aspect-ratio: 1 / 1 !important;
            border-radius: 25px !important;
            overflow: hidden !important;
            box-shadow:
                0 14px 30px rgba(15,23,42,.15),
                0 3px 8px rgba(15,23,42,.07) !important;
            cursor: pointer !important;
            background: #0f172a !important;
        }

        .home-v2-post-real-bg {
            opacity: 1 !important;
        }

        .home-v2-post-real-bg img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            object-position: center !important;
            transform: none !important;
            filter: none !important;
        }

        .home-v2-post-shade {
            background:
                linear-gradient(180deg, rgba(15,23,42,.02) 0%, rgba(15,23,42,.12) 55%, rgba(15,23,42,.32) 100%) !important;
        }

        /* REMOVE TODA A FIRULA SOBRE A IMAGEM */
        .home-v2-post-head,
        .home-v2-post-foot,
        .home-v2-post::before,
        .home-v24-action-orb,
        .home-v2-hand {
            display: none !important;
            content: none !important;
        }

        .home-v2-overlay {
            position: absolute !important;
            inset: auto 12px 12px 12px !important;
            z-index: 5 !important;
            display: flex !important;
            align-items: stretch !important;
            justify-content: flex-end !important;
            padding: 0 !important;
            pointer-events: none !important;
        }

        .home-v2-overlay h2 {
            display: none !important;
        }

        .home-v2-overlay p {
            width: 100% !important;
            max-width: none !important;
            margin: 0 !important;
            padding: 11px 13px !important;
            border-radius: 17px !important;
            background: rgba(15, 23, 42, .72) !important;
            border: 1px solid rgba(255, 255, 255, .16) !important;
            color: #fff !important;
            font-size: 13px !important;
            line-height: 1.05 !important;
            font-weight: 1000 !important;
            text-align: center !important;
            letter-spacing: -.025em !important;
            box-shadow: 0 12px 24px rgba(0,0,0,.20) !important;
            backdrop-filter: blur(8px) !important;
            -webkit-backdrop-filter: blur(8px) !important;
        }

        /* CAPTION */
        .home-v2-post-summary {
            margin-top: 10px !important;
            padding: 10px 12px !important;
            border-radius: 17px !important;
            background: #fff !important;
            border: 1px solid rgba(226, 232, 240, .95) !important;
            box-shadow: 0 10px 22px rgba(15,23,42,.045) !important;
        }

        .home-v2-post-summary span {
            display: block !important;
            margin-bottom: 4px !important;
            color: #16a34a !important;
            font-size: 9.5px !important;
            line-height: 1 !important;
            font-weight: 1000 !important;
            text-transform: uppercase !important;
            letter-spacing: .045em !important;
        }

        .home-v2-post-summary p {
            margin: 0 !important;
            color: #334155 !important;
            font-size: 11.5px !important;
            line-height: 1.22 !important;
            font-weight: 900 !important;
            letter-spacing: -.025em !important;
        }

        .home-v2-timer,
        .home-v2-real-cta-wrap,
        .home-v2-real-cta,
        .home-v2-real-cta-button,
        .home-v2-note,
        .home-v2-advance-card {
            display: none !important;
        }

        @media (max-width: 430px) {
            .home-v2-page {
                padding-left: 13px !important;
                padding-right: 13px !important;
            }

            .home-v2-header,
            .home-v2-mission-card {
                max-width: 100% !important;
            }

            .home-v2-mission-card {
                padding: 13px 11px 12px !important;
            }
        }

    
        /* =========================================================
           HOME GAME V26 — tela nova gamificada/dopamina
           Reaproveita dados reais, mata visual anterior
        ========================================================= */

        :root {
            --game-green: #16a34a;
            --game-lime: #22c55e;
            --game-yellow: #facc15;
            --game-ink: #0f172a;
            --game-muted: #8b95a7;
            --game-bg: #f6fbff;
        }

        body {
            background:
                radial-gradient(circle at 16% 8%, rgba(34,197,94,.13), transparent 30%),
                radial-gradient(circle at 88% 8%, rgba(250,204,21,.14), transparent 28%),
                linear-gradient(180deg, #f8fffb 0%, #f3f7fb 62%, #eef3f8 100%) !important;
        }

        .home-v2-page {
            width: min(100%, 430px) !important;
            padding: 14px 16px 186px !important;
        }

        /* HEADER NOVO */
        .home-v2-header {
            max-width: 398px !important;
            margin: 0 auto 14px !important;
            display: grid !important;
            grid-template-columns: 62px minmax(0, 1fr) !important;
            align-items: center !important;
            column-gap: 12px !important;
            row-gap: 8px !important;
            padding: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
        }

        .home-v2-avatar-frame {
            width: 60px !important;
            height: 60px !important;
            padding: 4px !important;
            border-radius: 999px !important;
            background: #fff !important;
            box-shadow:
                0 14px 30px rgba(22,163,74,.16),
                0 0 0 1px rgba(34,197,94,.12) !important;
        }

        .home-v2-avatar {
            border-radius: 999px !important;
            border: 3px solid var(--game-lime) !important;
        }

        .home-v2-greeting h1 {
            color: var(--game-ink) !important;
            font-size: clamp(28px, 7.3vw, 36px) !important;
            line-height: .92 !important;
            letter-spacing: -.07em !important;
            white-space: nowrap !important;
        }

        .home-v2-wave {
            font-size: .44em !important;
            transform: translateY(-5px) !important;
        }

        .home-v2-level {
            display: inline-flex !important;
            width: fit-content !important;
            margin-top: 5px !important;
            padding: 5px 10px !important;
            border-radius: 999px !important;
            background: rgba(22,163,74,.12) !important;
            color: var(--game-green) !important;
            font-size: 11px !important;
            font-weight: 1000 !important;
            box-shadow: inset 0 0 0 1px rgba(34,197,94,.15) !important;
        }

        .home-v2-header::after {
            content: "Energia da missão";
            grid-column: 1 / -1;
            display: block;
            margin-top: 2px;
            padding: 0 2px;
            color: #94a3b8;
            font-size: 10px;
            font-weight: 1000;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .home-v2-header-progress,
        .home-v24-header-progress,
        .home-v2-player-strip {
            display: none !important;
        }

        /* CARD NOVO */
        .home-v2-mission-card {
            position: relative !important;
            max-width: 398px !important;
            margin: 0 auto !important;
            padding: 14px 12px 13px !important;
            border-radius: 32px !important;
            overflow: hidden !important;
            background:
                radial-gradient(circle at 100% 0%, rgba(250,204,21,.18), transparent 32%),
                linear-gradient(180deg, rgba(255,255,255,.98), rgba(255,255,255,.92)) !important;
            border: 1px solid rgba(226,232,240,.95) !important;
            box-shadow:
                0 24px 60px rgba(15,23,42,.12),
                inset 0 1px 0 rgba(255,255,255,.92) !important;
        }

        .home-v2-mission-card::before {
            content: "";
            position: absolute;
            inset: -80px auto auto -80px;
            width: 180px;
            height: 180px;
            border-radius: 999px;
            background: rgba(34,197,94,.10);
            pointer-events: none;
        }

        .home-v2-carousel-hint {
            display: none !important;
        }

        /* TITULO DA MISSAO */
        .home-v2-mission-title {
            position: relative !important;
            z-index: 2 !important;
            display: grid !important;
            grid-template-columns: 44px minmax(0,1fr) !important;
            align-items: center !important;
            gap: 10px !important;
            margin-bottom: 12px !important;
            min-height: auto !important;
        }

        .home-v2-target {
            width: 44px !important;
            height: 44px !important;
            border-radius: 16px !important;
            background:
                radial-gradient(circle at 30% 25%, rgba(255,255,255,.98), rgba(255,255,255,.70)),
                linear-gradient(135deg, rgba(22,163,74,.16), rgba(250,204,21,.14)) !important;
            box-shadow:
                0 12px 22px rgba(15,23,42,.08),
                inset 0 1px 0 rgba(255,255,255,.92) !important;
            font-size: 0 !important;
        }

        .home-v2-target::after {
            content: "";
            width: 27px;
            height: 27px;
            display: block;
            background: url('/assets/animacoes/xp.webp') center / contain no-repeat;
        }

        .home-v2-mission-title-text {
            min-width: 0 !important;
        }

        .home-v24-title-line,
        .home-v2-mission-title-text strong {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            color: var(--game-ink) !important;
            font-size: clamp(20px, 5.4vw, 25px) !important;
            line-height: 1 !important;
            letter-spacing: -.055em !important;
            font-weight: 1000 !important;
            white-space: nowrap !important;
        }

        .home-v24-title-line > span:last-child {
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }

        .home-v24-social-icon {
            width: 31px !important;
            height: 31px !important;
            border-radius: 11px !important;
            background: #fff !important;
            box-shadow: 0 9px 18px rgba(15,23,42,.11) !important;
        }

        .home-v24-social-icon img {
            width: 23px !important;
            height: 23px !important;
            object-fit: contain !important;
        }

        .home-v2-mission-title-text small {
            display: block !important;
            margin-top: 4px !important;
            color: var(--game-muted) !important;
            font-size: 11px !important;
            line-height: 1.12 !important;
            font-weight: 950 !important;
            letter-spacing: -.02em !important;
        }

        /* CARD DE IMAGEM COMO ARENA */
        .home-v2-post {
            position: relative !important;
            z-index: 2 !important;
            min-height: 0 !important;
            height: auto !important;
            aspect-ratio: 1 / 1.05 !important;
            border-radius: 28px !important;
            overflow: hidden !important;
            background: var(--game-ink) !important;
            box-shadow:
                0 18px 40px rgba(15,23,42,.18),
                0 5px 12px rgba(15,23,42,.08) !important;
            cursor: pointer !important;
            transform: translateZ(0) !important;
        }

        .home-v2-post-real-bg {
            opacity: 1 !important;
        }

        .home-v2-post-real-bg img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            object-position: center !important;
            filter: saturate(1.05) contrast(.98) !important;
            transform: scale(1.015) !important;
        }

        .home-v2-post-shade {
            background:
                linear-gradient(180deg, rgba(15,23,42,.02) 0%, rgba(15,23,42,.16) 42%, rgba(15,23,42,.58) 100%),
                radial-gradient(circle at 50% 78%, rgba(34,197,94,.22), transparent 34%) !important;
        }

        .home-v2-post-head,
        .home-v2-post-foot,
        .home-v2-post::before,
        .home-v2-hand {
            display: none !important;
            content: none !important;
        }

        .home-v2-overlay {
            position: absolute !important;
            inset: auto 14px 14px 14px !important;
            z-index: 8 !important;
            display: grid !important;
            grid-template-columns: 58px minmax(0,1fr) !important;
            align-items: center !important;
            gap: 10px !important;
            padding: 10px !important;
            border-radius: 22px !important;
            background: rgba(15,23,42,.72) !important;
            border: 1px solid rgba(255,255,255,.15) !important;
            box-shadow: 0 18px 34px rgba(0,0,0,.24) !important;
            backdrop-filter: blur(10px) !important;
            -webkit-backdrop-filter: blur(10px) !important;
            pointer-events: none !important;
        }

        .home-v24-action-orb {
            width: 58px !important;
            height: 58px !important;
            margin: 0 !important;
            border-radius: 18px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            background: rgba(255,255,255,.94) !important;
            box-shadow: 0 10px 22px rgba(0,0,0,.18) !important;
        }

        .home-v24-action-orb img {
            width: 48px !important;
            height: 48px !important;
            object-fit: contain !important;
        }

        .home-v2-overlay h2 {
            display: block !important;
            grid-column: 2 !important;
            color: #fff !important;
            font-size: 18px !important;
            line-height: 1 !important;
            letter-spacing: -.045em !important;
            font-weight: 1000 !important;
            text-align: left !important;
            text-shadow: none !important;
            margin: 0 !important;
        }

        .home-v2-overlay p {
            grid-column: 2 !important;
            width: auto !important;
            max-width: none !important;
            margin: 4px 0 0 !important;
            padding: 0 !important;
            border: 0 !important;
            background: transparent !important;
            color: #bbf7d0 !important;
            box-shadow: none !important;
            font-size: 12px !important;
            line-height: 1.05 !important;
            font-weight: 1000 !important;
            text-align: left !important;
        }

        .home-v2-post:active {
            transform: scale(.985) !important;
        }

        /* LEGENDA */
        .home-v2-post-summary {
            position: relative !important;
            z-index: 2 !important;
            margin-top: 10px !important;
            padding: 11px 12px !important;
            border-radius: 19px !important;
            background: #fff !important;
            border: 1px solid rgba(226,232,240,.96) !important;
            box-shadow: 0 12px 26px rgba(15,23,42,.055) !important;
        }

        .home-v2-post-summary span {
            display: block !important;
            margin-bottom: 4px !important;
            color: var(--game-green) !important;
            font-size: 9.5px !important;
            line-height: 1 !important;
            font-weight: 1000 !important;
            text-transform: uppercase !important;
            letter-spacing: .045em !important;
        }

        .home-v2-post-summary p {
            margin: 0 !important;
            color: #334155 !important;
            font-size: 11.5px !important;
            line-height: 1.22 !important;
            font-weight: 900 !important;
            letter-spacing: -.025em !important;
        }

        .home-v2-timer,
        .home-v2-real-cta-wrap,
        .home-v2-real-cta,
        .home-v2-real-cta-button,
        .home-v2-note,
        .home-v2-advance-card {
            display: none !important;
        }

        /* DOPAMINA NO CLIQUE */
        .home-v2-post.is-game-clicked::after {
            content: "+25";
            position: absolute;
            z-index: 20;
            left: 50%;
            top: 44%;
            transform: translate(-50%, -50%);
            width: 112px;
            height: 112px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: radial-gradient(circle at 30% 20%, #fff 0%, #fef3c7 28%, #facc15 100%);
            color: #92400e;
            font-size: 34px;
            font-weight: 1000;
            letter-spacing: -.06em;
            box-shadow: 0 24px 42px rgba(250,204,21,.34);
            animation: gameRewardPop .78s ease both;
        }

        @keyframes gameRewardPop {
            0% { opacity: 0; transform: translate(-50%, -50%) scale(.55) rotate(-8deg); }
            35% { opacity: 1; transform: translate(-50%, -50%) scale(1.08) rotate(3deg); }
            100% { opacity: 0; transform: translate(-50%, -90%) scale(.86) rotate(0); }
        }

        @media (max-width: 430px) {
            .home-v2-page {
                padding-left: 13px !important;
                padding-right: 13px !important;
            }

            .home-v2-header,
            .home-v2-mission-card {
                max-width: 100% !important;
            }

            .home-v2-post {
                aspect-ratio: 1 / 1.08 !important;
            }
        }

    </style>
</head>
<body>

<main class="home-v2-page">
    <header class="home-v2-header">
        <div class="home-v2-avatar-frame">
            <img
                class="home-v2-avatar"
                src="<?= home_h($avatarUsuario) ?>"
                alt=""
                width="104"
                height="104"
                loading="eager"
            >
        </div>

        <div class="home-v2-greeting">
            <h1>Olá, <?= home_h($nomeExibicao) ?><span class="home-v2-wave">👋</span></h1>
            <div class="home-v2-level">
                <strong>Nível <?= number_format($nivelAtual, 0, ',', '.') ?></strong> · <?= home_h($nivelNome) ?>
            </div>
        </div>
    </header>

    <section
        class="home-v2-mission-card"
        id="cardMissaoAtual"
        data-missao-codigo="<?= home_h($missaoCard['codigo']) ?>"
        data-missao-post-id="<?= (int) $missaoCard['post_id'] ?>"
        data-missao-expira-em="<?= home_h((string) ($missaoCard['expira_em'] ?? '')) ?>"
    >
        <h2 class="home-v2-mission-title">
            <span class="home-v2-target">🎯</span>
            <span class="home-v2-mission-title-text">
                    <strong class="home-v24-title-line">
                        <span class="home-v24-social-icon">
                            <img src="<?= home_h($homeV24RedeIcon) ?>" alt="">
                        </span>
                        <span><?= home_h($homeV24Titulo) ?></span>
                    </strong>
                    <small><?= home_h($homeV24Instrucao) ?></small>
                </span>
        </h2>

        <a
            class="home-v2-post"
            href="<?= home_h((string) ($missaoCard['url_destino'] !== '' ? $missaoCard['url_destino'] : '/comunidade/missao.php')) ?>"
            <?= $missaoCard['tipo_acao'] === 'compartilhar' ? '' : 'target="_blank" rel="noopener"' ?>
            data-home-action="missao"
            data-missao-codigo="<?= home_h($missaoCard['codigo']) ?>"
            data-post-id="<?= (int) $missaoCard['post_id'] ?>"
            data-post-url="<?= home_h($missaoCard['url_destino']) ?>"
        >
            <div class="home-v2-post-real-bg">
                <img
                    src="<?= home_h($missaoImagemProxy) ?>"
                    alt=""
                    loading="eager"
                    onerror="this.style.display='none'; this.closest('.home-v2-post') && this.closest('.home-v2-post').classList.add('is-fallback');"
                >
            </div>

            <div class="home-v2-post-shade"></div>

            <div class="home-v2-post-head">
                <div class="home-v2-account">
                    <span class="home-v2-network-icon <?= $missaoCard['network'] === 'facebook' ? 'is-facebook' : ($missaoCard['tipo_acao'] === 'compartilhar' ? 'is-whatsapp' : '') ?>">
                        <?php if ($missaoCard['tipo_acao'] === 'compartilhar'): ?>
                            <i class="bi bi-whatsapp"></i>
                        <?php elseif ($missaoCard['network'] === 'facebook'): ?>
                            <i class="bi bi-facebook"></i>
                        <?php else: ?>
                            <i class="bi bi-instagram"></i>
                        <?php endif; ?>
                    </span>

                    <span class="home-v2-account-name">
                        <?= $missaoCard['network'] === 'facebook' ? 'facebook.com' : 'instagram.com' ?>
                    </span>

                    <span class="home-v2-check">●</span>
                </div>

                <div class="home-v2-dots">•••</div>
            </div>

            <div class="home-v2-post-foot">
                <div class="home-v2-actions">
                    <div class="home-v2-actions-left">
                        <span style="color: var(--home-red);">♥</span>
                        <span>♡</span>
                        <span>⌲</span>
                    </div>

                    <span>⌑</span>
                </div>

                <div class="home-v2-caption">
                    <strong><?= $missaoCard['network'] === 'facebook' ? 'facebook.com' : 'instagram.com' ?></strong>
                    <?= home_h(mb_strimwidth((string) ($missaoCard['caption'] ?: $missaoCard['descricao']), 0, 105, '...')) ?>
                    <?php if (!empty($missaoCard['pontos'])): ?>
                        <span>🔥 +<?= number_format((int) $missaoCard['pontos'], 0, ',', '.') ?> Pontos</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="home-v2-overlay">
                <div>
                    <div class="home-v24-action-orb">
                        <img src="<?= home_h($homeV24ActionIcon) ?>" alt="">
                    </div>
                    <h2><?= home_h($homeV24ActionLabel) ?></h2>
                    <p>Ganhe +<?= (int) $homeV24Pontos ?> pontos</p>
                    <div class="home-v2-hand">☝️</div>
                </div>
            </div>
        </a>

        <div class="home-v2-post-summary">
            <span>Post da missão</span>
            <p><?= home_h(mb_strimwidth($homeV24Caption, 0, 120, '...')) ?></p>
        </div>

        <div class="home-v2-timer" id="missaoTimer">
            ⏳ Tempo restante: <strong id="missaoTempoRestante">--:--</strong>
        </div>

        <div class="home-v2-real-cta-wrap" id="missaoAcoes">
            <?php if ($missaoCard['tipo_acao'] === 'compartilhar' && $temMissaoReal): ?>
                <button
                    type="button"
                    class="home-v2-real-cta-button <?= home_h($missaoCard['btn_class']) ?>"
                    id="btnMissaoAtualCompartilhar"
                    data-missao-codigo="<?= home_h($missaoCard['codigo']) ?>"
                    data-post-id="<?= (int) $missaoCard['post_id'] ?>"
                    data-post-url="<?= home_h($missaoCard['url_destino']) ?>"
                    data-home-action="missao"
                >
                    <i class="bi <?= home_h($missaoCard['icon']) ?>"></i>
                    <span id="missaoCtaLabel"><?= home_h($missaoCard['cta_label']) ?></span>
                </button>
            <?php else: ?>
                <a
                    href="<?= home_h((string) ($missaoCard['url_destino'] !== '' ? $missaoCard['url_destino'] : '/comunidade/missao.php')) ?>"
                    <?= $temMissaoReal ? 'target="_blank" rel="noopener"' : '' ?>
                    class="home-v2-real-cta <?= home_h($missaoCard['btn_class']) ?>"
                    id="missaoCtaLink"
                    data-missao-codigo="<?= home_h($missaoCard['codigo']) ?>"
                    data-post-id="<?= (int) $missaoCard['post_id'] ?>"
                    data-post-url="<?= home_h($missaoCard['url_destino']) ?>"
                    data-home-action="missao"
                >
                    <i class="bi <?= home_h($missaoCard['icon']) ?>" id="missaoCtaIcon"></i>
                    <span id="missaoCtaLabel"><?= home_h($missaoCard['cta_label']) ?></span>
                </a>
            <?php endif; ?>
        </div>


        <section class="home-v2-advance-card" aria-label="Seu próximo avanço">
            <div class="home-v2-advance-head">
                <span class="home-v2-advance-kicker">Seu próximo avanço</span>
                <span class="home-v2-advance-rank">🏆 <?= (int) $homeV19RankingPosicao ?>º</span>
            </div>

            <div class="home-v2-advance-main">
                <div>
                    <strong><?= home_h($homeV19ProgressLabel) ?></strong>
                    <small><?= (int) $homeV19XpAtual ?>/<?= (int) $homeV19XpProximo ?> XP acumulados</small>
                </div>
            </div>

            <div class="home-v2-advance-bar" aria-hidden="true">
                <span style="width: <?= (int) $homeV19XpPercent ?>%;"></span>
            </div>
        </section>

        <p class="home-v2-note">
            <?= home_h((string) ($missaoCard['narrativa'] ?: $missaoCard['descricao'])) ?>
        </p>
    </section>
</main>

<?php
$footerPath = __DIR__ . '/../assets/footer/menu.php';

if (is_file($footerPath)) {
    require_once $footerPath;
}
?>

<script>
const MISSAO_EXPIRA_EM = <?= json_encode($missaoExpiraEm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

const HOME_STATE_BOOT = <?= json_encode([
    'ok' => true,
    'xp_total' => (int) $xpTotal,
    'missao' => $missaoCard,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

const TENANT_FEATURE_WHATSAPP_SHARE_ENABLED = <?= $featureWhatsappShareEnabled ? 'true' : 'false' ?>;

document.addEventListener('DOMContentLoaded', function () {
    const timerEl = document.getElementById('missaoTempoRestante');
    const timerWrap = document.getElementById('missaoTimer');
    const cardMissao = document.getElementById('cardMissaoAtual');

    if (timerEl && timerWrap && cardMissao) {
        const raw = cardMissao.getAttribute('data-missao-expira-em') || MISSAO_EXPIRA_EM || '';

        if (raw) {
            const target = new Date(raw.replace(' ', 'T'));

            const tick = function () {
                const now = new Date();
                let diff = Math.floor((target.getTime() - now.getTime()) / 1000);

                if (isNaN(diff)) {
                    timerEl.textContent = '--:--';
                    return;
                }

                if (diff <= 0) {
                    timerEl.textContent = 'encerrando';
                    timerWrap.classList.add('is-expired');
                    return;
                }

                const horas = Math.floor(diff / 3600);
                const minutos = Math.floor((diff % 3600) / 60);
                const segundos = diff % 60;

                if (horas > 0) {
                    timerEl.textContent =
                        String(horas).padStart(2, '0') + ':' +
                        String(minutos).padStart(2, '0') + ':' +
                        String(segundos).padStart(2, '0');
                } else {
                    timerEl.textContent =
                        String(minutos).padStart(2, '0') + ':' +
                        String(segundos).padStart(2, '0');
                }

                if (diff <= 3600) {
                    timerWrap.classList.add('is-urgente');
                }
            };

            tick();
            setInterval(tick, 1000);
        }
    }

    document.querySelectorAll('[data-home-action="missao"]').forEach(function (el) {
        el.addEventListener('click', function () {
            localStorage.setItem('elab_home_last_action', 'missao');
        });
    });
});
</script>

<script src="<?= home_h($homeJsUrl) ?>"></script>

<script>
(function () {
    const missionCard = document.querySelector('.home-v2-post');
    if (!missionCard) return;

    missionCard.addEventListener('click', function () {
        missionCard.classList.remove('is-game-clicked');
        void missionCard.offsetWidth;
        missionCard.classList.add('is-game-clicked');

        if (navigator.vibrate) {
            navigator.vibrate([18, 25, 18]);
        }

        setTimeout(function () {
            missionCard.classList.remove('is-game-clicked');
        }, 850);
    }, { passive: true });
})();
</script>

</body>
</html>
