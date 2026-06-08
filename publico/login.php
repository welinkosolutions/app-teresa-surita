<?php
/**
 * ============================================================
 * CAMINHO: app.elab.social/publico/login.php
 * NOME: Login – Acesso Seguro (APP)
 * DESCRIÇÃO:
 * - Login mobile
 * - Mantém identidade híbrida do device:
 *   PWA mobile / Android WebView / iOS WebView
 * - Envia device_id e platform para o login_exec.php
 * - Registra status de push no backend
 * - Solicita permissão de notificação quando necessário
 * - Tenta criar subscription real do Web Push
 * ============================================================
 */

declare(strict_types=1);

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
    $tenantId = (int) ($tenant['id'] ?? 0);
    tenant_bootstrap_timezone();
} catch (Throwable $e) {
    $tenant = null;
    $tenantId = 0;
    date_default_timezone_set('America/Boa_Vista');
}

/*
|--------------------------------------------------------------------------
| MENSAGENS DE ERRO
|--------------------------------------------------------------------------
*/
$erro = $_GET['erro'] ?? '';
$msg  = '';

$loginErrorInvalid = (string) tenant_config_get('login.error.invalid_credentials', 'Informe telefone e senha válidos.', $tenantId);
$loginErrorPhoneNotFound = (string) tenant_config_get('login.error.phone_not_found', 'Telefone não encontrado.', $tenantId);
$loginErrorInvalidPin = (string) tenant_config_get('login.error.invalid_pin', 'Senha incorreta.', $tenantId);
$loginErrorBlocked = (string) tenant_config_get('login.error.blocked', 'Acesso temporariamente bloqueado.', $tenantId);

switch ($erro) {
    case '1':
        $msg = $loginErrorInvalid;
        break;
    case '2':
        $msg = $loginErrorPhoneNotFound;
        break;
    case 'pin':
        $msg = $loginErrorInvalidPin;
        break;
    case 'bloqueado':
        $msg = $loginErrorBlocked;
        break;
}

/*
|--------------------------------------------------------------------------
| FALLBACKS NEUTROS DO SISTEMA
|--------------------------------------------------------------------------
*/
$appTitle = (string) tenant_config_get('branding.app_title_login', 'Aplicativo · Acesso', $tenantId);
$heroImageUrl = (string) tenant_config_get('branding.login_hero_image_url', '/assets/img/default-login-hero.png', $tenantId);

$bgRadial1 = (string) tenant_config_get('branding.login_bg_radial_1', '#47d2c5', $tenantId);
$bgRadial2 = (string) tenant_config_get('branding.login_bg_radial_2', '#7ed6a3', $tenantId);
$bgLinearStart = (string) tenant_config_get('branding.login_bg_linear_start', '#38c3be', $tenantId);
$bgLinearEnd = (string) tenant_config_get('branding.login_bg_linear_end', '#7ed6a3', $tenantId);

$inputFocusColor = (string) tenant_config_get('branding.login_input_focus_color', '#169fa9', $tenantId);
$buttonBgStart = (string) tenant_config_get('branding.login_button_bg_start', '#0b6e7a', $tenantId);
$buttonBgEnd = (string) tenant_config_get('branding.login_button_bg_end', '#169fa9', $tenantId);
$linkColor = (string) tenant_config_get('branding.login_link_color', '#0b6e7a', $tenantId);

$secureTitle = (string) tenant_config_get('login.secure_title', 'Acesso seguro', $tenantId);
$labelPhone = (string) tenant_config_get('login.label_phone', 'WhatsApp', $tenantId);
$labelPassword = (string) tenant_config_get('login.label_password', 'Senha', $tenantId);
$placeholderPhone = (string) tenant_config_get('login.placeholder_phone', 'Digite aqui seu WhatsApp', $tenantId);
$placeholderPassword = (string) tenant_config_get('login.placeholder_password', 'Digite aqui sua senha', $tenantId);
$buttonEnter = (string) tenant_config_get('login.button_enter', 'Entrar', $tenantId);
$linkSignupLabel = (string) tenant_config_get('login.link_signup_label', 'Cadastre-se', $tenantId);
$linkForgotPasswordLabel = (string) tenant_config_get('login.link_forgot_password_label', 'Esqueci minha Senha', $tenantId);
$linkBackLabel = (string) tenant_config_get('login.link_back_label', 'VOLTAR', $tenantId);

