/*
====================================
HOME JS
VERSAO LIMPA - CORE/MISSAO
TENANT AWARE
====================================
*/

let heroAnimStarted = false;
let currentXpTotal = typeof XP_TOTAL !== "undefined" ? Number(XP_TOTAL) || 0 : 0;
let homeStateBusy = false;
let homeLastStateHash = "";
let elabToastCopyTimer = null;
let heroAnimationTimeout = null;
let missaoTimerInterval = null;

window.homeAlertAudio = null;
window.homeAlertVibInterval = null;
window.homeHeroAudio = null;
window.homeHeroYeeeAudio = null;

/*
====================================
TENANT CONFIG HELPERS
====================================
*/

function getTenantAppName() {
  return typeof TENANT_APP_NAME !== "undefined" && TENANT_APP_NAME
    ? String(TENANT_APP_NAME)
    : "App";
}

function getTenantHeroStaticUrl() {
  return typeof TENANT_HERO_STATIC_URL !== "undefined" && TENANT_HERO_STATIC_URL
    ? String(TENANT_HERO_STATIC_URL)
    : (typeof HERO_STATIC !== "undefined" ? String(HERO_STATIC || "") : "");
}

function getTenantHeroHappyUrl() {
  return typeof TENANT_HERO_HAPPY_URL !== "undefined" && TENANT_HERO_HAPPY_URL
    ? String(TENANT_HERO_HAPPY_URL)
    : "";
}

function getTenantHeroIntroUrl() {
  return typeof TENANT_HERO_INTRO_URL !== "undefined" && TENANT_HERO_INTRO_URL
    ? String(TENANT_HERO_INTRO_URL)
    : "";
}

function getTenantHeroBadUrl() {
  return typeof TENANT_HERO_BAD_URL !== "undefined" && TENANT_HERO_BAD_URL
    ? String(TENANT_HERO_BAD_URL)
    : "";
}

function getTenantPostPlaceholderUrl() {
  return typeof TENANT_POST_PLACEHOLDER_URL !== "undefined" && TENANT_POST_PLACEHOLDER_URL
    ? String(TENANT_POST_PLACEHOLDER_URL)
    : "";
}

function getTenantInviteCommunityText() {
  if (typeof TENANT_INVITE_SHARE_INTRO !== "undefined" && TENANT_INVITE_SHARE_INTRO) {
    return String(TENANT_INVITE_SHARE_INTRO);
  }
  return "Quero te convidar para participar da comunidade de " + getTenantAppName() + "!";
}

function getTenantInviteShortText() {
  if (typeof TENANT_INVITE_SHARE_SHORT_TEXT !== "undefined" && TENANT_INVITE_SHARE_SHORT_TEXT) {
    return String(TENANT_INVITE_SHARE_SHORT_TEXT);
  }
  return "Participe da comunidade de " + getTenantAppName() + " pelo meu convite:";
}

function getTenantInviteShareTitle() {
  if (typeof TENANT_INVITE_SHARE_TITLE !== "undefined" && TENANT_INVITE_SHARE_TITLE) {
    return String(TENANT_INVITE_SHARE_TITLE);
  }
  return "Convite " + getTenantAppName();
}

/*
====================================
HELPERS
====================================
*/

function getHeroEl() {
  return document.getElementById("heroImg");
}

function preloadImage(src) {
  return new Promise((resolve, reject) => {
    if (!src) {
      reject(new Error("src vazio"));
      return;
    }

    const img = new Image();
    img.decoding = "async";
    img.onload = () => resolve(src);
    img.onerror = reject;
    img.src = src;
  });
}

function formatNumberBR(value) {
  return Number(value || 0).toLocaleString("pt-BR");
}

function setText(el, value) {
  if (!el) return;
  el.textContent = value ?? "";
}

function showElement(el, displayType = "") {
  if (!el) return;
  el.style.display = displayType;
}

function hideElement(el) {
  if (!el) return;
  el.style.display = "none";
}

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function showElabToastCopy(message = "Link copiado com sucesso.", title = "Tudo certo!") {
  const toast = document.getElementById("elabToastCopy");
  const toastTitle = document.getElementById("elabToastCopyTitle");
  const toastText = document.getElementById("elabToastCopyText");

  if (!toast) return;

  if (toastTitle) toastTitle.textContent = title;
  if (toastText) toastText.textContent = message;

  toast.classList.add("is-visible");

  if (elabToastCopyTimer) {
    clearTimeout(elabToastCopyTimer);
  }

  elabToastCopyTimer = setTimeout(() => {
    toast.classList.remove("is-visible");
  }, 2400);
}

function stopHomeAlertEffects() {
  if (window.homeAlertVibInterval) {
    clearInterval(window.homeAlertVibInterval);
    window.homeAlertVibInterval = null;
  }

  if (navigator.vibrate) {
    navigator.vibrate(0);
  }

  if (window.homeAlertAudio) {
    try {
      window.homeAlertAudio.pause();
      window.homeAlertAudio.currentTime = 0;
    } catch (e) {}
    window.homeAlertAudio = null;
  }

  if (window.homeHeroAudio) {
    try {
      window.homeHeroAudio.pause();
      window.homeHeroAudio.currentTime = 0;
    } catch (e) {}
    window.homeHeroAudio = null;
  }

  if (window.homeHeroYeeeAudio) {
    try {
      window.homeHeroYeeeAudio.pause();
      window.homeHeroYeeeAudio.currentTime = 0;
    } catch (e) {}
    window.homeHeroYeeeAudio = null;
  }
}

function getLocalStorageSafe(key) {
  try {
    return window.localStorage.getItem(key);
  } catch (e) {
    return null;
  }
}

function setLocalStorageSafe(key, value) {
  try {
    window.localStorage.setItem(key, value);
  } catch (e) {}
}

function removeLocalStorageSafe(key) {
  try {
    window.localStorage.removeItem(key);
  } catch (e) {}
}

function rememberHomeAction(action) {
  if (!action) return;
  setLocalStorageSafe("elab_home_last_action", String(action));
}

function getRememberedHomeAction() {
  return getLocalStorageSafe("elab_home_last_action") || "";
}

function getSmartToastId() {
  const toast = document.getElementById("smartToast");
  if (!toast) return "";
  return (
    toast.getAttribute("data-toast-id") ||
    (typeof SMART_TOAST_ID !== "undefined" ? String(SMART_TOAST_ID || "") : "")
  );
}

function hideSmartToastPersisted() {
  const toast = document.getElementById("smartToast");
  if (!toast) return;

  const toastId = getSmartToastId();
  const hiddenId = getLocalStorageSafe("elab_home_toast_hidden");

  if (toastId && hiddenId && toastId === hiddenId) {
    hideElement(toast);
  }
}

