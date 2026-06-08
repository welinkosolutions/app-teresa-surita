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

/*
========================================
TENANT HELPERS
========================================
*/
if (!function_exists('tenant_resolver_dominio_atual')) {
    function tenant_resolver_dominio_atual(): string
    {
        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
        $host = preg_replace('/:\d+$/', '', $host);
        return $host;
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
        $tipo  = strtolower(trim((string) ($row['tipo'] ?? 'string')));

        $resultado = match ($tipo) {
            'int'   => (int) $valor,
            'float' => (float) $valor,
            'bool'  => in_array(strtolower((string) $valor), ['1', 'true', 'sim', 'yes', 'on'], true),
            'json'  => json_decode((string) $valor, true),
            default => (string) $valor,
        };

        $cache[$cacheKey] = $resultado;
        return $resultado;
    }
}

/*
========================================
TENANT STRICT
========================================
*/
if (!function_exists('tenantCfgStrict')) {
    function tenantCfgStrict(string $key): string
    {
        if (!function_exists('tenant_config_get')) {
            throw new RuntimeException('Função tenant_config_get() não está disponível.');
        }

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
        'app_name'             => tenantCfgStrict('branding.app_name'),
        'app_title'            => tenantCfgStrict('branding.app_title'),
        'app_title_login'      => tenantCfgStrict('branding.app_title_login'),
        'theme_color'          => tenantCfgStrict('branding.theme_color'),
        'logo_url'             => tenantCfgStrict('branding.logo_url'),
        'logo_footer_url'      => tenantCfgStrict('branding.logo_footer_url'),
        'favicon_url'          => tenantCfgStrict('branding.favicon_url'),
        'apple_touch_icon_url' => tenantCfgStrict('branding.apple_touch_icon_url'),

        'hero_image_url'       => tenantCfgStrict('branding.hero_image_url'),
        'hero_static_url'      => tenantCfgStrict('branding.hero_static_url'),
        'hero_happy_url'       => tenantCfgStrict('branding.hero_happy_url'),
        'hero_intro_url'       => tenantCfgStrict('branding.hero_intro_url'),
        'hero_bad_url'         => tenantCfgStrict('branding.hero_bad_url'),
        'post_placeholder_url' => tenantCfgStrict('branding.post_placeholder_url'),

        'primary_color'        => tenantCfgStrict('branding.primary_color'),
        'secondary_color'      => tenantCfgStrict('branding.secondary_color'),

        'bg_radial_1'          => tenantCfgStrict('branding.bg_radial_1'),
        'bg_radial_2'          => tenantCfgStrict('branding.bg_radial_2'),
        'bg_linear_start'      => tenantCfgStrict('branding.bg_linear_start'),
        'bg_linear_end'        => tenantCfgStrict('branding.bg_linear_end'),

        'notify_icon_color'    => tenantCfgStrict('branding.notify_icon_color'),
        'cta_bg_start'         => tenantCfgStrict('branding.cta_bg_start'),
        'cta_bg_end'           => tenantCfgStrict('branding.cta_bg_end'),
        'cta_text_color'       => tenantCfgStrict('branding.cta_text_color'),
        'cta_text_hover_color' => tenantCfgStrict('branding.cta_text_hover_color'),

        'page_bg_start'                => tenantCfgStrict('branding.page_bg_start'),
        'page_bg_end'                  => tenantCfgStrict('branding.page_bg_end'),

        'surface_soft_start'           => tenantCfgStrict('branding.surface_soft_start'),
        'surface_soft_mid'             => tenantCfgStrict('branding.surface_soft_mid'),
        'surface_soft_end'             => tenantCfgStrict('branding.surface_soft_end'),
        'surface_soft_border'          => tenantCfgStrict('branding.surface_soft_border'),
        'surface_soft_shadow'          => tenantCfgStrict('branding.surface_soft_shadow'),
        'surface_soft_title'           => tenantCfgStrict('branding.surface_soft_title'),
        'surface_soft_text'            => tenantCfgStrict('branding.surface_soft_text'),

         'toast_bg_start'      => tenantCfgStrict('branding.toast_bg_start'),
         'toast_bg_end'        => tenantCfgStrict('branding.toast_bg_end'),
         'toast_bg_accent'     => tenantCfgStrict('branding.toast_bg_accent'),


        'panel_dark_start'             => tenantCfgStrict('branding.panel_dark_start'),
        'panel_dark_end'               => tenantCfgStrict('branding.panel_dark_end'),

        'alert_bg_start'               => tenantCfgStrict('branding.alert_bg_start'),
        'alert_bg_end'                 => tenantCfgStrict('branding.alert_bg_end'),
        'alert_text'                   => tenantCfgStrict('branding.alert_text'),

        'narrative_bg_start'           => tenantCfgStrict('branding.narrative_bg_start'),
        'narrative_bg_end'             => tenantCfgStrict('branding.narrative_bg_end'),
        'narrative_border'             => tenantCfgStrict('branding.narrative_border'),
        'narrative_text'               => tenantCfgStrict('branding.narrative_text'),

        'timer_bg_start'               => tenantCfgStrict('branding.timer_bg_start'),
        'timer_bg_end'                 => tenantCfgStrict('branding.timer_bg_end'),
        'timer_text'                   => tenantCfgStrict('branding.timer_text'),
        'timer_strong'                 => tenantCfgStrict('branding.timer_strong'),
        'timer_border'                 => tenantCfgStrict('branding.timer_border'),

        'timer_urgent_bg_start'        => tenantCfgStrict('branding.timer_urgent_bg_start'),
        'timer_urgent_bg_end'          => tenantCfgStrict('branding.timer_urgent_bg_end'),
        'timer_urgent_text'            => tenantCfgStrict('branding.timer_urgent_text'),
        'timer_urgent_strong'          => tenantCfgStrict('branding.timer_urgent_strong'),
        'timer_urgent_border'          => tenantCfgStrict('branding.timer_urgent_border'),

        'timer_expired_bg_start'       => tenantCfgStrict('branding.timer_expired_bg_start'),
        'timer_expired_bg_end'         => tenantCfgStrict('branding.timer_expired_bg_end'),
        'timer_expired_text'           => tenantCfgStrict('branding.timer_expired_text'),
        'timer_expired_border'         => tenantCfgStrict('branding.timer_expired_border'),

        'highlight_bg_start'           => tenantCfgStrict('branding.highlight_bg_start'),
        'highlight_bg_end'             => tenantCfgStrict('branding.highlight_bg_end'),
        'highlight_text'               => tenantCfgStrict('branding.highlight_text'),

        'instagram_start'              => tenantCfgStrict('branding.instagram_start'),
        'instagram_mid'                => tenantCfgStrict('branding.instagram_mid'),
        'instagram_end'                => tenantCfgStrict('branding.instagram_end'),

        'facebook_start'               => tenantCfgStrict('branding.facebook_start'),
        'facebook_end'                 => tenantCfgStrict('branding.facebook_end'),

        'whatsapp_start'               => tenantCfgStrict('branding.whatsapp_start'),
        'whatsapp_end'                 => tenantCfgStrict('branding.whatsapp_end'),

        'danger_start'                 => tenantCfgStrict('branding.danger_start'),
        'danger_end'                   => tenantCfgStrict('branding.danger_end'),

        'monitor_app_bg_start'         => tenantCfgStrict('branding.monitor_app_bg_start'),
        'monitor_app_bg_mid'           => tenantCfgStrict('branding.monitor_app_bg_mid'),
        'monitor_app_bg_end'           => tenantCfgStrict('branding.monitor_app_bg_end'),
        'monitor_app_border'           => tenantCfgStrict('branding.monitor_app_border'),
        'monitor_app_glow_1'           => tenantCfgStrict('branding.monitor_app_glow_1'),
        'monitor_app_glow_2'           => tenantCfgStrict('branding.monitor_app_glow_2'),

        'kpi_alcance_start'            => tenantCfgStrict('branding.kpi_alcance_start'),
        'kpi_alcance_end'              => tenantCfgStrict('branding.kpi_alcance_end'),
        'kpi_alcance_text'             => tenantCfgStrict('branding.kpi_alcance_text'),

        'kpi_engajamento_start'        => tenantCfgStrict('branding.kpi_engajamento_start'),
        'kpi_engajamento_end'          => tenantCfgStrict('branding.kpi_engajamento_end'),
        'kpi_engajamento_text'         => tenantCfgStrict('branding.kpi_engajamento_text'),

        'kpi_visualizacoes_start'      => tenantCfgStrict('branding.kpi_visualizacoes_start'),
        'kpi_visualizacoes_end'        => tenantCfgStrict('branding.kpi_visualizacoes_end'),
        'kpi_visualizacoes_text'       => tenantCfgStrict('branding.kpi_visualizacoes_text'),

        'kpi_taxa_start'               => tenantCfgStrict('branding.kpi_taxa_start'),
        'kpi_taxa_end'                 => tenantCfgStrict('branding.kpi_taxa_end'),
        'kpi_taxa_text'                => tenantCfgStrict('branding.kpi_taxa_text'),

        'kpi_interacoes_start'         => tenantCfgStrict('branding.kpi_interacoes_start'),
        'kpi_interacoes_end'           => tenantCfgStrict('branding.kpi_interacoes_end'),
        'kpi_interacoes_text'          => tenantCfgStrict('branding.kpi_interacoes_text'),

        'kpi_curtidas_start'           => tenantCfgStrict('branding.kpi_curtidas_start'),
        'kpi_curtidas_end'             => tenantCfgStrict('branding.kpi_curtidas_end'),
        'kpi_curtidas_text'            => tenantCfgStrict('branding.kpi_curtidas_text'),

        'kpi_comentarios_start'        => tenantCfgStrict('branding.kpi_comentarios_start'),
        'kpi_comentarios_end'          => tenantCfgStrict('branding.kpi_comentarios_end'),
        'kpi_comentarios_text'         => tenantCfgStrict('branding.kpi_comentarios_text'),

        'kpi_compartilhamentos_start'  => tenantCfgStrict('branding.kpi_compartilhamentos_start'),
        'kpi_compartilhamentos_end'    => tenantCfgStrict('branding.kpi_compartilhamentos_end'),
        'kpi_compartilhamentos_text'   => tenantCfgStrict('branding.kpi_compartilhamentos_text'),

        'monitor_app_kpi_comentarios_start' => tenantCfgStrict('branding.monitor_app_kpi_comentarios_start'),
        'monitor_app_kpi_comentarios_end'   => tenantCfgStrict('branding.monitor_app_kpi_comentarios_end'),

        'monitor_app_kpi_interacoes_start'  => tenantCfgStrict('branding.monitor_app_kpi_interacoes_start'),
        'monitor_app_kpi_interacoes_end'    => tenantCfgStrict('branding.monitor_app_kpi_interacoes_end'),

        'monitor_app_kpi_impacto_start'     => tenantCfgStrict('branding.monitor_app_kpi_impacto_start'),
        'monitor_app_kpi_impacto_end'       => tenantCfgStrict('branding.monitor_app_kpi_impacto_end'),

        'monitor_app_kpi_usuarios_start'    => tenantCfgStrict('branding.monitor_app_kpi_usuarios_start'),
        'monitor_app_kpi_usuarios_end'      => tenantCfgStrict('branding.monitor_app_kpi_usuarios_end'),
        
        'module_social_competitors_label'   => tenantCfgStrict('module.social_competitors_label'),
'module_social_monitor_full_label'  => tenantCfgStrict('module.social_monitor_full_label'),

'module_demands_list_label'         => tenantCfgStrict('module.demands_list_label'),
'module_demands_new_label'          => tenantCfgStrict('module.demands_new_label'),
'module_team_label'                 => tenantCfgStrict('module.team_label'),
'module_general_list_label'         => tenantCfgStrict('module.general_list_label'),
'module_team_performance_label'     => tenantCfgStrict('module.team_performance_label'),

        'footer_text'          => tenantCfgStrict('branding.footer_text'),
        'footer_link_label'    => tenantCfgStrict('branding.footer_link_label'),
        'footer_link_url'      => tenantCfgStrict('branding.footer_link_url'),

        'tenant_app_url'       => tenantCfgStrict('tenant.app_url'),
        'tenant_login_url'     => tenantCfgStrict('tenant.login_url'),
        'tenant_crm_url'       => tenantCfgStrict('tenant.crm_url'),
        'tenant_base_domain'   => tenantCfgStrict('tenant.base_domain'),

        'landing_notify_typing'=> tenantCfgStrict('landing.notify_title_typing'),
        'landing_notify_final' => tenantCfgStrict('landing.notify_title_final'),
        'landing_notify_text'  => tenantCfgStrict('landing.notify_text_final'),

        'politico_nome'        => tenantCfgStrict('politico.nome'),
        'politico_cargo'       => tenantCfgStrict('politico.cargo'),
        
'feature_instagram_enabled'      => tenant_config_get('feature.instagram_enabled', true),
'feature_facebook_enabled'       => tenant_config_get('feature.facebook_enabled', true),
'feature_whatsapp_share_enabled' => tenant_config_get('feature.whatsapp_share_enabled', true),
'feature_social_monitor_enabled' => tenant_config_get('feature.social_monitor_enabled', true),
'feature_demands_enabled'        => tenant_config_get('feature.demands_enabled', true),
'feature_invite_enabled'         => tenant_config_get('feature.invite_enabled', true),
'feature_ranking_enabled'        => tenant_config_get('feature.ranking_enabled', true),
'feature_gestao_pessoas_enabled' => tenant_config_get('feature.gestao_pessoas_enabled', true),

'home_show_engagement_card'      => tenant_config_get('home.show_engagement_card', true),
'home_show_mission_card'         => tenant_config_get('home.show_mission_card', true),
'home_show_invite_card'          => tenant_config_get('home.show_invite_card', true),
'home_show_share_card'           => tenant_config_get('home.show_share_card', true),
'home_show_ranking_card'         => tenant_config_get('home.show_ranking_card', true),
'home_show_social_monitor_card'  => tenant_config_get('home.show_social_monitor_card', true),
'home_show_demands_card'         => tenant_config_get('home.show_demands_card', true),
'home_show_gestao_pessoas_card'  => tenant_config_get('home.show_gestao_pessoas_card', true),


'toast_default_chip'             => tenantCfgStrict('toast.default_chip'),
'toast_default_title'            => tenantCfgStrict('toast.default_title'),
'toast_default_text'             => tenantCfgStrict('toast.default_text'),
'toast_default_button'           => tenantCfgStrict('toast.default_button'),

'toast_mission_chip'             => tenantCfgStrict('toast.mission_chip'),
'toast_mission_title'            => tenantCfgStrict('toast.mission_title'),
'toast_mission_text'             => tenantCfgStrict('toast.mission_text'),
'toast_mission_button'           => tenantCfgStrict('toast.mission_button'),

'toast_pending_chip'             => tenantCfgStrict('toast.pending_chip'),
'toast_pending_title'            => tenantCfgStrict('toast.pending_title'),
'toast_pending_text'             => tenantCfgStrict('toast.pending_text'),
'toast_pending_button'           => tenantCfgStrict('toast.pending_button'),

'toast_invite_chip'              => tenantCfgStrict('toast.invite_chip'),
'toast_invite_title'             => tenantCfgStrict('toast.invite_title'),
'toast_invite_text'              => tenantCfgStrict('toast.invite_text'),
'toast_invite_button'            => tenantCfgStrict('toast.invite_button'),

'missao_card_title_comentario'   => tenantCfgStrict('missao.card_title_comentario'),
'missao_card_title_post'         => tenantCfgStrict('missao.card_title_post'),
'missao_card_title_compartilhar' => tenantCfgStrict('missao.card_title_compartilhar'),
'missao_card_title_video'        => tenantCfgStrict('missao.card_title_video'),

'missao_cta_comentario'          => tenantCfgStrict('missao.cta_comentario'),
'missao_cta_post'                => tenantCfgStrict('missao.cta_post'),
'missao_cta_compartilhar'        => tenantCfgStrict('missao.cta_compartilhar'),
'missao_cta_video'               => tenantCfgStrict('missao.cta_video'),

'missao_urgency_comment'         => tenantCfgStrict('missao.urgency_comment'),
'missao_urgency_post'            => tenantCfgStrict('missao.urgency_post'),
'missao_urgency_share'           => tenantCfgStrict('missao.urgency_share'),
'missao_urgency_video'           => tenantCfgStrict('missao.urgency_video'),

'missao_narrative_comment'       => tenantCfgStrict('missao.narrative_comment'),
'missao_narrative_post'          => tenantCfgStrict('missao.narrative_post'),
'missao_narrative_share'         => tenantCfgStrict('missao.narrative_share'),
'missao_narrative_video'         => tenantCfgStrict('missao.narrative_video'),

'missao_default_description_prefix' => tenantCfgStrict('missao.default_description_prefix'),
'missao_default_description_empty'  => tenantCfgStrict('missao.default_description_empty'),

'invite_share_title'             => tenantCfgStrict('invite.share_title'),
'invite_share_intro'             => tenantCfgStrict('invite.share_intro'),
'invite_share_short_text'        => tenantCfgStrict('invite.share_short_text'),
'invite_share_whatsapp_text'     => tenantCfgStrict('invite.share_whatsapp_text'),
'invite_share_facebook_text'     => tenantCfgStrict('invite.share_facebook_text'),
'invite_share_instagram_text'    => tenantCfgStrict('invite.share_instagram_text'),

'module_social_monitor_label'    => tenantCfgStrict('module.social_monitor_label'),
'module_demands_label'           => tenantCfgStrict('module.demands_label'),
'module_support_label'           => tenantCfgStrict('module.support_label'),
'module_ranking_label'           => tenantCfgStrict('module.ranking_label'),
'module_home_label'              => tenantCfgStrict('module.home_label'),
'module_profile_label'           => tenantCfgStrict('module.profile_label'),
'module_logout_label'            => tenantCfgStrict('module.logout_label'),

'module_social_monitor_url'      => tenantCfgStrict('module.social_monitor_url'),
'module_social_competitors_url'  => tenantCfgStrict('module.social_competitors_url'),
'module_demands_list_url'        => tenantCfgStrict('module.demands_list_url'),
'module_demands_new_url'         => tenantCfgStrict('module.demands_new_url'),
'module_team_url'                => tenantCfgStrict('module.team_url'),
'module_general_list_url'        => tenantCfgStrict('module.general_list_url'),
'module_ranking_url'             => tenantCfgStrict('module.ranking_url'),
'module_support_url'             => tenantCfgStrict('module.support_url'),
'module_profile_url'             => tenantCfgStrict('module.profile_url'),
'module_logout_url'              => tenantCfgStrict('module.logout_url'),

    ];
} catch (Throwable $e) {
    http_response_code(500);
    die('Tenant inválido ou incompleto: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$featureInstagramEnabled      = (bool) $tenantBrand['feature_instagram_enabled'];
$featureFacebookEnabled       = (bool) $tenantBrand['feature_facebook_enabled'];
$featureWhatsappShareEnabled  = (bool) $tenantBrand['feature_whatsapp_share_enabled'];
$featureSocialMonitorEnabled  = (bool) $tenantBrand['feature_social_monitor_enabled'];
$featureDemandsEnabled        = (bool) $tenantBrand['feature_demands_enabled'];
$featureInviteEnabled         = (bool) $tenantBrand['feature_invite_enabled'];
$featureRankingEnabled        = (bool) $tenantBrand['feature_ranking_enabled'];

$showEngagementCard           = (bool) $tenantBrand['home_show_engagement_card'];
$showMissionCard              = (bool) $tenantBrand['home_show_mission_card'];
$showInviteCard               = (bool) $tenantBrand['home_show_invite_card'];
$showShareCard                = (bool) $tenantBrand['home_show_share_card'];
$showRankingCard              = (bool) $tenantBrand['home_show_ranking_card'];
$showSocialMonitorCard        = (bool) $tenantBrand['home_show_social_monitor_card'];
$showDemandsCard              = (bool) $tenantBrand['home_show_demands_card'];

$featureGestaoPessoasEnabled = (bool) $tenantBrand['feature_gestao_pessoas_enabled'];
$showGestaoPessoasCard       = (bool) $tenantBrand['home_show_gestao_pessoas_card'];

/*
========================================
URLs FIXAS DO MÓDULO
========================================
*/
$manifestUrl = '/manifest.php';
$homeCssUrl = '/assets/css/home.css?v=76';
$homeJsUrl = '/assets/js/home.js?v=68';

/*
========================================
USUÁRIO
========================================
*/
$stmt = $pdo->prepare("
SELECT
    nome,
    apelido,
    chamar_por,
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
WHERE id = ? AND status = 'ativo'
LIMIT 1
");
$stmt->execute([$pessoa_id]);
$pessoa = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

/*
========================================
HELPER ACESSO ESPECIAL
========================================
*/
if (!function_exists('pessoaTemAcessoEspecial')) {
    function pessoaTemAcessoEspecial(PDO $pdo, int $tenantId, int $pessoaId, string $recurso): bool
    {
        if ($tenantId <= 0 || $pessoaId <= 0 || trim($recurso) === '') {
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT 1
            FROM acessos_especiais
            WHERE tenant_cliente_id = ?
              AND pessoa_id = ?
              AND recurso = ?
              AND status = 'ativo'
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $pessoaId, $recurso]);

        return (bool) $stmt->fetchColumn();
    }
}

/* ===== FIM PARTE 1/5 ===== */

/*
========================================
HELPER INSTAGRAM
========================================
*/
if (!function_exists('normalizarInstagramUsername')) {
    function normalizarInstagramUsername(string $valor): string
    {
        $valor = trim($valor);

        if ($valor === '') {
            return '';
        }

        $valor = html_entity_decode($valor, ENT_QUOTES, 'UTF-8');
        $valor = trim($valor);

        if (str_starts_with($valor, '@')) {
            $valor = substr($valor, 1);
        }

        if (preg_match('~(?:https?://)?(?:www\.)?instagram\.com/([^/?#]+)/?~i', $valor, $m)) {
            $valor = $m[1];
        }

        $valor = preg_replace('~\?.*$~', '', $valor);
        $valor = preg_replace('~/+$~', '', $valor);
        $valor = trim($valor);
        $valor = strtolower($valor);

        $rotasInvalidas = [
            'p', 'reel', 'reels', 'stories', 'explore', 'accounts',
            'direct', 'tv', 'about', 'developer'
        ];

        if (in_array($valor, $rotasInvalidas, true)) {
            return '';
        }

        $valor = preg_replace('/[^a-z0-9._]/', '', $valor);
        $valor = preg_replace('/\.{2,}/', '.', $valor);

        return trim($valor, '.');
    }
}

/*
========================================
HELPER FACEBOOK
========================================
*/
if (!function_exists('normalizarFacebookUsername')) {
    function normalizarFacebookUsername(string $valor): string
    {
        $valor = trim($valor);

        if ($valor === '') {
            return '';
        }

        $valor = html_entity_decode($valor, ENT_QUOTES, 'UTF-8');
        $valor = trim($valor);

        if (str_starts_with($valor, '@')) {
            $valor = substr($valor, 1);
        }

        if (preg_match('~(?:https?://)?(?:www\.)?facebook\.com/([^?#]+)~i', $valor, $m)) {
            $valor = trim($m[1], '/');
        }

        $valor = preg_replace('~\?.*$~', '', $valor);
        $valor = preg_replace('~/+$~', '', $valor);
        $valor = trim($valor);
        $valor = strtolower($valor);

        $path = trim($valor, '/');
        $segmentos = $path !== '' ? explode('/', $path) : [];
        $primeiro = $segmentos[0] ?? '';

        $rotasBloqueadas = [
            'share', 'sharer', 'share.php', 'story.php', 'photo', 'photos',
            'watch', 'reel', 'reels', 'posts', 'groups', 'events',
            'marketplace', 'gaming', 'hashtag', 'plugins', 'help',
            'pages', 'profile.php'
        ];

        if ($primeiro === '' || in_array($primeiro, $rotasBloqueadas, true)) {
            return '';
        }

        if (count($segmentos) > 1) {
            return '';
        }

        $valor = preg_replace('/[^a-z0-9._-]/', '', $primeiro);
        $valor = preg_replace('/\.{2,}/', '.', $valor);

        return trim($valor, '.');
    }
}

/*
========================================
SALVAR INSTAGRAM MANUAL
========================================
*/
$instagramErro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_instagram'])) {
    $instagramBruto = trim((string) ($_POST['instagram_input'] ?? ''));
    $instagramUser = normalizarInstagramUsername($instagramBruto);

    if ($instagramUser === '') {
        $instagramErro = 'Digite um Instagram válido. Use @usuario, usuario ou o link do seu perfil.';
    } elseif (strlen($instagramUser) < 3 || strlen($instagramUser) > 30) {
        $instagramErro = 'O usuário do Instagram precisa ter entre 3 e 30 caracteres.';
    } else {
        $instagramUrl = 'https://www.instagram.com/' . $instagramUser;

        $stmt = $pdo->prepare("
            UPDATE pessoas
            SET instagram = ?,
                instagram_username = ?,
                instagram_user = ?,
                instagram_confirmado = 'nao',
                usa_instagram = 'sim',
                atualizado_em = NOW()
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([
            $instagramUrl,
            $instagramUser,
            $instagramUser,
            $pessoa_id
        ]);

        header('Location: /interno/admin.php?instagram_ok=1');
        exit;
    }
}

/*
========================================
SALVAR FACEBOOK MANUAL
========================================
*/
$facebookErro = '';
$facebookSucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_facebook'])) {
    $facebookBruto = trim((string) ($_POST['facebook_input'] ?? ''));
    $facebookUser = normalizarFacebookUsername($facebookBruto);

    if ($facebookUser === '') {
        $facebookErro = 'Digite um Facebook válido. Use seu @usuario, usuario ou o link do seu perfil. Links com /share/ não funcionam.';
    } elseif (strlen($facebookUser) < 3 || strlen($facebookUser) > 80) {
        $facebookErro = 'O usuário do Facebook precisa ter entre 3 e 80 caracteres.';
    } else {
        $facebookUrl = 'https://www.facebook.com/' . $facebookUser;

        $stmt = $pdo->prepare("
            UPDATE pessoas
            SET facebook = ?,
                facebook_username = ?,
                facebook_confirmado = 'nao',
                usa_facebook = 'sim',
                atualizado_em = NOW()
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([
            $facebookUrl,
            $facebookUser,
            $pessoa_id
        ]);

        header('Location: /interno/admin.php?facebook_ok=1');
        exit;
    }
}

/*
========================================
MARCAR NÃO USA INSTAGRAM
========================================
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nao_uso_instagram'])) {
    $stmt = $pdo->prepare("
        UPDATE pessoas
        SET usa_instagram = 'nao',
            instagram = NULL,
            instagram_username = NULL,
            instagram_confirmado = 'nao',
            instagram_user = NULL,
            atualizado_em = NOW()
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$pessoa_id]);

    header('Location: /interno/admin.php');
    exit;
}

/*
========================================
MARCAR NÃO USA FACEBOOK
========================================
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nao_uso_facebook'])) {
    $stmt = $pdo->prepare("
        UPDATE pessoas
        SET usa_facebook = 'nao',
            facebook = NULL,
            facebook_username = NULL,
            facebook_confirmado = 'nao',
            facebook_user_id = NULL,
            atualizado_em = NOW()
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$pessoa_id]);

    header('Location: /interno/admin.php');
    exit;
}

/*
========================================
REDE DE INDICAÇÕES
========================================
*/
$stmt = $pdo->prepare("
SELECT COUNT(*)
FROM rede_indicacoes
WHERE indicador_id = ?
");
$stmt->execute([$pessoa_id]);
$totalRede = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
SELECT COUNT(*)
FROM rede_indicacoes
WHERE indicador_id = ?
AND nivel = 1
");
$stmt->execute([$pessoa_id]);
$convitesDiretos = (int) $stmt->fetchColumn();

/*
========================================
XP POSSÍVEL
========================================
*/
$xpConvitePossivel = 40 + 35 + 30 + 25 + 15 + 10 + 5;

/*
========================================
NOME EXIBIÇÃO
========================================
*/
$nomeCompleto = trim((string) ($pessoa['nome'] ?? ''));
$nomeExibicao = '';

if (($pessoa['chamar_por'] ?? '') === 'apelido' && !empty($pessoa['apelido'])) {
    $nomeExibicao = trim((string) $pessoa['apelido']);
} else {
    $partes = preg_split('/\s+/', $nomeCompleto);
    $nomeExibicao = implode(' ', array_slice($partes ?: [], 0, 2));
}

if ($nomeExibicao === '') {
    $nomeExibicao = 'Usuário';
}

$usaInstagram = (($pessoa['usa_instagram'] ?? 'sim') !== 'nao');
$usaFacebook  = (($pessoa['usa_facebook'] ?? 'sim') !== 'nao');

$temInstagram = !empty($pessoa['instagram_username']);
$temFacebook  = !empty($pessoa['facebook_username']);

$precisaInstagram = $featureInstagramEnabled && $usaInstagram && !$temInstagram;
$instagramSalvoAgora = isset($_GET['instagram_ok']) && $_GET['instagram_ok'] == '1';

$precisaFacebook  = $featureFacebookEnabled && $usaFacebook && !$temFacebook;
$facebookSalvoAgora = isset($_GET['facebook_ok']) && $_GET['facebook_ok'] == '1';

$temRedeElegivelMissao =
    ($featureInstagramEnabled && $usaInstagram && $temInstagram) ||
    ($featureFacebookEnabled && $usaFacebook && $temFacebook);

$perfil = trim((string) ($pessoa['perfil'] ?? 'pessoa'));
$temAcessoLideranca = in_array($perfil, ['lider', 'admin'], true);

/*
========================================
ACESSO MONITOR DE REDES
========================================
*/
try {
    $tenantIdAtual = tenant_resolver_id_atual($pdo);

    $stmt = $pdo->prepare("
        SELECT 1
        FROM acessos_especiais
        WHERE tenant_cliente_id = ?
          AND pessoa_id = ?
          AND recurso = 'monitor_redes'
          AND status = 'ativo'
        LIMIT 1
    ");
    $stmt->execute([$tenantIdAtual, $pessoa_id]);

    $temAcessoMonitorRedes = (bool) $stmt->fetchColumn();
} catch (Throwable $e) {
    error_log('[ACESSO_MONITOR_REDES] ' . $e->getMessage());
    $temAcessoMonitorRedes = false;
}

if (!$featureSocialMonitorEnabled) {
    $temAcessoMonitorRedes = false;
}

/*
========================================
ACESSO DEMANDAS
========================================
*/
try {
    $tenantIdAtual = tenant_resolver_id_atual($pdo);
    $temAcessoDemandas = pessoaTemAcessoEspecial(
        $pdo,
        $tenantIdAtual,
        $pessoa_id,
        'demandas'
    );
} catch (Throwable $e) {
    error_log('[ACESSO_DEMANDAS] ' . $e->getMessage());
    $temAcessoDemandas = false;
}

if (!$featureDemandsEnabled) {
    $temAcessoDemandas = false;
}

/*
========================================
ACESSO GESTÃO DE PESSOAS
========================================
*/
try {
    $tenantIdAtual = tenant_resolver_id_atual($pdo);
    $temAcessoGestaoPessoas = pessoaTemAcessoEspecial(
        $pdo,
        $tenantIdAtual,
        $pessoa_id,
        'gestao_pessoas'
    );
} catch (Throwable $e) {
    error_log('[ACESSO_GESTAO_PESSOAS] ' . $e->getMessage());
    $temAcessoGestaoPessoas = false;
}

if (!$featureGestaoPessoasEnabled) {
    $temAcessoGestaoPessoas = false;
}


/*
========================================
ATIVAÇÃO DA CONTA / CONVITES
========================================
*/
$statusValidacao = strtolower(trim((string) ($pessoa['status_validacao'] ?? 'validado')));
$contaAguardandoAtivacao = !in_array($statusValidacao, ['validado', 'aprovado'], true);

$pendenciasAtivacao = 0;
try {
    $pendenciasAtivacao = invitePendentesAprovacao($pdo, $pessoa_id);
} catch (Throwable $e) {
    error_log('[DASHBOARD_INVITES] ' . $e->getMessage());
    $pendenciasAtivacao = 0;
}

/*
========================================
ESTADO GAMIFICAÇÃO
========================================
*/
$stmt = $pdo->prepare("
SELECT chave, valor
FROM gamificacao_estado_usuario
WHERE pessoa_id = ?
");
$stmt->execute([$pessoa_id]);

$estado = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $estado[(string) $row['chave']] = (string) $row['valor'];
}

$ultimaAcaoUsuario = trim((string) ($estado['ultima_acao_home'] ?? ''));

/*
========================================
CONSUMIR EVENTOS (EVITA REPETIÇÃO)
========================================
*/
$eventosConsumir = [];

if (!empty($estado['xp_recente'])) {
    $eventosConsumir[] = 'xp_recente';
}
if (!empty($estado['subiu_ranking']) && $estado['subiu_ranking'] === 'sim') {
    $eventosConsumir[] = 'subiu_ranking';
}
if (!empty($estado['nova_missao']) && $estado['nova_missao'] === 'sim') {
    $eventosConsumir[] = 'nova_missao';
}
if (!empty($estado['combo_engajamento']) && $estado['combo_engajamento'] === 'sim') {
    $eventosConsumir[] = 'combo_engajamento';
}
if (!empty($estado['streak_comentarios']) && $estado['streak_comentarios'] === 'sim') {
    $eventosConsumir[] = 'streak_comentarios';
}

if ($eventosConsumir) {
    $in = implode(',', array_fill(0, count($eventosConsumir), '?'));

    $sql = "
    DELETE FROM gamificacao_estado_usuario
    WHERE pessoa_id = ?
    AND chave IN ($in)
    ";

    $stmt = $pdo->prepare($sql);
    $params = array_merge([$pessoa_id], $eventosConsumir);
    $stmt->execute($params);
}

$eventoTipo = null;
$eventoXP = 0;

if (!empty($estado['subiu_ranking']) && $estado['subiu_ranking'] === 'sim') {
    $eventoTipo = 'ranking';
} elseif (!empty($estado['combo_engajamento']) && $estado['combo_engajamento'] === 'sim') {
    $eventoTipo = 'combo';
} elseif (!empty($estado['streak_comentarios']) && $estado['streak_comentarios'] === 'sim') {
    $eventoTipo = 'streak';
} elseif (!empty($estado['xp_recente'])) {
    $eventoTipo = 'xp';
    $eventoXP = (int) $estado['xp_recente'];
} elseif (!empty($estado['nova_missao']) && $estado['nova_missao'] === 'sim') {
    $eventoTipo = 'missao';
}

/* ===== FIM PARTE 2/5 ===== */

/*
========================================
XP DO USUÁRIO (OFICIAL)
========================================
*/
$xpTotal = (int) ($pessoa['pontos'] ?? 0);

/*
========================================
POSIÇÃO NO RANKING
========================================
*/
$stmt = $pdo->prepare("
SELECT posicao
FROM vw_ranking_geral
WHERE pessoa_id = ?
LIMIT 1
");
$stmt->execute([$pessoa_id]);
$posicaoRanking = (int) ($stmt->fetchColumn() ?? 0);

/*
========================================
RANKING HOME
========================================
*/
$rankingHomeAba = trim((string) ($_GET['ranking_aba'] ?? 'engajamento'));
$rankingHome = elabBuscarRanking($pdo, $rankingHomeAba, $pessoa_id);

/*
========================================
MISSÃO NOVA (CORE/MISSAO)
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

        if (!$missaoPermitida) {
            $missaoAtual = null;
        }
    }
}

/*
========================================
SAUDAÇÃO
========================================
*/
$hora = (int) date('H');
$saudacao = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');

/*
========================================
VERIFICAR INATIVIDADE
========================================
*/
$inativo = false;
$nivelInatividade = 0;

$stmt = $pdo->prepare("
SELECT MAX(criado_em)
FROM gamificacao_xp
WHERE pessoa_id = ?
");
$stmt->execute([$pessoa_id]);
$ultimoXP = $stmt->fetchColumn();

if (!$ultimoXP) {
    $inativo = true;
    $nivelInatividade = 3;
} else {
    $horas = (time() - strtotime((string) $ultimoXP)) / 3600;

    if ($horas > 168) {
        $inativo = true;
        $nivelInatividade = 3;
    } elseif ($horas > 72) {
        $inativo = true;
        $nivelInatividade = 2;
    } elseif ($horas > 24) {
        $inativo = true;
        $nivelInatividade = 1;
    }
}

if ($inativo) {
    $eventoTipo = 'inatividade';
}

/*
========================================
MENSAGEM DE INATIVIDADE
========================================
*/
$toastInatividadeTitulo = '';
$toastInatividadeTexto = '';

if ($nivelInatividade === 1) {
    $msgs = [
        ['🚨 Alerta de Pontos Dando Sopa!', 'Alguém falou +20 Pontos? Corre lá, deixa seu comentário e resgate sua recompensa antes que expire!'],
        ['🔥 O Engajamento Tá Fervendo!', 'A galera tá destruindo nos comentários agora! Entra lá, deixa o seu e garanta +20 Pontos!'],
        ['💬 Seu Comentário Vale Ouro!', 'A comunidade tá te chamando pro combate! Manda seu comentário agora e impulsione o post!']
    ];
} elseif ($nivelInatividade === 2) {
    $msgs = [
        ['⚠️ PERIGO: Você tá ficando pra trás!', 'Sua barrinha de atividade caiu! Volta pro jogo agora, curte e comenta no post para recuperar o ritmo!'],
        ['🚀 A Tropa Tá Avançando!', 'Não perde o embalo! Seu like e comentário são a munição que a gente precisa agora. Vem!'],
        ['👀 Chamado de Emergência!', 'A ' . $tenantBrand['politico_nome'] . ' precisa da sua energia urgente! Destrói no comentário e mostra a sua força!']
    ];
} else {
    $msgs = [
        ['😱 STATUS: DESAPARECIDO!', 'Cadê você?! O jogo não parou! Volte com os dois pés na porta, comente agora e fature +20 Pontos!'],
        ['🔥 HORA DA REDENÇÃO!', 'As missões estão acumulando! Entra de cabeça, faz o post estourar e volta pro topo da tabela!'],
        ['💙 A Força Tarefa Não Para!', 'Mostre que você ainda tá no jogo! Engaje agora, apoie ' . $tenantBrand['politico_nome'] . ' e faça a diferença em segundos!']
    ];
}

$msgEscolhida = $msgs[array_rand($msgs)];
$toastInatividadeTitulo = $msgEscolhida[0];
$toastInatividadeTexto = $msgEscolhida[1];

/*
========================================
HERO DINÂMICO TENANT
========================================
*/
$hero = $tenantBrand['hero_static_url'];
$heroAnimSrc = '';

if ($inativo) {
    $heroAnimSrc = $tenantBrand['hero_bad_url'];
} elseif (
    $eventoTipo === 'ranking' ||
    $eventoTipo === 'xp' ||
    $eventoTipo === 'combo' ||
    $eventoTipo === 'streak'
) {
    $heroAnimSrc = $tenantBrand['hero_happy_url'];
} elseif ($eventoTipo === 'missao') {
    $heroAnimSrc = $tenantBrand['hero_intro_url'];
}

/*
========================================
CONVITES / APROVAÇÕES
NOVO MODELO
========================================
*/
$stmt = $pdo->prepare("
SELECT COUNT(*)
FROM convites_aprovacoes
WHERE convidador_id = ?
");
$stmt->execute([$pessoa_id]);
$totalConvites = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
SELECT COUNT(*)
FROM convites_aprovacoes
WHERE convidador_id = ?
AND status = 'aprovado'
");
$stmt->execute([$pessoa_id]);
$convitesConvertidos = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
SELECT COUNT(*)
FROM convites_aprovacoes
WHERE convidador_id = ?
AND status = 'pendente'
");
$stmt->execute([$pessoa_id]);
$convitesPendentes = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
SELECT
    ca.convidado_id,
    ca.aprovado_em,
    ca.criado_em,
    p.nome,
    p.apelido,
    p.chamar_por
FROM convites_aprovacoes ca
LEFT JOIN pessoas p
ON p.id = ca.convidado_id
WHERE ca.convidador_id = ?
AND ca.status = 'aprovado'
ORDER BY COALESCE(ca.aprovado_em, ca.criado_em) DESC
LIMIT 1
");
$stmt->execute([$pessoa_id]);

$ultimoCadastro = $stmt->fetch(PDO::FETCH_ASSOC);
$nomeUltimo = null;

if ($ultimoCadastro) {
    if (($ultimoCadastro['chamar_por'] ?? '') === 'apelido' && !empty($ultimoCadastro['apelido'])) {
        $nomeUltimo = trim((string) $ultimoCadastro['apelido']);
    } else {
        $nomeCompletoUltimo = trim((string) ($ultimoCadastro['nome'] ?? ''));
        $partes = preg_split('/\s+/', $nomeCompletoUltimo);
        $nomeUltimo = implode(' ', array_slice($partes ?: [], 0, 2));
    }
}

/*
========================================
XP DE CONVITES NA SEMANA
========================================
*/
$stmt = $pdo->prepare("
SELECT COALESCE(SUM(pontos_final), 0)
FROM gamificacao_pontos_temporada
WHERE pessoa_id = ?
AND origem_tipo IN ('direto','nivel1','nivel2','nivel3','nivel4')
AND criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stmt->execute([$pessoa_id]);
$xpConvitesSemana = (int) ($stmt->fetchColumn() ?? 0);

/*
========================================
MISSÃO ATUAL - DADOS DE EXIBIÇÃO
========================================
*/
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

/* ===== FIM PARTE 3/5 ===== */

/*
========================================
SMART MESSAGE ENGINE
========================================
*/
$msgTitulo = '💥 BORA PRO COMBATE!';
$msgTexto  = 'A comunidade precisa da sua força AGORA pra bater as metas do dia.';

if ($pendenciasAtivacao >= 3) {
    $msgTitulo = '🚨 GARGALO NA REDE!';
    $msgTexto  = "Você tem {$pendenciasAtivacao} cadastro" . ($pendenciasAtivacao > 1 ? 's' : '') . " bloqueado" . ($pendenciasAtivacao > 1 ? 's' : '') . "! Libere geral agora para não perder os bônus.";
} elseif (!empty($missaoCard)) {
    $msgTitulo = '🎯 ALVO NA MIRA!';
    $msgTexto  = 'O cronômetro tá rodando! Tem uma ação fresca te esperando agora.';
} elseif ($posicaoRanking > 3) {
    $msgTitulo = '🏆 O TOPO É LOGO ALI!';
    $msgTexto  = 'Você tá voando! Esmaga os likes e comenta rápido nas missões pra ultrapassar seus rivais!';
}

/*
========================================
TOAST HOME SMART
========================================
*/
$toastSmart = [
    'chip'   => $tenantBrand['toast_default_chip'],
    'icon'   => 'bi-megaphone-fill',
    'titulo' => $tenantBrand['toast_default_title'],
    'texto'  => $tenantBrand['toast_default_text'],
    'botao'  => $tenantBrand['toast_default_button'],
    'link'   => '#cardMissaoAtual',
    'tema'   => 'engajamento'
];

if ($pendenciasAtivacao > 0) {
    $toastSmart = [
        'chip'   => $tenantBrand['toast_pending_chip'],
        'icon'   => 'bi-person-check-fill',
        'titulo' => $tenantBrand['toast_pending_title'],
        'texto'  => $tenantBrand['toast_pending_text'],
        'botao'  => $tenantBrand['toast_pending_button'],
        'link'   => '/pessoas/aceitar-convites.php',
        'tema'   => 'cadastro'
    ];
} elseif (!empty($missaoCard)) {
    if ($ultimaAcaoUsuario === 'convite') {
        $toastSmart = [
            'chip'   => '🎯 VOLTA PRO TOPO',
            'icon'   => 'bi-rocket-takeoff-fill',
            'titulo' => 'Sua missão ainda está te esperando',
            'texto'  => 'Você já fez sua parte na rede. Agora entre na missão do dia e puxe seus pontos.',
            'botao'  => '⚡ VOLTAR PRA MISSÃO',
            'link'   => '#cardMissaoAtual',
            'tema'   => 'engajamento'
        ];
    } elseif ($ultimaAcaoUsuario === 'ranking') {
        $toastSmart = [
            'chip'   => '🏆 SUBIR MAIS',
            'icon'   => 'bi-trophy-fill',
            'titulo' => 'Você já olhou o ranking. Hora de reagir.',
            'texto'  => 'A melhor forma de subir agora é agir na missão crítica e somar pontos imediatamente.',
            'botao'  => '🔥 GANHAR PONTOS',
            'link'   => '#cardMissaoAtual',
            'tema'   => 'engajamento'
        ];
    } else {
        $toastSmart = [
            'chip'   => $tenantBrand['toast_mission_chip'],
            'icon'   => 'bi-rocket-takeoff-fill',
            'titulo' => $tenantBrand['toast_mission_title'],
            'texto'  => $tenantBrand['toast_mission_text'],
            'botao'  => $tenantBrand['toast_mission_button'],
            'link'   => '#cardMissaoAtual',
            'tema'   => 'engajamento'
        ];
    }
} elseif ($contaAguardandoAtivacao) {
    $toastSmart = [
        'chip'   => '🛡️ MODO AQUECIMENTO',
        'icon'   => 'bi-shield-lock-fill',
        'titulo' => 'Quase lá! Liberação em andamento...',
        'texto'  => 'Aproveita pra ir aquecendo: continua destruindo nas missões e marcando seu território.',
        'botao'  => '🎯 VER MISSÃO',
        'link'   => '#cardMissaoAtual',
        'tema'   => 'engajamento'
    ];
} elseif ($xpConvitesSemana > 0) {
    $toastSmart = [
        'chip'   => $tenantBrand['toast_invite_chip'],
        'icon'   => 'bi-whatsapp',
        'titulo' => $tenantBrand['toast_invite_title'],
        'texto'  => $tenantBrand['toast_invite_text'],
        'botao'  => $tenantBrand['toast_invite_button'],
        'link'   => '#cardConvite',
        'tema'   => 'convite'
    ];
}

$toastSmartChipClass = (($toastSmart['chip'] ?? '') === 'Missão do dia')
    ? ' toast-chip-missao'
    : '';

$toastSmartId = md5(($toastSmart['titulo'] ?? '') . '|' . ($toastSmart['texto'] ?? '') . '|' . ($toastSmart['botao'] ?? ''));

/*
========================================
CONVITES DA SEMANA
========================================
*/
$stmt = $pdo->prepare("
SELECT COUNT(*)
FROM convites_aprovacoes
WHERE convidador_id = ?
AND criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stmt->execute([$pessoa_id]);
$convitesSemana = (int) $stmt->fetchColumn();

$metaConvitesSemana = 10;
$percentMeta = min(100, (int) round(($convitesSemana / $metaConvitesSemana) * 100));

/*
========================================
LINK PÚBLICO DO CONVIDADOR
========================================
*/
$linkConvitePublico = null;
$codigoConvitePublico = null;

try {
    $linkPublicoConvite = inviteObterOuCriarLinkPublico($pdo, $pessoa_id);
    $codigoConvitePublico = trim((string) ($linkPublicoConvite['codigo_convite_publico'] ?? ''));
    $linkConvitePublico = trim((string) ($linkPublicoConvite['url_curta'] ?? ''));

    if ($linkConvitePublico === '' && $codigoConvitePublico !== '') {
        $linkConvitePublico = rtrim($tenantBrand['tenant_app_url'], '/') . '/i/' . rawurlencode($codigoConvitePublico);
    }
} catch (Throwable $e) {
    error_log('[DASHBOARD_LINK_PUBLICO] ' . $e->getMessage());
    $codigoConvitePublico = null;
    $linkConvitePublico = null;
}

/*
========================================
NARRATIVA DE ENGAJAMENTO
========================================
*/
$engajamentoNarrativaTitulo = '📊 Seu impacto hoje';
$engajamentoNarrativaTexto = '⚡ Hora de começar. Sua próxima ação já pode colocar você em movimento.';

if ($posicaoRanking > 0 && $posicaoRanking <= 3) {
    $engajamentoNarrativaTexto = '🏆 Você está no TOP ' . $posicaoRanking . '. Continue nesse ritmo para defender sua posição e abrir vantagem.';
} elseif ($posicaoRanking > 3 && $posicaoRanking <= 10) {
    $engajamentoNarrativaTexto = '🚀 Você está muito perto do topo. Mais algumas ações bem feitas e o TOP 3 fica ao seu alcance.';
} elseif ($xpTotal > 0) {
    $engajamentoNarrativaTexto = '💥 Você já entrou no jogo. Agora é manter consistência para transformar presença em subida real no ranking.';
}

if ($xpConvitesSemana > 0) {
    $engajamentoNarrativaTexto .= ' Sua rede já te rendeu +' . number_format($xpConvitesSemana, 0, ',', '.') . ' pontos nesta semana.';
}

/*
========================================
PUSH - PREPARAÇÃO PARA HOME.JS
========================================
*/
$pushForce = true;
$pushSom = false;
$pushVibracao = true;

?>
<?php
$perfilPermitidoInterno = in_array($perfil ?? '', ['admin', 'lider', 'gestor_lideres'], true);
$temAcessoInterno = $perfilPermitidoInterno || ($temAcessoDemandas ?? false);

$adminCards = [
    [
        'titulo' => $tenantBrand['module_demands_list_label'] ?? 'Demandas Registradas',
        'texto' => 'Acompanhe solicitações, status e atendimentos.',
        'icone' => 'bi-person-check',
        'url' => $tenantBrand['module_demands_list_url'] ?? '/lideranca/demandas.php',
        'grupo' => 'Demandas',
    ],
    [
        'titulo' => $tenantBrand['module_demands_new_label'] ?? 'Registrar Nova Demanda',
        'texto' => 'Cadastre uma nova demanda para acompanhamento.',
        'icone' => 'bi-person-plus',
        'url' => $tenantBrand['module_demands_new_url'] ?? '/lideranca/nova-demanda.php',
        'grupo' => 'Demandas',
    ],
    [
        'titulo' => $tenantBrand['module_team_label'] ?? 'Minha Equipe',
        'texto' => 'Veja sua rede, liderados e vínculos ativos.',
        'icone' => 'bi-people',
        'url' => $tenantBrand['module_team_url'] ?? '/lideranca/minha-equipe.php',
        'grupo' => 'Equipe',
    ],
    [
        'titulo' => $tenantBrand['module_general_list_label'] ?? 'Lista de Cadastrados',
        'texto' => 'Consulte pessoas cadastradas na operação.',
        'icone' => 'bi-card-list',
        'url' => $tenantBrand['module_general_list_url'] ?? '/lideranca/lista-indicados.php',
        'grupo' => 'Equipe',
    ],
    [
        'titulo' => $tenantBrand['module_team_performance_label'] ?? 'Desempenho da Equipe',
        'texto' => 'Indicadores operacionais da equipe.',
        'icone' => 'bi-graph-up',
        'url' => '#',
        'grupo' => 'Relatórios',
    ],
];

$grupos = [];
foreach ($adminCards as $card) {
    $grupos[$card['grupo']][] = $card;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Área Interna</title>
  <link rel="icon" href="<?= htmlspecialchars($tenantBrand['favicon_url'] ?? '/favicon.ico') ?>">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/assets/css/footer-v2.css?v=5">
  <style>
    *{box-sizing:border-box}
    body{
      margin:0;
      min-height:100vh;
      background:#eef3f8;
      color:#142033;
      font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
      padding:18px 14px calc(120px + env(safe-area-inset-bottom));
    }
    .page{
      width:100%;
      max-width:430px;
      margin:0 auto;
    }
    .top{
      background:linear-gradient(135deg,#132238,#1d304d);
      color:#fff;
      border-radius:28px;
      padding:22px 20px;
      box-shadow:0 18px 40px rgba(15,23,42,.16);
    }
    .top-row{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:14px;
    }
    .eyebrow{
      display:inline-flex;
      align-items:center;
      gap:8px;
      font-size:12px;
      font-weight:900;
      color:#8ff0b6;
      text-transform:uppercase;
      letter-spacing:.05em;
    }
    h1{
      margin:10px 0 0;
      font-size:28px;
      line-height:1;
      letter-spacing:-.04em;
    }
    .subtitle{
      margin:9px 0 0;
      font-size:13px;
      line-height:1.45;
      color:#dbe7f5;
      font-weight:700;
    }
    .back{
      width:42px;
      height:42px;
      border-radius:16px;
      display:grid;
      place-items:center;
      background:rgba(255,255,255,.12);
      color:#fff;
      text-decoration:none;
      border:1px solid rgba(255,255,255,.16);
    }
    .notice{
      margin:14px 0;
      background:#fff;
      border-radius:22px;
      padding:16px;
      box-shadow:0 10px 25px rgba(15,23,42,.06);
    }
    .notice-title{
      font-size:15px;
      font-weight:950;
      margin:0 0 5px;
    }
    .notice p{
      margin:0;
      font-size:13px;
      line-height:1.45;
      color:#64748b;
      font-weight:650;
    }
    .section{
      margin-top:16px;
    }
    .section-title{
      margin:0 0 10px;
      font-size:14px;
      font-weight:950;
      color:#334155;
      display:flex;
      align-items:center;
      gap:8px;
    }
    .grid{
      display:grid;
      gap:10px;
    }
    .op-card{
      display:flex;
      align-items:center;
      gap:13px;
      text-decoration:none;
      color:inherit;
      background:#fff;
      border:1px solid #dfe7f1;
      border-radius:22px;
      padding:15px;
      box-shadow:0 8px 22px rgba(15,23,42,.055);
      min-height:82px;
    }
    .op-card:active{
      transform:scale(.99);
    }
    .op-icon{
      width:46px;
      height:46px;
      border-radius:17px;
      display:grid;
      place-items:center;
      background:#e9fff1;
      color:#12a957;
      font-size:22px;
      flex:0 0 auto;
    }
    .op-main{
      flex:1;
      min-width:0;
    }
    .op-title{
      font-size:15px;
      line-height:1.15;
      font-weight:950;
      letter-spacing:-.02em;
    }
    .op-text{
      margin-top:4px;
      color:#64748b;
      font-size:12px;
      line-height:1.35;
      font-weight:650;
    }
    .op-arrow{
      color:#94a3b8;
      font-size:18px;
    }
    .restricted{
      background:#fff;
      border-radius:24px;
      padding:22px;
      margin-top:16px;
      text-align:center;
      box-shadow:0 10px 25px rgba(15,23,42,.06);
    }
    .restricted-icon{
      width:58px;
      height:58px;
      border-radius:22px;
      background:#fff1f2;
      color:#e11d48;
      display:grid;
      place-items:center;
      margin:0 auto 12px;
      font-size:28px;
    }
  </style>
</head>
<body>
  <main class="page">
    <section class="top">
      <div class="top-row">
        <div>
          <div class="eyebrow"><i class="bi bi-shield-check"></i> Operação V2</div>
          <h1>Área Interna</h1>
        </div>
        <a class="back" href="/pessoas/perfil.php" aria-label="Voltar">
          <i class="bi bi-arrow-left"></i>
        </a>
      </div>
      <p class="subtitle">Painel de trabalho para líderes e administradores acompanharem demandas, equipe e cadastros.</p>
    </section>

    <?php if (!$temAcessoInterno): ?>
      <section class="restricted">
        <div class="restricted-icon"><i class="bi bi-lock"></i></div>
        <h2 class="notice-title">Acesso restrito</h2>
        <p>Esta área é exclusiva para administradores e lideranças autorizadas.</p>
      </section>
    <?php else: ?>
      <section class="notice">
        <h2 class="notice-title">Olá, <?= htmlspecialchars((string)($pessoa['nome'] ?? 'líder')) ?>.</h2>
        <p>Escolha uma ação abaixo para continuar a operação.</p>
      </section>

      <?php foreach ($grupos as $grupo => $cards): ?>
        <section class="section">
          <h2 class="section-title">
            <?php if ($grupo === 'Demandas'): ?><i class="bi bi-life-preserver"></i><?php endif; ?>
            <?php if ($grupo === 'Equipe'): ?><i class="bi bi-people"></i><?php endif; ?>
            <?php if ($grupo === 'Relatórios'): ?><i class="bi bi-bar-chart"></i><?php endif; ?>
            <?= htmlspecialchars($grupo) ?>
          </h2>

          <div class="grid">
            <?php foreach ($cards as $card): ?>
              <a class="op-card" href="<?= htmlspecialchars($card['url']) ?>">
                <div class="op-icon"><i class="bi <?= htmlspecialchars($card['icone']) ?>"></i></div>
                <div class="op-main">
                  <div class="op-title"><?= htmlspecialchars($card['titulo']) ?></div>
                  <div class="op-text"><?= htmlspecialchars($card['texto']) ?></div>
                </div>
                <div class="op-arrow"><i class="bi bi-chevron-right"></i></div>
              </a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>

  <?php
  $footerPath = __DIR__ . '/../assets/footer/menu.php';
  if (is_file($footerPath)) {
      require_once $footerPath;
  }
  ?>
</body>
</html>
