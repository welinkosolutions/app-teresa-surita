<?php
declare(strict_types=1);

/**
 * ============================================================
 * CAMINHO: app.elab.social/index.php
 * Landing Mobile – white-label ready
 * ============================================================
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';
require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/tenant/bootstrap.php';

if (!empty($_SESSION['pessoa_id'])) {
    header('Location: /dashboard/index.php');
    exit;
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$tenant = null;
$tenantId = 0;

try {
    $tenant = tenant_get_active();
    $tenantId = (int)($tenant['id'] ?? 0);
    tenant_bootstrap_timezone();
} catch (Throwable $e) {
    $tenant = null;
    $tenantId = 0;
    date_default_timezone_set('America/Boa_Vista');
}

/*
|--------------------------------------------------------------------------
| FALLBACKS NEUTROS DO SISTEMA
|--------------------------------------------------------------------------
| Nunca usar nome/branding de cliente aqui.
| Isso serve só para evitar quebrar a página se faltar config.
|--------------------------------------------------------------------------
*/
$appName = (string) tenant_config_get('branding.app_name', 'Aplicativo', $tenantId);
$appTitle = (string) tenant_config_get('branding.app_title', $appName, $tenantId);
$themeColor = (string) tenant_config_get('branding.theme_color', '#38c3be', $tenantId);

$bgRadial1 = (string) tenant_config_get('branding.bg_radial_1', '#00879a', $tenantId);
$bgRadial2 = (string) tenant_config_get('branding.bg_radial_2', '#1aab65', $tenantId);
$bgLinearStart = (string) tenant_config_get('branding.bg_linear_start', '#38c3be', $tenantId);
$bgLinearEnd = (string) tenant_config_get('branding.bg_linear_end', '#1aab65', $tenantId);

$notifyIconColor = (string) tenant_config_get('branding.notify_icon_color', '#FFC83D', $tenantId);

$ctaBgStart = (string) tenant_config_get('branding.cta_bg_start', '#ffbb12', $tenantId);
$ctaBgEnd = (string) tenant_config_get('branding.cta_bg_end', '#f0d611', $tenantId);
$ctaTextColor = (string) tenant_config_get('branding.cta_text_color', '#3260b8', $tenantId);
$ctaTextHoverColor = (string) tenant_config_get('branding.cta_text_hover_color', '#f1476b', $tenantId);

$heroImageUrl = (string) tenant_config_get('branding.hero_image_url', '/assets/img/default-hero.png', $tenantId);
$logoFooterUrl = (string) tenant_config_get('branding.logo_footer_url', '/assets/img/default-logo-white.png', $tenantId);
$appleTouchIconUrl = (string) tenant_config_get('branding.apple_touch_icon_url', '/assets/img/default-icon-192.png', $tenantId);

$notifyTitleTyping = (string) tenant_config_get('landing.notify_title_typing', ($appName . ' está escrevendo...'), $tenantId);
$notifyTitleFinal = (string) tenant_config_get('landing.notify_title_final', ($appName . ' escreveu:'), $tenantId);
$notifyTextFinal = (string) tenant_config_get('landing.notify_text_final', 'Tem uma novidade para você. Acesse o app e confira.', $tenantId);

$loginUrl = (string) tenant_config_get('tenant.login_url', '/publico/login.php', $tenantId);
$ga4MeasurementId = (string) tenant_config_get('integrations.ga4_measurement_id', '', $tenantId);
$vapidPublicKey = (string) tenant_config_get('integrations.webpush_vapid_public_key', '', $tenantId);

$footerText = (string) tenant_config_get('branding.footer_text', 'Feito com ❤️ por', $tenantId);
$footerLinkLabel = (string) tenant_config_get('branding.footer_link_label', 'elab.social', $tenantId);
$footerLinkUrl = (string) tenant_config_get('branding.footer_link_url', '#', $tenantId);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php if ($ga4MeasurementId !== ''): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga4MeasurementId) ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '<?= e($ga4MeasurementId) ?>');
    </script>
    <?php endif; ?>

    <meta charset="UTF-8">
    <title><?= e($appTitle) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="<?= e($themeColor) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="apple-touch-icon" href="<?= e($appleTouchIconUrl) ?>">

<style>
:root {
    --vh: 1vh;
    --bg-radial-1: <?= e($bgRadial1) ?>;
    --bg-radial-2: <?= e($bgRadial2) ?>;
    --bg-linear-start: <?= e($bgLinearStart) ?>;
    --bg-linear-end: <?= e($bgLinearEnd) ?>;
    --notify-icon-color: <?= e($notifyIconColor) ?>;
    --cta-bg-start: <?= e($ctaBgStart) ?>;
    --cta-bg-end: <?= e($ctaBgEnd) ?>;
    --cta-text-color: <?= e($ctaTextColor) ?>;
    --cta-text-hover-color: <?= e($ctaTextHoverColor) ?>;
}
* { box-sizing: border-box; }

