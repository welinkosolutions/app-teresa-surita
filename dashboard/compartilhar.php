<?php
declare(strict_types=1);

ini_set('display_errors','1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');
session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

$pessoa_id = (int) $_SESSION['pessoa_id'];

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();

/* ================= FILTRO ================= */
$filtro = $_GET['rede'] ?? null;
$redesValidas = ['facebook','instagram','threads','tiktok','youtube'];

if (!in_array($filtro, $redesValidas, true)) {
    $filtro = null;
}

/* ================= PAGINAÇÃO ================= */
$limite = 6;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

/* ================= QUERY ================= */
$whereFiltro = '';
if ($filtro) {
    $whereFiltro = " AND sp.rede = :rede ";
}

$sql = "
SELECT sp.*
FROM social_posts sp
WHERE sp.ativo = 'sim'
{$whereFiltro}
ORDER BY sp.criado_em DESC
LIMIT {$limite} OFFSET {$offset}
";

$stmt = $pdo->prepare($sql);

if ($filtro) {
    $stmt->bindValue(':rede', $filtro, PDO::PARAM_STR);
}

$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= EXECUÇÕES ================= */
$stmtExec = $pdo->prepare("
    SELECT post_id, rede, quantidade_execucoes
    FROM social_posts_cliques_app
    WHERE pessoa_id = :pessoa
");
$stmtExec->bindValue(':pessoa', $pessoa_id, PDO::PARAM_INT);
$stmtExec->execute();
$execRaw = $stmtExec->fetchAll(PDO::FETCH_ASSOC);

$execucoes = [];
foreach ($execRaw as $e) {
    $execucoes[$e['post_id']][$e['rede']] = (int)$e['quantidade_execucoes'];
}

/* ================= FUNÇÕES ================= */
function resumoTexto(string $texto, int $limite = 150): string {
    $texto = trim(strip_tags($texto));
    if (mb_strlen($texto) <= $limite) return $texto;
    return mb_substr($texto, 0, $limite) . '...';
}

function montarLink(array $post, string $rede): string {

    switch ($rede) {

        case 'instagram':
            return $post['link_instagram'] ?? '';

        case 'facebook':
            return $post['link_facebook'] ?? '';

        case 'tiktok':
        case 'youtube':
        case 'threads':
            return $post['texto_compartilhamento'] ?? '';

        case 'whatsapp':

            $base = '';

            if (!empty($post['link_instagram'])) {
                $base = $post['link_instagram'];
            } elseif (!empty($post['link_facebook'])) {
                $base = $post['link_facebook'];
            } elseif (!empty($post['texto_compartilhamento'])) {
                $base = $post['texto_compartilhamento'];
            }

            return $base
                ? 'https://wa.me/?text=' . urlencode($base)
                : '';

        default:
            return '';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Compartilhar</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#f4f6f9; font-family:system-ui; }
.filtros { display:flex; gap:15px; margin-bottom:20px; align-items:center; }
.filtros img { width:34px; height:34px; opacity:0.4; cursor:pointer; transition:0.2s; }
.filtros img.ativo { opacity:1; transform:scale(1.15); }
.card-post { border-radius:16px; overflow:hidden; }
.thumbnail { width:100%; height:220px; object-fit:cover; background:#ddd; }
.progress { height:6px; }
.social-wrapper { position:relative; display:inline-block; }
.social-icons img { width:38px; height:38px; object-fit:contain; cursor:pointer; }
.social-icons img.disabled { opacity:0.3; pointer-events:none; }
.contador {
    position:absolute; top:-6px; right:-6px;
    background:#6c757d; color:#fff;
    font-size:11px; padding:2px 6px;
    border-radius:20px; font-weight:600;
}
.contador.finalizado { background:#198754; }
</style>
</head>
<body>

<div class="container py-4">

<div class="d-flex justify-content-between align-items-center mb-3">
<h5 class="mb-0">Compartilhe e Pontue</h5>
<a href="/dashboard/index.php" class="btn btn-sm btn-outline-secondary">← Voltar</a>
</div>

<!-- FILTROS -->
<div class="filtros">
<img src="/assets/img/social/reload.png"
     class="<?= !$filtro ? 'ativo' : '' ?>"
     onclick="window.location.href='compartilhar.php'">

<?php foreach ($redesValidas as $r): ?>
<img src="/assets/img/social/<?= $r ?>.png"
     class="<?= $filtro === $r ? 'ativo' : '' ?>"
     onclick="window.location.href='compartilhar.php?rede=<?= $r ?>'">
<?php endforeach; ?>
</div>

<div id="posts-container">

<?php foreach ($posts as $post):

$postId = (int)$post['id'];
$pontosBase = (int)$post['pontos_base'];
$limiteWhats = (int)$post['limite_execucoes'];

$redes = [$post['rede'], 'whatsapp'];

$imagemPath = '';
$possiblePath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/social/' . $postId . '.jpg';
if (file_exists($possiblePath)) {
    $imagemPath = '/uploads/social/' . $postId . '.jpg';
}

$totalExecReal = 0;
$totalMaxExec = 0;

foreach ($redes as $r) {
    $lim = ($r === 'whatsapp') ? $limiteWhats : 1;
    $totalMaxExec += $lim;
    $totalExecReal += $execucoes[$postId][$r] ?? 0;
}

$percentual = $totalMaxExec > 0
    ? round(($totalExecReal / $totalMaxExec) * 100)
    : 0;

$totalMaxPontos = $totalMaxExec * $pontosBase;
?>

<div class="card card-post mb-4 shadow-sm">

<?php if ($imagemPath): ?>
<img src="<?= $imagemPath ?>" class="thumbnail">
<?php endif; ?>

<div class="card-body">

<div class="d-flex justify-content-between align-items-start">
<div class="fw-bold"><?= htmlspecialchars($post['titulo']) ?></div>
<span class="badge bg-success">
Ganhe até <?= $totalMaxPontos ?> pts
</span>
</div>

<div class="text-muted mb-2">
<?= htmlspecialchars(resumoTexto($post['descricao'] ?? '')) ?>
</div>

<div class="progress mb-3">
<div class="progress-bar bg-success"
style="width: <?= $percentual ?>%"></div>
</div>

<div class="d-flex gap-4 social-icons">

<?php foreach ($redes as $rede):

$executado = $execucoes[$postId][$rede] ?? 0;
$limite = ($rede === 'whatsapp') ? $limiteWhats : 1;
$bloqueado = $executado >= $limite;
$link = montarLink($post, $rede);
?>

<div class="social-wrapper">
<img src="/assets/img/social/<?= $rede ?>.png"
class="<?= $bloqueado ? 'disabled' : '' ?>"
data-post="<?= $postId ?>"
data-rede="<?= $rede ?>"
data-limite="<?= $limite ?>"
data-exec="<?= $executado ?>"
data-link="<?= htmlspecialchars($link, ENT_QUOTES) ?>"
onclick="executarAcao(this)">
<span class="contador <?= $bloqueado ? 'finalizado' : '' ?>">
<?= $executado ?>/<?= $limite ?>
</span>
</div>

<?php endforeach; ?>

</div>
</div>
</div>

<?php endforeach; ?>

</div>
</div>

<script>
function executarAcao(el){

if(el.classList.contains('disabled')) return;

let postId = el.dataset.post;
let rede = el.dataset.rede;
let limite = parseInt(el.dataset.limite);
let exec = parseInt(el.dataset.exec);
let link = el.dataset.link;

if(!link){
alert('Link não configurado.');
return;
}

let novaAba = window.open(link, '_blank');
if(!novaAba){
alert('Permita abrir a aba para pontuar.');
return;
}

fetch('acao-compartilhar.php', {
method: 'POST',
headers: {'Content-Type':'application/json'},
body: JSON.stringify({ id: postId, rede: rede })
})
.then(r=>r.json())
.then(resp=>{

if(!resp.status) return;

exec++;
el.dataset.exec = exec;

let badge = el.parentElement.querySelector('.contador');
badge.innerText = exec + '/' + limite;

if(exec >= limite){
el.classList.add('disabled');
badge.classList.add('finalizado');
}

let card = el.closest('.card');
let allIcons = card.querySelectorAll('.social-icons img');

let totalExec = 0;
let totalMax = 0;

allIcons.forEach(icon=>{
totalExec += parseInt(icon.dataset.exec);
totalMax += parseInt(icon.dataset.limite);
});

let percent = Math.round((totalExec / totalMax) * 100);
card.querySelector('.progress-bar').style.width = percent + '%';

});
}
</script>

</body>
</html>