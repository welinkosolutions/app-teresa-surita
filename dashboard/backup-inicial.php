<?php
declare(strict_types=1);

ini_set('display_errors','1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

require_once '/home/elab/public_html/core/gamificacao/feed_missoes_home.php';


if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

$pdo = dbRoraima();
$pessoa_id = (int)$_SESSION['pessoa_id'];

require_once '/home/elab/public_html/core/social/feed_engine.php';
require_once '/home/elab/public_html/core/social/feed_inteligente.php';

if(!function_exists('elabFeedEngine')){
    die('feed_engine.php carregou mas função não existe');
}

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
    instagram_username
      pontos
FROM pessoas
WHERE id=? AND status='ativo'
LIMIT 1
");
$stmt->execute([$pessoa_id]);
$pessoa = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

/*
========================================
NOME EXIBIÇÃO
========================================
*/
$nomeCompleto = trim((string)($pessoa['nome'] ?? ''));
$nomeExibicao = '';

if (($pessoa['chamar_por'] ?? '') === 'apelido' && !empty($pessoa['apelido'])) {
    $nomeExibicao = trim((string)$pessoa['apelido']);
} else {
    $partes = preg_split('/\s+/', $nomeCompleto);
    $nomeExibicao = implode(' ', array_slice($partes ?: [], 0, 2));
}

if ($nomeExibicao === '') {
    $nomeExibicao = 'Usuário';
}

$precisaInstagram = empty($pessoa['instagram_username']);

/*
========================================
ESTADO GAMIFICAÇÃO
========================================
*/
$stmt = $pdo->prepare("
SELECT chave, valor
FROM gamificacao_estado_usuario
WHERE pessoa_id=?
");
$stmt->execute([$pessoa_id]);

$estado = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $estado[(string)$row['chave']] = (string)$row['valor'];
}

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

} elseif (!empty($estado['xp_recente'])) {

    $eventoTipo = 'xp';
    $eventoXP = (int)$estado['xp_recente'];

} elseif (!empty($estado['nova_missao']) && $estado['nova_missao'] === 'sim') {

    $eventoTipo = 'missao';

}
/*
========================================
XP DO USUÁRIO (OFICIAL)
========================================
*/