function persistSmartToastClosed() {
  const toastId = getSmartToastId();
  if (!toastId) return;
  setLocalStorageSafe("elab_home_toast_hidden", toastId);
}

function clearSmartToastHiddenIfChanged() {
  const toastId = getSmartToastId();
  const hiddenId = getLocalStorageSafe("elab_home_toast_hidden");

  if (!toastId) return;
  if (hiddenId && hiddenId !== toastId) {
    removeLocalStorageSafe("elab_home_toast_hidden");
  }
}

/*
====================================
PUSH / ALERTA FORCADO
====================================
*/

function canUsePushEffects() {
  return typeof PUSH_FORCE_ENABLED !== "undefined" && PUSH_FORCE_ENABLED === true;
}

function canUsePushSound() {
  return typeof PUSH_FORCE_SOUND !== "undefined" && PUSH_FORCE_SOUND === true;
}

function canUsePushVibration() {
  return typeof PUSH_FORCE_VIBRATION !== "undefined" && PUSH_FORCE_VIBRATION === true;
}

function pushAudioFileByContext() {
  const eventoTipo =
    typeof PUSH_CONTEXT !== "undefined" && PUSH_CONTEXT && PUSH_CONTEXT.evento_tipo
      ? String(PUSH_CONTEXT.evento_tipo)
      : "";

  if (["ranking", "xp", "combo", "streak"].includes(eventoTipo)) {
    return "/assets/sounds/aplausos.mp3";
  }

  return "/assets/sounds/alerta.mp3";
}

function startForcedPushEffects() {
  if (!canUsePushEffects()) return;

  stopHomeAlertEffects();

  if (canUsePushSound()) {
    try {
      window.homeAlertAudio = new Audio(pushAudioFileByContext());
      window.homeAlertAudio.volume = 0.65;
      window.homeAlertAudio.play().catch(() => {});
    } catch (e) {}
  }

  if (canUsePushVibration() && navigator.vibrate) {
    navigator.vibrate([220, 90, 220, 90, 320]);

    window.homeAlertVibInterval = setInterval(() => {
      navigator.vibrate([180, 70, 220]);
    }, 2600);
  }

  const toast = document.getElementById("smartToast");
  if (toast) {
    toast.classList.remove("is-hiding");
    toast.classList.add("is-bump");

    setTimeout(() => {
      toast.classList.remove("is-bump");
    }, 650);
  }
}

function bindPushInteractionStop() {
  const toast = document.getElementById("smartToast");
  const toastBtn = document.getElementById("smartToastBtn");
  const toastClose = document.getElementById("smartToastClose");
  const missaoLink = document.getElementById("missaoCtaLink");
  const missaoBtn = document.getElementById("btnMissaoAtualCompartilhar");

  [toast, toastBtn, toastClose, missaoLink, missaoBtn].forEach((el) => {
    if (!el || el.dataset.pushBound === "1") return;

    el.dataset.pushBound = "1";
    el.addEventListener("click", () => {
      stopHomeAlertEffects();
    });
  });
}

(function initForcedPushOnLoad() {
  if (!canUsePushEffects()) return;

  const hasRelevantContext =
    typeof PUSH_CONTEXT !== "undefined" &&
    PUSH_CONTEXT &&
    (
      String(PUSH_CONTEXT.evento_tipo || "") !== "" ||
      String(PUSH_CONTEXT.missao_codigo || "") !== "" ||
      String(PUSH_CONTEXT.toast_titulo || "") !== ""
    );

  if (!hasRelevantContext) return;

  document.addEventListener("DOMContentLoaded", () => {
    startForcedPushEffects();
    bindPushInteractionStop();
  });
})();

/*
====================================
HERO
====================================
*/

function resetHeroToStatic() {
  const hero = getHeroEl();
  if (!hero) return;

  const heroStatic = getTenantHeroStaticUrl();
  if (heroStatic) {
    hero.src = heroStatic;
  }

  heroAnimStarted = false;
}

function playHeroAnimation(src, duracao = 5000, tocarAudio = false) {
  const hero = getHeroEl();
  if (!hero || !src) return;

  if (heroAnimationTimeout) {
    clearTimeout(heroAnimationTimeout);
    heroAnimationTimeout = null;
  }

  if (tocarAudio) {
    if (window.homeHeroAudio) {
      try {
        window.homeHeroAudio.pause();
        window.homeHeroAudio.currentTime = 0;
      } catch (e) {}
    }

    window.homeHeroAudio = new Audio("/assets/sounds/aplausos.mp3");
    window.homeHeroAudio.volume = 0.6;
    window.homeHeroAudio.play().catch(() => {});
  }

  hero.src = src;

  heroAnimationTimeout = setTimeout(() => {
    resetHeroToStatic();
  }, duracao);
}

async function initHeroAnimation() {
  const hero = getHeroEl();

  if (
    !hero ||
    typeof HERO_ANIM_SRC === "undefined" ||
    !HERO_ANIM_SRC ||
    heroAnimStarted
  ) {
    return;
  }

  heroAnimStarted = true;

  try {
    await preloadImage(HERO_ANIM_SRC);
    playHeroAnimation(HERO_ANIM_SRC, 5000, false);
  } catch (err) {
    console.log("hero preload erro", err);
    playHeroAnimation(HERO_ANIM_SRC, 5000, false);
  }
}

function playHeroYeee() {
  try {
    if (window.homeHeroYeeeAudio) {
      window.homeHeroYeeeAudio.pause();
      window.homeHeroYeeeAudio.currentTime = 0;
    }

    window.homeHeroYeeeAudio = new Audio("/assets/sounds/yeee.mp3");
    window.homeHeroYeeeAudio.volume = 0.7;
    window.homeHeroYeeeAudio.play().catch(() => {});
  } catch (e) {}
}

function triggerHappyHero() {
  const hero = getHeroEl();
  const happyUrl = getTenantHeroHappyUrl();

  if (!hero || !happyUrl) return;

  if (heroAnimationTimeout) {
    clearTimeout(heroAnimationTimeout);
    heroAnimationTimeout = null;
  }

  heroAnimStarted = false;
  playHeroYeee();

  preloadImage(happyUrl)
    .then(() => {
      heroAnimStarted = true;
      playHeroAnimation(happyUrl, 3200, false);
    })
    .catch(() => {
      heroAnimStarted = true;
      playHeroAnimation(happyUrl, 3200, false);
    });
}

(function initHeroOnLoad() {
  if (typeof HERO_ANIM_SRC === "undefined" || !HERO_ANIM_SRC) return;

  if (document.readyState === "complete") {
    initHeroAnimation();
  } else {
    window.addEventListener("load", initHeroAnimation, { once: true });
  }
})();