$signupUrl = (string) tenant_config_get('login.signup_url', '/invite/novo.php', $tenantId);
$forgotPasswordUrl = (string) tenant_config_get('login.forgot_password_url', '/publico/esqueci-pin.php', $tenantId);
$backUrl = (string) tenant_config_get('login.back_url', '/index.php', $tenantId);

$vapidPublicKey = (string) tenant_config_get('integrations.webpush_vapid_public_key', '', $tenantId);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title><?= e($appTitle) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root {
    --vh: 1vh;
    --login-bg-radial-1: <?= e($bgRadial1) ?>;
    --login-bg-radial-2: <?= e($bgRadial2) ?>;
    --login-bg-linear-start: <?= e($bgLinearStart) ?>;
    --login-bg-linear-end: <?= e($bgLinearEnd) ?>;
    --login-input-focus-color: <?= e($inputFocusColor) ?>;
    --login-button-bg-start: <?= e($buttonBgStart) ?>;
    --login-button-bg-end: <?= e($buttonBgEnd) ?>;
    --login-link-color: <?= e($linkColor) ?>;
}
* { box-sizing: border-box; }

html, body {
    width: 100%;
    height: 100%;
}

body {
    margin: 0;
    min-height: calc(var(--vh) * 100);
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    background:
        radial-gradient(circle at 20% 20%, var(--login-bg-radial-1) 0%, transparent 55%),
        radial-gradient(circle at 80% 80%, var(--login-bg-radial-2) 0%, transparent 60%),
        linear-gradient(180deg, var(--login-bg-linear-start) 0%, var(--login-bg-linear-end) 100%);
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

#app {
    min-height: calc(var(--vh) * 100);
    display: flex;
    flex-direction: column;
    padding: 22px 18px 90px;
}

.top {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #fff;
    font-weight: 700;
    font-size: 15px;
}

.hero {
    display: flex;
    justify-content: center;
    margin: 14px 0 40px;
}

.hero img {
    width: 100%;
    max-width: 330px;
    max-height: 56vh;
    object-fit: contain;
    filter: drop-shadow(0 26px 38px rgba(0,0,0,.32));
}

.form-card {
    background: rgba(255,255,255,.97);
    border-radius: 18px;
    padding: 18px;
    box-shadow: 0 20px 40px rgba(0,0,0,.25);
}

.label {
    font-weight: 700;
    font-size: 14.5px;
    margin-bottom: 6px;
    color: #333;
}

.input-main {
    width: 100%;
    padding: 14px 16px;
    border-radius: 14px;
    border: 1px solid #ddd;
    font-size: 16px;
    margin-bottom: 10px;
    outline: none;
}

.input-main:focus {
    border-color: var(--login-input-focus-color);
    box-shadow: 0 0 0 3px rgba(22,159,169,.12);
}

.pin-wrapper {
    position: relative;
}

.pin-wrapper i {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 18px;
    color: #777;
    cursor: pointer;
}

.btn-main {
    width: 100%;
    margin-top: 10px;
    padding: 14px;
    font-size: 16px;
    font-weight: 800;
    border-radius: 14px;
    border: none;
    background: linear-gradient(135deg, var(--login-button-bg-start), var(--login-button-bg-end));
    color: #fff;
    box-shadow: 0 10px 22px rgba(0,0,0,.22);
}

.links {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-top: 12px;
    font-size: 14px;
}

.links a {
    color: var(--login-link-color);
    font-weight: 700;
    text-decoration: none;
}

.msg {
    text-align: center;
    margin-top: 12px;
    font-size: 14px;
    color: #b00020;
}

.footer-app {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    padding: 12px 16px calc(12px + env(safe-area-inset-bottom));
    background: rgba(255,255,255,.92);
    backdrop-filter: blur(6px);
    box-shadow: 0 -6px 18px rgba(0,0,0,.18);
}

