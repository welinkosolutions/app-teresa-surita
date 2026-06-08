<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/dashboard/alterar-lider.php
 * NOME: Dashboard App – Wrapper de Fluxo
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
    header('Location: index.php');
    exit;
}

$pessoa_id = (int) $_SESSION['pessoa_id'];

/* ================= BANCO ================= */
$pdo = dbRoraima();

/* ================= CANCELAR SOLICITAÇÃO ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar_id'])) {
    $id = (int) $_POST['cancelar_id'];

    $pdo->prepare("
        UPDATE solicitacoes
        SET status = 'cancelado',
            atualizado_em = NOW()
        WHERE id = ?
          AND pessoa_id = ?
          AND status IN ('aberto','andamento')
    ")->execute([$id, $pessoa_id]);

    header('Location: alterar-lider.php');
    exit;
}

/* ================= BUSCAR SOLICITAÇÃO EXISTENTE ================= */
$stmtSolic = $pdo->prepare("
    SELECT id, descricao, status, criado_em
    FROM solicitacoes
    WHERE pessoa_id = ?
      AND tipo = 'troca_lider'
      AND status IN ('aberto','andamento')
    ORDER BY criado_em DESC
    LIMIT 1
");
$stmtSolic->execute([$pessoa_id]);
$solicitacaoAtual = $stmtSolic->fetch(PDO::FETCH_ASSOC);

/* ================= SALVAR SOLICITAÇÃO ================= */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['novo_lider_id'])
    && !$solicitacaoAtual
) {
    $novo_lider = (int) $_POST['novo_lider_id'];

    $pdo->prepare("
        INSERT INTO solicitacoes
            (pessoa_id, tipo, categoria, descricao, status, criado_em)
        VALUES
            (?, 'troca_lider', 'lideranca', ?, 'aberto', NOW())
    ")->execute([
        $pessoa_id,
        'Solicitação de troca de líder para ID '.$novo_lider
    ]);

    header('Location: alterar-lider.php');
    exit;
}

/* ================= BUSCA ================= */
$busca = trim($_GET['q'] ?? '');
$resultados = [];

if ($busca && !$solicitacaoAtual) {
    $stmt = $pdo->prepare("
        SELECT id, nome, apelido, chamar_por
        FROM pessoas
        WHERE status = 'ativo'
          AND perfil IN ('lider','agente','chefe')
          AND (nome LIKE ? OR apelido LIKE ?)
        ORDER BY nome
        LIMIT 20
    ");
    $stmt->execute(["%$busca%","%$busca%"]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Troca de Líder</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{background:#f4f6f8;font-family:system-ui}
.app{max-width:420px;margin:0 auto;padding:12px}
.cardx{background:#fff;border-radius:14px;padding:16px;margin-bottom:14px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.rowx{font-size:.95rem;margin-bottom:6px}
.btnx{display:block;width:100%;padding:12px;border-radius:10px;text-align:center;font-weight:600;text-decoration:none;margin-bottom:8px}
.btn-main{background:#128C7E;color:#fff}
.btn-out{border:2px solid #128C7E;color:#128C7E}
.btn-danger{background:#dc3545;color:#fff}
input{border-radius:10px}
.badge{font-size:.75rem}
</style>
</head>

<body>
<div class="app">

<div class="cardx">
<strong>Solicitar troca de líder</strong>
<p class="text-muted small">
Escolha a pessoa para quem deseja ser vinculado.  
A solicitação será analisada pela coordenação.
</p>
</div>

<?php if($solicitacaoAtual): ?>
<div class="cardx">
<strong>Solicitação em andamento</strong>

<div class="rowx">
<strong>Status:</strong>
<span class="badge bg-warning text-dark">
<?= htmlspecialchars($solicitacaoAtual['status']) ?>
</span>
</div>

<div class="rowx text-muted small">
<?= htmlspecialchars($solicitacaoAtual['descricao']) ?>
</div>

<form method="post" class="mt-3">
<input type="hidden" name="cancelar_id" value="<?= $solicitacaoAtual['id'] ?>">
<button class="btnx btn-danger">Cancelar solicitação</button>
</form>
</div>
<?php endif; ?>

<?php if(!$solicitacaoAtual): ?>
<div class="cardx">
<form method="get">
<input type="text" name="q" class="form-control mb-2"
       placeholder="Buscar líder pelo nome ou apelido"
       value="<?= htmlspecialchars($busca) ?>">
<button class="btnx btn-main">Buscar</button>
</form>
</div>

<?php if($resultados): ?>
<div class="cardx">
<strong>Resultados</strong>
<?php foreach($resultados as $r):
    $nome = $r['chamar_por']==='apelido' && $r['apelido']
        ? $r['apelido']
        : $r['nome'];
?>
<form method="post">
<input type="hidden" name="novo_lider_id" value="<?= $r['id'] ?>">
<div class="rowx"><?= htmlspecialchars($nome) ?></div>
<button class="btnx btn-out">Solicitar vínculo</button>
</form>
<hr>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="cardx">
<a href="perfil.php" class="btnx btn-main">Voltar</a>
</div>

</div>
</body>
</html>