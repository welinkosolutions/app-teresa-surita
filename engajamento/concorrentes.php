<?php
declare(strict_types=1);

/**
 * ======================================================
 * CAMINHO: app.elab.social/engajamento/concorrentes.php
 * NOME: Analisar Concorrentes
 * DESCRIÇÃO:
 * - Resumo executivo mobile dos concorrentes
 * - Consome /api/monitor/concorrentes.php
 * - Mostra comparativo do cliente com líderes
 * - Mostra top 5 concorrentes com comparativo visual
 * - Mostra posts destaque dos concorrentes
 * ======================================================
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

$pdo = dbRoraima();
$pessoa_id = (int) $_SESSION['pessoa_id'];

$stmt = $pdo->prepare("
    SELECT nome, apelido, chamar_por, perfil, status
    FROM pessoas
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$pessoa_id]);
$pessoa = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

if (!$pessoa || ($pessoa['status'] ?? '') !== 'ativo') {
    header('Location: /publico/logout.php');
    exit;
}

$idsMonitorRedes = [
    6607,
    6169,
    7160,
];

$temAcessoMonitorRedes = in_array($pessoa_id, $idsMonitorRedes, true);

if (!$temAcessoMonitorRedes) {
    header('Location: /dashboard/index.php');
    exit;
}

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
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Concorrentes • ELAB Social</title>

<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#0b6e7a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="ELAB Social">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root{
  --text:#102331;
  --brand1:#0b6e7a;
  --brand2:#1aa8b2;
  --shadow:0 12px 28px rgba(16,35,49,.10);
  --green-soft:rgba(37,211,102,.18);
  --green-line:rgba(37,211,102,.28);
  --green-text:#95f2b4;
  --red-soft:rgba(239,68,68,.18);
  --red-line:rgba(239,68,68,.28);
  --red-text:#ffb0b0;
  --neutral-soft:rgba(255,255,255,.09);
  --neutral-line:rgba(255,255,255,.14);
  --neutral-text:rgba(255,255,255,.82);
}

*{ box-sizing:border-box; }
html{ -webkit-text-size-adjust:100%; }

body{
  margin:0;
  background:linear-gradient(180deg,#eef2f5 0%, #e9eef2 100%);
  font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
  color:var(--text);
}

a, button, input, select, textarea{ font:inherit; }

.page-wrap{
  padding-bottom:110px;
}

.header{
  position:relative;
  z-index:1;
  background:
    radial-gradient(circle at top right, rgba(255,255,255,.18), transparent 32%),
    linear-gradient(135deg,var(--brand1),var(--brand2));
  color:#fff;
  padding:26px 18px 30px;
  border-radius:0 0 34px 34px;
  box-shadow:0 12px 36px rgba(11,110,122,.28);
}

.header-top{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:14px;
}

.header-left h1{
  margin:0;
  font-size:22px;
  font-weight:1000;
  letter-spacing:-.25px;
}

.header-left .sub{
  margin-top:8px;
  font-size:14px;
  font-weight:700;
  opacity:.95;
}

.btn-voltar{
  display:inline-flex;
  align-items:center;
  gap:8px;
  min-height:42px;
  padding:0 14px;
  border-radius:999px;
  color:#fff;
  text-decoration:none;
  background:rgba(255,255,255,.14);
  border:1px solid rgba(255,255,255,.16);
  font-size:13px;
  font-weight:900;
  white-space:nowrap;
}

.btn-voltar:hover{
  color:#fff;
  text-decoration:none;
}

.card-box{
  margin:18px;
  border-radius:26px;
  padding:18px;
  background:#fff;
  box-shadow:var(--shadow);
}

.card-dark{
  background:linear-gradient(135deg,#182235 0%, #24324a 100%);
  color:#fff;
  box-shadow:0 18px 36px rgba(16,35,49,.22);
}

.card-title{
  display:flex;
  align-items:center;
  gap:10px;
  margin:0 0 14px;
  font-size:17px;
  font-weight:1000;
  letter-spacing:-.2px;
}

.card-dark .card-title{
  color:#fff;
}

.alerta-desktop{
  display:flex;
  align-items:flex-start;
  gap:10px;
  padding:14px 16px;
  border-radius:18px;
  background:linear-gradient(135deg,rgba(255,255,255,.10) 0%, rgba(255,255,255,.06) 100%);
  border:1px solid rgba(255,255,255,.10);
  color:#fff;
}

.alerta-desktop i{
  font-size:18px;
  line-height:1;
  margin-top:1px;
  color:#fde68a;
}

.alerta-desktop strong{
  display:block;
  font-size:13px;
  font-weight:1000;
  margin-bottom:4px;
}

.alerta-desktop span{
  display:block;
  font-size:13px;
  line-height:1.45;
  color:rgba(255,255,255,.9);
  font-weight:700;
}

.conc-topo{
  margin-top:14px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  flex-wrap:wrap;
}

.conc-chip{
  display:inline-flex;
  align-items:center;
  gap:8px;
  min-height:34px;
  padding:0 14px;
  border-radius:999px;
  background:linear-gradient(135deg,#fd5949 0%, #d6249f 48%, #285AEB 100%);
  color:#fff;
  font-size:12px;
  font-weight:1000;
  border:1px solid rgba(255,255,255,.12);
  box-shadow:0 8px 18px rgba(214,36,159,.18);
}

.conc-periodo{
  font-size:13px;
  font-weight:1000;
  color:rgba(255,255,255,.84);
}

.kpi-grid{
  margin-top:14px;
  display:grid;
  grid-template-columns:1fr;
  gap:12px;
}

.kpi-card{
  position:relative;
  overflow:hidden;
  border-radius:22px;
  padding:16px;
  background:rgba(255,255,255,.07);
  border:1px solid rgba(255,255,255,.10);
}

.kpi-card::before{
  content:'';
  position:absolute;
  left:0;
  top:0;
  bottom:0;
  width:5px;
  border-radius:22px 0 0 22px;
}

.kpi-card.kpi-gold::before{
  background:linear-gradient(180deg,#fbbf24 0%, #f59e0b 100%);
}

.kpi-card.kpi-blue::before{
  background:linear-gradient(180deg,#60a5fa 0%, #2563eb 100%);
}

.kpi-card.kpi-green::before{
  background:linear-gradient(180deg,#4ade80 0%, #16a34a 100%);
}

.kpi-label{
  font-size:12px;
  font-weight:1000;
  text-transform:uppercase;
  letter-spacing:.3px;
  color:rgba(255,255,255,.70);
  margin-bottom:8px;
}

.kpi-row{
  display:flex;
  align-items:flex-end;
  justify-content:space-between;
  gap:12px;
}

.kpi-main{
  min-width:0;
  flex:1;
}

.kpi-name{
  font-size:17px;
  font-weight:1000;
  color:#fff;
  line-height:1.12;
}

.kpi-sub{
  margin-top:5px;
  font-size:13px;
  line-height:1.35;
  color:rgba(255,255,255,.82);
  font-weight:800;
}

.kpi-values{
  min-width:112px;
  text-align:right;
}

.kpi-value{
  font-size:26px;
  font-weight:1000;
  color:#fff;
  line-height:1;
}

.kpi-you{
  margin-top:6px;
  font-size:13px;
  font-weight:900;
  color:rgba(255,255,255,.82);
}

.kpi-badge{
  margin-top:8px;
  display:inline-flex;
  align-items:center;
  gap:6px;
  min-height:28px;
  padding:0 10px;
  border-radius:999px;
  font-size:11px;
  font-weight:1000;
  line-height:1;
}

.kpi-badge.up{
  background:var(--green-soft);
  color:var(--green-text);
  border:1px solid var(--green-line);
}

.kpi-badge.down{
  background:var(--red-soft);
  color:var(--red-text);
  border:1px solid var(--red-line);
}

.kpi-badge.equal{
  background:var(--neutral-soft);
  color:var(--neutral-text);
  border:1px solid var(--neutral-line);
}

.section-title{
  margin:18px 0 12px;
  font-size:16px;
  font-weight:1000;
  color:#fff;
}

.concorrente-lista{
  display:flex;
  flex-direction:column;
  gap:12px;
}

.concorrente-card{
  border-radius:22px;
  padding:14px;
  background:rgba(255,255,255,.06);
  border:1px solid rgba(255,255,255,.10);
}

.concorrente-top{
  display:flex;
  align-items:center;
  gap:12px;
}

.concorrente-foto{
  width:54px;
  height:54px;
  border-radius:50%;
  object-fit:cover;
  display:block;
  background:rgba(255,255,255,.10);
  border:1px solid rgba(255,255,255,.14);
}

.concorrente-foto-placeholder{
  width:54px;
  height:54px;
  border-radius:50%;
  display:flex;
  align-items:center;
  justify-content:center;
  background:rgba(255,255,255,.10);
  border:1px solid rgba(255,255,255,.14);
  color:#fff;
  font-size:18px;
}

.concorrente-info{
  min-width:0;
  flex:1;
}

.concorrente-nome{
  font-size:16px;
  font-weight:1000;
  color:#fff;
  line-height:1.15;
}

.concorrente-user{
  margin-top:4px;
  font-size:13px;
  font-weight:800;
  color:rgba(255,255,255,.76);
}

.concorrente-selo{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:28px;
  padding:0 10px;
  border-radius:999px;
  font-size:11px;
  font-weight:1000;
  line-height:1;
  white-space:nowrap;
  color:#082313;
  background:linear-gradient(135deg,#25d366 0%, #18b957 100%);
}

.concorrente-metricas{
  margin-top:12px;
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:10px;
}

.conc-mini{
  border-radius:16px;
  padding:12px;
  background:rgba(255,255,255,.07);
  border:1px solid rgba(255,255,255,.09);
}

.conc-mini-label{
  font-size:11px;
  font-weight:1000;
  text-transform:uppercase;
  letter-spacing:.3px;
  color:rgba(255,255,255,.68);
  margin-bottom:5px;
}

.conc-mini-value{
  font-size:18px;
  font-weight:1000;
  line-height:1.05;
  color:#fff;
}

.conc-mini-value.is-better{
  color:var(--green-text);
}

.conc-mini-value.is-worse{
  color:var(--red-text);
}

.conc-mini-value.is-equal{
  color:#fff;
}

.conc-mini-compare{
  margin-top:6px;
  font-size:12px;
  line-height:1.25;
  font-weight:800;
  color:rgba(255,255,255,.72);
}

.conc-mini-compare strong{
  color:#fff;
  font-weight:1000;
}

.conc-mini-badge{
  margin-top:8px;
  display:inline-flex;
  align-items:center;
  gap:6px;
  min-height:26px;
  padding:0 10px;
  border-radius:999px;
  font-size:11px;
  font-weight:1000;
  line-height:1;
}

.conc-mini-badge.up{
  background:var(--green-soft);
  color:var(--green-text);
  border:1px solid var(--green-line);
}

.conc-mini-badge.down{
  background:var(--red-soft);
  color:var(--red-text);
  border:1px solid var(--red-line);
}

.conc-mini-badge.equal{
  background:var(--neutral-soft);
  color:var(--neutral-text);
  border:1px solid var(--neutral-line);
}

.posts-lista{
  display:flex;
  flex-direction:column;
  gap:12px;
}

.post-card{
  border-radius:22px;
  padding:14px;
  background:rgba(255,255,255,.06);
  border:1px solid rgba(255,255,255,.10);
}

.post-main{
  display:grid;
  grid-template-columns:88px 1fr;
  gap:12px;
}

.post-thumb{
  width:88px;
  height:88px;
  border-radius:18px;
  object-fit:cover;
  display:block;
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.12);
}

.post-thumb-placeholder{
  width:88px;
  height:88px;
  border-radius:18px;
  display:flex;
  align-items:center;
  justify-content:center;
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.12);
  color:#fff;
  font-size:24px;
}

.post-body{
  min-width:0;
}

.post-head{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:10px;
  margin-bottom:8px;
}

.post-concorrente{
  font-size:15px;
  font-weight:1000;
  color:#fff;
  line-height:1.15;
}

.post-user{
  margin-top:3px;
  font-size:12px;
  font-weight:800;
  color:rgba(255,255,255,.74);
}

.post-score{
  text-align:right;
  min-width:72px;
}

.post-score-label{
  font-size:10px;
  font-weight:1000;
  text-transform:uppercase;
  letter-spacing:.3px;
  color:rgba(255,255,255,.68);
  margin-bottom:4px;
}

.post-score-value{
  font-size:24px;
  font-weight:1000;
  color:#d1fae5;
  line-height:1;
}

.post-chips{
  display:flex;
  gap:8px;
  flex-wrap:wrap;
  margin-bottom:8px;
}

.post-chip{
  display:inline-flex;
  align-items:center;
  gap:6px;
  min-height:26px;
  padding:0 10px;
  border-radius:999px;
  font-size:11px;
  font-weight:1000;
  line-height:1;
  color:#fff;
  border:1px solid rgba(255,255,255,.12);
}

.post-chip.instagram{
  background:linear-gradient(135deg,#fd5949 0%, #d6249f 48%, #285AEB 100%);
}

.post-chip.tipo{
  background:rgba(255,255,255,.10);
}

.post-caption{
  font-size:13px;
  line-height:1.45;
  color:rgba(255,255,255,.84);
  font-weight:700;
}

.post-metricas{
  margin-top:12px;
  display:grid;
  grid-template-columns:1fr 1fr 1fr;
  gap:10px;
}

.post-mini{
  border-radius:14px;
  padding:10px;
  background:rgba(255,255,255,.07);
  border:1px solid rgba(255,255,255,.09);
}

.post-mini-label{
  font-size:10px;
  font-weight:1000;
  text-transform:uppercase;
  letter-spacing:.3px;
  color:rgba(255,255,255,.68);
  margin-bottom:4px;
}

.post-mini-value{
  font-size:16px;
  font-weight:1000;
  color:#fff;
  line-height:1.05;
}

.post-foot{
  margin-top:12px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  flex-wrap:wrap;
}

.post-date{
  font-size:12px;
  font-weight:800;
  color:rgba(255,255,255,.74);
}

.post-link{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  min-height:38px;
  padding:0 14px;
  border-radius:14px;
  text-decoration:none;
  color:#082313;
  background:linear-gradient(135deg,#25d366 0%, #18b957 100%);
  font-size:13px;
  font-weight:1000;
  box-shadow:0 10px 20px rgba(37,211,102,.18);
}

.post-link:hover{
  color:#082313;
  text-decoration:none;
  transform:translateY(-1px);
}

.empty-state,
.loading-state{
  padding:24px 18px;
  border-radius:18px;
  background:rgba(255,255,255,.06);
  border:1px solid rgba(255,255,255,.10);
  color:rgba(255,255,255,.88);
  font-size:14px;
  font-weight:700;
  text-align:center;
}

.footer-nav{
  position:fixed;
  left:0;
  right:0;
  bottom:0;
  z-index:20;
  background:rgba(255,255,255,.96);
  backdrop-filter:blur(10px);
  -webkit-backdrop-filter:blur(10px);
  border-top:1px solid #e6ecf1;
  box-shadow:0 -6px 20px rgba(0,0,0,.06);
  display:flex;
  justify-content:space-around;
  padding:10px 6px calc(10px + env(safe-area-inset-bottom));
}

.footer-nav a{
  flex:1;
  text-align:center;
  text-decoration:none;
  color:#5b6c78;
  font-size:12px;
  font-weight:800;
}

.footer-nav i{
  display:block;
  font-size:20px;
  margin-bottom:4px;
}

.footer-nav a.active,
.footer-nav a:hover{
  color:var(--brand1);
}

@media (min-width:700px){
  .page-wrap{
    max-width:680px;
    margin:0 auto;
  }

  .footer-nav{
    max-width:680px;
    margin:0 auto;
    left:50%;
    transform:translateX(-50%);
    border-radius:20px 20px 0 0;
  }
}

@media (max-width:420px){
  .concorrente-metricas{
    grid-template-columns:1fr;
  }

  .post-main{
    grid-template-columns:1fr;
  }

  .post-thumb,
  .post-thumb-placeholder{
    width:100%;
    height:180px;
  }

  .post-metricas{
    grid-template-columns:1fr;
  }

  .kpi-row{
    flex-direction:column;
    align-items:flex-start;
  }

  .kpi-values{
    text-align:left;
    min-width:0;
  }
}
</style>
</head>
<body>

<div class="page-wrap">

  <div class="header">
    <div class="header-top">
      <div class="header-left">
        <h1>Analisar Concorrentes</h1>
        <div class="sub">Leitura rápida para decisão em movimento</div>
      </div>

      <a href="/engajamento/engajamento.php" class="btn-voltar">
        <i class="bi bi-chevron-left"></i>
        Voltar
      </a>
    </div>
  </div>

  <div class="card-box card-dark">
    <div class="card-title">
      <i class="bi bi-people-fill"></i>
      Radar de Concorrência
    </div>

    <div class="alerta-desktop">
      <i class="bi bi-display"></i>
      <div>
        <span>Esta versão mobile foi pensada para acompanhamento executivo, comparação rápida e tomada de decisão em movimento.</span>
      </div>
    </div>

    <div class="conc-topo">
      <div class="conc-chip">
        <i class="bi bi-instagram"></i>
        Instagram
      </div>

      <div class="conc-periodo" id="concorrentesPeriodo">30 dias</div>
    </div>

    <div class="kpi-grid" id="concorrentesKpis">
      <div class="loading-state">Carregando resumo dos concorrentes...</div>
    </div>

    <div class="section-title">Top concorrentes</div>
    <div class="concorrente-lista" id="listaConcorrentes">
      <div class="loading-state">Carregando concorrentes...</div>
    </div>

    <div class="section-title">Posts destaque dos concorrentes</div>
    <div class="posts-lista" id="listaPostsConcorrentes">
      <div class="loading-state">Carregando posts destaque...</div>
    </div>
  </div>

   <div class="footer-nav">
       <a href="/perfil/suporte.php">
      <i class="bi bi-headset"></i>
      Suporte
    </a>
        <a href="/comunidade/ranking.php">
      <i class="bi bi-trophy"></i>
      Ranking
    </a>
    <a href="/dashboard/index.php" class="active">
      <i class="bi bi-house"></i>
      Início
    </a>
    <a href="/pessoas/perfil.php">
      <i class="bi bi-person-circle"></i>
      Perfil
    </a>
   
    <a href="/publico/logout.php">
      <i class="bi bi-box-arrow-right"></i>
      Sair
    </a>
  </div>
</div>

<script>
const CONCORRENTES_API_URL = '/api/monitor/concorrentes.php';

let concorrentesClienteRef = {
  nome: 'Você',
  username: 'teresasurita',
  seguidores_total: 0,
  posts_total: 0,
  score_total: 0,
  score_medio: 0
};
</script>

<script>
function concFormatNumber(value) {
  return Number(value || 0).toLocaleString('pt-BR');
}

function concEscapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function concFormatDate(value) {
  if (!value) return '--';

  const date = new Date(String(value).replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return '--';

  return date.toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  });
}

function concSeloLabel(selo) {
  switch (String(selo || '')) {
    case 'lider_seguidores':
      return 'Lidera em seguidores';
    case 'lider_engajamento':
      return 'Lidera em engajamento';
    case 'mais_ativo':
      return 'Mais ativo';
    case 'baixo_rendimento':
      return 'Baixo rendimento';
    default:
      return '';
  }
}

function concCompareState(cliente, concorrente) {
  const a = Number(cliente || 0);
  const b = Number(concorrente || 0);

  if (a > b) {
    return {
      cls: 'up',
      valueCls: 'is-better',
      icon: 'bi-arrow-up-right',
      text: 'Você está melhor'
    };
  }

  if (a < b) {
    return {
      cls: 'down',
      valueCls: 'is-worse',
      icon: 'bi-arrow-down-right',
      text: 'Você está pior'
    };
  }

  return {
    cls: 'equal',
    valueCls: 'is-equal',
    icon: 'bi-dash',
    text: 'Empate'
  };
}

function concRenderMetric(label, valorConcorrente, valorCliente) {
  const state = concCompareState(valorCliente, valorConcorrente);

  return `
    <div class="conc-mini">
      <div class="conc-mini-label">${label}</div>
      <div class="conc-mini-value ${state.valueCls}">${concFormatNumber(valorConcorrente)}</div>
      <div class="conc-mini-compare">
        Você: <strong>${concFormatNumber(valorCliente)}</strong>
      </div>
      <div class="conc-mini-badge ${state.cls}">
        <i class="bi ${state.icon}"></i>
        ${state.text}
      </div>
    </div>
  `;
}

function concRenderTopKpi(label, nomeLider, userLider, valorLider, valorMeu, cls) {
  const state = concCompareState(valorMeu, valorLider);

  return `
    <div class="kpi-card ${cls}">
      <div class="kpi-label">${label}</div>

      <div class="kpi-row">
        <div class="kpi-main">
          <div class="kpi-name">${concEscapeHtml(nomeLider || '--')}</div>
          <div class="kpi-sub">@${concEscapeHtml(userLider || '--')}</div>
        </div>

        <div class="kpi-values">
          <div class="kpi-value">${concFormatNumber(valorLider)}</div>
          <div class="kpi-you">Você: ${concFormatNumber(valorMeu)}</div>
          <div class="kpi-badge ${state.cls}">
            <i class="bi ${state.icon}"></i>
            ${state.text}
          </div>
        </div>
      </div>
    </div>
  `;
}

function renderKpis(data) {
  const box = document.getElementById('concorrentesKpis');
  if (!box) return;

  const resumo = data?.resumo || {};
  const liderSeguidores = resumo.lider_seguidores || null;
  const liderEngajamento = resumo.lider_engajamento || null;
  const maisAtivo = resumo.mais_ativo || null;

  box.innerHTML = `
    ${concRenderTopKpi(
      'Líder em seguidores x meus seguidores',
      liderSeguidores?.nome || '--',
      liderSeguidores?.username || '--',
      liderSeguidores?.seguidores || 0,
      concorrentesClienteRef.seguidores_total || 0,
      'kpi-blue'
    )}

    ${concRenderTopKpi(
      'Líder em engajamento x meu engajamento',
      liderEngajamento?.nome || '--',
      liderEngajamento?.username || '--',
      liderEngajamento?.score_total || 0,
      concorrentesClienteRef.score_total || 0,
      'kpi-green'
    )}

    ${concRenderTopKpi(
      'Mais ativo no período x meus posts do período',
      maisAtivo?.nome || '--',
      maisAtivo?.username || '--',
      maisAtivo?.posts_periodo || 0,
      concorrentesClienteRef.posts_total || 0,
      'kpi-gold'
    )}
  `;
}

function renderConcorrentes(items) {
  const box = document.getElementById('listaConcorrentes');
  if (!box) return;

  if (!Array.isArray(items) || items.length === 0) {
    box.innerHTML = '<div class="empty-state">Nenhum concorrente disponível no momento.</div>';
    return;
  }

  box.innerHTML = items.map((item) => {
    const foto = item.foto_url
      ? `<img src="${concEscapeHtml(item.foto_url)}" alt="${concEscapeHtml(item.nome)}" class="concorrente-foto">`
      : `<div class="concorrente-foto-placeholder"><i class="bi bi-person"></i></div>`;

    const selo = concSeloLabel(item.selo)
      ? `<div class="concorrente-selo">${concEscapeHtml(concSeloLabel(item.selo))}</div>`
      : '';

    return `
      <div class="concorrente-card">
        <div class="concorrente-top">
          ${foto}

          <div class="concorrente-info">
            <div class="concorrente-nome">${concEscapeHtml(item.nome || 'Concorrente')}</div>
            <div class="concorrente-user">@${concEscapeHtml(item.username || '')}</div>
          </div>

          ${selo}
        </div>

        <div class="concorrente-metricas">
          ${concRenderMetric('Seguidores', item.seguidores_total || 0, concorrentesClienteRef.seguidores_total || 0)}
          ${concRenderMetric('Posts período', item.posts_total || 0, concorrentesClienteRef.posts_total || 0)}
          ${concRenderMetric('Engajamento', item.score_total || 0, concorrentesClienteRef.score_total || 0)}
          ${concRenderMetric('Média por post', item.score_medio || 0, concorrentesClienteRef.score_medio || 0)}
        </div>
      </div>
    `;
  }).join('');
}

function renderPosts(items) {
  const box = document.getElementById('listaPostsConcorrentes');
  if (!box) return;

  if (!Array.isArray(items) || items.length === 0) {
    box.innerHTML = '<div class="empty-state">Nenhum post destaque disponível no momento.</div>';
    return;
  }

  box.innerHTML = items.map((item) => {
    const thumb = item.media_url_capa
      ? `<img src="${concEscapeHtml(item.media_url_capa)}" alt="Post do concorrente" class="post-thumb">`
      : `<div class="post-thumb-placeholder">Post do concorrente</div>`;

    const link = item.permalink
      ? `<a href="${concEscapeHtml(item.permalink)}" target="_blank" rel="noopener" class="post-link">
           <i class="bi bi-box-arrow-up-right"></i>
           Abrir post
         </a>`
      : '';

    return `
      <div class="post-card">
        <div class="post-main">
          ${thumb}

          <div class="post-body">
            <div class="post-head">
              <div>
                <div class="post-concorrente">${concEscapeHtml(item.concorrente_nome || 'Concorrente')}</div>
                <div class="post-user">@${concEscapeHtml(item.concorrente_username || '')}</div>
              </div>

              <div class="post-score">
                <div class="post-score-label">Score</div>
                <div class="post-score-value">${concFormatNumber(item.score_engajamento || 0)}</div>
              </div>
            </div>

            <div class="post-chips">
              <span class="post-chip instagram">
                <i class="bi bi-instagram"></i>
                Instagram
              </span>

              <span class="post-chip tipo">
                ${concEscapeHtml(item.post_tipo_label || 'Post')}
              </span>
            </div>

            <div class="post-caption">
              ${concEscapeHtml(item.caption_resumo || 'Sem legenda disponível.')}
            </div>
          </div>
        </div>

        <div class="post-metricas">
          <div class="post-mini">
            <div class="post-mini-label">Curtidas</div>
            <div class="post-mini-value">${concFormatNumber(item.curtidas || 0)}</div>
          </div>

          <div class="post-mini">
            <div class="post-mini-label">Comentários</div>
            <div class="post-mini-value">${concFormatNumber(item.comentarios || 0)}</div>
          </div>

          <div class="post-mini">
            <div class="post-mini-label">Reproduções</div>
            <div class="post-mini-value">${concFormatNumber(item.reproducoes || 0)}</div>
          </div>
        </div>

        <div class="post-foot">
          <div class="post-date">Publicado: ${concFormatDate(item.publicado_em)}</div>
          ${link}
        </div>
      </div>
    `;
  }).join('');
}

async function carregarConcorrentes() {
  try {
    const response = await fetch(CONCORRENTES_API_URL, {
      cache: 'no-store',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    });

    if (!response.ok) {
      throw new Error('Falha ao carregar concorrentes');
    }

    const data = await response.json();

    if (!data || data.ok !== true) {
      throw new Error('Resposta inválida da API');
    }

    if (data.cliente_instagram) {
      concorrentesClienteRef = {
        nome: 'Você',
        username: data.cliente_instagram.username || 'teresasurita',
        seguidores_total: data.cliente_instagram.seguidores_total || 0,
        posts_total: data.cliente_instagram.posts_total || 0,
        score_total: data.cliente_instagram.engajamento_total || 0,
        score_medio: data.cliente_instagram.media_por_post || 0
      };
    } else if (data.cliente) {
      concorrentesClienteRef = {
        nome: 'Você',
        username: data.cliente.username || 'teresasurita',
        seguidores_total: data.cliente.seguidores_total || 0,
        posts_total: data.cliente.posts_total || 0,
        score_total: data.cliente.score_total || 0,
        score_medio: data.cliente.score_medio || 0
      };
    }

    const periodo = document.getElementById('concorrentesPeriodo');
    if (periodo) {
      periodo.textContent = data.periodo_label || '30 dias';
    }

    renderKpis(data);
    renderConcorrentes(data.concorrentes || []);
    renderPosts(data.posts_destaque || []);
  } catch (err) {
    console.error('Erro ao carregar concorrentes:', err);

    const kpis = document.getElementById('concorrentesKpis');
    const listaConc = document.getElementById('listaConcorrentes');
    const listaPosts = document.getElementById('listaPostsConcorrentes');

    if (kpis) {
      kpis.innerHTML = '<div class="empty-state">Não foi possível carregar o resumo dos concorrentes agora.</div>';
    }

    if (listaConc) {
      listaConc.innerHTML = '<div class="empty-state">Não foi possível carregar os concorrentes agora.</div>';
    }

    if (listaPosts) {
      listaPosts.innerHTML = '<div class="empty-state">Não foi possível carregar os posts destaque agora.</div>';
    }
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', carregarConcorrentes);
} else {
  carregarConcorrentes();
}
</script>

</body>
</html>