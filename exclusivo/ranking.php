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

$usuariosExclusivos = [7160, 6168, 6607];
if (!in_array($pessoa_id, $usuariosExclusivos, true)) {
    header('Location: /interno/admin.php');
    exit;
}

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();

$sql = "
SELECT 
    r.posicao,
    r.pessoa_id,
    r.nome,
    r.telefone,
    r.pontos,
    r.bairro,
    r.cidade,
    r.total_cadastrados,

    p.apelido,
    p.chamar_por,
    p.online_status,
    p.online_desde,
    p.ultimo_ping,

    h7.pontos AS pontos_7d,
    (r.pontos - COALESCE(h7.pontos, r.pontos)) AS crescimento_7d,

    h_ontem.posicao AS posicao_ontem

FROM vw_ranking_executivo r
LEFT JOIN pessoas p ON p.id = r.pessoa_id
LEFT JOIN ranking_historico h7
    ON h7.pessoa_id = r.pessoa_id
   AND h7.snapshot_data = DATE_SUB(CURDATE(), INTERVAL 7 DAY)
LEFT JOIN ranking_historico h_ontem
    ON h_ontem.pessoa_id = r.pessoa_id
   AND h_ontem.snapshot_data = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
ORDER BY r.posicao ASC
";

$ranking = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

function gerarWhats(?string $telefone): ?string {
    if (!$telefone) return null;
    $n = preg_replace('/\D/','',$telefone);
    if (strlen($n) < 10) return null;
    if (!str_starts_with($n,'55')) $n = '55'.$n;
    return "https://wa.me/".$n;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Ranking Executivo</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
body{background:#f4f6f8;font-family:system-ui}

.top-bar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}

.section-title{
    margin:20px 0 10px;
    font-weight:700;
    font-size:13px;
    text-transform:uppercase;
    opacity:.6;
}

.card-base{
    background:#fff;
    border-radius:18px;
    padding:20px;
    margin-bottom:16px;
    box-shadow:0 6px 16px rgba(0,0,0,.06);
}

.podium{
    border-left:6px solid;
}

.podium-1{ border-color:#ffb40b; transform:scale(1.02); }
.podium-2{ border-color:#c0c0c0; }
.podium-3{ border-color:#b87333; }

.rank-header{
    display:flex;
    align-items:center;
    gap:10px;
}

.rank-badge{
    width:46px;
    height:46px;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:800;
    font-size:18px;
    background:#e9ecef;
    animation:pulse 2.5s infinite;
}

@keyframes pulse{
    0%{transform:scale(1)}
    50%{transform:scale(1.08)}
    100%{transform:scale(1)}
}

.rank-1{ background:#ffb40b;color:#272422; }
.rank-2{ background:#c0c0c0;color:#272422; }
.rank-3{ background:#b87333;color:#fff; }

.card-top10{
    background:linear-gradient(135deg,#0b6e7a,#169fa9);
    color:#fff;
}

.card-top10 .bi-whatsapp{
    color:#fff !important;
}

.up{color:#28a745;font-weight:700}
.down{color:#dc3545;font-weight:700}

.badge-online{
    font-size:11px;
    padding:4px 8px;
    border-radius:20px;
}
.online{background:#28a745;color:#fff}
.offline{background:#6c757d;color:#fff}

.btn-topo{
    position:fixed;
    bottom:20px;
    right:20px;
}
.card-top10 .rank-badge{
    background:rgba(255,255,255,0.95);
    color:#000;
}

</style>
</head>

<body>
<div class="container py-4">

<div class="top-bar">
<h4 class="fw-bold m-0">🏆 Ranking Executivo</h4>
<a href="/interno/admin.php" class="btn btn-outline-secondary btn-sm">
← Dashboard
</a>
</div>

<?php
$secTop10=false;
$secTop50=false;
$secOutros=false;

foreach ($ranking as $row):

$pos = (int)$row['posicao'];

if ($pos<=10 && !$secTop10){ echo '<div class="section-title">Top 10</div>'; $secTop10=true; }
if ($pos>10 && $pos<=50 && !$secTop50){ echo '<div class="section-title">Top 50</div>'; $secTop50=true; }
if ($pos>50 && !$secOutros){ echo '<div class="section-title">Outros</div>'; $secOutros=true; }

$extraClass='';
$badgeClass='';
$medalha='';

if($pos===1){ $extraClass='podium podium-1'; $badgeClass='rank-badge rank-1'; $medalha='<span style="font-size:36px;">🥇</span>'; }
elseif($pos===2){ $extraClass='podium podium-2'; $badgeClass='rank-badge rank-2'; $medalha='<span style="font-size:36px;">🥈</span>'; }
elseif($pos===3){ $extraClass='podium podium-3'; $badgeClass='rank-badge rank-3'; $medalha='<span style="font-size:36px;">🥉</span>'; }
elseif($pos<=10){ $extraClass='card-top10'; }

$nomeCompleto = trim($row['nome'] ?? '');
$apelido      = trim($row['apelido'] ?? '');
$chamarPor    = $row['chamar_por'] ?? 'nome';

if ($chamarPor === 'apelido' && !empty($apelido)) {
    $nomeExibicao = $apelido;
} else {
    $partes = preg_split('/\s+/', $nomeCompleto);
    $nomeExibicao = implode(' ', array_slice($partes, 0, 2));
}

$crescimento = (int)$row['crescimento_7d'];
$linkWhats = gerarWhats($row['telefone']);
?>

<div class="card-base <?= $extraClass ?>">
<div class="d-flex justify-content-between">

<div>

<div class="rank-header">
    <div class="<?= $badgeClass ?: 'rank-badge' ?>">
        <?= $pos ?>
    </div>
    <?= $medalha ?>
</div>

<div class="fw-bold fs-5 mt-2"><?= htmlspecialchars($nomeExibicao) ?></div>

<div>
<?= htmlspecialchars($row['bairro'] ?? '') ?>
<?php if(!empty($row['cidade'])): ?>
 - <?= htmlspecialchars($row['cidade']) ?>
<?php endif; ?>
</div>

<div>
<strong><?= (int)$row['pontos'] ?> pts</strong> |
<?= (int)$row['total_cadastrados'] ?> cadastrados
</div>

<div class="mt-1">
<?php if($crescimento>0): ?>
<span class="up">🔺 +<?= $crescimento ?> pts (7d)</span>
<?php elseif($crescimento<0): ?>
<span class="down">🔻 <?= $crescimento ?> pts (7d)</span>
<?php else: ?>
— crescimento 7d
<?php endif; ?>
</div>

</div>

<?php if($linkWhats): ?>
<a href="<?= $linkWhats ?>" target="_blank">
<i class="bi bi-whatsapp fs-1"></i>
</a>
<?php endif; ?>

</div>
</div>

<?php endforeach; ?>

</div>

<button class="btn btn-dark btn-topo"
onclick="window.scrollTo({top:0,behavior:'smooth'})">
↑ Topo
</button>

</body>
</html>