.footer-app a {
    display: flex;
    justify-content: center;
    gap: 6px;
    font-weight: 700;
    color: var(--login-link-color);
    text-decoration: none;
}
</style>
</head>

<body>

<div id="app">

    <div class="top">
        <i class="bi bi-lock-fill"></i>
        <span><?= e($secureTitle) ?></span>
    </div>

    <div class="hero">
        <img src="<?= e($heroImageUrl) ?>" alt="<?= e($appTitle) ?>">
    </div>

    <div class="form-card">

        <form method="post" action="/endpoint/login_exec.php" autocomplete="off">
            <input type="hidden" name="device_id" id="device_id" value="">
            <input type="hidden" name="platform" id="platform" value="">

            <div class="label"><?= e($labelPhone) ?></div>
            <input
                type="tel"
                name="telefone"
                id="telefone"
                class="input-main"
                placeholder="<?= e($placeholderPhone) ?>"
                inputmode="numeric"
                required
            >

            <div class="label"><?= e($labelPassword) ?></div>
            <div class="pin-wrapper">
                <input
                    type="password"
                    name="pin"
                    id="pin"
                    class="input-main"
                    placeholder="<?= e($placeholderPassword) ?>"
                    maxlength="4"
                    inputmode="numeric"
                    required
                >
                <i class="bi bi-eye" id="togglePin"></i>
            </div>

            <button type="submit" class="btn-main">
                <?= e($buttonEnter) ?>
            </button>

            <?php if ($msg): ?>
                <div class="msg"><?= e($msg) ?></div>
            <?php endif; ?>

            <div class="links">
                <a href="<?= e($signupUrl) ?>"><?= e($linkSignupLabel) ?></a>
                <a href="<?= e($forgotPasswordUrl) ?>"><?= e($linkForgotPasswordLabel) ?></a>
            </div>
        </form>

    </div>

</div>

<div class="footer-app">
    <a href="<?= e($backUrl) ?>">
        <i class="bi bi-chevron-left"></i>
        <?= e($linkBackLabel) ?>
    </a>
</div>

<script>
const ELAB_VAPID_PUBLIC_KEY = <?= json_encode($vapidPublicKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

/* ===== VIEWPORT FIX ===== */
function setVH() {
    document.documentElement.style.setProperty('--vh', (window.innerHeight * 0.01) + 'px');
}
setVH();
window.addEventListener('resize', setVH);

/* ===== DEVICE HYBRID IDENTITY ===== */
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

const deviceIdField = document.getElementById('device_id');
const platformField = document.getElementById('platform');

if (deviceIdField && platformField && window.ELAB_DEVICE) {
    deviceIdField.value = window.ELAB_DEVICE.device_id || '';
    platformField.value = window.ELAB_DEVICE.platform || 'pwa';
}

/* ===== PUSH REGISTER ===== */
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

/* ===== MÁSCARA TELEFONE (BACKSPACE LIVRE) ===== */
const tel = document.getElementById('telefone');

function formatTelefone(value) {
    const d = value.replace(/\D/g, '').slice(0, 11);

    if (!d) return '';

    if (d.length <= 2) {
        return '(' + d;
    }
    if (d.length <= 6) {
        return '(' + d.slice(0, 2) + ') ' + d.slice(2);
    }
    if (d.length <= 10) {
        return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
    }

    return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
}

let lastTel = '';

tel.addEventListener('input', (e) => {
    const cur = e.target.value;

    if (cur.length < lastTel.length) {
        lastTel = cur;
        return;
    }

    const formatted = formatTelefone(cur);
    lastTel = formatted;
    e.target.value = formatted;
});

/* ===== SHOW / HIDE PIN ===== */
const pin = document.getElementById('pin');
const toggle = document.getElementById('togglePin');

toggle.addEventListener('click', () => {
    if (pin.type === 'password') {
        pin.type = 'text';
        toggle.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        pin.type = 'password';
        toggle.classList.replace('bi-eye-slash', 'bi-eye');
    }
});

window.addEventListener('load', () => {
    setTimeout(() => {
        elabBootPush();
    }, 600);
});
</script>

</body>
</html>