/*
====================================
XP COUNTER
====================================
*/

function animateXpTo(newValue) {
  const el = document.getElementById("xpCounter");
  if (!el) return;

  const target = Number(newValue || 0);
  const start = Number(currentXpTotal || 0);

  if (target === start) {
    el.textContent = formatNumberBR(target) + " Pontos";
    currentXpTotal = target;
    return;
  }

  const duration = 700;
  const startedAt = performance.now();

  function step(now) {
    const progress = Math.min((now - startedAt) / duration, 1);
    const value = Math.round(start + (target - start) * progress);

    el.textContent = formatNumberBR(value) + " Pontos";

    if (progress < 1) {
      requestAnimationFrame(step);
    } else {
      currentXpTotal = target;
    }
  }

  requestAnimationFrame(step);
}

(function initXpCounter() {
  const el = document.getElementById("xpCounter");
  if (!el) return;
  el.textContent = formatNumberBR(currentXpTotal) + " Pontos";
})();

function updateRankingPosicaoValue(posicao) {
  const el = document.querySelector(".header-rank strong");
  if (!el) return;
  el.textContent = formatNumberBR(posicao || 0);
}

/* ===== FIM PARTE 1/3 ===== */

/*
====================================
SMART TOAST
====================================
*/

function revealSmartToastIfNeeded() {
  const toast = document.getElementById("smartToast");
  if (!toast) return;

  const toastId = getSmartToastId();
  const hiddenId = getLocalStorageSafe("elab_home_toast_hidden");

  if (toastId && hiddenId && toastId === hiddenId) {
    hideElement(toast);
    return;
  }

  showElement(toast);
}

(function initSmartToastClose() {
  const toast = document.getElementById("smartToast");
  const btnClose = document.getElementById("smartToastClose");

  if (!toast || !btnClose) return;

  btnClose.addEventListener("click", function (e) {
    e.preventDefault();
    e.stopPropagation();

    stopHomeAlertEffects();
    persistSmartToastClosed();

    toast.classList.add("is-hiding");
    toast.style.pointerEvents = "none";
    toast.style.opacity = "0";
    toast.style.transform = "translateY(-8px) scale(.98)";
    toast.style.maxHeight = "0";
    toast.style.marginBottom = "0";
    toast.style.overflow = "hidden";

    setTimeout(() => {
      if (toast.parentNode) {
        toast.remove();
      }
    }, 260);
  });
})();

function updateSmartToastForMissao(missao) {
  const toast = document.getElementById("smartToast");
  const toastChip = toast ? toast.querySelector(".toast-chip") : null;
  const toastTitle = toast ? toast.querySelector(".toast-title") : null;
  const toastText = toast ? toast.querySelector(".toast-text") : null;
  const toastBtn = document.getElementById("smartToastBtn");

  if (toastChip) {
    toastChip.innerHTML = '<i class="bi bi-rocket-takeoff-fill"></i> Missão do dia';
  }

  if (toastTitle) {
    toastTitle.textContent = "Nova missão liberada";
  }

  if (toastText) {
    toastText.textContent = missao && missao.descricao
      ? missao.descricao
      : "Sua próxima missão já está pronta.";
  }

  if (toastBtn) {
    toastBtn.setAttribute("href", "#cardMissaoAtual");
    toastBtn.textContent = "Ir para missão";
  }

  if (toast) {
    removeLocalStorageSafe("elab_home_toast_hidden");
    showElement(toast);
    toast.classList.remove("is-hiding");
    toast.classList.add("is-bump");

    setTimeout(() => {
      toast.classList.remove("is-bump");
    }, 500);
  }
}

/*
====================================
MEMORIA DE ACAO / TOAST
====================================
*/

function bindHomeActionMemory() {
  const els = document.querySelectorAll("[data-home-action]");

  els.forEach((el) => {
    if (!el || el.dataset.actionBound === "1") return;

    el.dataset.actionBound = "1";
    el.addEventListener("click", () => {
      const action = String(el.getAttribute("data-home-action") || "").trim();
      if (action) {
        rememberHomeAction(action);
      }
    });
  });
}

function initToastMemory() {
  clearSmartToastHiddenIfChanged();
  hideSmartToastPersisted();
  revealSmartToastIfNeeded();
}

/*
====================================
CONVITE WHATSAPP
====================================
*/

async function enviarConvite() {
  rememberHomeAction("convite");

  try {
    const response = await fetch("/api/invite/enviar.php", {
      method: "POST",
      cache: "no-store",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        canal: "whatsapp",
        origem: "dashboard_home_card_convite"
      })
    });

    const data = await response.json();

    if (!data || data.ok !== true) {
      alert(
        data && data.erro
          ? data.erro
          : "Seus convites ainda não estão liberados no momento."
      );
      return;
    }

    const link = String(data.link || "").trim();
    if (!link) {
      alert("Não foi possível montar seu link de convite agora.");
      return;
    }

    const textoBase =
      typeof TENANT_INVITE_SHARE_WHATSAPP_TEXT !== "undefined" && TENANT_INVITE_SHARE_WHATSAPP_TEXT
        ? String(TENANT_INVITE_SHARE_WHATSAPP_TEXT)
        : `Oii! Sou *${NOME_CONVIDADOR}*, tudo bem?

*${getTenantInviteCommunityText()}*

No aplicativo você pode participar das conversas, comentar nos posts ajudando a fortalecer nosso time e ainda ganhar Pontos!!

Vamos juntos?!

*Entre pelo meu convite:*
{link}`;

    const mensagem =
      String(data.mensagem || "").trim() ||
      textoBase
        .replaceAll("{nome}", String(NOME_CONVIDADOR || ""))
        .replaceAll("{link}", link);

    const url = "https://wa.me/?text=" + encodeURIComponent(mensagem);
    window.location.href = url;
  } catch (e) {
    alert("Não foi possível gerar seu convite agora. Tente novamente em instantes.");
  }
}

/*
====================================
CONVITE - COPIAR E COMPARTILHAR
====================================
*/

function montarMensagemConviteBase(link) {
  const textoBase =
    typeof TENANT_INVITE_SHARE_WHATSAPP_TEXT !== "undefined" && TENANT_INVITE_SHARE_WHATSAPP_TEXT
      ? String(TENANT_INVITE_SHARE_WHATSAPP_TEXT)
      : `Oii! Sou *${NOME_CONVIDADOR}*, tudo bem?

*${getTenantInviteCommunityText()}*

No aplicativo você pode participar das conversas, comentar nos posts ajudando a fortalecer nosso time e ainda ganhar Pontos!!

Vamos juntos?!

*Entre pelo meu convite:*
{link}`;

  return textoBase
    .replaceAll("{nome}", String(NOME_CONVIDADOR || ""))
    .replaceAll("{link}", String(link || "").trim());
}

