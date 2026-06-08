<?php
declare(strict_types=1);

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

function h(string|int|float|null $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

$footerActive = 'missao';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, viewport-fit=cover, initial-scale=1.0">
  <title>Missão semanal</title>
  <link rel="stylesheet" href="/assets/css/footer-v2.css?v=4">
  <style>
    :root {
      --ink: #071327;
      --muted: #8b95a8;
      --line: #e8edf5;
      --green: #21c866;
      --teal: #19c5b0;
      --orange: #ff9f1c;
      --pink: #ff3f74;
      --blue: #1877f2;
      --card: rgba(255,255,255,.88);
      --shadow: 0 18px 44px rgba(15,23,42,.09);
    }

    * {
      box-sizing: border-box;
    }

    html,
    body {
      min-height: 100%;
      margin: 0;
      background:
        radial-gradient(circle at 8% 0%, rgba(34,197,94,.10), transparent 28%),
        radial-gradient(circle at 92% 18%, rgba(25,197,176,.12), transparent 34%),
        linear-gradient(180deg, #f8fbff 0%, #eef4f8 100%);
      color: var(--ink);
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    body {
      padding-bottom: calc(92px + env(safe-area-inset-bottom));
    }

    .mission-page {
      width: min(100%, 430px);
      margin: 0 auto;
      padding: 18px 16px calc(98px + env(safe-area-inset-bottom));
    }

    .mission-top {
      margin: 0 0 16px;
      padding: 18px 18px 16px;
      border-radius: 30px;
      background:
        radial-gradient(circle at 18% 0%, rgba(255,255,255,.55), transparent 35%),
        linear-gradient(135deg, #ffb32d 0%, #ff7a00 100%);
      box-shadow: 0 18px 42px rgba(255, 122, 0, .20);
      color: #fff;
      overflow: hidden;
      position: relative;
    }

    .mission-top::after {
      content: "";
      position: absolute;
      right: -44px;
      top: -52px;
      width: 160px;
      height: 160px;
      border-radius: 999px;
      background: rgba(255,255,255,.16);
    }

    .mission-kicker {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 7px 11px;
      border-radius: 999px;
      background: rgba(255,255,255,.22);
      font-size: 12px;
      line-height: 1;
      font-weight: 1000;
      text-transform: uppercase;
      letter-spacing: .05em;
      position: relative;
      z-index: 2;
    }

    .mission-top h1 {
      margin: 13px 0 5px;
      font-size: 34px;
      line-height: .95;
      letter-spacing: -.07em;
      font-weight: 1000;
      position: relative;
      z-index: 2;
      text-shadow: 0 8px 18px rgba(0,0,0,.10);
    }

    .mission-top p {
      margin: 0;
      max-width: 280px;
      font-size: 14px;
      line-height: 1.22;
      font-weight: 850;
      color: rgba(255,255,255,.84);
      position: relative;
      z-index: 2;
    }

    .mission-summary {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin: 0 0 15px;
    }

    .mission-mini {
      min-height: 74px;
      border-radius: 20px;
      background: rgba(255,255,255,.86);
      border: 1px solid rgba(226,232,240,.9);
      box-shadow: 0 10px 24px rgba(15,23,42,.05);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 10px 8px;
    }

    .mission-mini strong {
      display: block;
      font-size: 21px;
      line-height: 1;
      font-weight: 1000;
      letter-spacing: -.04em;
    }

    .mission-mini span {
      display: block;
      margin-top: 5px;
      color: var(--muted);
      font-size: 10.5px;
      line-height: 1.05;
      font-weight: 900;
    }

    .mission-card {
      position: relative;
      margin: 0 0 14px;
      padding: 18px;
      border-radius: 30px;
      background:
        radial-gradient(circle at 94% 8%, rgba(34,197,94,.16), transparent 30%),
        linear-gradient(135deg, rgba(255,255,255,.95), rgba(246,255,250,.92));
      border: 1px solid rgba(226,232,240,.95);
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    .mission-card.is-comments {
      background:
        radial-gradient(circle at 94% 8%, rgba(217,70,239,.13), transparent 30%),
        linear-gradient(135deg, rgba(255,255,255,.95), rgba(253,244,255,.88));
    }

    .mission-card.is-share {
      background:
        radial-gradient(circle at 94% 8%, rgba(59,130,246,.14), transparent 30%),
        linear-gradient(135deg, rgba(255,255,255,.95), rgba(239,246,255,.9));
    }

    .mission-card-head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 14px;
      margin-bottom: 13px;
    }

    .mission-card-title {
      display: grid;
      grid-template-columns: 44px minmax(0, 1fr);
      gap: 12px;
      align-items: center;
      min-width: 0;
    }

    .mission-icon {
      width: 44px;
      height: 44px;
      border-radius: 16px;
      display: grid;
      place-items: center;
      background: #ecfdf5;
      box-shadow: 0 10px 22px rgba(34,197,94,.12);
      font-size: 23px;
    }

    .mission-card.is-comments .mission-icon {
      background: #fdf4ff;
      box-shadow: 0 10px 22px rgba(217,70,239,.10);
    }

    .mission-card.is-share .mission-icon {
      background: #eff6ff;
      box-shadow: 0 10px 22px rgba(59,130,246,.10);
    }

    .mission-card h2 {
      margin: 0;
      font-size: 20px;
      line-height: 1.05;
      letter-spacing: -.055em;
      font-weight: 1000;
    }

    .mission-card p {
      margin: 5px 0 0;
      color: #8b95a8;
      font-size: 13px;
      line-height: 1.15;
      font-weight: 900;
    }

    .mission-pill {
      flex: 0 0 auto;
      min-width: 58px;
      padding: 9px 11px;
      border-radius: 999px;
      background: #dcfce7;
      color: #05a64f;
      text-align: center;
      font-size: 15px;
      line-height: 1;
      font-weight: 1000;
      box-shadow: inset 0 -2px 0 rgba(5,150,105,.08);
    }

    .mission-card.is-comments .mission-pill {
      background: #fae8ff;
      color: #c026d3;
    }

    .mission-card.is-share .mission-pill {
      background: #dbeafe;
      color: #1877f2;
    }

    .mission-progress {
      height: 15px;
      border-radius: 999px;
      background: #e8eef7;
      overflow: hidden;
      box-shadow: inset 0 2px 5px rgba(15,23,42,.08);
      margin: 0 0 17px;
    }

    .mission-progress i {
      display: block;
      height: 100%;
      width: 0%;
      border-radius: inherit;
      background: linear-gradient(90deg, #17b65e, #27d16d, #facc15);
      transition: width .45s ease;
    }

    .mission-card.is-comments .mission-progress i {
      background: linear-gradient(90deg, #ec4899, #a855f7, #6366f1);
    }

    .mission-card.is-share .mission-progress i {
      background: linear-gradient(90deg, #1877f2, #06b6d4, #22c55e);
    }

    .mission-steps {
      display: flex;
      justify-content: space-between;
      gap: 8px;
    }

    .mission-step {
      width: 47px;
      height: 47px;
      border-radius: 999px;
      display: grid;
      place-items: center;
      border: 3px dashed #22c55e;
      color: #0ea34f;
      background: #fff;
      font-size: 22px;
      font-weight: 1000;
      line-height: 1;
      box-shadow: 0 9px 20px rgba(34,197,94,.08);
    }

    .mission-step.is-done {
      border-style: solid;
      border-color: #062f25;
      color: #071327;
      background: linear-gradient(135deg, #ff3f74, #ffb020);
      box-shadow: 0 12px 24px rgba(34,197,94,.16);
    }

    .mission-card.is-comments .mission-step {
      border-color: #d946ef;
      color: #c026d3;
      box-shadow: 0 9px 20px rgba(217,70,239,.08);
    }

    .mission-card.is-comments .mission-step.is-done {
      border-color: #3b0764;
      color: #fff;
      background: linear-gradient(135deg, #ec4899, #8b5cf6);
    }

    .mission-card.is-share .mission-step {
      border-color: #3b82f6;
      color: #1877f2;
      box-shadow: 0 9px 20px rgba(59,130,246,.08);
    }

    .mission-card.is-share .mission-step.is-done {
      border-color: #082f49;
      color: #fff;
      background: linear-gradient(135deg, #1877f2, #06b6d4);
    }

    .mission-card.is-loading {
      opacity: .72;
    }

    .mission-card.is-loading .mission-progress i {
      width: 22% !important;
      animation: loadingBar 1s ease-in-out infinite alternate;
    }

    @keyframes loadingBar {
      from { transform: translateX(-20%); }
      to { transform: translateX(60%); }
    }

    .mission-error {
      margin: 12px 0 0;
      padding: 12px 14px;
      border-radius: 18px;
      background: #fff1f2;
      color: #be123c;
      font-size: 12px;
      font-weight: 900;
      display: none;
    }

    .mission-error.is-visible {
      display: block;
    }

    @media (max-width: 390px) {
      .mission-page {
        padding-left: 12px;
        padding-right: 12px;
      }

      .mission-top h1 {
        font-size: 30px;
      }

      .mission-card {
        padding: 16px;
        border-radius: 26px;
      }

      .mission-card h2 {
        font-size: 18px;
      }

      .mission-card p {
        font-size: 12px;
      }

      .mission-step {
        width: 42px;
        height: 42px;
        font-size: 19px;
      }
    }
  </style>
</head>
<body>
<main class="mission-page">
  <section class="mission-top">
    <span class="mission-kicker">🎯 Missão semanal</span>
    <h1>Complete sua semana</h1>
    <p>Avance nas ações, fortaleça o time e acompanhe seu progresso em tempo real.</p>
  </section>

  <section class="mission-summary" aria-label="Resumo semanal">
    <div class="mission-mini">
      <strong id="miniDone">0</strong>
      <span>ações feitas</span>
    </div>
    <div class="mission-mini">
      <strong id="miniMissing">15</strong>
      <span>faltam concluir</span>
    </div>
    <div class="mission-mini">
      <strong id="miniPercent">0%</strong>
      <span>progresso total</span>
    </div>
  </section>

  <section class="mission-card is-invite is-loading" id="missionCardConvites" data-mission-card="convites">
    <div class="mission-card-head">
      <div class="mission-card-title">
        <span class="mission-icon">🤝</span>
        <div>
          <h2>Convide 5 pessoas para o time</h2>
          <p>Carregando progresso...</p>
        </div>
      </div>
      <span class="mission-pill">0/5</span>
    </div>
    <div class="mission-progress"><i></i></div>
    <div class="mission-steps" aria-hidden="true"></div>
  </section>

  <section class="mission-card is-comments is-loading" id="missionCardComentarios" data-mission-card="comentarios">
    <div class="mission-card-head">
      <div class="mission-card-title">
        <span class="mission-icon">💬</span>
        <div>
          <h2>Comente 5 posts essa semana</h2>
          <p>Carregando progresso...</p>
        </div>
      </div>
      <span class="mission-pill">0/5</span>
    </div>
    <div class="mission-progress"><i></i></div>
    <div class="mission-steps" aria-hidden="true"></div>
  </section>

  <section class="mission-card is-share is-loading" id="missionCardCompartilhar" data-mission-card="compartilhar">
    <div class="mission-card-head">
      <div class="mission-card-title">
        <span class="mission-icon">📣</span>
        <div>
          <h2>Compartilhe 5 posts essa semana</h2>
          <p>Carregando progresso...</p>
        </div>
      </div>
      <span class="mission-pill">0/5</span>
    </div>
    <div class="mission-progress"><i></i></div>
    <div class="mission-steps" aria-hidden="true"></div>
  </section>

  <div class="mission-error" id="missionError">
    Não foi possível carregar uma das missões agora. Tente novamente em instantes.
  </div>
</main>

<?php
$activeMenu = 'missao';
require '/home/elab/app.elab.social/assets/footer-v2.php';
?>

<script>
window.missaoSemanalReload = function () {
  const endpoints = {
    convites: '/endpoints/convites.php',
    comentarios: '/endpoints/comentarios.php',
    compartilhar: '/endpoints/compartilhar.php'
  };

  const cardIds = {
    convites: 'missionCardConvites',
    comentarios: 'missionCardComentarios',
    compartilhar: 'missionCardCompartilhar'
  };

  const state = {
    convites: { ok: true, feitos: 0, meta: 5 },
    comentarios: { ok: true, feitos: 0, meta: 5 },
    compartilhar: { ok: true, feitos: 0, meta: 5 }
  };

  function steps(meta, feitos) {
    let html = '';
    meta = Math.max(1, Number(meta || 5));
    feitos = Math.max(0, Number(feitos || 0));

    for (let i = 1; i <= meta; i++) {
      html += '<span class="mission-step ' + (i <= feitos ? 'is-done' : '') + '">' + i + '</span>';
    }

    return html;
  }

  function summary() {
    const values = Object.values(state);
    const done = values.reduce((s, i) => s + Number(i.feitos || 0), 0);
    const meta = values.reduce((s, i) => s + Number(i.meta || 0), 0);
    const missing = Math.max(0, meta - done);
    const percent = meta > 0 ? Math.round((done / meta) * 100) : 0;

    const miniDone = document.getElementById('miniDone');
    const miniMissing = document.getElementById('miniMissing');
    const miniPercent = document.getElementById('miniPercent');

    if (miniDone) miniDone.textContent = String(done);
    if (miniMissing) miniMissing.textContent = String(missing);
    if (miniPercent) miniPercent.textContent = percent + '%';
  }

  function render(tipo, data) {
    const card = document.getElementById(cardIds[tipo]);
    if (!card || !data || !data.ok) return;

    const feitos = Math.max(0, Number(data.feitos || 0));
    const meta = Math.max(1, Number(data.meta || 5));
    const percent = Math.max(0, Math.min(100, Number(data.percent || Math.round((feitos / meta) * 100))));

    const h2 = card.querySelector('h2');
    const p = card.querySelector('p');
    const pill = card.querySelector('.mission-pill');
    const bar = card.querySelector('.mission-progress i');
    const stepBox = card.querySelector('.mission-steps');

    if (h2) h2.textContent = data.titulo || h2.textContent;
    if (p) p.textContent = data.subtitulo || '';
    if (pill) pill.textContent = feitos + '/' + meta;
    if (bar) bar.style.width = percent + '%';
    if (stepBox) stepBox.innerHTML = steps(meta, feitos);

    card.classList.remove('is-loading');
    card.setAttribute('data-loaded', '1');

    state[tipo] = data;
    summary();
  }

  Object.keys(endpoints).forEach(function (tipo) {
    fetch(endpoints[tipo] + '?_=' + Date.now(), {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json' }
    })
      .then(r => r.json())
      .then(data => render(tipo, data))
      .catch(function (err) {
        console.warn('[MISSAO_SEMANAL]', tipo, err);
        const card = document.getElementById(cardIds[tipo]);
        if (card) {
          card.classList.remove('is-loading');
          const p = card.querySelector('p');
          if (p) p.textContent = 'Não foi possível carregar agora.';
        }
      });
  });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', window.missaoSemanalReload);
} else {
  window.missaoSemanalReload();
}

document.addEventListener('visibilitychange', function () {
  if (!document.hidden) window.missaoSemanalReload();
});

window.addEventListener('focus', window.missaoSemanalReload);
</script>

</body>
</html>
