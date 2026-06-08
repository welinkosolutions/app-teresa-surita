<?php
/**
 * ============================================================
 * CAMINHO: app.elab.social/criar-grupo.php
 * NOME: Criar Grupo WhatsApp (FINAL COM RETORNO VISUAL)
 * DESCRIÇÃO:
 * - Cria grupo no banco (garantido)
 * - Dispara criação no ChatPro (best effort)
 * - UX clara com progresso
 * - Finaliza pedindo ativação manual do link
 * ============================================================
 */

declare(strict_types=1);

/* ================= ERROS (DEV) ================= */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/* ================= SESSION ================= */
date_default_timezone_set('America/Boa_Vista');
session_name('ELAB_APP_SESSION');
session_start();

/* ================= SEGURANÇA ================= */
if (empty($_SESSION['pessoa_id'])) {
    header('Location: index.php');
    exit;
}

$pessoaId = (int) $_SESSION['pessoa_id'];

/* ================= CORE ================= */
$CORE = '/home/elab/public_html/core';
require_once $CORE . '/data/config.php';
require_once $CORE . '/data/data.php';
require_once $CORE . '/chatpro/core.php';

/* ================= LOG ================= */
$LOG_PATH = '/home/elab/logs/criar-grupo-error.log';
function logErro(string $msg): void {
    global $LOG_PATH;
    file_put_contents($LOG_PATH, date('Y-m-d H:i:s').' | '.$msg.PHP_EOL, FILE_APPEND);
}

/* ================= PDO ================= */
$pdo = db();

/* ================= PROCESSO ================= */
$mensagens = [];
$erro = null;

try {

    $mensagens[] = 'Iniciando criação do grupo…';

    /* ===== USUÁRIO ===== */
    $stmt = $pdo->prepare("
        SELECT id, nome
        FROM pessoas
        WHERE id = :id AND status = 'ativo'
        LIMIT 1
    ");
    $stmt->execute([':id' => $pessoaId]);
    $pessoa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pessoa) {
        throw new Exception('Usuário inválido');
    }

    /* ===== BLOQUEAR DUPLICIDADE ===== */
    $stmt = $pdo->prepare("
        SELECT id FROM whatsapp_grupos WHERE pessoa_id = :pid LIMIT 1
    ");
    $stmt->execute([':pid' => $pessoaId]);
    if ($stmt->fetch()) {
        throw new Exception('Você já possui um grupo criado');
    }

    /* ===== INDICADOS ===== */
    $mensagens[] = 'Buscando indicados com WhatsApp…';

    $stmt = $pdo->prepare("
        SELECT telefone
        FROM pessoas
        WHERE criado_por = :pid
        AND status = 'ativo'
        AND telefone IS NOT NULL
    ");
    $stmt->execute([':pid' => $pessoaId]);
    $telefones = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$telefones) {
        throw new Exception('Nenhum indicado com WhatsApp válido');
    }

    /* ===== REGISTRO NO BANCO ===== */
    $mensagens[] = 'Registrando grupo no sistema…';

    $nomeGrupo = 'Grupo do '.$pessoa['nome'].' – Teresa Surita';

    $stmt = $pdo->prepare("
        INSERT INTO whatsapp_grupos
        (pessoa_id, link, grupo_jid, status, criado_por, bloqueado)
        VALUES (:pid, '', NULL, 'ativo', 'usuario', 'nao')
    ");
    $stmt->execute([':pid' => $pessoaId]);
    $grupoId = (int)$pdo->lastInsertId();

    /* ===== CHATPRO (BEST EFFORT) ===== */
    $mensagens[] = 'Criando grupo no WhatsApp…';

    try {
        $resultado = chatpro_create_group($nomeGrupo, $telefones);

        if (!empty($resultado['id'])) {
            $jid  = $resultado['id'];
            $link = !empty($resultado['inviteCode'])
                ? 'https://chat.whatsapp.com/'.$resultado['inviteCode']
                : '';

            $stmt = $pdo->prepare("
                UPDATE whatsapp_grupos
                SET grupo_jid = :jid, link = :link
                WHERE id = :id
            ");
            $stmt->execute([
                ':jid' => $jid,
                ':link' => $link,
                ':id' => $grupoId
            ]);
        }

    } catch (Throwable $e) {
        logErro('ChatPro erro/timeout: '.$e->getMessage());
    }

    $mensagens[] = 'Grupo criado com sucesso!';

} catch (Throwable $e) {
    $erro = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Criando Grupo</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#f4f6f9;
    font-family:system-ui,-apple-system,BlinkMacSystemFont,sans-serif;
}
.card{
    max-width:520px;
    margin:60px auto;
    border-radius:16px;
    box-shadow:0 6px 20px rgba(0,0,0,.08);
}
.step{
    display:none;
}
.step.show{
    display:block;
}
</style>
</head>
<body>

<div class="card">
<div class="card-body text-center">

<?php if ($erro): ?>

    <div class="alert alert-danger">
        ❌ <?php echo htmlspecialchars($erro); ?>
    </div>

    <a href="meu-grupo.php" class="btn btn-secondary">
        Voltar
    </a>

<?php else: ?>

    <h5 class="mb-3">Criando seu grupo</h5>

    <div class="progress mb-4">
        <div id="progressBar"
             class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
             style="width:0%"></div>
    </div>

    <ul class="list-group text-start mb-4">
        <?php foreach ($mensagens as $i => $m): ?>
            <li class="list-group-item step" id="step-<?php echo $i; ?>">
                ✔ <?php echo htmlspecialchars($m); ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <div id="finalMsg" class="alert alert-info d-none">
        Para receber o <strong>selo de Grupo Oficial Teresa Surita</strong>,<br>
        ative seu grupo colando o link oficial.
    </div>

    <a id="btnFinal"
       href="/dashboard/meu-grupo.php"
       class="btn btn-primary btn-lg d-none">
        Gerenciar Meu Grupo!
    </a>

<?php endif; ?>

</div>
</div>

<script>
const steps = document.querySelectorAll('.step');
const progress = document.getElementById('progressBar');
let current = 0;

function showNextStep(){
    if(current < steps.length){
        steps[current].classList.add('show');
        progress.style.width = ((current+1)/steps.length*100) + '%';
        current++;
        setTimeout(showNextStep, 700);
    } else {
        // FINAL → SUCESSO VISUAL
        progress.classList.remove(
            'progress-bar-striped',
            'progress-bar-animated',
            'bg-primary'
        );
        progress.classList.add('bg-success');

        document.getElementById('finalMsg')?.classList.remove('d-none');
        document.getElementById('btnFinal')?.classList.remove('d-none');
    }
}

showNextStep();
</script>

</body>
</html>