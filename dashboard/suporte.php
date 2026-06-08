<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/suporte.php
 * NOME: Suporte — Dashboard do Usuário
 * DESCRIÇÃO:
 * - Lista tickets de suporte do usuário
 * - Exibe status e SLA
 * - Botão para abrir novo suporte
 * ======================================================
 */

declare(strict_types=1);
date_default_timezone_set('America/Boa_Vista');

/* ================= SESSION ================= */
session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    header('Location: index.php');
    exit;
}

$pessoa_id = (int) $_SESSION['pessoa_id'];

/* ================= CORE ================= */
$CORE = '/home/elab/public_html/core';
require_once $CORE . '/data/config.php';
require_once $CORE . '/data/data.php';

$pdo = db();

/* ================= BUSCA TICKETS ================= */
$stmt = $pdo->prepare("
SELECT 
    p.id,
    p.assunto,
    p.status,
    p.criado_em,
    TIMESTAMPDIFF(MINUTE, p.criado_em, NOW()) AS sla_minutos
FROM protocolo p
WHERE p.pessoa_id = ?
ORDER BY p.criado_em DESC
");
$stmt->execute([$pessoa_id]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= SLA LABEL ================= */
function slaLabel(string $status, int $min): string {
    if ($status === 'concluido') return '🟢 Concluído';
    if ($min < 30) return '🟢 Dentro do SLA';
    if ($min < 120) return '🟡 Atenção';
    return '🔴 SLA Estourado';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Suporte</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="container py-4">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">🛠️ Suporte</h4>
    <a href="abrir-suporte.php" class="btn btn-sm btn-success">
        ➕ Abrir Suporte
    </a>
</div>

<?php if (!$tickets): ?>
    <p class="text-muted">Você ainda não abriu nenhum chamado.</p>
<?php endif; ?>

<div class="list-group">
<?php foreach ($tickets as $t): ?>
    <a href="suporte-ver.php?id=<?= $t['id'] ?>"
       class="list-group-item list-group-item-action">

        <div class="d-flex justify-content-between">
            <strong>🎫 Ticket #<?= $t['id'] ?></strong>
            <span><?= slaLabel($t['status'], (int)$t['sla_minutos']) ?></span>
        </div>

        <div class="small text-muted">
            <?= htmlspecialchars($t['assunto']) ?><br>
            Aberto em <?= date('d/m/Y H:i', strtotime($t['criado_em'])) ?> ·
            Status: <?= htmlspecialchars($t['status']) ?>
        </div>
    </a>
<?php endforeach; ?>
</div>

<a href="/dashboard/index.php" class="btn btn-light btn-sm mt-4">← Voltar</a>

</body>
</html>