html, body {
    width: 100%;
    height: 100%;
}

body {
    margin: 0;
    min-height: calc(var(--vh) * 100);
    overflow: hidden;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    background:
        radial-gradient(circle at 20% 20%, var(--bg-radial-1) 0%, transparent 55%),
        radial-gradient(circle at 80% 80%, var(--bg-radial-2) 0%, transparent 60%),
        linear-gradient(180deg, var(--bg-linear-start) 0%, var(--bg-linear-end) 100%);
    animation: bgMove 18s ease-in-out infinite;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

@keyframes bgMove {
    0%   { background-position: 0% 0%, 100% 100%, 0% 0%; }
    50%  { background-position: 10% 6%, 90% 94%, 0% 50%; }
    100% { background-position: 0% 0%, 100% 100%, 0% 0%; }
}

#app {
    min-height: calc(var(--vh) * 100);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 22px 20px 18px;
}

.notify {
    background: rgba(255,255,255,.98);
    border-radius: 18px;
    padding: 14px 16px;
    display: flex;
    gap: 12px;
    align-items: flex-start;
    box-shadow: 0 18px 40px rgba(0,0,0,.22);
}

.notify strong {
    font-weight: 700;
    font-size: 14.5px;
}

.notify p {
    margin: 6px 0 0;
    font-size: 14px;
    line-height: 1.4;
}

.notify-icon {
    font-size: 22px;
    color: var(--notify-icon-color);
    animation: bell 2.4s ease-in-out infinite;
    transform-origin: top center;
}

@keyframes bell {
    0% { transform: rotate(0); }
    6% { transform: rotate(10deg); }
    12% { transform: rotate(-10deg); }
    18% { transform: rotate(8deg); }
    24% { transform: rotate(-8deg); }
    30% { transform: rotate(0); }
    100% { transform: rotate(0); }
}

.hero {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hero img {
    width: 100%;
    max-width: 360px;
    max-height: 56vh;
    object-fit: contain;
    filter: drop-shadow(0 18px 28px rgba(0,0,0,.28));
}

.cta {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    margin-top: 8px;
}

.btn-main {
    width: 80%;
    max-width: 360px;
    padding: 14px;
    font-size: 16.5px;
    font-weight: 800;
    border-radius: 14px;
    border: none;
    background: linear-gradient(135deg, var(--cta-bg-start), var(--cta-bg-end));
    color: var(--cta-text-color) !important;
    box-shadow: 0 10px 22px rgba(0,0,0,.26);
    animation: breathe 3s infinite;
    text-align: center;
    text-decoration: none;
}

.btn-main:active,
.btn-main:focus,
.btn-main:hover {
    color: var(--cta-text-hover-color) !important;
    text-decoration: none;
}

@keyframes breathe {
    0% { transform: scale(1); }
    50% { transform: scale(1.03); }
    100% { transform: scale(1); }
}

.assinatura {
    text-align: center;
    margin-top: 14px;
}

.assinatura img {
    width: 190px;
}

.footer {
    text-align: center;
    font-size: 12px;
    color: rgba(255,255,255,.95);
    margin-top: 10px;
}

#desktop-block {
    display: none;
    height: 100vh;
    background: #f7f7f7;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 30px;
}

#desktop-block i {
    font-size: 64px;
    color: var(--bg-linear-start);
}
</style>
</head>

<body>

<div id="app">
    <div class="notify">
        <i class="bi bi-bell-fill notify-icon"></i>
        <div>
            <strong id="notify-title"><?= e($notifyTitleTyping) ?></strong>
            <p id="notify-text"></p>
        </div>
    </div>

    <div class="hero">
        <img src="<?= e($heroImageUrl) ?>" alt="<?= e($appName) ?>">
    </div>

    <div class="cta">
        <a href="<?= e($loginUrl) ?>" class="btn btn-main">
            CLIQUE PARA ACESSAR
        </a>

        <button type="button" id="btn-install" class="btn btn-main" style="display:none;">
            INSTALAR APP
        </button>
    </div>

    <div class="assinatura">
        <img src="<?= e($logoFooterUrl) ?>" alt="<?= e($appName) ?>">
    </div>

    <div class="footer">
        <?= e($footerText) ?>
        <a href="<?= e($footerLinkUrl) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;">
            <?= e($footerLinkLabel) ?>
        </a>
    </div>
