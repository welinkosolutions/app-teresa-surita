(function () {
  'use strict';

  window.missaoSemanalReload = function () {
    if (typeof window.__missaoSemanalInit === 'function') {
      return window.__missaoSemanalInit();
    }
    console.warn('[MISSAO_SEMANAL] init ainda não disponível');
  };

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

  function renderSteps(meta, feitos) {
    const total = Math.max(1, Number(meta || 5));
    const done = Math.max(0, Number(feitos || 0));
    let html = '';

    for (let i = 1; i <= total; i++) {
      html += '<span class="mission-step ' + (i <= done ? 'is-done' : '') + '">' + i + '</span>';
    }

    return html;
  }

  function renderCard(tipo, data) {
    const card = document.getElementById(cardIds[tipo]);

    if (!card || !data || !data.ok) {
      console.warn('[MISSAO_SEMANAL] render ignorado', tipo, data);
      return;
    }

    const feitos = Math.max(0, Number(data.feitos || 0));
    const meta = Math.max(1, Number(data.meta || 5));
    const percent = Math.max(0, Math.min(100, Number(data.percent || Math.round((feitos / meta) * 100))));

    const h2 = card.querySelector('h2');
    const p = card.querySelector('p');
    const pill = card.querySelector('.mission-pill');
    const bar = card.querySelector('.mission-progress i');
    const steps = card.querySelector('.mission-steps');

    if (h2) h2.textContent = data.titulo || h2.textContent;
    if (p) p.textContent = data.subtitulo || '';
    if (pill) pill.textContent = feitos + '/' + meta;
    if (bar) bar.style.width = percent + '%';
    if (steps) steps.innerHTML = renderSteps(meta, feitos);

    card.classList.remove('is-loading');
    card.setAttribute('data-loaded', '1');
  }

  function renderSummary() {
    const values = Object.values(state).filter(function (item) {
      return item && item.ok;
    });

    const done = values.reduce(function (sum, item) {
      return sum + Number(item.feitos || 0);
    }, 0);

    const meta = values.reduce(function (sum, item) {
      return sum + Number(item.meta || 0);
    }, 0);

    const missing = Math.max(0, meta - done);
    const percent = meta > 0 ? Math.round((done / meta) * 100) : 0;

    const miniDone = document.getElementById('miniDone');
    const miniMissing = document.getElementById('miniMissing');
    const miniPercent = document.getElementById('miniPercent');

    if (miniDone) miniDone.textContent = String(done);
    if (miniMissing) miniMissing.textContent = String(missing);
    if (miniPercent) miniPercent.textContent = percent + '%';
  }

  async function loadOne(tipo) {
    const response = await fetch(endpoints[tipo] + '?_=' + Date.now(), {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json' }
    });

    const data = await response.json();

    if (!data || !data.ok) {
      throw new Error(tipo + ': ' + (data && data.error ? data.error : 'erro'));
    }

    state[tipo] = data;
    renderCard(tipo, data);
    renderSummary();

    return data;
  }

  function setError(show) {
    const error = document.getElementById('missionError');
    if (error) {
      error.classList.toggle('is-visible', Boolean(show));
    }
  }

  function init() {
    setError(false);

    Object.keys(endpoints).forEach(function (tipo) {
      loadOne(tipo).catch(function (err) {
        console.warn('[MISSAO_SEMANAL]', err);
        setError(true);

        const card = document.getElementById(cardIds[tipo]);
        if (card) {
          card.classList.remove('is-loading');

          const p = card.querySelector('p');
          if (p) {
            p.textContent = 'Não foi possível carregar agora.';
          }
        }
      });
    });
  }

  window.__missaoSemanalInit = init;
  window.missaoSemanalReload = init;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) {
      init();
    }
  });

  window.addEventListener('focus', init);
})();