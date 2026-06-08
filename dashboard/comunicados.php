<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/dashboard/comunicados.php
 * NOME: Comunicados – App (GERAL + PARTICULAR)
 * ======================================================
 */

declare(strict_types=1);

/* ================= ERROS (DEV) ================= */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/* ================= SESSION + TIMEZONE ================= */
date_default_timezone_set('America/Boa_Vista');
session_name('ELAB_APP_SESSION');
session_start();

/* ================= CORE ================= */
$CORE = '/home/elab/public_html/core';
require_once $CORE . '/data/config.php';
require_once $CORE . '/data/data.php';

/* ================= SEGURANÇA ================= */
if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

$pessoa_id = (int)$_SESSION['pessoa_id'];

/* ================= BANCO ================= */
$pdo = dbRoraima();

/* ================= FILTRO ================= */
$filtro = $_GET['filtro'] ?? 'todos'; // todos | nao_lidos | lidos

/* ================= BUSCA ================= */
$sql = "
SELECT
    c.id,
    c.titulo,
    c.criado_em,

    CASE
        WHEN c.publico_tipo = 'pessoa' AND c.publico_valor IS NULL THEN 'lido'
        ELSE cd.status
    END AS status

FROM comunicados c
LEFT JOIN comunicados_destinatarios cd
       ON cd.comunicado_id = c.id
      AND cd.pessoa_id = :pessoa1

WHERE
    (
        c.publico_tipo = 'pessoa'
        AND c.publico_valor IS NULL
    )
    OR
    (
        cd.pessoa_id = :pessoa2
    )
";

$params = [
    ':pessoa1' => $pessoa_id,
    ':pessoa2' => $pessoa_id
];

if ($filtro === 'nao_lidos') {
    $sql .= " AND cd.status = 'pendente' ";
} elseif ($filtro === 'lidos') {
    $sql .= " AND (
        (c.publico_tipo = 'pessoa' AND c.publico_valor IS NULL)
        OR cd.status = 'lido'
    ) ";
}

$sql .= " ORDER BY c.criado_em DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$comunicados = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= CONTADOR NÃO LIDOS ================= */
$cnt = $pdo->prepare("
    SELECT COUNT(*)
    FROM comunicados_destinatarios
    WHERE pessoa_id = :p
      AND status = 'pendente'
");
$cnt->execute([':p' => $pessoa_id]);
$naoLidos = (int)$cnt->fetchColumn();

/* ================= HELPER ================= */
function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Comunicados</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
.nao-lido{
    font-weight:600;
    background:#fff7e6;
    border-left:4px solid #ff9800;
}
.push-dot{
    width:8px;
    height:8px;
    background:#ff3b3b;
    border-radius:50%;
    display:inline-block;
    margin-right:6px;
}
</style>
</head>

<body class="container py-3">

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="/dashboard/index.php" class="btn btn-sm btn-outline-secondary">← Voltar</a>

    <h5 class="mb-0">
        📨 Comunicados
        <?php if ($naoLidos > 0): ?>
            <span class="badge bg-danger"><?= $naoLidos ?></span>
        <?php endif; ?>
    </h5>
</div>

<div class="mb-3">
    <div class="btn-group btn-group-sm">
        <a href="?filtro=todos"
           class="btn btn-outline-secondary <?= $filtro==='todos'?'active':'' ?>">
           Todos
        </a>
        <a href="?filtro=nao_lidos"
           class="btn btn-outline-warning <?= $filtro==='nao_lidos'?'active':'' ?>">
           Não lidos
        </a>
        <a href="?filtro=lidos"
           class="btn btn-outline-success <?= $filtro==='lidos'?'active':'' ?>">
           Lidos
        </a>
    </div>
</div>

<?php if (!$comunicados): ?>
    <p class="text-muted">Nenhum comunicado encontrado.</p>
<?php endif; ?>

<div class="list-group">
<?php foreach ($comunicados as $c): ?>
<a href="ver-comunicado.php?id=<?= (int)$c['id'] ?>"
   class="list-group-item list-group-item-action <?= $c['status']==='pendente'?'nao-lido':'' ?>">

    <div class="d-flex justify-content-between align-items-center">
        <div>
            <?php if ($c['status']==='pendente'): ?>
                <span class="push-dot"></span>
            <?php endif; ?>
            <?= h($c['titulo']) ?>
        </div>
    </div>

    <small class="text-muted">
        <?= date('d/m/Y H:i', strtotime($c['criado_em'])) ?>
        <?= $c['status']==='lido' ? '• Lido' : '• Novo' ?>
    </small>

</a>
<?php endforeach; ?>
</div>

</body>
</html>