function montarMensagemConviteSemLink() {
  const textoBase =
    typeof TENANT_INVITE_SHARE_INSTAGRAM_TEXT !== "undefined" && TENANT_INVITE_SHARE_INSTAGRAM_TEXT
      ? String(TENANT_INVITE_SHARE_INSTAGRAM_TEXT)
      : `Oii! Sou *${NOME_CONVIDADOR}*, tudo bem?

${getTenantInviteCommunityText()}

No aplicativo você pode participar das conversas, comentar nos posts ajudando a fortalecer nosso time e ainda ganhar Pontos!!

Vamos juntos?!

{link}`;

  return textoBase
    .replaceAll("{nome}", String(NOME_CONVIDADOR || ""))
    .replaceAll("{link}", "");
}

function montarMensagemCurtaStatus() {
  return getTenantInviteShortText();
}

async function solicitarLinkConvite(canal = "whatsapp", origem = "dashboard_card_share") {
  const response = await fetch("/api/invite/enviar.php", {
    method: "POST",
    cache: "no-store",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      canal,
      origem
    })
  });

  const data = await response.json();

  if (!data || data.ok !== true) {
    throw new Error(data && data.erro ? data.erro : "Não foi possível gerar o link.");
  }

  const link = String(data.link || CONVITE_LINK_PUBLICO || "").trim();

  if (!link) {
    throw new Error("Link de convite vazio.");
  }

  return { data, link };
}

async function copiarLinkConvite() {
  rememberHomeAction("convite");

  try {
    const link = String(CONVITE_LINK_PUBLICO || "").trim();

    if (!link) {
      showElabToastCopy("Seu link ainda não está disponível.", "Ops!");
      return;
    }

    await navigator.clipboard.writeText(link);
    showElabToastCopy("Link copiado com sucesso.", "Tudo certo!");
  } catch (e) {
    showElabToastCopy("Não foi possível copiar o link agora.", "Ops!");
  }
}

async function compartilharConviteCanal(canal) {
  rememberHomeAction("convite");

  try {
    const origem = "dashboard_card_share_" + String(canal || "geral");
    const { link } = await solicitarLinkConvite(canal, origem);

    const mensagemCompleta = montarMensagemConviteBase(link);
    const mensagemSemLink = montarMensagemConviteSemLink();
    const mensagemCurtaStatus = montarMensagemCurtaStatus();

    if (canal === "whatsapp") {
      window.location.href = "https://wa.me/?text=" + encodeURIComponent(mensagemCompleta);
      return;
    }

   if (canal === "facebook_post") {
  const textoFacebookBase =
    typeof TENANT_INVITE_SHARE_FACEBOOK_TEXT !== "undefined" && TENANT_INVITE_SHARE_FACEBOOK_TEXT
      ? String(TENANT_INVITE_SHARE_FACEBOOK_TEXT)
      : `${getTenantInviteCommunityText()}

No app você pode acompanhar as conversas, fortalecer nosso time comentando nos posts e ainda ganhar pontos.

Entre pelo meu convite:
{link}`;

  const textoFacebook = textoFacebookBase
    .replaceAll("{nome}", String(NOME_CONVIDADOR || ""))
    .replaceAll("{link}", link);

  try {
    await navigator.clipboard.writeText(textoFacebook);
    showElabToastCopy("Texto copiado. Agora cole no post do Facebook.", "Pronto!");
  } catch (e) {
    showElabToastCopy("Abra o Facebook e cole seu texto manualmente.", "Atenção");
  }

  window.open(
    "https://www.facebook.com/sharer/sharer.php?u=" + encodeURIComponent(link),
    "_blank"
  );
  return;
}

    if (canal === "instagram_dm") {
      if (navigator.share) {
        try {
          await navigator.share({
            title: getTenantInviteShareTitle(),
            text: mensagemSemLink,
            url: link
          });
          return;
        } catch (err) {
          if (err && err.name === "AbortError") {
            return;
          }
        }
      }

      await navigator.clipboard.writeText(mensagemCompleta);
      showElabToastCopy("Mensagem copiada. Abra o Instagram e envie na DM.", "Pronto!");
      return;
    }

    if (canal === "whatsapp_status") {
      if (navigator.share) {
        try {
          await navigator.share({
            title: getTenantInviteShareTitle(),
            text: mensagemCurtaStatus,
            url: link
          });
          return;
        } catch (err) {
          if (err && err.name === "AbortError") {
            return;
          }
        }
      }

      await navigator.clipboard.writeText(`${mensagemCurtaStatus}\n${link}`);
      showElabToastCopy("Conteúdo copiado. Abra o WhatsApp Status e cole.", "Pronto!");
      return;
    }

    if (canal === "share_nativo") {
      if (navigator.share) {
        try {
          await navigator.share({
            title: getTenantInviteShareTitle(),
            text: mensagemSemLink,
            url: link
          });
          return;
        } catch (err) {
          if (err && err.name === "AbortError") {
            return;
          }
        }
      }

      await navigator.clipboard.writeText(mensagemCompleta);
      showElabToastCopy("Mensagem copiada com sucesso.", "Pronto!");
      return;
    }

    await navigator.clipboard.writeText(mensagemCompleta);
    showElabToastCopy("Mensagem copiada com sucesso.", "Pronto!");
  } catch (e) {
    showElabToastCopy(
      e && e.message ? e.message : "Não foi possível compartilhar agora.",
      "Ops!"
    );
  }
}

/*
====================================
RANKING HOME AJAX
====================================
*/

(function initRankingTabs() {
  function bindRankingTabs() {
    const rankingCard = document.getElementById("rankingHomeCard");
    if (!rankingCard) return;

    const tabs = rankingCard.querySelectorAll("[data-ranking-tab]");
    if (!tabs.length) return;

    tabs.forEach((tab) => {
      if (tab.dataset.rankingBound === "1") return;

      tab.dataset.rankingBound = "1";

      tab.addEventListener("click", async function (e) {
        e.preventDefault();

        const aba = this.getAttribute("data-ranking-tab");
        if (!aba) return;

        rememberHomeAction("ranking");

        try {
          rankingCard.classList.add("is-loading");

          const response = await fetch(
            "/dashboard/index.php?ranking_aba=" + encodeURIComponent(aba),
            {
              headers: {
                "X-Requested-With": "XMLHttpRequest"
              },
              cache: "no-store"
            }
          );

          const html = await response.text();
          const parser = new DOMParser();
          const doc = parser.parseFromString(html, "text/html");
          const novoCard = doc.getElementById("rankingHomeCard");

          if (!novoCard) {
            throw new Error("rankingHomeCard não encontrado no retorno");
          }

          rankingCard.replaceWith(novoCard);
          bindRankingTabs();
          bindHomeActionMemory();
        } catch (err) {
          console.error("Erro ao atualizar ranking da home:", err);
        }
      });
    });
  }

  bindRankingTabs();
})();

