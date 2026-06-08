<?php
declare(strict_types=1);

/**
 * ======================================================
 * CAMINHO: app.elab.social/engajamento/engajamento.php
 * NOME: Engajamento
 * DESCRIÇÃO:
 * - Painel mobile executivo de engajamento
 * - Consome /api/monitor/social.php
 * - Exibe resumo dos últimos 30 dias
 * - Diferencia seguidores atuais de saldo no período
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
<title>Engajamento • ELAB Social</title>

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
  --bg:#eef2f5;
  --card:#ffffff;
  --text:#102331;
  --muted:#5f7280;
  --brand1:#0b6e7a;
  --brand2:#1aa8b2;
  --shadow:0 12px 28px rgba(16,35,49,.10);
  --shadow-strong:0 18px 40px rgba(16,35,49,.18);
  --green:#86efac;
  --red:#fca5a5;
  --neutral:#e5e7eb;
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
  background:var(--card);
  box-shadow:var(--shadow);
}

.card-monitor-detalhado{
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

.card-monitor-detalhado .card-title{
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

.monitor-topo{
  margin-top:14px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  flex-wrap:wrap;
  padding:14px 16px;
  border-radius:18px;
  background:rgba(255,255,255,.06);
  border:1px solid rgba(255,255,255,.10);
}

.monitor-redes{
  display:flex;
  align-items:center;
  gap:8px;
  flex-wrap:wrap;
}

.monitor-chip{
  display:inline-flex;
  align-items:center;
  gap:7px;
  min-height:34px;
  padding:0 12px;
  border-radius:999px;
  font-size:12px;
  font-weight:900;
  line-height:1;
  border:1px solid transparent;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.05);
  color:#fff;
}

.monitor-chip i{
  font-size:14px;
  line-height:1;
}

.monitor-chip-instagram{
  background:linear-gradient(135deg,#fd5949 0%, #d6249f 48%, #285AEB 100%);
  border-color:rgba(255,255,255,.12);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.10), 0 8px 18px rgba(214,36,159,.20);
}

.monitor-chip-facebook{
  background:linear-gradient(135deg,#1877f2 0%, #0d5ed7 100%);
  border-color:rgba(255,255,255,.12);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.10), 0 8px 18px rgba(24,119,242,.20);
}

.monitor-destaques{
  margin-top:14px;
  display:grid;
  grid-template-columns:1fr;
  gap:12px;
}

.monitor-grid{
  margin-top:14px;
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:12px;
}

.monitor-kpi{
  position:relative;
  overflow:hidden;
  border-radius:20px;
  background:linear-gradient(180deg, rgba(255,255,255,.05) 0%, rgba(255,255,255,.03) 100%);
  border:1px solid rgba(255,255,255,.10);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.04);
}

.monitor-kpi::before{
  content:'';
  position:absolute;
  left:0;
  top:0;
  bottom:0;
  width:4px;
  border-radius:20px 0 0 20px;
  opacity:.95;
}

.monitor-kpi.big{
  padding:18px 18px 16px;
}

.monitor-kpi.small{
  padding:14px 14px 13px;
}

.monitor-kpi-label{
  font-size:12px;
  font-weight:1000;
  color:rgba(255,255,255,.72);
  margin-bottom:6px;
  text-transform:uppercase;
  letter-spacing:.3px;
  line-height:1.25;
}

.monitor-kpi-value{
  font-weight:1000;
  line-height:1.05;
  letter-spacing:-.4px;
  text-shadow:0 2px 10px rgba(0,0,0,.18);
  color:#fff;
}

.monitor-kpi.big .monitor-kpi-value{
  font-size:30px;
}

.monitor-kpi.small .monitor-kpi-value{
  font-size:22px;
}

.kpi-alcance::before{ background:linear-gradient(180deg,#60a5fa 0%, #2563eb 100%); }
.kpi-engajamento::before{ background:linear-gradient(180deg,#c084fc 0%, #7c3aed 100%); }
.kpi-visualizacoes::before{ background:linear-gradient(180deg,#4ade80 0%, #16a34a 100%); }
.kpi-taxa::before{ background:linear-gradient(180deg,#fde047 0%, #eab308 100%); }
.kpi-interacoes::before{ background:linear-gradient(180deg,#67e8f9 0%, #0891b2 100%); }
.kpi-curtidas::before{ background:linear-gradient(180deg,#fb7185 0%, #e11d48 100%); }
.kpi-comentarios::before{ background:linear-gradient(180deg,#fb923c 0%, #ea580c 100%); }
.kpi-compartilhamentos::before{ background:linear-gradient(180deg,#a78bfa 0%, #6d28d9 100%); }

.monitor-rede-resumo{
  margin-top:14px;
  display:grid;
  grid-template-columns:1fr;
  gap:12px;
}

.monitor-rede-card{
  position:relative;
  overflow:hidden;
  border-radius:22px;
  padding:16px;
  border:1px solid rgba(255,255,255,.10);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.05), 0 14px 28px rgba(0,0,0,.14);
}

.monitor-rede-card::before{
  content:'';
  position:absolute;
  inset:0;
  pointer-events:none;
  background:linear-gradient(135deg, rgba(255,255,255,.08) 0%, rgba(255,255,255,0) 38%);
}

.monitor-rede-card-instagram{
  background:
    radial-gradient(circle at top right, rgba(255,255,255,.12), transparent 30%),
    linear-gradient(135deg,#833ab4 0%, #c13584 34%, #e1306c 62%, #fd1d1d 82%, #f77737 100%);
}

.monitor-rede-card-facebook{
  background:
    radial-gradient(circle at top right, rgba(255,255,255,.14), transparent 30%),
    linear-gradient(135deg,#1877f2 0%, #1664d9 52%, #0f4fb8 100%);
}

.monitor-rede-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  margin-bottom:14px;
  position:relative;
  z-index:1;
}

.monitor-rede-titulo{
  font-size:16px;
  font-weight:1000;
  color:#fff;
  letter-spacing:-.2px;
}

.monitor-rede-head i{
  width:38px;
  height:38px;
  border-radius:999px;
  display:flex;
  align-items:center;
  justify-content:center;
  background:rgba(255,255,255,.16);
  border:1px solid rgba(255,255,255,.18);
  color:#fff;
  font-size:20px;
}

.monitor-mini-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:10px;
  position:relative;
  z-index:1;
}

.monitor-mini-item{
  padding:12px 12px 10px;
  border-radius:16px;
  background:rgba(255,255,255,.10);
  border:1px solid rgba(255,255,255,.12);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.08);
  backdrop-filter:blur(6px);
  -webkit-backdrop-filter:blur(6px);
}

.monitor-mini-label{
  font-size:11px;
  font-weight:1000;
  text-transform:uppercase;
  letter-spacing:.3px;
  color:rgba(255,255,255,.78);
  margin-bottom:5px;
}

.monitor-mini-value{
  font-size:18px;
  font-weight:1000;
  color:#fff;
  line-height:1.05;
  text-shadow:0 2px 8px rgba(0,0,0,.18);
}

.monitor-mini-sub{
  margin-top:6px;
  font-size:11px;
  font-weight:900;
  line-height:1.3;
  color:rgba(255,255,255,.86);
}

.monitor-mini-sub.positivo{ color:var(--green); }
.monitor-mini-sub.negativo{ color:var(--red); }
.monitor-mini-sub.neutro{ color:var(--neutral); }

.monitor-acoes{
  margin-top:14px;
  display:grid;
  grid-template-columns:1fr;
  gap:12px;
}

.monitor-periodo-wrap{
  display:flex;
  flex-direction:column;
  align-items:flex-end;
  justify-content:flex-end;
  gap:6px;
  max-width:320px;
  margin-left:auto;
  text-align:right;
}

.monitor-periodo-texto{
  font-size:11px;
  line-height:1.35;
  font-weight:700;
  color:rgba(255,255,255,.72);
  text-align:right;
}

.monitor-periodo{
  display:block;
  font-size:20px;
  line-height:1;
  font-weight:1000;
  color:#fff;
  text-align:right;
  letter-spacing:-.3px;
}

.monitor-btn{
  display:flex;
  align-items:center;
  justify-content:center;
  gap:10px;
  min-height:56px;
  padding:0 16px;
  border-radius:18px;
  text-decoration:none;
  color:#fff;
  font-size:15px;
  font-weight:900;
  transition:transform .18s ease, box-shadow .18s ease;
}

.monitor-btn:hover{
  color:#fff;
  text-decoration:none;
  transform:translateY(-1px);
}

.monitor-btn-primary{
  background:linear-gradient(135deg,#3b82f6 0%, #06b6d4 100%);
  box-shadow:0 10px 22px rgba(6,182,212,.22);
}

.monitor-btn-secondary{
  background:linear-gradient(135deg,#6366f1 0%, #8b5cf6 100%);
  box-shadow:0 10px 22px rgba(139,92,246,.22);
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

@media (max-width:560px){
  .monitor-topo{
    align-items:flex-start;
  }

  .monitor-periodo-wrap{
    width:100%;
    max-width:none;
    align-items:flex-end;
    text-align:right;
  }

  .monitor-periodo{
    font-size:18px;
  }
}
</style>
</head>
<body>

<div class="page-wrap">

  <div class="header">
    <div class="header-top">
      <div class="header-left">
        <h1>Monitor de Redes Sociais</h1>
        <div class="sub">Resumo executivo | Últimos 30 dias</div>
      </div>

      <a href="/dashboard/index.php" class="btn-voltar">
        <i class="bi bi-chevron-left"></i>
        Voltar
      </a>
    </div>
  </div>

  <div class="card-box card-monitor-detalhado">
    <div class="card-title">
      <i class="bi bi-graph-up-arrow"></i>
      Monitor de Engajamento
    </div>

    <div class="alerta-desktop">
      <i class="bi bi-display"></i>
      <div>
        <strong>Esta versão mobile foi pensada para leitura rápida, acompanhamento executivo e tomada de decisão em movimento. Para uma análise completa, use o CRM para desktop.</strong>
      </div>
    </div>

    <div class="monitor-topo">
      <div class="monitor-redes">
        <span class="monitor-chip monitor-chip-instagram" id="engajamentoRedeInstagram">
          <i class="bi bi-instagram"></i>
          Instagram
        </span>

        <span class="monitor-chip monitor-chip-facebook" id="engajamentoRedeFacebook">
          <i class="bi bi-facebook"></i>
          Facebook
        </span>
      </div>

      <div class="monitor-periodo-wrap">
        <div class="monitor-periodo-texto">
          Dados fornecidos pelo Facebook e Instagram via API ELAB Social Platform para o período de
        </div>
        <div class="monitor-periodo" id="engajamentoPeriodo">30 dias</div>
      </div>
    </div>

    <div class="monitor-destaques">
      <div class="monitor-kpi big kpi-alcance">
        <div class="monitor-kpi-label">Alcance total</div>
        <div class="monitor-kpi-value" id="engajamentoAlcanceTotal">--</div>
      </div>

      <div class="monitor-kpi big kpi-engajamento">
        <div class="monitor-kpi-label">Engajamento total</div>
        <div class="monitor-kpi-value" id="engajamentoTotal">--</div>
      </div>
    </div>

    <div class="monitor-grid">
      <div class="monitor-kpi small kpi-visualizacoes">
        <div class="monitor-kpi-label">Visualizações</div>
        <div class="monitor-kpi-value" id="engajamentoVisualizacoes">--</div>
      </div>

      <div class="monitor-kpi small kpi-taxa">
        <div class="monitor-kpi-label">Taxa de engajamento</div>
        <div class="monitor-kpi-value" id="engajamentoTaxa">--</div>
      </div>

      <div class="monitor-kpi small kpi-interacoes">
        <div class="monitor-kpi-label">Interações</div>
        <div class="monitor-kpi-value" id="engajamentoInteracoes">--</div>
      </div>

      <div class="monitor-kpi small kpi-curtidas">
        <div class="monitor-kpi-label">Curtidas</div>
        <div class="monitor-kpi-value" id="engajamentoCurtidas">--</div>
      </div>

      <div class="monitor-kpi small kpi-comentarios">
        <div class="monitor-kpi-label">Comentários</div>
        <div class="monitor-kpi-value" id="engajamentoComentarios">--</div>
      </div>

      <div class="monitor-kpi small kpi-compartilhamentos">
        <div class="monitor-kpi-label">Compartilhamentos</div>
        <div class="monitor-kpi-value" id="engajamentoCompartilhamentos">--</div>
      </div>
    </div>

    <div class="monitor-rede-resumo">
      <div class="monitor-rede-card monitor-rede-card-instagram">
        <div class="monitor-rede-head">
          <div class="monitor-rede-titulo">Instagram</div>
          <i class="bi bi-instagram"></i>
        </div>

        <div class="monitor-mini-grid">
          <div class="monitor-mini-item">
            <div class="monitor-mini-label">Alcance</div>
            <div class="monitor-mini-value" id="engajamentoInstagramAlcance">--</div>
          </div>

          <div class="monitor-mini-item">
            <div class="monitor-mini-label">Engajamento</div>
            <div class="monitor-mini-value" id="engajamentoInstagramEngajamento">--</div>
          </div>

          <div class="monitor-mini-item">
            <div class="monitor-mini-label">Comentários</div>
            <div class="monitor-mini-value" id="engajamentoInstagramComentarios">--</div>
          </div>

          <div class="monitor-mini-item">
            <div class="monitor-mini-label">Seguidores atuais</div>
            <div class="monitor-mini-value" id="engajamentoInstagramSeguidoresAtuais">--</div>
            <div class="monitor-mini-sub" id="engajamentoInstagramSeguidoresSaldo">Saldo 30d: --</div>
          </div>
        </div>
      </div>

      <div class="monitor-rede-card monitor-rede-card-facebook">
        <div class="monitor-rede-head">
          <div class="monitor-rede-titulo">Facebook</div>
          <i class="bi bi-facebook"></i>
        </div>

        <div class="monitor-mini-grid">
          <div class="monitor-mini-item">
            <div class="monitor-mini-label">Alcance</div>
            <div class="monitor-mini-value" id="engajamentoFacebookAlcance">--</div>
          </div>

          <div class="monitor-mini-item">
            <div class="monitor-mini-label">Engajamento</div>
            <div class="monitor-mini-value" id="engajamentoFacebookEngajamento">--</div>
          </div>

          <div class="monitor-mini-item">
            <div class="monitor-mini-label">Comentários</div>
            <div class="monitor-mini-value" id="engajamentoFacebookComentarios">--</div>
          </div>

          <div class="monitor-mini-item">
            <div class="monitor-mini-label">Seguidores atuais</div>
            <div class="monitor-mini-value" id="engajamentoFacebookSeguidoresAtuais">--</div>
            <div class="monitor-mini-sub" id="engajamentoFacebookSeguidoresSaldo">Saldo 30d: --</div>
          </div>
        </div>
      </div>
    </div>

    <div class="monitor-acoes">
      <a href="/engajamento/concorrentes.php" class="monitor-btn monitor-btn-primary">
        <i class="bi bi-people-fill"></i>
        <span>Analisar concorrentes</span>
      </a>

      <a href="/engajamento/melhores-post.php" class="monitor-btn monitor-btn-secondary">
        <i class="bi bi-stars"></i>
        <span>Ver melhores posts</span>
      </a>

      <a href="/engajamento/piores-post.php" class="monitor-btn monitor-btn-secondary">
        <i class="bi bi-graph-down-arrow"></i>
        <span>Ver piores posts</span>
      </a>
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
const ENGAJAMENTO_API_URL = '/api/monitor/social.php';
</script>

<script>
function formatEngNumber(value, decimals = 0) {
    if (value === null || typeof value === 'undefined' || value === '') {
        return '--';
    }

    const num = Number(value);
    if (!Number.isFinite(num)) {
        return '--';
    }

    return num.toLocaleString('pt-BR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

function formatEngPercent(value) {
    if (value === null || typeof value === 'undefined' || value === '') {
        return '--';
    }

    const num = Number(value);
    if (!Number.isFinite(num)) {
        return '--';
    }

    return formatEngNumber(num, 2) + '%';
}

function setEngValue(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = value;
}

function setChipVisible(id, visible) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.display = visible ? 'inline-flex' : 'none';
}

function setSaldoSeguidores(id, value) {
    const el = document.getElementById(id);
    if (!el) return;

    const num = Number(value || 0);
    let texto = 'Saldo 30d: ' + formatEngNumber(num);
    let cls = 'neutro';

    if (num > 0) {
        texto = 'Saldo 30d: +' + formatEngNumber(num);
        cls = 'positivo';
    } else if (num < 0) {
        texto = 'Saldo 30d: ' + formatEngNumber(num);
        cls = 'negativo';
    }

    el.textContent = texto;
    el.classList.remove('positivo', 'negativo', 'neutro');
    el.classList.add(cls);
}

function applyEngajamentoState(data) {
    if (!data || data.ok !== true) return;

    const resumo = data.resumo_executivo || {};

    setEngValue('engajamentoPeriodo', data.periodo_label || '30 dias');

    setEngValue('engajamentoAlcanceTotal', formatEngNumber(resumo.alcance_total ?? data.alcance_total));
    setEngValue('engajamentoTotal', formatEngNumber(resumo.engajamento_total ?? data.engajamento_total));
    setEngValue('engajamentoVisualizacoes', formatEngNumber(resumo.visualizacoes ?? data.visualizacoes));
    setEngValue('engajamentoTaxa', formatEngPercent(resumo.taxa_engajamento ?? data.taxa_engajamento));
    setEngValue('engajamentoInteracoes', formatEngNumber(resumo.interacoes ?? data.interacoes));
    setEngValue('engajamentoCurtidas', formatEngNumber(resumo.curtidas ?? data.curtidas));
    setEngValue('engajamentoComentarios', formatEngNumber(resumo.comentarios ?? data.comentarios_total_30d));
    setEngValue('engajamentoCompartilhamentos', formatEngNumber(resumo.compartilhamentos ?? data.compartilhamentos));

    if (data.redes_conectadas) {
        setChipVisible('engajamentoRedeInstagram', !!data.redes_conectadas.instagram);
        setChipVisible('engajamentoRedeFacebook', !!data.redes_conectadas.facebook);
    }

    const instagram = data.redes?.instagram || {};
    const facebook = data.redes?.facebook || {};

    setEngValue('engajamentoInstagramAlcance', formatEngNumber(instagram.alcance?.['30d']));
    setEngValue('engajamentoInstagramEngajamento', formatEngNumber(instagram.engajamento?.['30d']));
    setEngValue('engajamentoInstagramComentarios', formatEngNumber(instagram.comentarios?.['30d']));
    setEngValue('engajamentoInstagramSeguidoresAtuais', formatEngNumber(instagram.seguidores?.total_atual));
    setSaldoSeguidores('engajamentoInstagramSeguidoresSaldo', instagram.seguidores?.saldo_30d);

    setEngValue('engajamentoFacebookAlcance', formatEngNumber(facebook.alcance?.['30d']));
    setEngValue('engajamentoFacebookEngajamento', formatEngNumber(facebook.engajamento?.['30d']));
    setEngValue('engajamentoFacebookComentarios', formatEngNumber(facebook.comentarios?.['30d']));
    setEngValue('engajamentoFacebookSeguidoresAtuais', formatEngNumber(facebook.seguidores?.total_atual));
    setSaldoSeguidores('engajamentoFacebookSeguidoresSaldo', facebook.seguidores?.saldo_30d);
}

async function carregarEngajamento() {
    try {
        const response = await fetch(ENGAJAMENTO_API_URL, {
            cache: 'no-store',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error('Falha ao carregar engajamento');
        }

        const data = await response.json();

        if (!data || data.ok !== true) {
            return;
        }

        applyEngajamentoState(data);
    } catch (err) {
        console.error('Erro ao carregar engajamento:', err);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', carregarEngajamento);
} else {
    carregarEngajamento();
}
</script>

</body>
</html>