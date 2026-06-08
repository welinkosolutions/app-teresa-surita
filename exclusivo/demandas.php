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

/* ================= FILTROS ================= */

$statusFiltro = $_GET['status'] ?? 'todas';
$origemFiltro = $_GET['origem'] ?? 'todas';

$whereParts = [];
$params = [];

if (in_array($statusFiltro, ['aberto','em_atendimento','atendido'], true)) {
    $whereParts[] = "s.status = ?";
    $params[] = $statusFiltro;
}

if ($origemFiltro === 'mensagens') {
    $whereParts[] = "s.categoria = 'conte' AND s.criado_por IS NULL";
}
elseif ($origemFiltro === 'operacionais') {
    $whereParts[] = "NOT (s.categoria = 'conte' AND s.criado_por IS NULL)";
}

$where = $whereParts ? 'WHERE '.implode(' AND ', $whereParts) : '';

/* ================= CONTADORES ================= */

$totais = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(status='aberto') as aberto,
        SUM(status='em_atendimento') as atendimento,
        SUM(status='atendido') as atendido,
        SUM(categoria='conte' AND criado_por IS NULL) as mensagens,
        SUM(NOT (categoria='conte' AND criado_por IS NULL)) as operacionais
    FROM solicitacoes
")->fetch(PDO::FETCH_ASSOC);

/* ================= LISTAGEM ================= */

$limit = 30;
$offset = (int)($_GET['offset'] ?? 0);

$sql = "
SELECT 
    s.*,

    p.id as pessoa_id_real,
    p.nome as pessoa_nome,
    p.telefone as pessoa_telefone,

    c.id as criador_id,
    c.nome as criador_nome,
    c.telefone as criador_telefone

FROM solicitacoes s
LEFT JOIN pessoas p ON p.id = s.pessoa_id
LEFT JOIN pessoas c ON c.id = s.criado_por
$where
ORDER BY 
    FIELD(s.prioridade,'urgente','alta','normal') ASC,
    s.criado_em DESC
LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$demandas = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= HELPERS ================= */

function badgeStatus($status){
    return match($status){
        'aberto' => '<span class="badge bg-danger">Aberto</span>',
        'em_atendimento' => '<span class="badge bg-warning text-dark">Em Atendimento</span>',
        'atendido' => '<span class="badge bg-success">Atendido</span>',
        default => '<span class="badge bg-secondary">Respondida</span>'
    };
}

function badgePrioridade($prioridade){
    return match($prioridade){
        'urgente' => '<span class="badge bg-danger">Urgente</span>',
        'alta' => '<span class="badge bg-warning text-dark">Alta</span>',
        default => '<span class="badge bg-secondary">Normal</span>'
    };
}

function gerarLinkWhats(?string $telefone): ?string {
    if(!$telefone) return null;
    $n = preg_replace('/\D/','',$telefone);
    if(strlen($n) < 10) return null;
    if(!str_starts_with($n,'55')) $n = '55'.$n;
    return "https://wa.me/".$n;
}