/*
====================================
MONITOR REDES ACCORDION
====================================
*/

(function initMonitorRedesAccordion() {
  const card = document.getElementById("monitorRedesCard");
  const toggle = document.getElementById("monitorRedesToggle");

  if (!card || !toggle) return;

  toggle.addEventListener("click", function () {
    const aberto = card.classList.toggle("is-open");
    toggle.setAttribute("aria-expanded", aberto ? "true" : "false");
  });
})();

/*
====================================
LIDERANCA ACCORDION
====================================
*/

(function initLiderancaAccordion() {
  const card = document.getElementById("liderancaCard");
  const toggle = document.getElementById("liderancaToggle");

  if (!card || !toggle) return;

  toggle.addEventListener("click", function () {
    const aberto = card.classList.toggle("is-open");
    toggle.setAttribute("aria-expanded", aberto ? "true" : "false");
  });
})();

/*
====================================
MISSAO TIMER
====================================
*/

function stopMissaoTimer() {
  if (missaoTimerInterval) {
    clearInterval(missaoTimerInterval);
    missaoTimerInterval = null;
  }
}

function resolveMissaoDeadline() {
  const card = document.getElementById("cardMissaoAtual");
  if (!card) return "";

  const datasetValue = String(card.dataset.missaoExpiraEm || "").trim();
  if (datasetValue) return datasetValue;

  if (typeof MISSAO_EXPIRA_EM !== "undefined" && MISSAO_EXPIRA_EM) {
    return String(MISSAO_EXPIRA_EM).trim();
  }

  return "";
}

function parseMissaoDeadline(raw) {
  if (!raw) return null;

  const normalized = String(raw).replace(" ", "T");
  const date = new Date(normalized);

  if (Number.isNaN(date.getTime())) {
    return null;
  }

  return date;
}

function formatCountdown(diffSeconds) {
  const total = Math.max(0, Number(diffSeconds || 0));
  const horas = Math.floor(total / 3600);
  const minutos = Math.floor((total % 3600) / 60);
  const segundos = total % 60;

  if (horas > 0) {
    return (
      String(horas).padStart(2, "0") + ":" +
      String(minutos).padStart(2, "0") + ":" +
      String(segundos).padStart(2, "0")
    );
  }

  return (
    String(minutos).padStart(2, "0") + ":" +
    String(segundos).padStart(2, "0")
  );
}

function updateMissaoTimerTick() {
  const timerWrap = document.getElementById("missaoTimer");
  const timerText = document.getElementById("missaoTempoRestante");
  const card = document.getElementById("cardMissaoAtual");

  if (!timerWrap || !timerText || !card || card.style.display === "none") {
    stopMissaoTimer();
    return;
  }

  const deadlineRaw = resolveMissaoDeadline();
  const deadline = parseMissaoDeadline(deadlineRaw);

  if (!deadline) {
    timerText.textContent = "--:--";
    hideElement(timerWrap);
    stopMissaoTimer();
    return;
  }

  const now = new Date();
  const diff = Math.floor((deadline.getTime() - now.getTime()) / 1000);

  showElement(timerWrap, "flex");
  timerWrap.classList.remove("is-urgente", "is-expired");

  if (diff <= 0) {
    timerText.textContent = "encerrando";
    timerWrap.classList.add("is-expired");
    stopMissaoTimer();
    return;
  }

  timerText.textContent = formatCountdown(diff);

  if (diff <= 3600) {
    timerWrap.classList.add("is-urgente");
  }
}

function initMissaoTimer() {
  stopMissaoTimer();

  const timerWrap = document.getElementById("missaoTimer");
  const card = document.getElementById("cardMissaoAtual");
  if (!timerWrap || !card || card.style.display === "none") return;

  updateMissaoTimerTick();
  missaoTimerInterval = setInterval(updateMissaoTimerTick, 1000);
}

/* ===== FIM PARTE 2/3 ===== */

/*
====================================
MISSAO ATUAL - COMPARTILHAR
====================================
*/

function bindMissaoAtualCompartilhar() {
  const btn = document.getElementById("btnMissaoAtualCompartilhar");
  if (!btn || btn.dataset.bound === "1") return;

  btn.dataset.bound = "1";

  btn.addEventListener("click", async function () {
    rememberHomeAction("missao");

    const postId = parseInt(btn.dataset.postId || "0", 10);
    const postUrl = (btn.dataset.postUrl || "").trim();
    const missaoCodigo = (btn.dataset.missaoCodigo || "").trim();

    if (!postUrl) {
      alert("Não foi possível preparar o compartilhamento agora.");
      return;
    }

    btn.disabled = true;

    try {
      const response = await fetch("/core/missao/share-endpoint.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify({
          post_id: postId,
          url: postUrl,
          missao_codigo: missaoCodigo
        })
      });

      const data = await response.json();

      if (!data || data.ok !== true || !data.wa_url) {
        throw new Error(
          data && data.erro ? data.erro : "Falha ao gerar compartilhamento"
        );
      }

      stopHomeAlertEffects();
      window.location.href = data.wa_url;
    } catch (err) {
      console.error("Erro no compartilhamento da missão atual:", err);
      alert("Não foi possível gerar o compartilhamento agora.");
      btn.disabled = false;
    }
  });
}

bindMissaoAtualCompartilhar();

/*
====================================
MISSAO CARD
====================================
*/