$stmt = $pdo->prepare("
SELECT pontos
FROM pessoas
WHERE id=?
LIMIT 1
");
$stmt->execute([$pessoa_id]);

$xpTotal = (int)$stmt->fetchColumn();

/*
========================================
POSIÇÃO NO RANKING
========================================
*/

$stmt = $pdo->prepare("
SELECT posicao
FROM vw_ranking_geral
WHERE pessoa_id=?
LIMIT 1
");
$stmt->execute([$pessoa_id]);

$posicaoRanking = (int)($stmt->fetchColumn() ?? 0);
/*
========================================
TOP 3 XP
========================================
*/
$topXP = $pdo->query("
SELECT nome, xp_total
FROM vw_ranking_geral
ORDER BY posicao
LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

/*
========================================
MISSÕES
========================================
*/
$missoes = elabBuscarMissoesHome($pdo, $pessoa_id);

/*
========================================
SAUDAÇÃO
========================================
*/
$hora = (int)date('H');
$saudacao = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');

/*
========================================
HERO DINÂMICO
========================================
*/
$hero = '/assets/anime/dom-static.webp';

if ($eventoTipo === 'ranking' || $eventoTipo === 'xp') {

    $hero = '/assets/anime/animado-happy.webp';

} elseif ($eventoTipo === 'missao') {

    $hero = '/assets/anime/animado-intro.webp';

}

/*
========================================
MISSÃO PRINCIPAL
========================================
*/
$missaoDia = $missoes['missao_dia'] ?? null;
$missaoExtra = $missoes['missao_extra'] ?? null;

$imgMissaoDia = !empty($missaoDia['media_url']) ? (string)$missaoDia['media_url'] : '/assets/img/post-placeholder.jpg';
$imgMissaoExtra = !empty($missaoExtra['media_url']) ? (string)$missaoExtra['media_url'] : '/assets/img/post-placeholder.jpg';

$xpAtualMissao = (int)($missaoDia['xp_usuario'] ?? 0);
$xpTotalMissao = (int)($missaoDia['xp_total'] ?? 40);
if ($xpTotalMissao <= 0) {
    $xpTotalMissao = 40;
}
$percentMissao = $xpTotalMissao > 0
    ? (int)min(100, round(($xpAtualMissao / $xpTotalMissao) * 100))
    : 0;

$totalPendentes = 0;

/*
========================================
SMART MESSAGE ENGINE
========================================
*/
$msgTitulo = '👋 Bem-vindo de volta';
$msgTexto  = 'Vamos fortalecer nossa comunidade hoje.';

/* Pendências */
if ($totalPendentes >= 3) {

    $msgTitulo = '😟 Muitas pendências';
    $msgTexto  = "Você tem {$totalPendentes} ações pendentes. A Teresa conta com você para manter nossa comunidade ativa.";

/* Missão incompleta */
} elseif (!empty($missaoDia) && $percentMissao < 100) {

    $faltam = $xpTotalMissao - $xpAtualMissao;

    $msgTitulo = '🎯 Missão em andamento';
    $msgTexto  = "Faltam apenas {$faltam} XP para completar sua missão de hoje.";

/* Ranking */
} elseif ($posicaoRanking > 3) {

    $msgTitulo = '🏆 Suba no ranking';
    $msgTexto  = "Continue assim! Mais alguns pontos e você pode subir no ranking.";
}

/*
========================================
COMENTÁRIOS 24H
========================================
*/

$stmt = $pdo->query("
SELECT
e.username,
e.object_id,
m.permalink
FROM social_events e
LEFT JOIN social_media m
ON m.instagram_media_id = e.object_id
WHERE e.event_type='comment'
AND e.processado='sim'
AND e.event_time >= NOW() - INTERVAL 24 HOUR
ORDER BY e.event_time DESC
LIMIT 100
");

$comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
========================================
POST EM ALTA (24H)
========================================
*/

$stmt = $pdo->query("
SELECT 
    m.permalink,
    COUNT(*) AS total_comentarios
FROM social_events e
JOIN social_media m 
    ON m.instagram_media_id = e.object_id
WHERE e.event_type = 'comment'
AND e.event_time >= NOW() - INTERVAL 24 HOUR
GROUP BY e.object_id
ORDER BY total_comentarios DESC
LIMIT 1
");

$postTrending = $stmt->fetch(PDO::FETCH_ASSOC);

$postTrendingUrl = $postTrending['permalink'] ?? '/dashboard/index.php';
$postTrendingCount = (int)($postTrending['total_comentarios'] ?? 0);

/*
========================================
COMUNIDADE ATIVA (24H)
========================================
*/

$stmt = $pdo->query("
SELECT 
COUNT(*) AS total_comentarios,
COUNT(DISTINCT username) AS total_pessoas
FROM social_events
WHERE event_type = 'comment'
AND event_time >= NOW() - INTERVAL 24 HOUR
AND username IS NOT NULL
");

$comunidade = $stmt->fetch(PDO::FETCH_ASSOC);

$totalComentarios24h = (int)($comunidade['total_comentarios'] ?? 0);
$totalPessoas24h = (int)($comunidade['total_pessoas'] ?? 0);



/*
========================================
EVENTOS RECENTES (INBOX)
BASEADO EM EVENTOS REAIS
========================================
*/

if(!isset($feedEventos) || !is_array($feedEventos)){
    $feedEventos = [];
}

foreach($comentarios as $c){

$user = strtolower(trim((string)($c['username'] ?? '')));
$post = !empty($c['permalink'])
    ? $c['permalink']
    : '/dashboard/index.php';
if($user === ''){
$user = 'alguém';
}

/*
========================================
TERESA RESPONDEU
========================================
*/

if($user === 'teresasurita'){

$feedEventos[] = [
'tipo'=>'teresa_respondeu',
'post'=>$post
];

continue;
}

/*
========================================
COMENTÁRIO NORMAL
========================================
*/

$feedEventos[] = [
'tipo'=>'comentario',
'user'=>$user,
'post'=>$post
];

}

/*
========================================
POST EM ALTA
========================================
*/

if($postTrendingCount > 5){

$feedEventos[] = [
'tipo'=>'post_trending',
'post'=>$postTrendingUrl,
'total'=>$postTrendingCount
];

}

/*
========================================
COMUNIDADE ATIVA
========================================
*/

if($totalComentarios24h > 20){

$feedEventos[] = [
'tipo'=>'comunidade_ativa',
'total'=>$totalComentarios24h,
'pessoas'=>$totalPessoas24h
];

}
/*
========================================
FEED ENGINE + FEED INTELIGENTE
========================================
*/

$feedEngine = elabFeedEngine($pdo) ?: [];
$feedInteligente = elabFeedInteligente($pdo) ?: [];

/* junta tudo */
$feedEventos = array_merge(
    $feedEventos,
    (array)$feedEngine,
    (array)$feedInteligente
);

/* embaralha */
shuffle($feedEventos);

$postsVistos = [];

$feedEventos = array_filter($feedEventos,function($e) use (&$postsVistos){

if(empty($e['post'])) return true;

if(isset($postsVistos[$e['post']])) return false;

$postsVistos[$e['post']] = true;

return true;

});

$feedEventos = array_values($feedEventos);

$feedEventos = array_slice($feedEventos,0,12);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ELAB Social</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<link rel="preload" as="image" href="/assets/anime/animado-happy.webp">
<link rel="preload" as="image" href="/assets/anime/animado-intro.webp">
<link rel="preload" as="image" href="/assets/anime/animado-bad.webp">
<link rel="preload" as="image" href="/assets/anime/dom-static.webp">

<style>
:root{
  --bg:#ffffff;
  --card:#ffffff;
  --text:#102331;
  --muted:#5f7280;
  --brand1:#0b6e7a;
  --brand2:#1aa8b2;
  --primary:#256ef1;
  --success:#25d366;
  --warning:#ffb400;
  --shadow:0 12px 28px rgba(16,35,49,.10);
  --shadow-strong:0 18px 40px rgba(16,35,49,.18);
  --radius:24px;
}

*{box-sizing:border-box}
body{
  margin:0;
  background:linear-gradient(180deg,#eef2f5 0%, #e9eef2 100%);
  font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
  color:var(--text);
}

.page-wrap{
  padding-bottom:110px;
}

/* HEADER */
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
  justify-content:space-between;
  align-items:flex-start;
  gap:16px;
}
.header-greeting h1{
  margin:0;
  font-size:20px;
  font-weight:900;
  letter-spacing:-.2px;
}
.header-greeting .name{
  margin-top:6px;
  font-size:15px;
  font-weight:800;
}
.header-score{
  text-align:right;
}
.header-xp{
  font-size:24px;
  line-height:1;
  font-weight:1000;
  letter-spacing:.2px;
}
.header-rank{
  margin-top:8px;
  font-size:13px;
  font-weight:700;
  opacity:.95;
}

/* HERO */
.hero{
  position:relative;
  z-index:3;
  margin:-120px 18px 18px;
  border-radius:26px;
  overflow:hidden;
  box-shadow:transparent;
 background:transparent;
}
.hero img{
  display:block;
  width:100%;
  height:272px;
  object-fit:cover;
  object-position:center top;
}
.hero::after{
  content:'';
  position:absolute;
  inset:auto 0 0 0;
  height:76px;
  background:linear-gradient(180deg, rgba(0,0,0,0) 0%, rgba(0,0,0,.12) 100%);
  pointer-events:none;
}

/* TOAST */
.toast-event{
  margin:0 18px 18px;
  padding:14px 16px;
  border-radius:18px;
  color:#fff;
  font-weight:900;
  box-shadow:var(--shadow);
  animation:toastIn .35s ease;
}
.toast-event small{
  display:block;
  margin-top:3px;
  font-size:12px;
  font-weight:700;
  opacity:.92;
}
.toast-xp{ background:linear-gradient(135deg,#ff4d6d,#ff7a91); }
.toast-ranking{ background:linear-gradient(135deg,#ff9f1c,#ffbf40); }
.toast-missao{ background:linear-gradient(135deg,#7c4dff,#9a67ff); }

@keyframes toastIn{
  from{opacity:0; transform:translateY(-8px);}
  to{opacity:1; transform:translateY(0);}
}

/* CARDS */
.card-box,
.card-convite{
  margin:0 18px 18px;
  border-radius:26px;
  padding:18px;
  background:var(--card);
  box-shadow:var(--shadow);
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
.card-subtext{
  color:var(--muted);
  font-size:13px;
  line-height:1.45;
}

/* MENSAGEM */
.msg-box{
  padding:20px 18px;
  animation:msgReveal .5s ease .1s both;
}
.msg-title{
  font-size:17px;
  font-weight:1000;
  margin:0 0 6px;
}

/* CONVITE */
.card-convite{
  background:
    radial-gradient(circle at top right, #199146, transparent 28%),
    linear-gradient(135deg,#20cb5a,#1ab34e);
  color:#fff;
  box-shadow:0 18px 38px rgba(37,211,102,.22);
}
.card-convite .card-title,
.card-convite .card-subtext{
  color:#fff;
}
.btn-pill{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  text-decoration:none;
  border:none;
  border-radius:999px;
  padding:14px 22px;
  font-size:15px;
  font-weight:1000;
  cursor:pointer;
  transition:transform .15s ease, box-shadow .15s ease, opacity .15s ease;
}
.btn-pill:active{
  transform:scale(.98);
}
.btn-pill-white{
  background:#fff;
  color:#18a84b;
  box-shadow:0 8px 18px rgba(0,0,0,.16);
}

/* MISSÕES */
.missao-img{
  width:100%;
  display:block;
  border-radius:20px;
  margin:10px 0 14px;
  box-shadow:0 10px 22px rgba(0,0,0,.16);
  background:#eef2f5;
}
.missao-caption{
  font-size:15px;
  line-height:1.45;
  color:#233746;
}
.progress-wrap{
  margin:14px 0 8px;
}
.progress-meta{
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:8px;
  font-size:12px;
  font-weight:900;
  color:var(--muted);
}
.progress{
  height:12px;
  border-radius:999px;
  background:#e9edf2;
  overflow:hidden;
}
.progress-bar{
  background:linear-gradient(90deg,#ffc400,#ff8c00);
  border-radius:999px;
}
.btn-missao{
  display:block;
  width:100%;
  text-align:center;
  text-decoration:none;
  margin-top:14px;
  padding:15px 18px;
  border-radius:18px;
  background:linear-gradient(135deg,#2467e8,#2f78ff);
  color:#fff;
  font-size:16px;
  font-weight:1000;
  box-shadow:0 12px 24px rgba(37,110,241,.24);
}
.btn-missao.disabled,
.btn-missao[aria-disabled="true"]{
  background:#d5dce4;
  color:#7a8894;
  box-shadow:none;
  pointer-events:none;
}

/* RANKING */
.ranking-list{
  margin-top:4px;
}
.ranking-item{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  padding:13px 0;
  border-bottom:1px solid #edf1f4;
}
.ranking-item:last-child{
  border-bottom:none;
}
.ranking-left{
  display:flex;
  align-items:center;
  gap:10px;
  min-width:0;
}
.rank-badge{
  font-size:18px;
  line-height:1;
}
.rank-name{
  font-size:15px;
  font-weight:900;
  color:var(--text);
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}
.rank-score{
  font-size:16px;
  font-weight:1000;
  color:var(--text);
}
.rank-you{
  margin-top:10px;
  padding:14px 16px;
  border-radius:18px;
  background:linear-gradient(180deg,#f2f6ff,#eef3fb);
  border:1px solid #e3ebfb;
}

/* FOOTER */
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

.msg-box{
position:relative;
padding:20px 18px 20px 20px;
border-radius:22px;
background:#ffffff;
border:1px solid #e7edf3;
box-shadow:0 10px 24px rgba(16,35,49,.08);
animation:msgPop .35s ease;
}

.msg-box::before{
content:'';
position:absolute;
left:0;
top:14px;
bottom:14px;
width:6px;
border-radius:6px;
background:linear-gradient(180deg,#ff7a18,#ffb347);
}

/* badge tipo notificação */

.msg-badge{
display:inline-flex;
align-items:center;
gap:6px;
font-size:11px;
font-weight:900;
color:#ff7a18;
background:#fff2e8;
padding:4px 8px;
border-radius:999px;
margin-bottom:8px;
}

.msg-dot{
width:8px;
height:8px;
border-radius:50%;
background:#ff7a18;
animation:pulseDot 1.6s infinite;
}

/* animação chegada */

@keyframes msgPop{
from{
opacity:0;
transform:translateY(6px);
}
to{
opacity:1;
transform:translateY(0);
}
}

/* animação notificação */

@keyframes pulseDot{
0%{transform:scale(1);opacity:1}
50%{transform:scale(1.6);opacity:.4}
100%{transform:scale(1);opacity:1}
}

.inbox-box{
padding:16px 18px;
}

.inbox-item{
display:flex;
justify-content:space-between;
align-items:center;
gap:12px;
padding:12px 0;
border-bottom:1px solid #edf1f4;
}

.inbox-item:last-child{
border-bottom:none;
}

.social-comment{
display:none;
opacity:0;
transition:opacity .3s ease;
}

.inbox-text{
font-size:14px;
font-weight:700;
color:#223846;
}

.inbox-btn{
font-size:13px;
font-weight:900;
padding:8px 14px;
border-radius:999px;
background:linear-gradient(135deg,#256ef1,#4c8dff);
color:#fff;
text-decoration:none;
box-shadow:0 6px 14px rgba(37,110,241,.25);
transition:.15s;
}

.inbox-btn:active{
transform:scale(.96);
}

@media (min-width: 700px){
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

.inbox-item{
display:flex;
align-items:center;
justify-content:space-between;
gap:10px;
font-size:14px;
font-weight:700;
color:#223846;
animation:feedSlide .35s ease;
}

.live-badge-inline{
display:inline-flex;
align-items:center;
gap:6px;
font-size:11px;
font-weight:900;
color:#ff4d4d;
background:rgba(255,77,77,0.08);
padding:5px 10px;
border-radius:999px;
letter-spacing:.4px;
}

.live-dot{
width:8px;
height:8px;
background:#ff4d4d;
border-radius:50%;
animation:livePulse 1.4s infinite;
box-shadow:0 0 0 0 rgba(255,77,77,.7);
}

@keyframes livePulse{
0%{
transform:scale(1);
box-shadow:0 0 0 0 rgba(255,77,77,.7);
}
70%{
transform:scale(1.3);
box-shadow:0 0 0 6px rgba(255,77,77,0);
}
100%{
transform:scale(1);
box-shadow:0 0 0 0 rgba(255,77,77,0);
}
}

.live-dot{
width:7px;
height:7px;
background:#ff4d4d;
border-radius:50%;
animation:livePulse 1.6s infinite;
}

@keyframes livePulse{
0%{opacity:1}
50%{opacity:.4}
100%{opacity:1}
}

@keyframes feedSlide{
from{
opacity:0;
transform:translateY(6px);
}
to{
opacity:1;
transform:translateY(0);
}
}

.card-title-live{
display:flex;
align-items:center;
gap:10px;
}

.inbox-item{
display:flex;
flex-direction:column;
align-items:flex-start;
gap:10px;
padding:12px 0;
border-bottom:1px solid #edf1f4;
animation:feedSlide .35s ease;
}

.inbox-content{
display:flex;
flex-direction:column;
gap:4px;
}

.inbox-line1{
font-size:14px;
font-weight:800;
color:#223846;
}

.inbox-line2{
font-size:13px;
font-weight:700;
color:#6a7b86;
}

.user-tag{
color:#ff4d4d;
font-weight:900;
}

.inbox-btn{
align-self:flex-start;
font-size:13px;
font-weight:900;
padding:9px 16px;
border-radius:999px;
background:linear-gradient(135deg,#256ef1,#4c8dff);
color:#fff;
text-decoration:none;
box-shadow:0 6px 14px rgba(37,110,241,.25);
transition:.15s;
}
</style>
</head>
<body>

<div class="page-wrap">

  <div class="header">
    <div class="header-top">
      <div class="header-greeting">
        <h1><?= htmlspecialchars($saudacao) ?></h1>
        <div class="name"><?= htmlspecialchars($nomeExibicao) ?></div>
      </div>

      <div class="header-score">
        <div class="header-xp" id="xpCounter"><?= number_format($xpTotal, 0, ',', '.') ?> XP</div>
        <div class="header-rank">Sua posição no <br>Ranking é <strong><?= number_format($posicaoRanking, 0, ',', '.') ?></strong>🎖️️</div>
      </div>
    </div>
  </div>

  <div class="hero">
  <img id="heroImg" src="<?= htmlspecialchars($hero) ?>" alt="Hero ELAB">
  </div>

  <?php if ($eventoTipo === 'xp'): ?>
    <div class="toast-event toast-xp">
      🔥 Comentário validado
      <small>+<?= number_format($eventoXP, 0, ',', '.') ?> XP caiu na sua conta.</small>
    </div>
  <?php elseif ($eventoTipo === 'ranking'): ?>
    <div class="toast-event toast-ranking">
      🏆 Você subiu no ranking!
      <small>Boa! Continua assim que dá para subir ainda mais.</small>
    </div>
  <?php elseif ($eventoTipo === 'missao'): ?>
    <div class="toast-event toast-missao">
      ✨ Nova missão disponível
      <small>Tem mais uma chance de engajar e somar pontos agora.</small>
    </div>
  <?php endif; ?>

<div class="card-box inbox-box">
<div class="card-title card-title-live">

<span>🔔 PARTICIPE AGORA</span>

<span class="live-badge-inline">
<span class="live-dot"></span>
AO VIVO
</span>
</div>
<div style="font-size:12px;color:#6a7b86;font-weight:700;margin-bottom:10px;">
🔥 <?=$totalComentarios24h?> comentários nas últimas 2 horas
</div>
<div id="socialFeed">
<?php foreach($feedEventos as $e): ?>

<?php if($e['tipo']=='teresa_respondeu'): ?>

<?php
$msgs = [
"😍 A Teresa respondeu um comentário nesse post!",
"👀 A Teresa entrou na conversa agora.",
"Eita!! 🤣 A Diva tá atacada hoje nos comentários. Corre lá!",
"Você viu a resposta da Teresa nesse comentário?! 🥹",
"Você viu o que responderam para a Teresa nesse post?! 🤔",
"💬 A Teresa respondeu alguém nesse post."
];
$msg = $msgs[array_rand($msgs)];
?>

<div class="inbox-item feed-event">

<div class="inbox-content">
<div class="inbox-line1"><?= $msg ?></div>
</div>
<div class="inbox-line2">
Comente agora e desbloqueie <strong>+20 XP</strong>🔐 
</div>
<a class="inbox-btn" target="_blank" href="<?= htmlspecialchars($e['post']) ?>">
Clique para comentar!
</a>

</div>


<?php elseif($e['tipo']=='comentario'): ?>

<div class="inbox-item feed-event">

<div class="inbox-content">

<div class="inbox-line1">
💬 <strong class="user-tag">@<?=htmlspecialchars($e['user'] ?? 'alguém')?></strong> comentou agora no instagram 🤩
</div>

<div class="inbox-line2">
Participe da conversa e libere <strong>+20 XP</strong>🔐
</div>

</div>

<a class="inbox-btn" target="_blank" href="<?=htmlspecialchars($e['post'])?>">
Participe agora!
</a>
</div>

<?php elseif($e['tipo']=='comentario_sugerido'): ?>

<div class="inbox-item feed-event">

<div class="inbox-content">

<div class="inbox-line1">
💬 Comentário de <strong class="user-tag">@<?=htmlspecialchars($e['user'])?></strong> em destaque
</div>
<div class="inbox-line2">
Comente agora e desbloqueie <strong>+20 XP</strong>🔐 
</div>

</div>

<a class="inbox-btn" target="_blank" href="<?=htmlspecialchars($e['post'])?>">
Clique para comentar!
</a>

</div>

<?php elseif($e['tipo']=='xp_convite'): ?>

<div class="inbox-item feed-event">

<div class="inbox-content">

<div class="inbox-line1">
🔥 Comente agora e ganhe <strong>+20 XP</strong>
</div>

<div class="inbox-line2">
Comente agora e desbloqueie <strong>+20 XP</strong>🔐 
</div>
</div>

<a class="inbox-btn" target="_blank" href="<?=htmlspecialchars($e['post'])?>">
Clique para comentar!
</a>

</div>


<?php elseif($e['tipo']=='post_trending'): ?>

<div class="inbox-item feed-event">

<div class="inbox-content">

<div class="inbox-line1">
🔥 Post com <strong><?= (int)$e['total'] ?></strong> comentários nas últimas 24h
</div>

<div class="inbox-line2">
Eita!! A conversa está bombando nesse post 😱 você viu?
</div>

</div>

<a class="inbox-btn" target="_blank" href="<?= htmlspecialchars($e['post']) ?>">
Participe agora!
</a>

</div>

<?php elseif($e['tipo']=='comunidade_ativa'): ?>

<?php
$msgs = [
"👀 Tem gente movimentando o instagram! {$e['pessoas']} pessoas já fizeram {$e['total']} comentários nas últimas horas.",
"🔥 A conversa está rolando forte no Instagram! {$e['total']} comentários nas últimas 24h — bora participar também?",
"🚀 Eita que esse post bombou! {$e['pessoas']} pessoas comentaram hoje. Bora comentar também?!"
];
$msg = $msgs[array_rand($msgs)];
?>

<div class="inbox-item feed-event">

<div class="inbox-content">
<div class="inbox-line1"><?= $msg ?></div>
</div>
<div class="inbox-line2">
Mostre seu apoio a Teresa no Instagram com um comentário 😉
</div>
<a class="inbox-btn" target="_blank" href="/dashboard/index.php">
Apoiar agora!
</a>

</div>


<?php elseif($e['tipo']=='critica'): ?>

<?php
$msgs = [
"🚨 Estão criando fakenews neste post... bora mostrar que estamos juntos?",
"😡 Oposição apareceu neste post. Vamos responder com respeito e presença?",
"⚠️ Comentários negativos detectados. Sua participação ajuda a equilibrar a conversa."
];
$msg = $msgs[array_rand($msgs)];
?>

<div class="inbox-item feed-event">

<div class="inbox-content">
<div class="inbox-line1"><?= $msg ?></div>
</div>
<div class="inbox-line2">
Mostre seu apoio a Teresa no Instagram com um comentário 😉
</div>
<a class="inbox-btn" target="_blank" href="<?= htmlspecialchars($e['post']) ?>">
Clique e apoie agora!
</a>

</div>


<?php elseif($e['tipo']=='apoio'): ?>

<?php
$msgs = [
"❤️ Esse post está cheio de apoio à Teresa! Bora reforçar também?",
"😍 O time está apoiando forte aqui. Entre e deixe seu comentário!",
"👏 Esse post virou uma corrente de apoio. Participe também!"
];
$msg = $msgs[array_rand($msgs)];
?>

<div class="inbox-item feed-event">

<div class="inbox-content">
<div class="inbox-line1"><?= $msg ?></div>
</div>
<div class="inbox-line2">
Mostre seu apoio a Teresa no Instagram com um comentário 😉
</div>
<a class="inbox-btn" target="_blank" href="<?= htmlspecialchars($e['post']) ?>">
Clique para Participar!
</a>

</div>


<?php elseif($e['tipo']=='post_viral'): ?>

<?php
$msgs = [
"🔥 Esse post está pegando fogo! {$e['total']} comentários agora.",
"🚀 Viralizou! Já são {$e['total']} comentários nesse post.",
"🤯 Esse post explodiu de comentários. Corre lá ver!"
];
$msg = $msgs[array_rand($msgs)];
?>

<div class="inbox-item feed-event">

<div class="inbox-content">
<div class="inbox-line1"><?= $msg ?></div>
</div>
<div class="inbox-line2">
Comente agora e desbloqueie <strong>+20 XP</strong>🔐 
</div>
<a class="inbox-btn" target="_blank" href="<?= htmlspecialchars($e['post']) ?>">
Clique para comentar! 
</a>

</div>


<?php elseif($e['tipo']=='onda_comentarios'): ?>

<?php
$msgs = [
"⚡ Uma onda de comentários começou agora. Bora entrar na conversa?",
"🚨 Muita gente comentando nesse post neste momento!",
"🔥 A discussão está acelerando — sua voz pode fazer diferença."
];
$msg = $msgs[array_rand($msgs)];
?>

<div class="inbox-item feed-event">

<div class="inbox-content">
<div class="inbox-line1"><?= $msg ?></div>
</div>
<div class="inbox-line2">
Comente agora e desbloqueie <strong>+20 XP</strong>🔐 
</div>
<a class="inbox-btn" target="_blank" href="/dashboard/index.php">
Participar agora!
</a>

</div>


<?php elseif($e['tipo']=='discussao'): ?>

<?php
$msgs = [
"🚨 Uma discussão começou nesse post. Bora participar?",
"👀 O povo começou a debater aqui. Sua opinião importa!",
"🧯️ A conversa esquentou nesse post. Entre também!"
];
$msg = $msgs[array_rand($msgs)];
?>

<div class="inbox-item feed-event">

<div class="inbox-content">
<div class="inbox-line1"><?= $msg ?></div>
</div>
<div class="inbox-line2">
Apoie a Teresa agora com um comentário e desbloqueie <strong>+20 XP</strong>🔐 
</div>
<a class="inbox-btn" target="_blank" href="<?= htmlspecialchars($e['post']) ?>">
Participar agora!
</a>

</div>


<?php elseif($e['tipo']=='post_crescendo'): ?>

<?php
$msgs = [
"⏰ Esse post está crescendo rápido — ajuda a impulsionar com um comentário?",
"🚀 A conversa começou a subir aqui. Bora engajar e apoiar com um comentário?!",
"🔥 Esse post está ganhando força agora. Comenta lá e apoie a Teresa!"
];
$msg = $msgs[array_rand($msgs)];
?>

<div class="inbox-item feed-event">

<div class="inbox-content">
<div class="inbox-line1"><?= $msg ?></div>
</div>
<div class="inbox-line2">
Apoie a Teresa agora com um comentário e desbloqueie <strong>+20 XP</strong>🔐 
</div>
<a class="inbox-btn" target="_blank" href="<?= htmlspecialchars($e['post']) ?>">
Clique para comentar!
</a>

</div>

<?php endif; ?>

<?php endforeach; ?>
</div>
</div>

  <div class="card-convite">
    <div class="card-title">😍 Convide amigos para participar!</div>
    <div class="card-subtext">Traga pessoas para fortalecer nossa comunidade e ganhe XP quando sua rede crescer.</div>
    <div style="margin-top:14px">
      <a href="/convite/whatsapp.php" class="btn-pill btn-pill-white"><i class="bi bi-whatsapp"></i>Convidar pelo WhatsApp</a>
    </div>
  </div>

  <?php if (!empty($missaoDia)): ?>
    <div class="card-box">
      <div class="card-title">🎯 Missão Principal</div>

          <div class="missao-caption">
        <?= htmlspecialchars((string)mb_strimwidth((string)($missaoDia['caption'] ?? ''), 0, 170, '...')) ?>
      </div>

      <div class="progress-wrap">
        <div class="progress-meta">
          <span>Progresso da missão</span>
          <span><?= $xpAtualMissao ?> / <?= $xpTotalMissao ?> XP</span>
        </div>
        <div class="progress">
          <div class="progress-bar" style="width:<?= $percentMissao ?>%"></div>
        </div>
      </div>

      <?php if ($precisaInstagram): ?>
        <a href="/pessoas/perfil.php" class="btn-missao disabled" aria-disabled="true">Vincule seu Instagram para participar</a>
      <?php else: ?>
        <a href="<?= htmlspecialchars((string)($missaoDia['permalink'] ?? '#')) ?>" target="_blank" class="btn-missao">Cumprir Missão</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="card-box">
    <div class="card-title">🏆 Ranking XP</div>

    <div class="ranking-list">
      <?php foreach ($topXP as $k => $r): ?>
        <div class="ranking-item">
          <div class="ranking-left">
            <div class="rank-badge"><?= ['🥇','🥈','🥉'][$k] ?? '🏅' ?></div>
            <div class="rank-name"><?= htmlspecialchars((string)mb_strimwidth((string)($r['nome'] ?? ''), 0, 24, '...')) ?></div>
          </div>
          <div class="rank-score"><?= number_format((float)($r['xp_total'] ?? 0), 0, ',', '.') ?></div>
        </div>
      <?php endforeach; ?>

      <div class="rank-you">
        <div class="ranking-item" style="padding:0;border-bottom:none;">
          <div class="ranking-left">
            <div class="rank-badge">⭐</div>
            <div class="rank-name">Você</div>
          </div>
          <div class="rank-score"><?= number_format($xpTotal, 0, ',', '.') ?></div>
        </div>
      </div>
    </div>
  </div>

  <?php if (!empty($missaoExtra)): ?>
    <div class="card-box">
      <div class="card-title">🔥 Missão Extra</div>

      <img src="<?= htmlspecialchars($imgMissaoExtra) ?>" class="missao-img" alt="Missão extra">

      <div class="missao-caption">
        <?= htmlspecialchars((string)mb_strimwidth((string)($missaoExtra['caption'] ?? ''), 0, 170, '...')) ?>
      </div>

      <?php if ($precisaInstagram): ?>
        <a href="/pessoas/perfil.php" class="btn-missao disabled" aria-disabled="true">Vincule Instagram para liberar</a>
      <?php else: ?>
        <a href="<?= htmlspecialchars((string)($missaoExtra['permalink'] ?? '#')) ?>" target="_blank" class="btn-missao">Realizar Missão</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</div>

<div class="footer-nav">
  <a href="/dashboard/inicial.php" class="active">
    <i class="bi bi-house"></i>
    Início
  </a>
  <a href="/comunidade/ranking.php">
    <i class="bi bi-trophy"></i>
    Ranking
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

<script>
const EVENTO_TIPO = <?= json_encode($eventoTipo) ?>;
</script>
<script>

const hero = document.getElementById("heroImg");

const HERO_STATIC = "/assets/anime/dom-static.webp";

function heroAnim(nome, duracao = 5000){

  if(!hero) return;

  hero.src = "/assets/anime/animado-" + nome + ".webp";

  setTimeout(()=>{
    hero.src = HERO_STATIC;
  }, duracao);
}

if(EVENTO_TIPO === 'xp'){
  heroAnim("happy");
}

if(EVENTO_TIPO === 'ranking'){
  heroAnim("happy");
}

if(EVENTO_TIPO === 'missao'){
  heroAnim("intro");
}

(function(){
  const el = document.getElementById('xpCounter');
  if (!el) return;

  const target = <?= (int)$xpTotal ?>;
  let current = Math.max(0, target - 35);

  const tick = () => {
    current++;
    el.textContent = current.toLocaleString('pt-BR') + ' XP';
    if (current < target) {
      requestAnimationFrame(tick);
    }
  };

  if (current < target) {
    requestAnimationFrame(tick);
  }
})();

const feed = document.getElementById("socialFeed");

if(feed){

const items = [...feed.querySelectorAll(".feed-event")];

if(items.length){

let index = 0;

function rotateFeed(){

items.forEach(el=>{
el.style.display = "none";
el.style.opacity = "0";
});

const el = items[index % items.length];

if(el){

el.style.display = "flex";

setTimeout(()=>{
el.style.opacity = "1";
},50);

}

index++;

}

rotateFeed();
setInterval(rotateFeed, 4000);

}

}

/*
====================================
LIVE FEED (SEM REFRESH)
====================================
*/

let lastSeen = 0;

async function checkLiveFeed(){

try{

const r = await fetch("/core/social/feed_live.php");
const data = await r.json();

if(!data || !data.length) return;

data.forEach(e=>{

const html = `
<div class="inbox-item feed-event">

<div class="inbox-content">

<div class="inbox-line1">
💬 <strong class="user-tag">@${e.username}</strong> comentou agora
</div>

<div class="inbox-line2">
Participe da conversa e ganhe <strong>+20 XP</strong>
</div>

</div>

<a class="inbox-btn" target="_blank" href="${e.permalink}">
Participar
</a>

</div>
`;

feed.insertAdjacentHTML("afterbegin", html);

});

}catch(err){
console.log("live feed erro",err);
}

}

setInterval(checkLiveFeed,15000);
</script>

</body>
</html>