</div>

<div id="desktop-block">
    <div>
        <i class="bi bi-phone"></i>
        <h4>Aplicativo disponível apenas no celular</h4>
        <p>Acesse pelo seu smartphone.</p>
    </div>
</div>

<script>
const ELAB_VAPID_PUBLIC_KEY = <?= json_encode($vapidPublicKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const ELAB_NOTIFY_TITLE_FINAL = <?= json_encode($notifyTitleFinal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const ELAB_NOTIFY_TEXT_FINAL = <?= json_encode($notifyTextFinal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function setVH() {
    document.documentElement.style.setProperty('--vh', (window.innerHeight * 0.01) + 'px');
}
setVH();
window.addEventListener('resize', setVH);

function elabReadNativeDevice() {
    try {
        if (window.ELAB_NATIVE_DEVICE_ID && window.ELAB_NATIVE_PLATFORM) {
            return {
                device_id: String(window.ELAB_NATIVE_DEVICE_ID),
                platform: String(window.ELAB_NATIVE_PLATFORM)
            };
        }

        if (window.ELAB_NATIVE_DEVICE_ID) {
            return {
                device_id: String(window.ELAB_NATIVE_DEVICE_ID),
                platform: 'android_webview'
            };
        }

        if (window.AndroidDevice && typeof window.AndroidDevice.getDeviceId === 'function') {
            const id = window.AndroidDevice.getDeviceId();
            if (id) {
                return {
                    device_id: String(id),
                    platform: 'android_webview'
                };
            }
        }

        if (
            window.webkit &&
            window.webkit.messageHandlers &&
            window.webkit.messageHandlers.elabDevice
        ) {
            return {
                device_id: '',
                platform: 'ios_webview'
            };
        }
    } catch (e) {}

    return null;
}

function elabGetBrowserDevice() {
    let deviceId = '';

    try {
        deviceId = localStorage.getItem('ELAB_DEVICE_ID') || '';
    } catch (e) {
        deviceId = '';
    }

    if (!deviceId) {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            deviceId = 'dev_' + window.crypto.randomUUID();
        } else {
            deviceId = 'dev_' + Date.now() + '_' + Math.random().toString(36).slice(2, 12);
        }

        try {
            localStorage.setItem('ELAB_DEVICE_ID', deviceId);
        } catch (e) {}
    }

    return {
        device_id: deviceId,
        platform: 'pwa'
    };
}

function elabResolveDevice() {
    const nativeDevice = elabReadNativeDevice();
    if (nativeDevice && nativeDevice.device_id) {
        return nativeDevice;
    }
    return elabGetBrowserDevice();
}

window.ELAB_DEVICE = elabResolveDevice();

const textEl = document.getElementById('notify-text');
const titleEl = document.getElementById('notify-title');

let dots = 0;
const typing = setInterval(() => {
    dots = (dots + 1) % 4;
    textEl.textContent = 'digitando' + '.'.repeat(dots);
}, 500);

setTimeout(() => {
    clearInterval(typing);
    titleEl.textContent = ELAB_NOTIFY_TITLE_FINAL;
    textEl.textContent = ELAB_NOTIFY_TEXT_FINAL;
}, 2600);

let deferredPrompt = null;
const btnInstall = document.getElementById('btn-install');

function isInstalled() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

function isMobileBrowser() {
    const ua = navigator.userAgent || '';
    const isMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(ua);
    const platform = (window.ELAB_DEVICE && window.ELAB_DEVICE.platform) ? window.ELAB_DEVICE.platform : 'pwa';
    return isMobile && platform === 'pwa';
}

function showInstallButtonIfEligible() {
    if (!btnInstall) return;
    if (!isMobileBrowser()) return;
    if (isInstalled()) return;

    if (deferredPrompt) {
        btnInstall.style.display = 'block';
        return;
    }

    const isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent || '');
    const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent || '');

    if (isIOS && isSafari) {
        btnInstall.style.display = 'block';
        btnInstall.dataset.mode = 'ios';
    }
}

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;

    if (isMobileBrowser() && !isInstalled()) {
        btnInstall.style.display = 'block';
        btnInstall.dataset.mode = 'native';
    }
});

btnInstall.addEventListener('click', async () => {
    if (btnInstall.dataset.mode === 'ios') {
        alert('No iPhone, toque em Compartilhar e depois em "Adicionar à Tela de Início".');
        return;
    }

    if (!deferredPrompt) {
        return;
    }

    deferredPrompt.prompt();
    try {
        await deferredPrompt.userChoice;
    } catch (e) {}

    deferredPrompt = null;
    btnInstall.style.display = 'none';
});