function renderMissaoCard(missao) {
  const card = document.getElementById("cardMissaoAtual");
  const titulo = document.getElementById("missaoTitulo");
  const urgencia = document.getElementById("missaoUrgenciaLabel");
  const imagem = document.getElementById("missaoImagem");
  const caption = document.getElementById("missaoCaption");
  const descricao = document.getElementById("missaoDescricao");
  const narrativa = document.getElementById("missaoNarrativa");
  const timer = document.getElementById("missaoTimer");
  const pontos = document.getElementById("missaoPontos");
  const acoes = document.getElementById("missaoAcoes");

  if (!card || !titulo || !imagem || !caption || !descricao || !pontos || !acoes) {
    return;
  }

  if (!missao || !missao.titulo) {
    hideElement(card);
    stopMissaoTimer();
    return;
  }

  showElement(card);

  card.dataset.missaoCodigo = missao.codigo || "";
  card.dataset.missaoPostId = String(missao.post_id || 0);
  card.dataset.missaoExpiraEm = String(
    missao.expira_em || (typeof MISSAO_EXPIRA_EM !== "undefined" ? MISSAO_EXPIRA_EM : "")
  );

  setText(titulo, missao.titulo || "Missão do dia");
  setText(descricao, missao.descricao || "");

  if (urgencia) {
    const urgenciaTexto = String(missao.urgencia_label || "").trim();
    if (urgenciaTexto) {
      setText(urgencia, urgenciaTexto);
      showElement(urgencia, "inline-flex");
    } else {
      hideElement(urgencia);
    }
  }

  if (missao.imagem) {
    imagem.src = missao.imagem;
    showElement(imagem);
  } else {
    const placeholder = getTenantPostPlaceholderUrl();
    if (placeholder) {
      imagem.src = placeholder;
    }
    hideElement(imagem);
  }

  if (missao.caption) {
    setText(caption, missao.caption);
    showElement(caption);
  } else {
    setText(caption, "");
    hideElement(caption);
  }

  if (narrativa) {
    if (missao.narrativa) {
      setText(narrativa, missao.narrativa);
      showElement(narrativa);
    } else {
      setText(narrativa, "");
      hideElement(narrativa);
    }
  }

  if (timer) {
    showElement(timer, "flex");
  }

  if (Number(missao.pontos || 0) > 0) {
    setText(pontos, "🔥 +" + formatNumberBR(missao.pontos) + " Pontos");
    showElement(pontos);
  } else {
    setText(pontos, "");
    hideElement(pontos);
  }

  const tipoAcao = String(missao.tipo_acao || "abrir");
  const btnClass = String(missao.btn_class || "btn-missao-cta is-default");
  const icon = String(missao.icon || "bi-lightning-charge-fill");
  const ctaLabel = String(missao.cta_label || "Abrir agora");
  const urlDestino = String(missao.url_destino || "#");
  const codigo = String(missao.codigo || "");
  const postId = Number(missao.post_id || 0);

  if (tipoAcao === "compartilhar") {
    acoes.innerHTML = `
      <button
        type="button"
        class="${escapeHtml(btnClass)}"
        id="btnMissaoAtualCompartilhar"
        data-missao-codigo="${escapeHtml(codigo)}"
        data-post-id="${escapeHtml(postId)}"
        data-post-url="${escapeHtml(urlDestino)}"
        data-home-action="missao"
      >
        <i class="bi ${escapeHtml(icon)}"></i>
        <span id="missaoCtaLabel">${escapeHtml(ctaLabel)}</span>
      </button>
    `;
  } else {
    acoes.innerHTML = `
      <a
        href="${escapeHtml(urlDestino !== "" ? urlDestino : "#")}"
        target="_blank"
        class="${escapeHtml(btnClass)}"
        id="missaoCtaLink"
        data-missao-codigo="${escapeHtml(codigo)}"
        data-post-id="${escapeHtml(postId)}"
        data-post-url="${escapeHtml(urlDestino)}"
        data-home-action="missao"
      >
        <i class="bi ${escapeHtml(icon)}" id="missaoCtaIcon"></i>
        <span id="missaoCtaLabel">${escapeHtml(ctaLabel)}</span>
      </a>
    `;
  }

  bindMissaoAtualCompartilhar();
  bindPushInteractionStop();
  bindHomeActionMemory();
  initMissaoTimer();
}

/*
====================================
HOME STATE
====================================
*/

function homeStateHash(data) {
  try {
    return JSON.stringify({
      xp_total: data?.xp_total || 0,
      ranking_posicao: data?.ranking_posicao || 0,
      xp_recente: data?.xp_recente || 0,
      hero_estado: data?.hero_estado || "",
      toast_tipo: data?.toast_tipo || "",
      comentario_validado: data?.comentario_validado || "",
      novo_post: data?.novo_post || "",
      novo_post_id: data?.novo_post_id || 0,
      missao_codigo: data?.missao?.codigo || "",
      missao_post_id: data?.missao?.post_id || 0,
      missao_expira_em: data?.missao?.expira_em || "",
      missao_urgencia_label: data?.missao?.urgencia_label || ""
    });
  } catch (e) {
    return String(Date.now());
  }
}

function applyHomeState(state) {
  if (!state || state.ok !== true) return;

  animateXpTo(Number(state.xp_total || 0));
  updateRankingPosicaoValue(Number(state.ranking_posicao || 0));
  renderMissaoCard(state.missao || null);

  if (Number(state.xp_recente || 0) > 0 || state.comentario_validado === "sim") {
    triggerHappyHero();
  }

  if (state.toast_tipo === "nova_missao" && state.missao) {
    updateSmartToastForMissao(state.missao);
    startForcedPushEffects();
  }

  bindPushInteractionStop();
  bindHomeActionMemory();
}

async function checkHomeState() {
  if (homeStateBusy) return;
  if (typeof HOME_STATE_URL === "undefined" || !HOME_STATE_URL) return;

  homeStateBusy = true;

  try {
    const response = await fetch(HOME_STATE_URL, {
      cache: "no-store",
      headers: {
        "X-Requested-With": "XMLHttpRequest"
      }
    });

    if (!response.ok) {
      throw new Error("Falha ao consultar home-state");
    }

    const state = await response.json();

    if (!state || state.ok !== true) {
      return;
    }

    const hash = homeStateHash(state);
    if (hash === homeLastStateHash) {
      return;
    }

    homeLastStateHash = hash;
    applyHomeState(state);
  } catch (err) {
    console.log("home state erro", err);
  } finally {
    homeStateBusy = false;
  }
}

(function initHomeState() {
  if (typeof HOME_STATE_BOOT !== "undefined" && HOME_STATE_BOOT) {
    homeLastStateHash = homeStateHash(HOME_STATE_BOOT);
    applyHomeState(HOME_STATE_BOOT);
  }

  setInterval(checkHomeState, 4000);
})();

/*
====================================
MONITOR REDES
====================================
*/

function formatMonitorNumber(value, decimals = 0) {
  if (value === null || typeof value === "undefined" || value === "") {
    return "--";
  }

  const num = Number(value);
  if (!Number.isFinite(num)) {
    return "--";
  }

  return num.toLocaleString("pt-BR", {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals
  });
}

function formatMonitorPercent(value) {
  if (value === null || typeof value === "undefined" || value === "") {
    return "--";
  }

  const num = Number(value);
  if (!Number.isFinite(num)) {
    return "--";
  }

  return formatMonitorNumber(num, 2) + "%";
}

function setMonitorValue(id, text) {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent = text;
}

function setMonitorChipVisible(id, visible) {
  const el = document.getElementById(id);
  if (!el) return;
  el.style.display = visible ? "inline-flex" : "none";
}

