<?php
declare(strict_types=1);

/**
 * ======================================================
 * CAMINHO: app.elab.social/engajamento/piores-post.php
 * NOME: Piores Posts
 * DESCRIÇÃO:
 * - Ranking mobile dos posts com pior desempenho
 * - Consome /api/monitor/piores-posts.php
 * - Exibe thumb, rede, tipo, legenda resumida e métricas
 * - Visual com apoio vermelho
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

$rede = strtolower(trim((string) ($_GET['rede'] ?? 'todos')));
$ordem = strtolower(trim((string) ($_GET['ordem'] ?? 'score')));

$redesPermitidas = ['todos', 'instagram', 'facebook'];
$ordensPermitidas = ['score', 'comentarios', 'curtidas', 'alcance', 'compartilhamentos', 'reproducoes'];

if (!in_array($rede, $redesPermitidas, true)) {
    $rede = 'todos';
}

if (!in_array($ordem, $ordensPermitidas, true)) {
    $ordem = 'score';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Piores Posts • ELAB Social</title>

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
  --danger1:#ef4444;
  --danger2:#b91c1c;
  --shadow:0 12px 28px rgba(16,35,49,.10);
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

.card-dark-danger{
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

.card-dark-danger .card-title{
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
  color:#fecaca;
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

.filtros{
  margin-top:14px;
  display:flex;
  flex-direction:column;
  gap:10px;
}

.tabs{
  display:flex;
  gap:8px;
  flex-wrap:wrap;
}

.tab{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:36px;
  padding:0 14px;
  border-radius:999px;
  text-decoration:none;
  font-size:12px;
  font-weight:900;
  color:#fbe4e6;
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.12);
  transition:.18s ease;
}

.tab.active{
  color:#fff;
  background:linear-gradient(135deg,var(--danger1) 0%, var(--danger2) 100%);
  border-color:transparent;
  box-shadow:0 10px 22px rgba(185,28,28,.22);
}

.tab:hover{
  color:#fff;
  text-decoration:none;
  transform:translateY(-1px);
}

.tab-rede-instagram{
  background:linear-gradient(135deg,#fd5949 0%, #d6249f 48%, #285AEB 100%);
  border-color:rgba(255,255,255,.14);
  color:#fff;
  box-shadow:0 8px 18px rgba(214,36,159,.18);
}

.tab-rede-instagram:hover{
  color:#fff;
}

.tab-rede-facebook{
  background:linear-gradient(135deg,#1877f2 0%, #0d5ed7 100%);
  border-color:rgba(255,255,255,.14);
  color:#fff;
  box-shadow:0 8px 18px rgba(24,119,242,.18);
}

.tab-rede-facebook:hover{
  color:#fff;
}

.tab-rede-instagram.active,
.tab-rede-facebook.active{
  color:#fff;
  transform:none;
  box-shadow:0 10px 22px rgba(0,0,0,.22);
}

.lista-posts{
  margin-top:14px;
  display:flex;
  flex-direction:column;
  gap:14px;
}

.post-card{
  position:relative;
  overflow:hidden;
  border-radius:22px;
  padding:16px;
  background:rgba(255,255,255,.06);
  border:1px solid rgba(255,255,255,.10);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.05);
}

.post-card::before{
  content:'';
  position:absolute;
  left:0;
  top:0;
  bottom:0;
  width:5px;
  border-radius:22px 0 0 22px;
  background:linear-gradient(180deg,#f87171 0%, #b91c1c 100%);
}

.post-main{
  display:grid;
  grid-template-columns:92px 1fr;
  gap:14px;
  align-items:start;
}

.post-thumb-wrap{
  position:relative;
}

.post-thumb{
  width:92px;
  height:92px;
  border-radius:18px;
  object-fit:cover;
  display:block;
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.12);
}

.post-thumb-placeholder{
  width:92px;
  height:92px;
  border-radius:18px;
  display:flex;
  align-items:center;
  justify-content:center;
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.12);
  color:#fff;
  font-size:26px;
}

.post-rank{
  position:absolute;
  left:8px;
  top:8px;
  display:inline-flex;
  align-items:center;
  gap:6px;
  min-height:26px;
  padding:0 8px;
  border-radius:999px;
  background:rgba(36,8,12,.82);
  color:#fff;
  font-size:10px;
  font-weight:1000;
  backdrop-filter:blur(6px);
  -webkit-backdrop-filter:blur(6px);
}

.post-body{
  min-width:0;
}

.post-head{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:12px;
  margin-bottom:10px;
}

.post-chips{
  display:flex;
  gap:8px;
  flex-wrap:wrap;
}

.post-chip{
  display:inline-flex;
  align-items:center;
  gap:6px;
  min-height:28px;
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

.post-chip.facebook{
  background:linear-gradient(135deg,#1877f2 0%, #0d5ed7 100%);
}

.post-chip.tipo{
  background:rgba(255,255,255,.10);
}

.post-score{
  text-align:right;
  min-width:88px;
}

.post-score-label{
  font-size:11px;
  font-weight:1000;
  text-transform:uppercase;
  letter-spacing:.3px;
  color:rgba(255,255,255,.68);
  margin-bottom:5px;
}

.post-score-value{
  font-size:28px;
  font-weight:1000;
  color:#fecaca;
  line-height:1;
}

.post-title{
  font-size:17px;
  font-weight:1000;
  color:#fff;
  line-height:1.15;
  margin-bottom:6px;
}

.post-caption{
  font-size:13px;
  line-height:1.45;
  color:rgba(255,255,255,.84);
  font-weight:700;
}

.post-grid{
  margin-top:14px;
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:10px;
}

.post-mini{
  padding:12px 12px 10px;
  border-radius:16px;
  background:rgba(255,255,255,.07);
  border:1px solid rgba(255,255,255,.09);
}

.post-mini-label{
  font-size:11px;
  font-weight:1000;
  text-transform:uppercase;
  letter-spacing:.3px;
  color:rgba(255,255,255,.68);
  margin-bottom:5px;
}

.post-mini-value{
  font-size:18px;
  font-weight:1000;
  color:#fff;
  line-height:1.05;
}

.post-foot{
  margin-top:12px;
  display:flex;
  justify-content:space-between;
  gap:10px;
  flex-wrap:wrap;
  align-items:center;
}

.post-foot-left{
  display:flex;
  flex-direction:column;
  gap:4px;
}

.post-foot-item{
  font-size:12px;
  font-weight:800;
  color:rgba(255,255,255,.78);
}

.post-link{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  min-height:40px;
  padding:0 14px;
  border-radius:14px;
  text-decoration:none;
  color:#fff;
  background:linear-gradient(135deg,#ef4444 0%, #b91c1c 100%);
  font-size:13px;
  font-weight:1000;
  box-shadow:0 10px 20px rgba(185,28,28,.22);
}

.post-link:hover{
  color:#fff;
  text-decoration:none;
  transform:translateY(-1px);
}

.loading-state,
.empty-state{
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
  .post-main{
    grid-template-columns:1fr;
  }

  .post-thumb,
  .post-thumb-placeholder{
    width:100%;
    height:180px;
  }

  .post-grid{
    grid-template-columns:1fr;
  }

  .post-score-value{
    font-size:24px;
  }
}
</style>
</head>
<body>

<div class="page-wrap">

  <div class="header">
    <div class="header-top">
      <div class="header-left">
        <h1>Piores Posts</h1>
        <div class="sub">Ranking executivo dos conteúdos</div>
      </div>

      <a href="/engajamento/engajamento.php" class="btn-voltar">
        <i class="bi bi-chevron-left"></i>
        Voltar
      </a>
    </div>
  </div>

  <div class="card-box card-dark-danger">
    <div class="card-title">
      <i class="bi bi-graph-down-arrow"></i>
      Posts que Precisam de Atenção
    </div>

    <div class="alerta-desktop">
      <i class="bi bi-display"></i>
      <div>
        <span>Esta versão mobile destaca rapidamente os conteúdos com menor desempenho para revisão, correção de rota e decisões rápidas.</span>
      </div>
    </div>

    <div class="filtros">
      <div class="tabs">
        <a href="?rede=todos&ordem=<?= urlencode($ordem) ?>" class="tab <?= $rede === 'todos' ? 'active' : '' ?>">Todas</a>
        <a href="?rede=instagram&ordem=<?= urlencode($ordem) ?>" class="tab tab-rede-instagram <?= $rede === 'instagram' ? 'active' : '' ?>">Instagram</a>
        <a href="?rede=facebook&ordem=<?= urlencode($ordem) ?>" class="tab tab-rede-facebook <?= $rede === 'facebook' ? 'active' : '' ?>">Facebook</a>
      </div>
    </div>

    <div class="lista-posts" id="listaPioresPosts">
      <div class="loading-state">Carregando piores posts...</div>
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
const PIORES_POSTS_API_URL = '/api/monitor/piores-posts.php';
const PIORES_POSTS_REDE = <?= json_encode($rede) ?>;
const PIORES_POSTS_ORDEM = <?= json_encode($ordem) ?>;
</script>

<script>
function ppFormatNumber(value) {
  const num = Number(value || 0);
  return num.toLocaleString('pt-BR');
}

function ppEscapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function ppFormatDate(value) {
  if (!value) return '--';

  const date = new Date(String(value).replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return '--';

  return date.toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function ppRankEmoji(index) {
  if (index === 0) return '🚨';
  if (index === 1) return '⚠️';
  if (index === 2) return '📉';
  return '🔻';
}

function ppRankLabel(index) {
  if (index === 0) return 'Crítico';
  if (index === 1) return 'Atenção';
  if (index === 2) return 'Atenção';
  return 'Monitorar';
}

function ppNetworkClass(network) {
  return network === 'facebook' ? 'facebook' : 'instagram';
}

function renderPioresPosts(posts) {
  const lista = document.getElementById('listaPioresPosts');
  if (!lista) return;

  if (!Array.isArray(posts) || posts.length === 0) {
    lista.innerHTML = '<div class="empty-state">Nenhum post com métricas disponíveis no momento.</div>';
    return;
  }

  lista.innerHTML = posts.map((post, index) => {
    const thumb = post.media_url_capa
      ? `<img src="${ppEscapeHtml(post.media_url_capa)}" alt="Capa do post" class="post-thumb">`
      : `<div class="post-thumb-placeholder"><i class="bi bi-image"></i></div>`;

    const permalink = post.permalink
      ? `<a href="${ppEscapeHtml(post.permalink)}" target="_blank" rel="noopener" class="post-link">
           <i class="bi bi-box-arrow-up-right"></i>
           Abrir post
         </a>`
      : '';

    return `
      <div class="post-card">
        <div class="post-main">
          <div class="post-thumb-wrap">
            ${thumb}
            <div class="post-rank">
              ${ppRankEmoji(index)}
              ${ppRankLabel(index)}
            </div>
          </div>

          <div class="post-body">
            <div class="post-head">
              <div class="post-chips">
                <span class="post-chip ${ppNetworkClass(post.network)}">
                  <i class="bi ${post.network === 'facebook' ? 'bi-facebook' : 'bi-instagram'}"></i>
                  ${ppEscapeHtml(post.network_label || 'Rede')}
                </span>

                <span class="post-chip tipo">
                  ${ppEscapeHtml(post.post_tipo_label || 'Post')}
                </span>
              </div>

              <div class="post-score">
                <div class="post-score-label">Score</div>
                <div class="post-score-value">${ppFormatNumber(post.score_engajamento)}</div>
              </div>
            </div>

            <div class="post-title">Post #${ppEscapeHtml(post.post_id)}</div>

            <div class="post-caption">
              ${ppEscapeHtml(post.caption_resumo || 'Sem legenda disponível.')}
            </div>
          </div>
        </div>

        <div class="post-grid">
          <div class="post-mini">
            <div class="post-mini-label">Curtidas</div>
            <div class="post-mini-value">${ppFormatNumber(post.curtidas)}</div>
          </div>

          <div class="post-mini">
            <div class="post-mini-label">Comentários</div>
            <div class="post-mini-value">${ppFormatNumber(post.comentarios)}</div>
          </div>

          <div class="post-mini">
            <div class="post-mini-label">Compartilhamentos</div>
            <div class="post-mini-value">${ppFormatNumber(post.compartilhamentos)}</div>
          </div>

          <div class="post-mini">
            <div class="post-mini-label">Salvamentos</div>
            <div class="post-mini-value">${ppFormatNumber(post.salvamentos)}</div>
          </div>

          <div class="post-mini">
            <div class="post-mini-label">Alcance</div>
            <div class="post-mini-value">${ppFormatNumber(post.alcance)}</div>
          </div>

          <div class="post-mini">
            <div class="post-mini-label">Reproduções</div>
            <div class="post-mini-value">${ppFormatNumber(post.reproducoes)}</div>
          </div>
        </div>

        <div class="post-foot">
          <div class="post-foot-left">
            <div class="post-foot-item">Publicado: ${ppFormatDate(post.publicado_em)}</div>
            <div class="post-foot-item">Atualizado: ${ppFormatDate(post.atualizado_em)}</div>
          </div>

          ${permalink}
        </div>
      </div>
    `;
  }).join('');
}

async function carregarPioresPosts() {
  const lista = document.getElementById('listaPioresPosts');
  if (!lista) return;

  try {
    const url = new URL(PIORES_POSTS_API_URL, window.location.origin);
    url.searchParams.set('rede', PIORES_POSTS_REDE);
    url.searchParams.set('ordem', PIORES_POSTS_ORDEM);
    url.searchParams.set('limite', '3');

    const response = await fetch(url.toString(), {
      cache: 'no-store',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    });

    if (!response.ok) {
      throw new Error('Falha ao carregar piores posts');
    }

    const data = await response.json();

    if (!data || data.ok !== true) {
      throw new Error('Resposta inválida da API');
    }

    renderPioresPosts(data.posts || []);
  } catch (err) {
    console.error('Erro ao carregar piores posts:', err);
    lista.innerHTML = '<div class="empty-state">Não foi possível carregar os piores posts agora.</div>';
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', carregarPioresPosts);
} else {
  carregarPioresPosts();
}
</script>

</body>
</html>