window.addEventListener('appinstalled', () => {
    btnInstall.style.display = 'none';
});

showInstallButtonIfEligible();

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
}

async function elabPostPushRegister(payload) {
    try {
        const response = await fetch('/endpoint/push_register.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        });

        return await response.json();
    } catch (e) {
        return {
            ok: false,
            erro: 'network_error'
        };
    }
}

function elabGetPermissionState() {
    if (typeof Notification === 'undefined') {
        return 'default';
    }

    const p = String(Notification.permission || 'default').toLowerCase();
    if (p === 'granted' || p === 'denied') {
        return p;
    }

    return 'default';
}

async function elabAskNotificationPermission() {
    if (!window.ELAB_DEVICE || window.ELAB_DEVICE.platform !== 'pwa') {
        return elabGetPermissionState();
    }

    if (typeof Notification === 'undefined') {
        return 'default';
    }

    let permission = elabGetPermissionState();

    if (permission === 'default') {
        try {
            permission = await Notification.requestPermission();
        } catch (e) {
            permission = elabGetPermissionState();
        }
    }

    return permission;
}

async function elabRegisterPushBase() {
    if (!window.ELAB_DEVICE || !window.ELAB_DEVICE.device_id) {
        return;
    }

    await elabPostPushRegister({
        device_id: window.ELAB_DEVICE.device_id,
        platform: window.ELAB_DEVICE.platform || 'pwa',
        push_permissao: elabGetPermissionState(),
        push_provider: window.ELAB_DEVICE.platform === 'pwa' ? 'webpush' : '',
        push_token: '',
        push_subscription_json: ''
    });
}

async function elabRegisterWebPushSubscription() {
    if (!window.ELAB_DEVICE || window.ELAB_DEVICE.platform !== 'pwa') {
        return;
    }

    if (!('serviceWorker' in navigator)) {
        await elabRegisterPushBase();
        return;
    }

    if (!('PushManager' in window)) {
        await elabRegisterPushBase();
        return;
    }

    let registration = null;

    try {
        registration = await navigator.serviceWorker.register('/service-worker.js');
        await navigator.serviceWorker.ready;
    } catch (e) {
        console.error('ERRO_REGISTER_SW', e);
        await elabRegisterPushBase();
        return;
    }

    const permission = elabGetPermissionState();

    if (permission !== 'granted') {
        await elabPostPushRegister({
            device_id: window.ELAB_DEVICE.device_id,
            platform: window.ELAB_DEVICE.platform || 'pwa',
            push_permissao: permission,
            push_provider: 'webpush',
            push_token: '',
            push_subscription_json: ''
        });
        return;
    }

    if (!ELAB_VAPID_PUBLIC_KEY) {
        await elabPostPushRegister({
            device_id: window.ELAB_DEVICE.device_id,
            platform: window.ELAB_DEVICE.platform || 'pwa',
            push_permissao: permission,
            push_provider: 'webpush',
            push_token: '',
            push_subscription_json: ''
        });
        return;
    }

    try {
        let subscription = await registration.pushManager.getSubscription();

        if (!subscription) {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(ELAB_VAPID_PUBLIC_KEY)
            });
        }

        await elabPostPushRegister({
            device_id: window.ELAB_DEVICE.device_id,
            platform: window.ELAB_DEVICE.platform || 'pwa',
            push_permissao: permission,
            push_provider: 'webpush',
            push_token: subscription && subscription.endpoint ? subscription.endpoint : '',
            push_subscription_json: subscription ? subscription.toJSON() : ''
        });
    } catch (e) {
        console.error('ERRO_SUBSCRIBE_PUSH', e);

        await elabPostPushRegister({
            device_id: window.ELAB_DEVICE.device_id,
            platform: window.ELAB_DEVICE.platform || 'pwa',
            push_permissao: elabGetPermissionState(),
            push_provider: 'webpush',
            push_token: '',
            push_subscription_json: JSON.stringify({
                erro_subscribe: true,
                message: e && e.message ? e.message : String(e),
                name: e && e.name ? e.name : '',
                stack: e && e.stack ? e.stack : ''
            })
        });
    }
}

async function elabBootPush() {
    await elabRegisterPushBase();

    if (window.ELAB_DEVICE && window.ELAB_DEVICE.platform === 'pwa') {
        await elabAskNotificationPermission();
        await elabRegisterWebPushSubscription();
    }
}

window.addEventListener('load', () => {
    setTimeout(() => {
        elabBootPush();
    }, 800);
});
</script>

</body>
</html>