function extrairTelefoneDescricao(string $texto): ?string {
    if(preg_match('/(?<!\d)(\+?55?\s*\(?\d{2}\)?\s*\d{4,5}[-\s]?\d{4})(?!\d)/',$texto,$m)){
        return $m[1];
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Demandas – Exclusivo</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f4f6f8;font-family:system-ui}
.top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.card-demanda{background:#fff;border-radius:16px;padding:18px;margin-bottom:16px;box-shadow:0 6px 16px rgba(0,0,0,.06)}
.filtro-btn{border-radius:30px}
.origem-badge{font-size:11px}
</style>
</head>

<body>
<div class="container py-4">

<div class="top-bar">
    <h4 class="fw-bold m-0">🛟 Demandas</h4>
    <a href="/exclusivo/index.php" class="btn btn-outline-secondary btn-sm">← Painel</a>
</div>

<!-- FILTRO STATUS -->
<div class="d-flex gap-2 flex-wrap mb-3">

<a href="?status=aberto&origem=<?= $origemFiltro ?>"
   class="btn btn-sm <?= $statusFiltro==='aberto'?'btn-danger':'btn-outline-danger' ?>">
Abertas (<?= (int)$totais['aberto'] ?>)
</a>

<a href="?status=em_atendimento&origem=<?= $origemFiltro ?>"
   class="btn btn-sm <?= $statusFiltro==='em_atendimento'?'btn-warning':'btn-outline-warning' ?>">
Atendimento (<?= (int)$totais['atendimento'] ?>)
</a>

<a href="?status=atendido&origem=<?= $origemFiltro ?>"
   class="btn btn-sm <?= $statusFiltro==='atendido'?'btn-success':'btn-outline-success' ?>">
Atendidas (<?= (int)$totais['atendido'] ?>)
</a>

</div>

<!-- FILTRO ORIGEM -->
<div class="d-flex gap-2 flex-wrap mb-4">

<a href="?status=<?= $statusFiltro ?>&origem=todas"
   class="btn btn-sm <?= $origemFiltro==='todas'?'btn-secondary':'btn-outline-secondary' ?>">
Todas
</a>

<a href="?status=<?= $statusFiltro ?>&origem=mensagens"
   class="btn btn-sm <?= $origemFiltro==='mensagens'?'btn-primary':'btn-outline-primary' ?>">
❤️ Mensagens (<?= (int)$totais['mensagens'] ?>)
</a>

<a href="?status=<?= $statusFiltro ?>&origem=operacionais"
   class="btn btn-sm <?= $origemFiltro==='operacionais'?'btn-dark':'btn-outline-dark' ?>">
🛟 Operacionais (<?= (int)$totais['operacionais'] ?>)
</a>

</div>

<?php foreach($demandas as $d): 

$isMensagem = ($d['categoria']==='conte' && empty($d['criado_por']));
$origemLabel = $isMensagem ? '🤍 Mensagem Direta' : '⚠️ Operacional';
$origemClass = $isMensagem ? 'bg-primary' : 'bg-dark';

/* ===== TELEFONE INTELIGENTE ===== */

$telefoneBase = null;

if ($d['pessoa_telefone']) {
    $telefoneBase = $d['pessoa_telefone'];
}
elseif ($d['criador_telefone']) {
    $telefoneBase = $d['criador_telefone'];
}
else {
    $telefoneBase = extrairTelefoneDescricao($d['descricao']);
}

$whats = gerarLinkWhats($telefoneBase);

?>

<div class="card-demanda">

<div class="d-flex justify-content-between mb-2">
    <?= badgeStatus($d['status']) ?>
    <?= badgePrioridade($d['prioridade']) ?>
</div>

<div class="mb-2">
    <span class="badge <?= $origemClass ?> origem-badge"><?= $origemLabel ?></span>
</div>

<div class="text-muted small mb-1">
<?= date('d/m/Y H:i', strtotime($d['criado_em'])) ?>
</div>

<div class="small mb-2 text-muted">
<?php if($d['pessoa_nome']): ?>
Pessoa: <strong><?= htmlspecialchars($d['pessoa_nome']) ?></strong>
<?php endif; ?>

<?php if($d['criador_nome']): ?>
<?php if($d['pessoa_nome']) echo ' | '; ?>
Cadastrado por: <strong><?= htmlspecialchars($d['criador_nome']) ?></strong>
<?php endif; ?>
</div>

<div class="mb-3">
<?= nl2br(htmlspecialchars($d['descricao'])) ?>
</div>

<div class="d-flex gap-2 flex-wrap">

<a href="/exclusivo/demanda-ver.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-dark">
Ver Demanda
</a>

<a href="/exclusivo/demanda-ver.php?id=<?= $d['id'] ?>&responder=1" class="btn btn-sm btn-outline-primary">
Responder
</a>

<?php if($whats): ?>
<a href="<?= $whats ?>" target="_blank" class="btn btn-sm btn-success">
WhatsApp
</a>
<?php endif; ?>

</div>

</div>

<?php endforeach; ?>

</div>
</body>
</html>