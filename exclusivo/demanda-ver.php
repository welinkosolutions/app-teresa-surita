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

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: demandas.php');
    exit;
}

$responderAberto = isset($_GET['responder']);

/* ================= NOVA RESPOSTA ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mensagem'])) {

    $mensagem = trim($_POST['mensagem']);
    $visibilidade = $_POST['visibilidade'] ?? 'publico';

    if ($mensagem !== '') {

        $pdo->beginTransaction();

        /* ===== INSERE RESPOSTA ===== */

        $stmt = $pdo->prepare("
            INSERT INTO demandas_respostas
            (solicitacao_id,autor_tipo,autor_id,mensagem,visibilidade,tipo)
            VALUES (?,?,?,?,?,'resposta')
        ");
        $stmt->execute([$id,'admin',$pessoa_id,$mensagem,$visibilidade]);

        /* ===== BUSCA STATUS ATUAL ===== */

        $stmtAtual = $pdo->prepare("
            SELECT status, responsavel_id
            FROM solicitacoes
            WHERE id = ?
            LIMIT 1
        ");
        $stmtAtual->execute([$id]);
        $atual = $stmtAtual->fetch(PDO::FETCH_ASSOC);

        $statusAtual = $atual['status'];
        $responsavelAtual = $atual['responsavel_id'];

        /* ===== DEFINE RESPONSÁVEL SE NÃO TIVER ===== */

        if (!$responsavelAtual) {

            $stmtResp = $pdo->prepare("
                UPDATE solicitacoes
                SET responsavel_id = ?
                WHERE id = ?
            ");
            $stmtResp->execute([$pessoa_id, $id]);

            $stmtEvento = $pdo->prepare("
                INSERT INTO solicitacoes_eventos
                (solicitacao_id,tipo,valor_anterior,valor_novo,autor_id,autor_tipo)
                VALUES (?,?,?,?,?,'admin')
            ");
            $stmtEvento->execute([
                $id,
                'responsavel_assumido',
                null,
                (string)$pessoa_id,
                $pessoa_id
            ]);
        }

        /* ===== ALTERA STATUS SE ESTAVA ABERTO ===== */

        if ($statusAtual === 'aberto') {

            $stmtStatus = $pdo->prepare("
                UPDATE solicitacoes
                SET status = 'em_atendimento',
                    atualizado_em = NOW()
                WHERE id = ?
            ");
            $stmtStatus->execute([$id]);

            $stmtEvento = $pdo->prepare("
                INSERT INTO solicitacoes_eventos
                (solicitacao_id,tipo,valor_anterior,valor_novo,autor_id,autor_tipo)
                VALUES (?,?,?,?,?,'admin')
            ");
            $stmtEvento->execute([
                $id,
                'status_alterado',
                'aberto',
                'em_atendimento',
                $pessoa_id
            ]);
        }

        $pdo->commit();
    }

    header("Location: demanda-ver.php?id=".$id);
    exit;
}

/* ================= BUSCA DEMANDA ================= */

$stmt = $pdo->prepare("
SELECT 
    s.*,
    p.nome as pessoa_nome,
    p.telefone as pessoa_telefone,
    c.nome as criador_nome,
    c.telefone as criador_telefone
FROM solicitacoes s
LEFT JOIN pessoas p ON p.id = s.pessoa_id
LEFT JOIN pessoas c ON c.id = s.criado_por
WHERE s.id = ?
LIMIT 1
");
$stmt->execute([$id]);
$demanda = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$demanda) {
    header('Location: demandas.php');
    exit;
}

/* ================= FEED ================= */

$stmtFeed = $pdo->prepare("
SELECT r.*, p.nome
FROM demandas_respostas r
LEFT JOIN pessoas p ON p.id = r.autor_id
WHERE solicitacao_id = ?
ORDER BY criado_em ASC
");
$stmtFeed->execute([$id]);
$respostas = $stmtFeed->fetchAll(PDO::FETCH_ASSOC);

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
        'prioritario' => '<span class="badge bg-warning text-dark">Prioritário</span>',
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

$telefoneBase = $demanda['pessoa_telefone']
                ?? $demanda['criador_telefone'];

$whats = gerarLinkWhats($telefoneBase);

$isMensagem = ($demanda['categoria']==='conte' && empty($demanda['criado_por']));
$origemLabel = $isMensagem ? '🤍 Mensagem Direta' : '⚠️ Operacional';
$origemClass = $isMensagem ? 'bg-primary' : 'bg-dark';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Demanda</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f4f6f8;font-family:system-ui}
.card-box{background:#fff;border-radius:16px;padding:18px;margin-bottom:16px;box-shadow:0 6px 16px rgba(0,0,0,.06)}
.feed-publico{background:#e9f7ef;padding:12px;border-radius:10px}
.feed-interno{background:#fff3cd;padding:12px;border-radius:10px}
</style>
</head>
<body>

<div class="container py-4">

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="demandas.php" class="btn btn-outline-secondary btn-sm">← Voltar</a>

    <div class="d-flex gap-2">
        <a href="?id=<?= $id ?>&responder=1" class="btn btn-outline-primary btn-sm">
            Responder
        </a>

        <?php if($whats): ?>
        <a href="<?= $whats ?>" target="_blank" class="btn btn-success btn-sm">
            WhatsApp
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card-box">

<div class="d-flex justify-content-between mb-2">
    <?= badgeStatus($demanda['status']) ?>
    <?= badgePrioridade($demanda['prioridade']) ?>
</div>

<span class="badge <?= $origemClass ?> mb-2"><?= $origemLabel ?></span>

<div class="small text-muted mb-1">
Criado em <?= date('d/m/Y H:i', strtotime($demanda['criado_em'])) ?>
</div>

<?php if($demanda['pessoa_nome']): ?>
<div class="small mb-1">
Pessoa: <strong><?= htmlspecialchars($demanda['pessoa_nome']) ?></strong>
</div>
<?php endif; ?>

<?php if($demanda['criador_nome']): ?>
<div class="small mb-1">
Cadastrado por: <strong><?= htmlspecialchars($demanda['criador_nome']) ?></strong>
</div>
<?php endif; ?>

</div>

<div class="card-box">
<?= nl2br(htmlspecialchars($demanda['descricao'])) ?>
</div>

<div class="card-box">

<h6 class="fw-bold mb-3">Histórico</h6>

<?php foreach($respostas as $r): ?>

<div class="mb-3">

<div class="small text-muted mb-1">
<?= date('d/m/Y H:i', strtotime($r['criado_em'])) ?>
— <?= htmlspecialchars($r['nome'] ?? 'Sistema') ?>
</div>

<div class="<?= $r['visibilidade']=='interno'?'feed-interno':'feed-publico' ?>">
<?= nl2br(htmlspecialchars($r['mensagem'])) ?>
</div>

</div>

<?php endforeach; ?>

</div>

<?php if($responderAberto): ?>

<div class="card-box" id="form-resposta">


<div class="card-box">

<form method="POST">

<textarea name="mensagem" class="form-control mb-3" rows="3" required></textarea>

<select name="visibilidade" class="form-select mb-3">
<option value="publico">Resposta Pública</option>
<option value="interno">Nota Interna</option>
</select>

<button class="btn btn-dark">Enviar</button>

</form>

</div>

<?php endif; ?>

</div>
<?php if($responderAberto): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {

    const bloco = document.getElementById('form-resposta');
    if (bloco) {
        bloco.scrollIntoView({ behavior: 'smooth', block: 'start' });

        const textarea = bloco.querySelector('textarea');
        if (textarea) {
            setTimeout(() => textarea.focus(), 400);
        }
    }

});
</script>
<?php endif; ?>

</body>
</html>