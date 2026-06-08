<?php
/**
 * ============================================================
 * CAMINHO: v3.elab.social/instalar.php
 * NOME: Central de Instalação Inteligente
 * DESCRIÇÃO:
 * - Android: instala a PWA quando houver prompt real
 * - Fallback Android: abre no Chrome / mostra instrução
 * - iPhone: abre a home e orienta Safari > Compartilhar
 * - Desktop: usa QR Code para cair no fluxo certo
 * - Fluxo visual simplificado para usuários leigos
 * ============================================================
 */

declare(strict_types=1);

date_default_timezone_set('America/Boa_Vista');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'v3.elab.social';

$indexUrl       = $scheme . '://' . $host . '/index.php?source=pwa';
$installPageUrl = $scheme . '://' . $host . '/instalar.php';
$qrTargetUrl    = $installPageUrl . '?via=qr';

/*
|--------------------------------------------------------------------------
| QR dinâmico
|--------------------------------------------------------------------------
*/
$qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=360x360&data=' . urlencode($qrTargetUrl);

/*
|--------------------------------------------------------------------------
| Chrome intent para Android
|--------------------------------------------------------------------------
*/
$chromeIntentUrl = 'intent://' . $host . '/index.php?source=pwa#Intent;scheme=https;package=com.android.chrome;end';