function applyMonitorRedesState(data) {
  if (!data || data.ok !== true) return;

  const alcanceTotal =
    data.alcance_total ??
    data.alcance ??
    (
      Number(data.redes?.instagram?.alcance?.["30d"] || 0) +
      Number(data.redes?.facebook?.alcance?.["30d"] || 0)
    );

  const engajamentoTotal =
    data.engajamento_total ??
    data.engajamento?.["30d"] ??
    null;

  const visualizacoes =
    data.visualizacoes ??
    data.views_total ??
    null;

  const taxaEngajamento =
    data.taxa_engajamento ??
    data.taxa_engajamento_total ??
    null;

  const interacoes =
    data.interacoes ??
    engajamentoTotal ??
    null;

  const curtidas =
    data.curtidas ??
    data.reacoes_total ??
    null;

  const comentarios =
    data.comentarios_total_30d ??
    data.comentarios?.["30d"] ??
    null;

  const compartilhamentos =
    data.compartilhamentos ??
    data.compartilhamentos_total ??
    null;

  setMonitorValue("monitorAlcanceTotal", formatMonitorNumber(alcanceTotal));
  setMonitorValue("monitorEngajamentoTotal", formatMonitorNumber(engajamentoTotal));
  setMonitorValue("monitorVisualizacoes", formatMonitorNumber(visualizacoes));
  setMonitorValue("monitorTaxaEngajamento", formatMonitorPercent(taxaEngajamento));
  setMonitorValue("monitorInteracoes", formatMonitorNumber(interacoes));
  setMonitorValue("monitorCurtidas", formatMonitorNumber(curtidas));
  setMonitorValue("monitorComentarios", formatMonitorNumber(comentarios));
  setMonitorValue("monitorCompartilhamentos", formatMonitorNumber(compartilhamentos));

  if (document.getElementById("monitorRedesPeriodo")) {
    setMonitorValue(
      "monitorRedesPeriodo",
      data.periodo_label || "30 dias"
    );
  }

  if (data.redes_conectadas) {
    setMonitorChipVisible("monitorRedeInstagram", !!data.redes_conectadas.instagram);
    setMonitorChipVisible("monitorRedeFacebook", !!data.redes_conectadas.facebook);
  }
}

async function carregarMonitorRedes() {
  if (typeof MONITOR_REDES_URL === "undefined" || !MONITOR_REDES_URL) return;

  const card = document.getElementById("monitorRedesCard");
  if (!card) return;

  try {
    const response = await fetch(MONITOR_REDES_URL, {
      cache: "no-store",
      headers: {
        "X-Requested-With": "XMLHttpRequest"
      }
    });

    if (!response.ok) {
      throw new Error("Falha ao carregar monitor de redes");
    }

    const data = await response.json();

    if (!data || data.ok !== true) {
      return;
    }

    applyMonitorRedesState(data);
  } catch (err) {
    console.error("Erro ao carregar monitor de redes:", err);
  }
}

(function initMonitorRedes() {
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", carregarMonitorRedes);
  } else {
    carregarMonitorRedes();
  }
})();

/*
====================================
MONITOR APP
====================================
*/

function formatMonitorAppNumber(value) {
  if (value === null || typeof value === "undefined" || value === "") {
    return "--";
  }

  const num = Number(value);
  if (!Number.isFinite(num)) {
    return "--";
  }

  return num.toLocaleString("pt-BR");
}

function formatMonitorAppPercent(value) {
  if (value === null || typeof value === "undefined" || value === "") {
    return "--";
  }

  const num = Number(value);
  if (!Number.isFinite(num)) {
    return "--";
  }

  return num.toLocaleString("pt-BR", {
    minimumFractionDigits: 1,
    maximumFractionDigits: 2
  }) + "%";
}

function updateMonitorAppNarrativa() {
  const narrativa = document.getElementById("monitorAppNarrativa");
  const impactoEl = document.getElementById("monitorAppImpactoEngajamento");
  const comentariosEl = document.getElementById("monitorAppComentariosElab");
  const interacoesEl = document.getElementById("monitorAppInteracoesElab");

  if (!narrativa || !impactoEl || !comentariosEl || !interacoesEl) return;

  const impacto = String(impactoEl.textContent || "").trim();
  const comentarios = String(comentariosEl.textContent || "").trim();
  const interacoes = String(interacoesEl.textContent || "").trim();
  const appName = getTenantAppName();

  if (
    !impacto || impacto === "--" ||
    !comentarios || comentarios === "--" ||
    !interacoes || interacoes === "--"
  ) {
    narrativa.textContent = "A comunidade " + appName + " está ajudando a transformar presença em impacto real nas redes.";
    return;
  }

  narrativa.textContent =
    "A comunidade " +
    appName +
    " já gerou " +
    impacto +
    " do engajamento, " +
    comentarios +
    " comentários e " +
    interacoes +
    " interações no período.";
}

function applyMonitorAppState(data) {
  if (!data || data.ok !== true) return;

  const resumo = data.resumo || {};
  const periodo = data.periodo || {};

  setMonitorValue(
    "monitorAppComentariosElab",
    formatMonitorAppNumber(resumo.comentarios_elab)
  );

  setMonitorValue(
    "monitorAppInteracoesElab",
    formatMonitorAppNumber(resumo.interacoes_elab)
  );

  setMonitorValue(
    "monitorAppImpactoEngajamento",
    formatMonitorAppPercent(resumo.impacto_engajamento_percentual)
  );

  setMonitorValue(
    "monitorAppUsuariosAtivos",
    formatMonitorAppNumber(resumo.usuarios_ativos)
  );

  setMonitorValue(
    "monitorAppPeriodo",
    periodo.label || "30 dias"
  );

  updateMonitorAppNarrativa();
}

async function carregarMonitorApp() {
  if (typeof MONITOR_APP_URL === "undefined" || !MONITOR_APP_URL) return;

  const card = document.getElementById("monitorAppCard");
  if (!card) return;

  card.classList.add("is-loading");

  try {
    const response = await fetch(MONITOR_APP_URL + "?days=30", {
      cache: "no-store",
      headers: {
        "X-Requested-With": "XMLHttpRequest"
      }
    });

    if (!response.ok) {
      throw new Error("Falha ao carregar monitor do aplicativo");
    }

    const data = await response.json();

    if (!data || data.ok !== true) {
      throw new Error("Resposta inválida do monitor do aplicativo");
    }

    applyMonitorAppState(data);
  } catch (err) {
    console.error("Erro ao carregar monitor do aplicativo:", err);

    setMonitorValue("monitorAppComentariosElab", "--");
    setMonitorValue("monitorAppInteracoesElab", "--");
    setMonitorValue("monitorAppImpactoEngajamento", "--");
    setMonitorValue("monitorAppUsuariosAtivos", "--");
    setMonitorValue("monitorAppPeriodo", "Últimos 30 dias");
    updateMonitorAppNarrativa();
  } finally {
    card.classList.remove("is-loading");
  }
}

