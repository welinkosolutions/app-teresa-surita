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

try {
    $stmtExpirarMissaoHome = $pdo->prepare("
        UPDATE missao_estado_usuario
        SET status = 'expirada',
            atualizada_em = NOW()
        WHERE pessoa_id = ?
          AND status = 'ativa'
          AND expira_em IS NOT NULL
          AND expira_em < NOW()
    ");
    $stmtExpirarMissaoHome->execute([$pessoa_id]);
} catch (Throwable $e) {
    error_log('[HOME_EXPIRA_MISSAO_VENCIDA] ' . $e->getMessage());
}

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
    sexo,
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

        header('Location: /dashboard/index.php?instagram_ok=1');
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

        header('Location: /dashboard/index.php?facebook_ok=1');
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

    header('Location: /dashboard/index.php');
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

    header('Location: /dashboard/index.php');
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
HOME V2 FINAL - AVATAR / NIVEL / PROGRESSO
========================================
*/
if (!function_exists('home_h')) {
    function home_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('home_avatar_usuario')) {
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
}

$avatarUsuario = home_avatar_usuario($pessoa);

$homeStartNivelNumero = 1;
$homeStartNivelNome = 'Iniciante';
$homeStartXpAtual = (int) $xpTotal;

try {
    $tenantIdAtualHomeV2 = tenant_resolver_id_atual($pdo);

    $stmt = $pdo->prepare("
        SELECT nivel_atual, xp_total
        FROM vw_game_usuario_estado_resumo
        WHERE tenant_cliente_id = ?
          AND pessoa_id = ?
        ORDER BY atualizado_em DESC
        LIMIT 1
    ");
    $stmt->execute([$tenantIdAtualHomeV2, $pessoa_id]);
    $estadoGameHomeV2 = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($estadoGameHomeV2) {
        $homeStartNivelNumero = max(1, (int) ($estadoGameHomeV2['nivel_atual'] ?? 1));
        $homeStartXpAtual = max($homeStartXpAtual, (int) ($estadoGameHomeV2['xp_total'] ?? 0));
    }

    $stmt = $pdo->prepare("
        SELECT nome
        FROM game_niveis
        WHERE nivel = ?
          AND ativo = 'sim'
        LIMIT 1
    ");
    $stmt->execute([$homeStartNivelNumero]);
    $nivelNomeBancoHomeV2 = trim((string) ($stmt->fetchColumn() ?: ''));

    if ($nivelNomeBancoHomeV2 !== '') {
        $homeStartNivelNome = $nivelNomeBancoHomeV2;
    }
} catch (Throwable $e) {
    error_log('[HOME_V2_FINAL_GAME] ' . $e->getMessage());
}

$homeStartXpProximo = match (true) {
    $homeStartXpAtual < 10 => 10,
    $homeStartXpAtual < 30 => 30,
    $homeStartXpAtual < 60 => 60,
    $homeStartXpAtual < 100 => 100,
    $homeStartXpAtual < 160 => 160,
    $homeStartXpAtual < 250 => 250,
    $homeStartXpAtual < 400 => 400,
    $homeStartXpAtual < 700 => 700,
    $homeStartXpAtual < 1000 => 1000,
    default => (int) (ceil(($homeStartXpAtual + 1) / 500) * 500),
};

$homeStartXpFaltante = max(0, $homeStartXpProximo - $homeStartXpAtual);
$homeStartXpPercent = max(4, min(100, (int) round(($homeStartXpAtual / max(1, $homeStartXpProximo)) * 100)));

$homeStartNivelIcones = [
    1 => '/assets/statics/awards/001-startup.svg',
    2 => '/assets/statics/awards/007-coin.svg',
    3 => '/assets/statics/awards/008-shield.svg',
    4 => '/assets/statics/awards/006-energy-bar.svg',
    5 => '/assets/statics/awards/009-swords.svg',
    6 => '/assets/statics/awards/016-star.svg',
    7 => '/assets/statics/awards/002-7.svg',
    8 => '/assets/statics/awards/022-rating.svg',
    9 => '/assets/statics/awards/029-vip.svg',
    10 => '/assets/statics/awards/023-shooting-star.svg',
];

$homeStartNivelIcone = $homeStartNivelIcones[$homeStartNivelNumero] ?? '/assets/statics/awards/001-startup.svg';

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
        $missaoImagem = '/assets/animations/teresa.webp';
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

/*
========================================
HOME V2 FINAL - MISSAO VISUAL
========================================
*/
$temMissaoReal = !empty($missaoCard);

if (!$temMissaoReal) {
    $missaoCard = [
        'codigo' => '',
        'tipo_acao' => 'abrir',
        'network' => $temRedeElegivelMissao ? 'instagram' : 'instagram',
        'titulo' => 'Missão em preparação',
        'descricao' => $temRedeElegivelMissao
            ? 'Assim que uma nova ação for liberada, ela aparece aqui.'
            : 'Cadastre seu Instagram ou Facebook para liberar missões com pontos.',
        'caption' => $temRedeElegivelMissao
            ? 'A próxima missão aparece aqui assim que estiver disponível.'
            : 'Conecte sua rede social para começar a pontuar.',
        'cta_label' => $temRedeElegivelMissao ? 'Ver comunidade' : 'Cadastrar rede',
        'imagem' => '/assets/animations/teresa.webp',
        'url_destino' => $temRedeElegivelMissao ? '/comunidade/social.php' : '/dashboard/index.php',
        'pontos' => 0,
        'post_id' => 0,
        'btn_class' => 'is-default',
        'icon' => 'bi-cursor-fill',
        'expira_em' => $missaoExpiraEm ?? date('Y-m-d 23:59:59'),
        'urgencia_label' => $temRedeElegivelMissao ? 'Aguardando nova missão' : 'Rede social pendente',
        'narrativa' => '',
    ];
}

$homeV2NetworkRaw = strtolower(trim((string) ($missaoCard['network'] ?? 'instagram')));
$homeV2RedeLabel = $homeV2NetworkRaw === 'facebook' ? 'Facebook' : 'Instagram';
$homeV2RedeIcon = $homeV2NetworkRaw === 'facebook'
    ? '/assets/feed/statics/002-facebook.svg'
    : '/assets/feed/statics/001-instagram.svg';

$homeV2NetworkClass = match ($homeV2NetworkRaw) {
    'facebook' => 'is-facebook',
    'whatsapp' => 'is-whatsapp',
    default => 'is-instagram',
};

$homeV2Action = strtolower(trim((string) ($missaoCard['tipo_acao'] ?? 'comentario')));

$homeV2ActionLabel = 'Comente';
$homeV2ActionIcon = '/assets/feed/animateds/curte-porfavorzinho.webp';

if ($homeV2Action === 'compartilhar') {
    $homeV2ActionLabel = 'Compartilhe';
    $homeV2ActionIcon = '/assets/feed/animateds/novo-post-facebook.webp';
} elseif ($homeV2Action === 'curtir') {
    $homeV2ActionLabel = 'Curta';
    $homeV2ActionIcon = '/assets/feed/animateds/coracao-feliz-dancando.webp';
} elseif ($homeV2Action === 'video') {
    $homeV2ActionLabel = 'Assista';
    $homeV2ActionIcon = '/assets/feed/animateds/novo-post-facebook.webp';
}

$homeV2Pontos = (int) ($missaoCard['pontos'] ?? 0);
if ($homeV2Pontos <= 0 && $temMissaoReal) {
    $homeV2Pontos = 25;
}

$homeV2Titulo = 'Apoie a Teresa neste post!';
$homeV2Instrucao = $temMissaoReal
    ? $homeV2ActionLabel . ' no ' . $homeV2RedeLabel . ' e ganhe +' . $homeV2Pontos . ' pontos'
    : (string) ($missaoCard['urgencia_label'] ?? 'Aguardando missão');

$homeV2OverlayText = match (true) {
    !$temMissaoReal => 'VER COMUNIDADE',
    $homeV2Action === 'compartilhar' => 'COMPARTILHAR NO WHATSAPP',
    $homeV2Action === 'curtir' => 'CURTIR NO ' . mb_strtoupper($homeV2RedeLabel, 'UTF-8'),
    $homeV2Action === 'video' => 'ASSISTIR',
    default => 'COMENTAR NO ' . mb_strtoupper($homeV2RedeLabel, 'UTF-8'),
};

$homeV2Caption = trim((string) ($missaoCard['caption'] ?? ''));
if ($homeV2Caption === '') {
    $homeV2Caption = trim((string) ($missaoCard['descricao'] ?? 'Abra a missão, participe e ajude esse conteúdo a ganhar força.'));
}

$homeV2Imagem = trim((string) ($missaoCard['imagem'] ?? ''));
if ($homeV2Imagem === '') {
    $homeV2Imagem = '/assets/animations/teresa.webp';
}

$homeV2ImagemProxy = $homeV2Imagem;

/*
 * Facebook/CDN image URLs expiram com frequência ou bloqueiam hotlink.
 * Para manter a Home estável, Facebook usa fallback local da Teresa.
 * Instagram continua podendo usar proxy/cache quando necessário.
 */
if (
    $homeV2NetworkRaw === 'facebook' &&
    $homeV2Imagem !== '' &&
    str_starts_with($homeV2Imagem, 'https://') &&
    (
        str_contains($homeV2Imagem, 'fbcdn.net') ||
        str_contains($homeV2Imagem, 'scontent')
    )
) {
    $homeV2ImagemProxy = '/assets/animations/teresa.webp';
} elseif (
    $homeV2NetworkRaw === 'instagram' &&
    $homeV2Imagem !== '' &&
    str_starts_with($homeV2Imagem, 'https://') &&
    (
        str_contains($homeV2Imagem, 'cdninstagram.com') ||
        str_contains($homeV2Imagem, 'scontent')
    )
) {
    $homeV2ImagemProxy = '/assets/animations/teresa.webp';
} elseif (
    $homeV2Imagem !== '' &&
    str_starts_with($homeV2Imagem, 'https://') &&
    (
        str_contains($homeV2Imagem, 'cdninstagram.com') ||
        str_contains($homeV2Imagem, 'fbcdn.net') ||
        str_contains($homeV2Imagem, 'scontent')
    )
) {
    $homeV2ImagemProxy = '/inicial/img-missao-proxy.php?u=' . rtrim(strtr(base64_encode($homeV2Imagem), '+/', '-_'), '=');
}

$homeV2MissaoUrl = trim((string) ($missaoCard['url_destino'] ?? ''));
if ($homeV2MissaoUrl === '') {
    $homeV2MissaoUrl = $temMissaoReal ? '/comunidade/missao.php' : '/comunidade/social.php';
}

$homeV2MissaoTarget = $temMissaoReal && $homeV2MissaoUrl !== '' && !str_starts_with($homeV2MissaoUrl, '/')
    ? 'target="_blank" rel="noopener noreferrer"'
    : '';

$homeV2ShareTitle = trim((string) ($tenantBrand['invite_share_title'] ?? 'Compartilhar seu link'));
$homeV2ShareIntro = trim((string) ($tenantBrand['invite_share_intro'] ?? 'Copie seu link ou compartilhe direto nas redes.'));

if (!isset($missaoCard['estado_id'])) {
    $missaoCard['estado_id'] = 0;

    try {
        if (!empty($missaoCard['codigo']) && !empty($pessoa_id)) {
            $stmtEstadoHomeV2 = $pdo->prepare("
                SELECT id
                FROM missao_estado_usuario
                WHERE pessoa_id = ?
                  AND status = 'ativa'
                  AND missao_codigo = ?
                ORDER BY prioridade DESC, atualizada_em DESC, id DESC
                LIMIT 1
            ");
            $stmtEstadoHomeV2->execute([$pessoa_id, (string) $missaoCard['codigo']]);
            $missaoCard['estado_id'] = (int) ($stmtEstadoHomeV2->fetchColumn() ?: 0);
        }
    } catch (Throwable $e) {
        error_log('[HOME_V2_ESTADO_ID] ' . $e->getMessage());
        $missaoCard['estado_id'] = 0;
    }
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<link rel="manifest" href="<?= htmlspecialchars($manifestUrl) ?>">
<meta name="theme-color" content="<?= home_h($tenantBrand['theme_color']) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= home_h($tenantBrand['app_name']) ?>">

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= home_h($tenantBrand['app_title']) ?></title>

<link rel="icon" href="<?= home_h($tenantBrand['favicon_url']) ?>">
<link rel="apple-touch-icon" href="<?= home_h($tenantBrand['apple_touch_icon_url']) ?>">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/footer-v2.css?v=5">

<style>
:root {
  --home-bg: #f8fafc;
  --home-ink: #0f172a;
  --home-muted: #7b8498;
  --home-green: #16a34a;
  --home-green-2: #22c55e;
  --home-line: #e7edf5;
  --home-card: #ffffff;
  --home-shadow: 0 26px 70px rgba(15, 23, 42, .13);
  --home-soft-shadow: 0 16px 42px rgba(15, 23, 42, .10);

  --instagram-start: <?= home_h($tenantBrand['instagram_start'] ?? '#feda75') ?>;
  --instagram-mid: <?= home_h($tenantBrand['instagram_mid'] ?? '#d62976') ?>;
  --instagram-end: <?= home_h($tenantBrand['instagram_end'] ?? '#4f5bd5') ?>;
  --facebook-start: <?= home_h($tenantBrand['facebook_start'] ?? '#1877f2') ?>;
  --facebook-end: <?= home_h($tenantBrand['facebook_end'] ?? '#0f5fd1') ?>;
  --whatsapp-start: <?= home_h($tenantBrand['whatsapp_start'] ?? '#22c55e') ?>;
  --whatsapp-end: <?= home_h($tenantBrand['whatsapp_end'] ?? '#16a34a') ?>;
}

* {
  box-sizing: border-box;
  -webkit-tap-highlight-color: transparent;
}

html,
body {
  min-height: 100%;
  margin: 0;
}

body {
  min-height: 100vh;
  overflow-x: hidden;
  padding-bottom: calc(52px + env(safe-area-inset-bottom));
  background:
    radial-gradient(circle at top left, rgba(34, 197, 94, .10), transparent 32%),
    linear-gradient(180deg, #fbfdff 0%, #f8fafc 100%);
  color: var(--home-ink);
  font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
}

a {
  color: inherit;
  text-decoration: none;
}

button {
  font-family: inherit;
}

.home-v2-page {
  width: min(100%, 430px);
  min-height: 100svh;
  margin: 0 auto;
  padding: 0 16px calc(58px + env(safe-area-inset-bottom));
}

/* HEADER FINAL */
.home-start-header {
  position: relative;
  width: min(100%, 430px);
  margin: 0 auto 12px;
  padding: 15px 16px 17px;
  border-radius: 0 0 32px 32px;
  overflow: hidden;
  background:
    radial-gradient(circle at 92% 24%, rgba(255,255,255,.15) 0 86px, transparent 87px),
    radial-gradient(circle at 6% 94%, rgba(255,255,255,.12) 0 108px, transparent 109px),
    linear-gradient(135deg, #ffbe3b 0%, #ff9417 55%, #ff7a00 100%);
  box-shadow: 0 18px 42px rgba(249, 115, 22, .22);
}

.home-start-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  margin-bottom: 12px;
}

.home-start-user {
  display: flex;
  align-items: center;
  gap: 13px;
  min-width: 0;
  flex: 1;
}

.home-start-avatar {
  width: 74px;
  height: 74px;
  border-radius: 999px;
  padding: 7px;
  flex-shrink: 0;
  background: rgba(255,255,255,.30);
  box-shadow: 0 12px 24px rgba(124,45,18,.14);
}

.home-start-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center top;
  border-radius: inherit;
  display: block;
  background: #fff;
}

.home-start-user-copy {
  min-width: 0;
}

.home-start-user-copy h1 {
  margin: 0;
  color: #fff;
  font-size: clamp(34px, 8vw, 54px);
  line-height: .92;
  font-weight: 1000;
  letter-spacing: -.065em;
  text-shadow: 0 3px 10px rgba(0,0,0,.12);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.home-start-level-card {
  width: 92px;
  min-width: 92px;
  height: 112px;
  border-radius: 24px;
  background: rgba(255,255,255,.94);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 5px;
  box-shadow: 0 14px 28px rgba(124,45,18,.14);
  flex-shrink: 0;
}

.home-start-level-card img {
  width: 30px;
  height: 30px;
  object-fit: contain;
}

.home-start-level-card span {
  color: #92400e;
  font-size: 12px;
  font-weight: 900;
  text-transform: uppercase;
  line-height: 1;
}

.home-start-level-card strong {
  color: #0f172a;
  font-size: 44px;
  line-height: .9;
  font-weight: 1000;
  letter-spacing: -.06em;
}

.home-start-progress-wrap {
  background: rgba(255,255,255,.92);
  border-radius: 26px;
  padding: 13px 14px 15px;
  box-shadow: 0 14px 28px rgba(124,45,18,.12);
}

.home-start-progress-text {
  display: flex;
  flex-direction: column;
  gap: 3px;
  margin-bottom: 11px;
}

.home-start-progress-text strong {
  color: #64748b;
  font-size: 15px;
  font-weight: 1000;
  line-height: 1.1;
}

.home-start-progress-text span {
  color: #94a3b8;
  font-size: 13px;
  font-weight: 700;
  line-height: 1.2;
}

.home-start-progress-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 9px;
  color: #64748b;
  font-size: 12px;
  font-weight: 900;
}

.home-start-progress-bar {
  position: relative;
  height: 12px;
  border-radius: 999px;
  overflow: hidden;
  background: #e7edf5;
  box-shadow: inset 0 2px 4px rgba(15,23,42,.08);
}

.home-start-progress-bar i {
  position: absolute;
  inset: 0 auto 0 0;
  border-radius: inherit;
  background: linear-gradient(90deg, #16a34a 0%, #22c55e 55%, #facc15 100%);
  box-shadow: 0 6px 14px rgba(34,197,94,.28);
}

/* MISSAO FINAL */
.home-mission-card {
  width: 100%;
  margin: 0 auto 12px;
  padding: 13px 11px 12px;
  border-radius: 24px;
  background: #fff;
  border: 1px solid rgba(226, 232, 240, .95);
  box-shadow:
    0 18px 46px rgba(15, 23, 42, .10),
    inset 0 1px 0 rgba(255,255,255,.9);
}

.home-mission-title {
  display: grid;
  grid-template-columns: 34px minmax(0, 1fr);
  align-items: center;
  gap: 9px;
  margin: 0 0 11px;
}

.home-mission-target {
  width: 30px;
  height: 30px;
  display: grid;
  place-items: center;
  border-radius: 999px;
  background: rgba(240, 253, 244, .9);
  box-shadow: 0 8px 18px rgba(34,197,94,.10);
  font-size: 19px;
}

.home-mission-target-social {
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 8px 18px rgba(15, 23, 42, .10);
}

.home-mission-target-social img {
  width: 22px;
  height: 22px;
  display: block;
  object-fit: contain;
}

.home-mission-copy {
  min-width: 0;
}

.home-mission-title-line {
  display: flex;
  align-items: center;
  gap: 7px;
  min-width: 0;
  color: #0f172a;
  font-size: clamp(19px, 5.2vw, 24px);
  line-height: 1;
  letter-spacing: -.055em;
  font-weight: 1000;
  white-space: nowrap;
}

.home-mission-title-line > span:last-child {
  overflow: hidden;
  text-overflow: ellipsis;
}

.home-mission-social-icon {
  flex: 0 0 auto;
  width: 28px;
  height: 28px;
  border-radius: 9px;
  display: none;
  align-items: center;
  justify-content: center;
  background: #fff;
  box-shadow: 0 7px 16px rgba(15, 23, 42, .10);
}

.home-mission-social-icon img {
  width: 21px;
  height: 21px;
  display: block;
  object-fit: contain;
}

.home-mission-subtitle {
  display: block;
  margin-top: 4px;
  color: #7b8799;
  font-size: 10.5px;
  line-height: 1.15;
  font-weight: 950;
  letter-spacing: -.02em;
}

.home-mission-post {
  position: relative;
  display: block;
  aspect-ratio: 1 / .88;
  overflow: hidden;
  border-radius: 25px;
  background: #0f172a;
  box-shadow:
    0 14px 30px rgba(15,23,42,.15),
    0 3px 8px rgba(15,23,42,.07);
  cursor: pointer;
  isolation: isolate;
}

.home-mission-post-bg {
  position: absolute;
  inset: 0;
  z-index: 0;
  background:
    radial-gradient(circle at 50% 40%, rgba(255,255,255,.22), transparent 30%),
    linear-gradient(135deg, #74d9ff 0%, #bff6dc 52%, #fff0a6 100%);
}

.home-mission-post-bg img {
  width: 100%;
  height: 100%;
  display: block;
  object-fit: cover;
  object-position: center;
}

.home-mission-post.is-fallback .home-mission-post-bg::before {
  content: "⚡";
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  color: rgba(255,255,255,.95);
  font-size: 120px;
  filter: drop-shadow(0 16px 28px rgba(0,0,0,.22));
}

.home-mission-shade {
  position: absolute;
  inset: 0;
  z-index: 1;
  background:
    linear-gradient(180deg,
      rgba(15,23,42,.03) 0%,
      rgba(15,23,42,.08) 34%,
      rgba(15,23,42,.34) 62%,
      rgba(15,23,42,.72) 100%
    );
  pointer-events: none;
}

.home-mission-shade::after {
  content: "";
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  height: 58%;
  background:
    radial-gradient(circle at 50% 70%, rgba(34,197,94,.18), transparent 42%),
    linear-gradient(0deg,
      rgba(7,17,31,.86) 0%,
      rgba(7,17,31,.62) 36%,
      rgba(7,17,31,.20) 72%,
      transparent 100%
    );
}

.home-mission-overlay {
  position: absolute;
  inset: 10% 6% 8%;
  z-index: 2;
  pointer-events: none;
}

.home-mission-tap-frame {
  position: relative;
  width: 100%;
  height: 100%;
  border: 2px dashed rgba(255, 255, 255, .86);
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(15, 23, 42, .14);
  box-shadow:
    inset 0 0 0 1px rgba(15, 23, 42, .18),
    0 16px 34px rgba(15, 23, 42, .18);
}

.home-mission-tap-finger {
  position: absolute;
  top: 32%;
  left: 50%;
  width: clamp(72px, 20vw, 112px);
  height: clamp(72px, 20vw, 112px);
  transform: translate(-50%, -50%);
  filter: drop-shadow(0 14px 18px rgba(0,0,0,.34));
}

.home-mission-tap-finger img {
  width: 100%;
  height: 100%;
  display: block;
  object-fit: contain;
}

.home-mission-tap-copy {
  position: absolute;
  left: 8%;
  right: 8%;
  bottom: 12%;
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 0;
  text-align: center;
  padding: 13px 14px 14px;
  border-radius: 22px;
  background:
    radial-gradient(circle at 18% 0%, rgba(34,197,94,.26), transparent 34%),
    linear-gradient(135deg, rgba(7,17,31,.92), rgba(15,23,42,.84));
  border: 1px solid rgba(255,255,255,.18);
  box-shadow:
    0 20px 42px rgba(0,0,0,.34),
    inset 0 1px 0 rgba(255,255,255,.14);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
}

.home-mission-tap-copy span,
.home-mission-tap-copy strong {
  width: 100%;
  max-width: 100%;
  padding: 0;
  background: transparent;
  line-height: 1.05;
  font-weight: 1000;
  box-shadow: none;
}

.home-mission-tap-copy span {
  color: rgba(255,255,255,.78);
  font-size: clamp(11px, 3.1vw, 14px);
  letter-spacing: .08em;
  text-transform: uppercase;
}

.home-mission-tap-copy strong {
  margin-top: 6px;
  color: #86efac;
  font-size: clamp(19px, 5.4vw, 29px);
  letter-spacing: -.045em;
  text-transform: uppercase;
  text-shadow: 0 8px 20px rgba(0,0,0,.22);
}

.home-mission-card.is-instagram .home-mission-tap-copy strong {
  color: #f0abfc;
  text-shadow:
    0 0 18px rgba(217,70,239,.34),
    0 8px 20px rgba(0,0,0,.24);
}

.home-mission-card.is-facebook .home-mission-tap-copy strong {
  color: #93c5fd;
  text-shadow:
    0 0 18px rgba(59,130,246,.34),
    0 8px 20px rgba(0,0,0,.24);
}

.home-mission-card.is-whatsapp .home-mission-tap-copy strong {
  color: #86efac;
  text-shadow:
    0 0 18px rgba(34,197,94,.34),
    0 8px 20px rgba(0,0,0,.24);
}

.home-mission-summary {
  margin-top: 9px;
  padding: 9px 11px;
  border-radius: 17px;
  background: #fff;
  border: 1px solid rgba(226, 232, 240, .95);
  box-shadow: 0 10px 22px rgba(15,23,42,.045);
}

.home-mission-summary span {
  display: block;
  margin-bottom: 4px;
  color: #16a34a;
  font-size: 9.5px;
  line-height: 1;
  font-weight: 1000;
  text-transform: uppercase;
  letter-spacing: .045em;
}

.home-mission-summary p {
  margin: 0;
  color: #334155;
  font-size: 11.5px;
  line-height: 1.22;
  font-weight: 900;
  letter-spacing: -.025em;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* COMPARTILHAR */
.home-share-card {
  margin: 0 auto 16px;
  padding: 20px;
  border-radius: 24px;
  background: #fff;
  border: 1px solid rgba(226,232,240,.95);
  box-shadow: 0 18px 46px rgba(15,23,42,.08);
}

.home-share-title {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #0f172a;
  font-size: 23px;
  font-weight: 1000;
  letter-spacing: -.05em;
}

.home-share-card p {
  margin: 8px 0 0;
  color: #64748b;
  font-size: 14px;
  line-height: 1.35;
  font-weight: 750;
}

.home-share-link-box {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 16px;
  padding: 9px;
  border-radius: 22px;
  background: #f8fafc;
  border: 1px solid rgba(226,232,240,.9);
}

.home-share-link-box span {
  min-width: 0;
  flex: 1;
  overflow: hidden;
  color: #0f172a;
  font-size: 14px;
  font-weight: 900;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.home-share-link-box button {
  border: 0;
  border-radius: 18px;
  padding: 12px 14px;
  color: #fff;
  font-weight: 950;
  background: linear-gradient(135deg, #0f8f98, #20b7bd);
}

.home-share-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-top: 14px;
}

.home-share-grid button {
  min-height: 54px;
  border: 0;
  border-radius: 20px;
  color: #fff;
  font-size: 14px;
  font-weight: 950;
}

.home-share-instagram {
  background: linear-gradient(135deg, var(--instagram-start), var(--instagram-mid), var(--instagram-end));
}

.home-share-facebook {
  background: linear-gradient(135deg, var(--facebook-start), var(--facebook-end));
}

.home-share-whatsapp {
  background: linear-gradient(135deg, var(--whatsapp-start), var(--whatsapp-end));
}

.home-share-more {
  color: #0f172a !important;
  background: #f8fafc !important;
  border: 1px solid rgba(226,232,240,.95) !important;
}

.elab-toast-copy {
  position: fixed;
  left: 16px;
  right: 16px;
  bottom: calc(104px + env(safe-area-inset-bottom));
  z-index: 9999;
  display: none;
  pointer-events: none;
}

.elab-toast-copy.show {
  display: block;
}

.elab-toast-copy-inner {
  max-width: 520px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 22px;
  color: #fff;
  background: linear-gradient(135deg, #07111f, #102441);
  box-shadow: 0 22px 48px rgba(15,23,42,.28);
}

.elab-toast-copy-icon {
  font-size: 24px;
  color: #86efac;
}

.elab-toast-copy-title {
  font-weight: 1000;
}

.elab-toast-copy-text {
  color: rgba(255,255,255,.72);
  font-size: 13px;
  font-weight: 700;
}

@media (max-width: 430px) {
  .home-v2-page {
    padding-left: 13px;
    padding-right: 13px;
    padding-bottom: calc(58px + env(safe-area-inset-bottom));
  }

  .home-start-header {
    padding: 13px 14px 15px;
    border-radius: 0 0 28px 28px;
  }

  .home-start-top {
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
  }

  .home-start-user {
    gap: 10px;
  }

  .home-start-avatar {
    width: 72px;
    height: 72px;
    padding: 5px;
  }

  .home-start-user-copy h1 {
    font-size: 31px;
  }

  .home-start-level-card {
    width: 82px;
    min-width: 82px;
    height: 104px;
    border-radius: 22px;
    gap: 4px;
  }

  .home-start-level-card img {
    width: 26px;
    height: 26px;
  }

  .home-start-level-card span {
    font-size: 10px;
  }

  .home-start-level-card strong {
    font-size: 40px;
  }

  .home-start-progress-wrap {
    padding: 14px 14px 16px;
    border-radius: 22px;
  }

  .home-start-progress-text strong {
    font-size: 15px;
  }

  .home-start-progress-text span {
    font-size: 12px;
  }

  .home-start-progress-meta {
    font-size: 12px;
  }

  .home-start-progress-bar {
    height: 12px;
  }

  .home-share-grid {
    grid-template-columns: 1fr;
  }
}


/* ===============================
   HOME V2 - RESPONSIVIDADE FINAL
   =============================== */

@media (max-width: 390px) {
  .home-v2-page {
    padding-left: 10px;
    padding-right: 10px;
  }

  .home-start-header {
    padding: 13px 13px 15px;
    border-radius: 0 0 24px 24px;
  }

  .home-start-top {
    gap: 8px;
    margin-bottom: 10px;
  }

  .home-start-avatar {
    width: 62px;
    height: 62px;
    padding: 4px;
  }

  .home-start-user {
    gap: 8px;
  }

  .home-start-user-copy h1 {
    font-size: 28px;
    letter-spacing: -.065em;
  }

  .home-start-level-card {
    width: 74px;
    min-width: 74px;
    height: 94px;
    border-radius: 20px;
  }

  .home-start-level-card img {
    width: 24px;
    height: 24px;
  }

  .home-start-level-card strong {
    font-size: 36px;
  }

  .home-start-progress-wrap {
    padding: 12px 12px 14px;
    border-radius: 20px;
  }

  .home-start-progress-text strong {
    font-size: 14px;
  }

  .home-start-progress-text span {
    font-size: 11.5px;
  }

  .home-mission-card,
  .home-share-card {
    border-radius: 24px;
  }

  .home-mission-title {
    grid-template-columns: 28px minmax(0, 1fr);
    gap: 8px;
  }

  .home-mission-target {
    width: 28px;
    height: 28px;
  }

  .home-mission-target-social img {
    width: 20px;
    height: 20px;
  }

  .home-mission-title-line {
    font-size: 20px;
    letter-spacing: -.055em;
  }

  .home-mission-subtitle {
    font-size: 10px;
  }

  .home-mission-post {
    border-radius: 22px;
  }

  .home-mission-overlay {
    inset: 12% 6% 9%;
  }

  .home-mission-tap-frame {
    border-width: 2px;
  }

  .home-mission-tap-finger {
    top: 30%;
    width: 78px;
    height: 78px;
  }

  .home-mission-tap-copy {
    left: 6%;
    right: 6%;
    bottom: 13%;
    padding: 11px 12px 12px;
  }

  .home-mission-tap-copy span {
    font-size: 10.5px;
  }

  .home-mission-tap-copy strong {
    font-size: 20px;
  }

  .home-mission-summary p {
    font-size: 11px;
  }

  .home-share-title {
    font-size: 21px;
  }

  .home-share-link-box {
    gap: 8px;
  }

  .home-share-link-box span {
    font-size: 12.5px;
  }

  .home-share-link-box button {
    padding: 11px 12px;
    font-size: 13px;
  }
}

@media (max-width: 350px) {
  .home-start-user-copy h1 {
    font-size: 24px;
  }

  .home-start-avatar {
    width: 56px;
    height: 56px;
  }

  .home-start-level-card {
    width: 68px;
    min-width: 68px;
    height: 86px;
  }

  .home-start-level-card strong {
    font-size: 32px;
  }

  .home-start-progress-meta {
    font-size: 10px;
  }

  .home-mission-title-line {
    font-size: 18px;
  }

  .home-mission-subtitle {
    font-size: 9.5px;
  }

  .home-mission-tap-finger {
    width: 66px;
    height: 66px;
  }

  .home-mission-tap-copy span {
    font-size: 9.5px;
  }

  .home-mission-tap-copy strong {
    font-size: 17px;
  }

  .home-share-card {
    padding: 16px;
  }

  .home-share-grid button {
    min-height: 50px;
    font-size: 13px;
  }
}

@media (max-height: 740px) and (max-width: 430px) {
  .home-start-header {
    margin-bottom: 10px;
  }

  .home-start-avatar {
    width: 62px;
    height: 62px;
  }

  .home-start-level-card {
    height: 92px;
  }

  .home-start-progress-wrap {
    padding-top: 11px;
    padding-bottom: 12px;
  }

  .home-mission-card {
    padding-top: 11px;
  }

  .home-mission-post {
    aspect-ratio: 1 / .82;
  }

  .home-mission-tap-finger {
    top: 29%;
  }

  .home-mission-tap-copy {
    bottom: 12%;
  }

  .home-share-card {
    padding-top: 16px;
    padding-bottom: 16px;
  }
}

@media (min-width: 431px) {
  .home-v2-page {
    max-width: 430px;
  }

  .home-start-header,
  .home-mission-card,
  .home-share-card {
    max-width: 398px;
  }
}


.home-mission-card.is-checking-mission .home-mission-tap-copy::after {
  content: "Verificando sua ação...";
  display: block;
  margin-top: 8px;
  color: rgba(255,255,255,.72);
  font-size: 10px;
  font-weight: 900;
  letter-spacing: .08em;
  text-transform: uppercase;
}

.home-mission-card.is-mission-updated {
  animation: homeMissionUpdated .72s ease both;
}

@keyframes homeMissionUpdated {
  0% {
    transform: translateY(8px) scale(.985);
    opacity: .72;
  }

  100% {
    transform: translateY(0) scale(1);
    opacity: 1;
  }
}

</style>

<script>
const TENANT_APP_NAME = <?= json_encode($tenantBrand['app_name'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const TENANT_APP_TITLE = <?= json_encode($tenantBrand['app_title'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const TENANT_THEME_COLOR = <?= json_encode($tenantBrand['theme_color'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const TENANT_LOGO_URL = <?= json_encode($tenantBrand['logo_url'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const TENANT_HERO_STATIC_URL = <?= json_encode($tenantBrand['hero_static_url'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const TENANT_HERO_HAPPY_URL = <?= json_encode($tenantBrand['hero_happy_url'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const TENANT_HERO_INTRO_URL = <?= json_encode($tenantBrand['hero_intro_url'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const TENANT_HERO_BAD_URL = <?= json_encode($tenantBrand['hero_bad_url'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const TENANT_POST_PLACEHOLDER_URL = <?= json_encode($tenantBrand['post_placeholder_url'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

const NOME_CONVIDADOR = <?= json_encode($nomeExibicao, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const CONVITE_LINK_PUBLICO = <?= json_encode($linkConvitePublico, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const CONVITE_CODIGO_PUBLICO = <?= json_encode($codigoConvitePublico, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const EVENTO_TIPO = <?= json_encode($eventoTipo ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const EVENTO_XP = <?= (int) ($eventoXP ?? 0) ?>;
const XP_TOTAL = <?= (int) $xpTotal ?>;
const EVENTO_POSITIVO = ['xp','ranking','combo','streak'].includes(EVENTO_TIPO);
const HERO_STATIC = <?= json_encode($tenantBrand['hero_static_url'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const HERO_ANIM_SRC = <?= json_encode($heroAnimSrc ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const MISSAO_EXPIRA_EM = <?= json_encode($missaoExpiraEm ?? date('Y-m-d 23:59:59'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

const TENANT_INVITE_SHARE_TITLE = <?= json_encode($tenantBrand['invite_share_title'] ?? 'Compartilhar seu link', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const TENANT_INVITE_SHARE_INTRO = <?= json_encode($tenantBrand['invite_share_intro'] ?? 'Copie seu link ou compartilhe direto nas redes.', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const TENANT_INVITE_SHARE_SHORT_TEXT = <?= json_encode($tenantBrand['invite_share_short_text'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const TENANT_INVITE_SHARE_WHATSAPP_TEXT = <?= json_encode($tenantBrand['invite_share_whatsapp_text'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const TENANT_INVITE_SHARE_FACEBOOK_TEXT = <?= json_encode($tenantBrand['invite_share_facebook_text'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const TENANT_INVITE_SHARE_INSTAGRAM_TEXT = <?= json_encode($tenantBrand['invite_share_instagram_text'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const TENANT_FEATURE_WHATSAPP_SHARE_ENABLED = <?= $featureWhatsappShareEnabled ? 'true' : 'false' ?>;

const HOME_STATE_BOOT = <?= json_encode([
    'ok' => true,
    'xp_total' => (int) $xpTotal,
    'ranking_posicao' => (int) $posicaoRanking,
    'missao' => $missaoCard,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
</head>

<body>

<main class="home-v2-page">
  <header class="home-start-header">
    <div class="home-start-top">
      <div class="home-start-user">
        <div class="home-start-avatar">
          <img src="<?= home_h($avatarUsuario) ?>" alt="<?= home_h($nomeExibicao) ?>">
        </div>

        <div class="home-start-user-copy">
          <h1><?= home_h($nomeExibicao) ?></h1>
        </div>
      </div>

      <div class="home-start-level-card">
        <img src="<?= home_h($homeStartNivelIcone) ?>" alt="">
        <span>Nível</span>
        <strong><?= (int) $homeStartNivelNumero ?></strong>
      </div>
    </div>

    <div class="home-start-progress-wrap">
      <div class="home-start-progress-text">
        <strong>
          <?= $homeStartXpFaltante > 0
              ? 'Faltam ' . number_format($homeStartXpFaltante, 0, ',', '.') . ' XP'
              : 'Você já pode subir de nível' ?>
        </strong>
        <span>para chegar ao próximo nível</span>
      </div>

      <div class="home-start-progress-meta">
        <span><?= number_format($homeStartXpAtual, 0, ',', '.') ?> XP</span>
        <span><?= number_format($homeStartXpProximo, 0, ',', '.') ?> XP</span>
      </div>

      <div class="home-start-progress-bar" aria-hidden="true">
        <i style="width: <?= (int) $homeStartXpPercent ?>%;"></i>
      </div>
    </div>
  </header>

  <section
    class="home-mission-card <?= home_h($homeV2NetworkClass) ?>"
    id="cardMissaoAtual"
    data-missao-estado-id="<?= (int) ($missaoCard['estado_id'] ?? 0) ?>"
    data-missao-codigo="<?= home_h($missaoCard['codigo']) ?>"
    data-missao-post-id="<?= (int) $missaoCard['post_id'] ?>"
    data-missao-expira-em="<?= home_h((string) ($missaoCard['expira_em'] ?? '')) ?>"
  >
    <h2 class="home-mission-title">
      <span class="home-mission-target home-mission-target-social">
        <img src="<?= home_h($homeV2RedeIcon) ?>" alt="<?= home_h($homeV2RedeLabel) ?>">
      </span>

      <span class="home-mission-copy">
        <strong class="home-mission-title-line">
          <span><?= home_h($homeV2Titulo) ?></span>
        </strong>
        <small class="home-mission-subtitle"><?= home_h($homeV2Instrucao) ?></small>
      </span>
    </h2>

    <a
      class="home-mission-post"
      href="<?= home_h($homeV2MissaoUrl) ?>"
      <?= $homeV2MissaoTarget ?>
      data-home-action="missao"
      data-missao-codigo="<?= home_h($missaoCard['codigo']) ?>"
      data-post-id="<?= (int) $missaoCard['post_id'] ?>"
      data-post-url="<?= home_h((string) ($missaoCard['url_destino'] ?? '')) ?>"
    >
      <div class="home-mission-post-bg">
        <img
          src="<?= home_h($homeV2ImagemProxy) ?>"
          alt=""
          loading="eager"
          onerror="this.onerror=null; this.src='/assets/animations/teresa.webp'; this.closest('.home-mission-post') && this.closest('.home-mission-post').classList.add('is-fallback');"
        >
      </div>

      <div class="home-mission-shade"></div>

      <div class="home-mission-overlay" aria-hidden="true">
        <div class="home-mission-tap-frame">
          <div class="home-mission-tap-finger">
            <img src="/assets/animations/dedao.webp" alt="">
          </div>

          <div class="home-mission-tap-copy">
            <span>Toque na área marcada</span>
            <strong><?= home_h($homeV2OverlayText) ?></strong>
          </div>
        </div>
      </div>
    </a>

    <div class="home-mission-summary">
      <span>Post da missão</span>
      <p><?= home_h(mb_strimwidth($homeV2Caption, 0, 180, '...')) ?></p>
    </div>
  </section>

  <?php if ($featureInviteEnabled && $featureWhatsappShareEnabled && $showShareCard && !empty($linkConvitePublico)): ?>
    <section class="home-share-card" id="cardConviteShare">
      <div class="home-share-title">
        <i class="bi bi-share-fill"></i>
        <strong>Compartilhar seu link</strong>
      </div>

      <p>Copie seu link ou compartilhe direto nas redes.</p>

      <div class="home-share-link-box">
        <span id="conviteLinkTexto"><?= home_h($linkConvitePublico) ?></span>

        <button type="button" onclick="copiarLinkConvite()" data-home-action="convite">
          <i class="bi bi-copy"></i>
          Copiar
        </button>
      </div>

      <div class="home-share-grid">
        <button type="button" class="home-share-instagram" onclick="compartilharConviteCanal('instagram_dm')" data-home-action="convite">
          <i class="bi bi-instagram"></i>
          Instagram
        </button>

        <button type="button" class="home-share-facebook" onclick="compartilharConviteCanal('facebook_post')" data-home-action="convite">
          <i class="bi bi-facebook"></i>
          Facebook
        </button>

        <button type="button" class="home-share-whatsapp" onclick="compartilharConviteCanal('whatsapp_status')" data-home-action="convite">
          <i class="bi bi-whatsapp"></i>
          WhatsApp
        </button>

        <button type="button" class="home-share-more" onclick="compartilharConviteCanal('share_nativo')" data-home-action="convite">
          <i class="bi bi-phone"></i>
          Mais opções
        </button>
      </div>
    </section>
  <?php endif; ?>
</main>

<?php
$footerActive = 'inicio';
require '/home/elab/app.elab.social/assets/footer/menu.php';
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<?php if ($instagramErro !== ''): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('modalInstagram');
    if (el && window.bootstrap) {
        const modal = new bootstrap.Modal(el);
        modal.show();
    }
});
</script>
<?php endif; ?>

<?php if ($facebookErro !== ''): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('modalFacebook');
    if (el && window.bootstrap) {
        const modal = new bootstrap.Modal(el);
        modal.show();
    }
});
</script>
<?php endif; ?>

<script src="<?= home_h($homeJsUrl) ?>"></script>

<script>
(function () {
  let missaoCheckTimer = null;
  let missaoCheckRunning = false;
  let lastCheckAt = 0;

  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }

  function qsa(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function truncate(value, max) {
    const text = String(value || '').trim();
    if (text.length <= max) {
      return text;
    }
    return text.slice(0, max - 3).trim() + '...';
  }

  function getMissaoCard() {
    return document.getElementById('cardMissaoAtual');
  }

  function getEstadoAtualId() {
    const card = getMissaoCard();
    return card ? (card.getAttribute('data-missao-estado-id') || '0') : '0';
  }

  function getCodigoAtual() {
    const card = getMissaoCard();
    return card ? (card.getAttribute('data-missao-codigo') || '') : '';
  }

  function setVerificando(isChecking) {
    const card = getMissaoCard();
    if (!card) {
      return;
    }

    card.classList.toggle('is-checking-mission', !!isChecking);
  }

  function atualizarHeader(data) {
    if (!data) {
      return;
    }

    if (data.nivel) {
      const faltamEl = qs('.home-start-progress-text strong');
      const atualEl = qs('.home-start-progress-meta span:first-child');
      const proximoEl = qs('.home-start-progress-meta span:last-child');
      const barEl = qs('.home-start-progress-bar i');
      const nivelEl = qs('.home-start-level-card strong');

      if (faltamEl) {
        faltamEl.textContent = data.nivel.xp_faltante > 0
          ? 'Faltam ' + Number(data.nivel.xp_faltante).toLocaleString('pt-BR') + ' XP'
          : 'Você já pode subir de nível';
      }

      if (atualEl) {
        atualEl.textContent = Number(data.nivel.xp_atual || 0).toLocaleString('pt-BR') + ' XP';
      }

      if (proximoEl) {
        proximoEl.textContent = Number(data.nivel.xp_proximo || 0).toLocaleString('pt-BR') + ' XP';
      }

      if (barEl) {
        barEl.style.width = Math.max(4, Math.min(100, Number(data.nivel.percent || 0))) + '%';
      }

      if (nivelEl) {
        const nivelAtualTela = parseInt(String(nivelEl.textContent || '0').replace(/\D+/g, ''), 10) || 0;
        const nivelApi = parseInt(data.nivel.numero || 0, 10) || 0;

        // Evita rebaixar visualmente o nível quando a API cair em fallback.
        // A renderização inicial PHP já vem da fonte correta do app.
        if (nivelApi > 0 && nivelApi >= nivelAtualTela) {
          nivelEl.textContent = String(nivelApi);
        }
      }
    }
  }

  function montarMissaoHtml(missao) {
    if (!missao) {
      return [
        '<section class="home-mission-card is-instagram" id="cardMissaoAtual" data-missao-estado-id="0" data-missao-codigo="">',
          '<h2 class="home-mission-title">',
            '<span class="home-mission-target home-mission-target-social"><img src="/assets/feed/statics/001-instagram.svg" alt="Instagram"></span>',
            '<span class="home-mission-copy">',
              '<strong class="home-mission-title-line"><span>Missão em preparação</span></strong>',
              '<small class="home-mission-subtitle">Assim que uma nova ação for liberada, ela aparece aqui.</small>',
            '</span>',
          '</h2>',
          '<a class="home-mission-post" href="/comunidade/social.php" data-home-action="missao">',
            '<div class="home-mission-post-bg"><img src="/assets/animations/teresa.webp" alt="" loading="eager"></div>',
            '<div class="home-mission-shade"></div>',
          '</a>',
          '<div class="home-mission-summary"><span>Post da missão</span><p>Volte em breve para novas ações da comunidade.</p></div>',
        '</section>'
      ].join('');
    }

    const target = missao.url_destino && !missao.url_destino.startsWith('/') ? ' target="_blank" rel="noopener noreferrer"' : '';

    return [
      '<section class="home-mission-card ' + escapeHtml(missao.network_class || 'is-instagram') + '" id="cardMissaoAtual"',
        ' data-missao-estado-id="' + escapeHtml(missao.estado_id || 0) + '"',
        ' data-missao-codigo="' + escapeHtml(missao.codigo || '') + '"',
        ' data-missao-post-id="' + escapeHtml(missao.post_id || 0) + '"',
        ' data-missao-expira-em="' + escapeHtml(missao.expira_em || '') + '">',
        '<h2 class="home-mission-title">',
          '<span class="home-mission-target home-mission-target-social">',
            '<img src="' + escapeHtml(missao.rede_icon || '/assets/feed/statics/001-instagram.svg') + '" alt="' + escapeHtml(missao.rede_label || '') + '">',
          '</span>',
          '<span class="home-mission-copy">',
            '<strong class="home-mission-title-line"><span>' + escapeHtml(missao.titulo || 'Apoie a Teresa neste post!') + '</span></strong>',
            '<small class="home-mission-subtitle">' + escapeHtml(missao.instrucao || '') + '</small>',
          '</span>',
        '</h2>',
        '<a class="home-mission-post" href="' + escapeHtml(missao.url_destino || '#') + '"' + target,
          ' data-home-action="missao"',
          ' data-missao-codigo="' + escapeHtml(missao.codigo || '') + '"',
          ' data-post-id="' + escapeHtml(missao.post_id || 0) + '"',
          ' data-post-url="' + escapeHtml(missao.url_destino || '') + '">',
          '<div class="home-mission-post-bg">',
            '<img src="' + escapeHtml(missao.imagem_url || '/assets/animations/teresa.webp') + '" alt="" loading="eager" onerror="this.src=\'/assets/animations/teresa.webp\';">',
          '</div>',
          '<div class="home-mission-shade"></div>',
          '<div class="home-mission-overlay" aria-hidden="true">',
            '<div class="home-mission-tap-frame">',
              '<div class="home-mission-tap-finger"><img src="/assets/animations/dedao.webp" alt=""></div>',
              '<div class="home-mission-tap-copy">',
                '<span>Toque na área marcada</span>',
                '<strong>' + escapeHtml(missao.overlay || '') + '</strong>',
              '</div>',
            '</div>',
          '</div>',
        '</a>',
        '<div class="home-mission-summary">',
          '<span>Post da missão</span>',
          '<p>' + escapeHtml(truncate(missao.caption || '', 180)) + '</p>',
        '</div>',
      '</section>'
    ].join('');
  }

  function bindHomeActions(root) {
    qsa('[data-home-action]', root).forEach(function (el) {
      if (el.dataset.boundHomeAction === '1') {
        return;
      }

      el.dataset.boundHomeAction = '1';

      el.addEventListener('click', function () {
        const action = el.getAttribute('data-home-action') || '';
        localStorage.setItem('elab_home_last_action', action);

        if (action === 'missao') {
          localStorage.setItem('elab_missao_aberta_em', String(Date.now()));
          iniciarVerificacaoMissao();
        }
      });
    });
  }

  function trocarCardMissao(missao) {
    const atual = getMissaoCard();
    if (!atual) {
      return;
    }

    const wrapper = document.createElement('div');
    wrapper.innerHTML = montarMissaoHtml(missao);
    const novo = wrapper.firstElementChild;

    if (!novo) {
      return;
    }

    atual.replaceWith(novo);
    bindHomeActions(novo);
    novo.classList.add('is-mission-updated');

    setTimeout(function () {
      novo.classList.remove('is-mission-updated');
    }, 900);
  }

  async function verificarMissaoAtual(force) {
    const now = Date.now();

    if (!force && now - lastCheckAt < 2500) {
      return;
    }

    if (missaoCheckRunning) {
      return;
    }

    lastCheckAt = now;
    missaoCheckRunning = true;
    setVerificando(true);

    try {
      const params = new URLSearchParams();
      params.set('estado_id_atual', getEstadoAtualId());
      params.set('codigo_atual', getCodigoAtual());
      params.set('_', String(Date.now()));

      const res = await fetch('/dashboard/api/missao-atual.php?' + params.toString(), {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
          'Accept': 'application/json'
        }
      });

      if (!res.ok) {
        return;
      }

      const data = await res.json();

      if (!data || !data.ok) {
        return;
      }

      atualizarHeader(data);

      if (data.changed) {
        trocarCardMissao(data.missao);
      }
    } catch (e) {
      console.warn('[HOME] Falha ao verificar missão atual', e);
    } finally {
      missaoCheckRunning = false;
      setVerificando(false);
    }
  }

  function iniciarVerificacaoMissao() {
    let tentativas = 0;

    clearInterval(missaoCheckTimer);

    setTimeout(function () {
      verificarMissaoAtual(true);
    }, 1200);

    missaoCheckTimer = setInterval(function () {
      tentativas += 1;
      verificarMissaoAtual(true);

      if (tentativas >= 12) {
        clearInterval(missaoCheckTimer);
      }
    }, 4000);
  }

  document.addEventListener('DOMContentLoaded', function () {
    bindHomeActions(document);

    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) {
        verificarMissaoAtual(false);
      }
    });

    window.addEventListener('focus', function () {
      verificarMissaoAtual(false);
    });

    const openedAt = Number(localStorage.getItem('elab_missao_aberta_em') || '0');
    if (openedAt > 0 && Date.now() - openedAt < 2 * 60 * 1000) {
      iniciarVerificacaoMissao();
    }
  });
})();
</script>

<div class="modal fade" id="modalInstagram" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-instagram-content">
      <form method="post">
        <input type="hidden" name="salvar_instagram" value="1">

        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold">Cadastrar Instagram</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body pt-2">
          <div class="modal-instagram-texto">
            Você pode digitar de qualquer forma:
            <strong>@usuario</strong>,
            <strong>usuario</strong>
            ou
            <strong>instagram.com/usuario</strong>
          </div>

          <div class="modal-instagram-texto mt-2" style="font-size:14px; opacity:.8;">
            Dica: toque em <strong>Ver meu perfil</strong>, copie seu @ e cole aqui.
          </div>

          <input
            type="text"
            name="instagram_input"
            id="instagram_input"
            class="form-control form-control-lg mt-3"
            placeholder="@seuinstagram"
            value="<?= home_h((string) ($_POST['instagram_input'] ?? '')) ?>"
            required
          >

          <?php if ($instagramErro !== ''): ?>
            <div class="alert alert-danger mt-3 mb-0">
              <?= home_h($instagramErro) ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="modal-footer border-0 pt-0 d-flex gap-2 flex-wrap justify-content-end">
          <a
            href="https://www.instagram.com/"
            target="_blank"
            rel="noopener"
            class="btn btn-outline-secondary"
          >
            Ver meu perfil
          </a>

          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>

          <button type="submit" class="btn btn-danger fw-bold">
            Salvar Instagram
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalFacebook" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-facebook-content">
      <form method="post">
        <input type="hidden" name="salvar_facebook" value="1">

        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold">Cadastrar Facebook</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body pt-2">
          <div class="modal-facebook-texto">
            Você pode digitar de qualquer forma:
            <strong>@usuario</strong>,
            <strong>usuario</strong>
            ou
            <strong>facebook.com/usuario</strong>
          </div>

          <div class="modal-facebook-texto mt-2" style="font-size:14px; opacity:.8;">
            Cole o link do seu perfil. Links com <strong>/share/</strong> não funcionam.
          </div>

          <input
            type="text"
            name="facebook_input"
            id="facebook_input"
            class="form-control form-control-lg mt-3"
            placeholder="@seufacebook"
            value="<?= home_h((string) ($_POST['facebook_input'] ?? '')) ?>"
            required
          >

          <?php if ($facebookErro !== ''): ?>
            <div class="alert alert-danger mt-3 mb-0">
              <?= home_h($facebookErro) ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="modal-footer border-0 pt-0 d-flex gap-2 flex-wrap justify-content-end">
          <a
            href="https://www.facebook.com/me"
            target="_blank"
            rel="noopener"
            class="btn btn-outline-secondary"
          >
            Ver meu perfil
          </a>

          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>

          <button type="submit" class="btn btn-primary fw-bold">
            Salvar Facebook
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="elab-toast-copy" id="elabToastCopy" aria-live="polite" aria-atomic="true">
  <div class="elab-toast-copy-inner">
    <div class="elab-toast-copy-icon">
      <i class="bi bi-check2-circle"></i>
    </div>
    <div class="elab-toast-copy-texts">
      <div class="elab-toast-copy-title" id="elabToastCopyTitle">Tudo certo!</div>
      <div class="elab-toast-copy-text" id="elabToastCopyText">Link copiado com sucesso.</div>
    </div>
  </div>
</div>

</body>
</html>