/*
|--------------------------------------------------------------------------
| Links de loja (preencher depois, se quiser)
|--------------------------------------------------------------------------
*/
const PLAY_STORE_URL = '';
const APP_STORE_URL  = '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Instalar app | Teresa Surita</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#38c3be">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Teresa">

    <link rel="manifest" href="/manifest.json">

    <style>
        :root{
            --vh: 1vh;
            --bg1:#38c3be;
            --bg2:#7ed6a3;
            --card:rgba(255,255,255,.97);
            --text:#1f2a33;
            --muted:#5e6f78;
            --brand1:#0b6e7a;
            --brand2:#169fa9;
            --white:#fff;
            --soft:rgba(11,110,122,.08);
            --soft2:rgba(11,110,122,.12);
            --line:rgba(11,110,122,.10);
            --shadow:0 18px 40px rgba(0,0,0,.18);
            --shadowBtn:0 10px 22px rgba(0,0,0,.18);
            --radiusXl:28px;
            --radiusLg:18px;
            --radiusMd:14px;
        }

        *{ box-sizing:border-box; }

        html,body{
            margin:0;
            padding:0;
            min-height:100%;
            font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
            color:var(--text);
            background:
                radial-gradient(circle at 20% 20%, #47d2c5 0%, transparent 55%),
                radial-gradient(circle at 80% 80%, #7ed6a3 0%, transparent 60%),
                linear-gradient(180deg, var(--bg1) 0%, var(--bg2) 100%);
            animation:bgMove 18s ease-in-out infinite;
        }

        body{
            min-height:calc(var(--vh) * 100);
        }

        @keyframes bgMove{
            0%   { background-position:0% 0%, 100% 100%, 0% 0%; }
            50%  { background-position:10% 6%, 90% 94%, 0% 50%; }
            100% { background-position:0% 0%, 100% 100%, 0% 0%; }
        }

        .page{
            min-height:calc(var(--vh) * 100);
            display:flex;
            align-items:center;
            justify-content:center;
            padding:22px 16px;
        }

        .card{
            width:100%;
            max-width:460px;
            background:var(--card);
            border-radius:var(--radiusXl);
            box-shadow:var(--shadow);
            padding:22px 16px 20px;
        }

        .chip{
            display:inline-flex;
            align-items:center;
            gap:8px;
            font-size:12px;
            font-weight:800;
            color:var(--brand1);
            background:var(--soft2);
            border-radius:999px;
            padding:8px 12px;
            margin:0 auto 14px;
        }

        .chip-wrap{
            text-align:center;
        }

        .title{
            margin:0;
            text-align:center;
            font-size:20px;
            line-height:1.08;
        }

        .subtitle{
            margin:12px auto 18px;
            max-width:340px;
            text-align:center;
            font-size:15px;
            line-height:1.55;
            color:var(--muted);
        }

        .chooser-title{
            margin:0 0 10px;
            font-size:14px;
            font-weight:900;
            text-align:center;
            color:var(--text);
        }

        .platform-grid{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:10px;
            margin-bottom:14px;
        }

        .platform-card{
            border:none;
            border-radius:var(--radiusLg);
            padding:16px 14px;
            background:var(--soft);
            text-align:left;
            cursor:pointer;
            transition:.18s ease;
        }

        .platform-card.active{
            outline:2px solid rgba(11,110,122,.14);
            background:rgba(11,110,122,.10);
            transform:translateY(-1px);
        }

        .platform-card:active{
            transform:scale(.99);
        }

        .platform-title{
            display:flex;
            align-items:center;
            gap:8px;
            font-size:16px;
            font-weight:900;
            margin:0 0 6px;
        }

        .platform-desc{
            font-size:13px;
            line-height:1.5;
            color:var(--muted);
            margin:0;
        }

        .actions{
            display:flex;
            flex-direction:column;
            gap:10px;
            margin:8px 0 14px;
        }

        .btn{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            width:100%;
            min-height:54px;
            padding:14px 16px;
            border:none;
            border-radius:var(--radiusMd);
            text-decoration:none;
            font-size:16px;
            font-weight:900;
            cursor:pointer;
        }

        .btn-primary{
            background:linear-gradient(135deg, var(--brand1), var(--brand2));
            color:var(--white);
            box-shadow:var(--shadowBtn);
        }

        .btn-soft{
            background:var(--soft);
            color:var(--brand1);
        }

        .btn-ghost{
            background:transparent;
            color:var(--brand1);
            border:1px solid var(--line);
        }

        .btn[disabled]{
            opacity:.65;
            cursor:not-allowed;
        }

        .flow-box{
            margin-top:12px;
            border-radius:var(--radiusLg);
            padding:14px;
            background:var(--soft);
        }

        .flow-title{
            margin:0 0 8px;
            font-size:14px;
            font-weight:900;
            color:var(--text);
        }

        .flow-steps{
            margin:0;
            padding-left:18px;
            color:var(--muted);
            font-size:13px;
            line-height:1.65;
        }

        .status{
            margin-top:12px;
            border-radius:var(--radiusLg);
            padding:14px;
            background:rgba(11,110,122,.08);
            color:var(--brand1);
            font-size:13px;
            font-weight:800;
        }

        .section{
            margin-top:16px;
            padding-top:14px;
            border-top:1px solid var(--line);
        }

        .section-title{
            margin:0 0 10px;
            font-size:14px;
            font-weight:900;
            color:var(--text);
            text-align:center;
        }

        .qr-wrap{
            display:flex;
            justify-content:center;
            margin:10px 0 8px;
        }

        .qr-box{
            background:#fff;
            border-radius:22px;
            padding:14px;
            box-shadow:0 10px 24px rgba(0,0,0,.10);
        }

        .qr-box img{
            display:block;
            width:220px;
            max-width:100%;
            height:auto;
            border-radius:10px;
        }

        .qr-caption{
            text-align:center;
            font-size:13px;
            line-height:1.5;
            color:var(--muted);
            margin-top:10px;
        }

        .stores{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:10px;
            margin-top:10px;
        }
        
        .platform-title{
    display:flex;
    align-items:center;
    gap:8px;
    font-size:16px;
    font-weight:900;
    margin:0 0 6px;
}

.platform-icon{
    width:22px;
    height:22px;
    object-fit:contain;
    flex:0 0 22px;
}

        .hidden{
            display:none !important;
        }

        .footer-link{
            display:block;
            margin-top:16px;
            text-align:center;
            font-size:13px;
            font-weight:800;
            color:var(--brand1);
            text-decoration:none;
        }

        @media (max-width:420px){
            .platform-grid,
            .stores{
                grid-template-columns:1fr;
            }
        }
    </style>
</head>
<body>

<div class="page">
    <div class="card">
        <div class="chip-wrap">
            <div class="chip">INSTALAR O APLICATIVO</div>
        </div>

        <h1 class="title">Leve a Teresa Surita com você.</h1>

        <div class="subtitle">
            
        </div>

        <div class="chooser-title">Escolha seu aparelho ou leia o QR Code abaixo</div>

       <div class="platform-grid">
    <button type="button" class="platform-card" id="cardAndroid">
        <div class="platform-title">
            <img src="/assets/icon/android.png" alt="Android" class="platform-icon">
            <span>Android</span>
        </div>
        <p class="platform-desc">
            Instala o app no seu celular. Se o navegador pedir permissão, é só confirmar.
        </p>
    </button>

    <button type="button" class="platform-card" id="cardIOS">
        <div class="platform-title">
            <img src="/assets/icon/ios.png" alt="iPhone iOS" class="platform-icon">
            <span>iPhone / iOS</span>
        </div>
        <p class="platform-desc">
            Abre o app e te mostra como colocar o ícone na tela inicial do iPhone.
        </p>
    </button>
</div>
        <div class="actions">
            <button type="button" id="btnPrimary" class="btn btn-primary hidden">
                INSTALAR NO ANDROID
            </button>

            <a href="<?= htmlspecialchars($indexUrl) ?>" id="btnSecondary" class="btn btn-soft hidden">
                ABRIR NO IOS
            </a>

            <a href="<?= htmlspecialchars($chromeIntentUrl) ?>" id="btnChrome" class="btn btn-ghost hidden">
                ABRIR NO CHROME
            </a>
        </div>

        <div id="statusBox" class="status hidden"></div>

        <div id="androidFlow" class="flow-box hidden">
            <div class="flow-title">Como instalar no Android</div>
            <ol class="flow-steps">
                <li>Toque em <strong>Instalar no Android</strong>.</li>
                <li>Se aparecer a confirmação, toque em <strong>Instalar</strong>.</li>
                <li>Se não aparecer, toque em <strong>Abrir no Chrome</strong>.</li>
                <li>No Chrome, abra o menu e toque em <strong>Instalar app</strong>.</li>
            </ol>
        </div>

        <div id="iosFlow" class="flow-box hidden">
            <div class="flow-title">Como colocar no iPhone / iOS</div>
            <ol class="flow-steps">
                <li>Toque em <strong>Abrir no iOS</strong>.</li>
                <li>Abra a página no <strong>Safari</strong>.</li>
                <li>Toque em <strong>Compartilhar</strong>.</li>
                <li>Depois toque em <strong>Adicionar à Tela de Início</strong>.</li>
            </ol>
        </div>

        <div class="section">
            <div class="section-title">Ou leia o QR Code</div>

            <div class="qr-wrap">
                <div class="qr-box">
                    <img src="<?= htmlspecialchars($qrImageUrl) ?>" alt="QR Code para instalar o app">
                </div>
            </div>

            <div class="qr-caption">
                Escaneie com a câmera do celular. A página vai abrir no fluxo certo para Android ou iPhone.
            </div>
        </div>

        <?php if (PLAY_STORE_URL !== '' || APP_STORE_URL !== ''): ?>
            <div class="section">
                <div class="section-title">Ou instale pela loja</div>
                <div class="stores">
                    <?php if (PLAY_STORE_URL !== ''): ?>
                        <a href="<?= htmlspecialchars(PLAY_STORE_URL) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-soft">
                            GOOGLE PLAY
                        </a>
                    <?php endif; ?>

                    <?php if (APP_STORE_URL !== ''): ?>
                        <a href="<?= htmlspecialchars(APP_STORE_URL) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-soft">
                            APP STORE
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <a href="/index.php" class="footer-link">← Voltar</a>
    </div>
</div>

<script>
(function () {
    function setViewportHeight() {
        document.documentElement.style.setProperty('--vh', (window.innerHeight * 0.01) + 'px');
    }

    setViewportHeight();
    window.addEventListener('resize', setViewportHeight);
    window.addEventListener('orientationchange', setViewportHeight);

    const btnPrimary = document.getElementById('btnPrimary');
    const btnSecondary = document.getElementById('btnSecondary');
    const btnChrome = document.getElementById('btnChrome');

    const cardAndroid = document.getElementById('cardAndroid');
    const cardIOS = document.getElementById('cardIOS');

    const androidFlow = document.getElementById('androidFlow');
    const iosFlow = document.getElementById('iosFlow');
    const statusBox = document.getElementById('statusBox');

    let deferredPrompt = null;

    const ua = navigator.userAgent || '';
    const isAndroid = /android/i.test(ua);
    const isIOS = /iphone|ipad|ipod/i.test(ua);
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const isChromeLike = /chrome|crios|edg/i.test(ua) && !/samsungbrowser/i.test(ua);

    function showStatus(text) {
        statusBox.textContent = text;
        statusBox.classList.remove('hidden');
    }

    function resetFlow() {
        cardAndroid.classList.remove('active');
        cardIOS.classList.remove('active');
        btnPrimary.classList.add('hidden');
        btnSecondary.classList.add('hidden');
        btnChrome.classList.add('hidden');
        androidFlow.classList.add('hidden');
        iosFlow.classList.add('hidden');
    }

    function openAndroidFlow() {
        resetFlow();
        cardAndroid.classList.add('active');
        btnPrimary.classList.remove('hidden');
        androidFlow.classList.remove('hidden');

        if (!isChromeLike) {
            btnChrome.classList.remove('hidden');
        }

        if (deferredPrompt) {
            btnPrimary.textContent = 'INSTALAR APP AGORA';
        } else {
            btnPrimary.textContent = 'INSTALAR NO ANDROID';
        }
    }

    function openIOSFlow() {
        resetFlow();
        cardIOS.classList.add('active');
        btnSecondary.classList.remove('hidden');
        iosFlow.classList.remove('hidden');
        btnSecondary.textContent = 'ABRIR NO IOS';
    }

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/service-worker.js').catch(function (err) {
                console.error('Falha ao registrar service worker:', err);
            });
        });
    }

    if (isStandalone) {
        showStatus('Esse app já está instalado no seu aparelho. ✅');
    }

    if (isAndroid) {
        openAndroidFlow();
    } else if (isIOS) {
        openIOSFlow();
    }

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        if (cardAndroid.classList.contains('active') || isAndroid) {
            openAndroidFlow();
        }
    });

    window.addEventListener('appinstalled', function () {
        showStatus('App instalado com sucesso. 🎉');
    });

    cardAndroid.addEventListener('click', openAndroidFlow);
    cardIOS.addEventListener('click', openIOSFlow);

    btnPrimary.addEventListener('click', async function () {
        if (!isAndroid) {
            showStatus('Abra esta página em um celular Android para instalar o app por aqui.');
            return;
        }

        if (deferredPrompt) {
            deferredPrompt.prompt();
            try {
                await deferredPrompt.userChoice;
            } catch (err) {
                console.error(err);
            }
            deferredPrompt = null;
            return;
        }

        if (!isChromeLike) {
            showStatus('Seu navegador pode criar só um atalho. Toque em "Abrir no Chrome" para instalar melhor.');
            btnChrome.classList.remove('hidden');
            return;
        }

        showStatus('Se não aparecer a confirmação, abra o menu do navegador e toque em "Instalar app".');
        window.location.href = '<?= htmlspecialchars($indexUrl . '&install=android') ?>';
    });

    btnSecondary.addEventListener('click', function () {
        if (!isIOS) {
            showStatus('No iPhone, abra no Safari e use "Compartilhar > Adicionar à Tela de Início".');
        }
    });
})();
</script>

</body>
</html>