(function initMonitorApp() {
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", carregarMonitorApp);
  } else {
    carregarMonitorApp();
  }
})();

/*
====================================
BOOT GERAL
====================================
*/

(function initHomeEnhancements() {
  document.addEventListener("DOMContentLoaded", () => {
    bindHomeActionMemory();
    initToastMemory();
    initMissaoTimer();

    const remembered = getRememberedHomeAction();
    if (remembered === "missao") {
      removeLocalStorageSafe("elab_home_toast_hidden");
      revealSmartToastIfNeeded();
    }
  });
})();

/* ===== FIM PARTE 3/3 ===== */

let gestaoPessoasLoaded = false;

function toggleGestaoPessoas() {
  const body = document.getElementById("gestaoPessoasBody");
  const chevron = document.getElementById("gestaoPessoasChevron");

  if (!body || !chevron) {
    return;
  }

  const aberto = body.style.display !== "none";

  if (aberto) {
    body.style.display = "none";
    chevron.innerHTML = '<i class="bi bi-chevron-down"></i>';
    return;
  }

  body.style.display = "block";
  chevron.innerHTML = '<i class="bi bi-chevron-up"></i>';

  if (!gestaoPessoasLoaded) {
    carregarGestaoPessoas();
  }
}

function getRitmoCadastro(cadastros24h, cadastros7d) {
  const hoje = Number(cadastros24h || 0);
  const media7d = Number(cadastros7d || 0) / 7;

  if (media7d <= 0 && hoje <= 0) {
    return "⚪ Sem movimento recente";
  }

  if (hoje > media7d * 1.2) {
    return "🔥 Crescimento acelerando";
  }

  if (hoje >= media7d * 0.7) {
    return "⚖️ Crescimento estável";
  }

  return "⚠️ Crescimento abaixo do esperado";
}

function getAtivacaoTexto(comentarios30d) {
  const media = Number(comentarios30d || 0) / 30;

  if (media > 500) {
    return {
      titulo: "Alta participação",
      resumo: "A comunidade está muito ativa nas redes."
    };
  }

  if (media > 200) {
    return {
      titulo: "Engajamento consistente",
      resumo: "A base segue respondendo com boa frequência."
    };
  }

  return {
    titulo: "Engajamento baixo",
    resumo: "Há espaço para ativar mais comentários da base."
  };
}

function montarResumoPulso(d) {
  const partes = [];
  const hoje = Number(d.cadastros_24h || 0);
  const semana = Number(d.cadastros_7d || 0);
  const comentarios30d = Number(d.comentarios_30d || 0);
  const mediaComentarios = comentarios30d / 30;

  if (semana > 0) {
    partes.push("Sua base trouxe " + formatNumberBR(semana) + " novos cadastros nos últimos 7 dias.");
  } else {
    partes.push("Sua base ainda não gerou novos cadastros nos últimos 7 dias.");
  }

  if (hoje === 0) {
    partes.push("Hoje o crescimento está parado e merece atenção imediata.");
  } else if (hoje >= (semana / 7)) {
    partes.push("O ritmo de hoje está sustentando bem a média da semana.");
  } else {
    partes.push("Hoje o ritmo está abaixo da média recente.");
  }

  if (mediaComentarios > 500) {
    partes.push("A presença da comunidade nas redes segue forte.");
  } else if (mediaComentarios > 200) {
    partes.push("O engajamento está consistente nas redes.");
  } else {
    partes.push("O engajamento ainda pode crescer mais nas redes.");
  }

  return partes.join(" ");
}

async function carregarGestaoPessoas() {
  try {
    const response = await fetch(
      typeof GESTAO_PESSOAS_URL !== "undefined"
        ? GESTAO_PESSOAS_URL
        : "/api/pessoas/gestao.php",
      {
        method: "GET",
        cache: "no-store",
        headers: {
          "Accept": "application/json"
        }
      }
    );

    const data = await response.json();

    if (!data || data.ok !== true || !data.dados) {
      return;
    }

    const d = data.dados;

    const setTextById = (id, value) => {
      const el = document.getElementById(id);
      if (el) {
        el.textContent = value;
      }
    };

    const mediaCadastrosDia = Number(d.cadastros_7d || 0) / 7;
    const mediaComentariosDia = Number(d.comentarios_30d || 0) / 30;
    const ritmoBadge = getRitmoCadastro(d.cadastros_24h, d.cadastros_7d);
    const ativacao = getAtivacaoTexto(d.comentarios_30d);

    setTextById("gpTotalCadastros", formatNumberBR(d.total_cadastros));
    setTextById("gpCadastros24h", formatNumberBR(d.cadastros_24h));
    setTextById("gpCadastros7d", formatNumberBR(d.cadastros_7d));
    setTextById("gpAniversariantesDia", formatNumberBR(d.aniversariantes_dia));
    setTextById("gpAniversariantesMes", formatNumberBR(d.aniversariantes_mes));
    setTextById("gpMesAtualNome", String(d.mes_atual_nome || "--"));
    setTextById("gpTotalConvites", formatNumberBR(d.total_convites_enviados));
    setTextById("gpComentarios30d", formatNumberBR(d.comentarios_30d));
    setTextById("gpMediaCadastrosDia", mediaCadastrosDia.toLocaleString("pt-BR", {
      minimumFractionDigits: 1,
      maximumFractionDigits: 1
    }) + "/dia");
    setTextById("gpMediaComentariosDia", formatNumberBR(Math.round(mediaComentariosDia)) + "/dia");
    setTextById("gpRitmoBadge", ritmoBadge);
setTextById(
  "gpRitmoTextoSecundario",
  Number(d.cadastros_24h || 0) === 0
    ? "Hoje o crescimento está parado e merece atenção."
    : "A base segue em movimento no curto prazo."
);
    setTextById("gpAtivacaoTexto", ativacao.titulo);
    setTextById("gpAtivacaoResumo", ativacao.resumo);

    const oportunidadeTexto =
      Number(d.aniversariantes_dia || 0) > 0
        ? "👉 Hoje é um excelente momento para contato e ativação da base."
        : "👉 Hoje há menos aniversários, então o foco pode ficar em crescimento e conversão.";

    setTextById("gpOportunidadeTexto", oportunidadeTexto);
    setTextById("gpResumoInteligente", montarResumoPulso(d));

    gestaoPessoasLoaded = true;
  } catch (error) {
    console.error("[GESTAO_PESSOAS]", error);
  }
}