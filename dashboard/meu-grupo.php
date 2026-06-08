<?php
/**
 * ============================================================
 * CAMINHO: app.elab.social/meu-grupo.php
 * NOME: Meu Grupo – Dashboard WhatsApp (FINAL OFICIAL)
 * DESCRIÇÃO:
 * - Sem grupo → criar grupo
 * - Grupo sem link → ativação manual (selo oficial)
 * - Grupo oficial → operar + KPIs
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

/* ================= PDO ================= */
$pdo = db();

/* ================= USUÁRIO ================= */
$stmt = $pdo->prepare("
    SELECT id, nome
    FROM pessoas
    WHERE id = :id
    AND status = 'ativo'
    LIMIT 1
");
$stmt->execute([':id' => $pessoaId]);
$pessoa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pessoa) {
    exit('Usuário inválido');
}

/* ================= GRUPO ================= */
$stmt = $pdo->prepare("
    SELECT *
    FROM whatsapp_grupos
    WHERE pessoa_id = :pid
    LIMIT 1
");
$stmt->execute([':pid' => $pessoaId]);
$grupo = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= KPIs ================= */
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM pessoas
    WHERE criado_por = :pid
    AND status = 'ativo'
");
$stmt->execute([':pid' => $pessoaId]);
$totalIndicados = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM pessoas
    WHERE criado_por = :pid
    AND status = 'ativo'
    AND telefone IS NOT NULL
");
$stmt->execute([':pid' => $pessoaId]);
$comWhatsapp = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM pessoas
    WHERE criado_por = :pid
    AND status = 'ativo'
    AND ultimo_ping >= NOW() - INTERVAL 24 HOUR
");
$stmt->execute([':pid' => $pessoaId]);
$ativos24h = (int) $stmt->fetchColumn();

$potencial = max(0, $totalIndicados - $comWhatsapp);

/* ================= CONVITE ================= */
$linkConvite = ($grupo && !empty($grupo['link']))
    ? 'https://wa.me/?text=' . urlencode(
        "Oi! Estou te convidando para o meu grupo oficial da Teresa Surita 👇\n\n" .
        $grupo['link']
      )
    : '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Meu Grupo</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#f4f6f9;
    font-family:system-ui,-apple-system,BlinkMacSystemFont,sans-serif;
}
.card{
    border-radius:16px;
    box-shadow:0 6px 20px rgba(0,0,0,.08);
}
.kpi{
    font-size:24px;
    font-weight:700;
}
.kpi-label{
    font-size:12px;
    color:#777;
}
.btn-app{
    border-radius:12px;
    font-weight:600;
}
.badge-official{
    background:#0d6efd;
}
</style>
</head>
<body>

<div class="container py-4">

<h4 class="mb-3">👥 Meu Grupo</h4>

<?php if (!$grupo): ?>

    <!-- ================= SEM GRUPO ================= -->
    <div class="card mb-4">
        <div class="card-body text-center">
            <h5 class="mb-3">Você ainda não tem um grupo</h5>
            <p class="text-muted mb-4">
                Crie seu grupo oficial para conectar seus indicados no WhatsApp.
            </p>
            <a href="iniciar-criacao-grupo.php" class="btn btn-success">
                ➕ Criar meu grupo
            </a>
        </div>
    </div>

<?php elseif (empty($grupo['link'])): ?>

    <!-- ================= GRUPO PENDENTE ================= -->
    <div class="card mb-4">
        <div class="card-body text-center">

            <h5 class="mb-2">
                Grupo do <?php echo htmlspecialchars($pessoa['nome']); ?> – Teresa Surita
            </h5>

            <span class="badge bg-warning text-dark mb-3">
                ⏳ Grupo criado – pendente de ativação
            </span>

            <p class="text-muted mt-3">
                O WhatsApp não permite recuperar automaticamente o link do grupo.<br>
                Para receber o <strong>selo oficial da Teresa Surita</strong>,
                cole manualmente o link do grupo.
            </p>

            <a href="atualizar-grupo.php" class="btn btn-primary btn-app mt-2">
                🔗 Ativar grupo oficial
            </a>

        </div>
    </div>

<?php else: ?>

    <!-- ================= GRUPO OFICIAL ================= -->
    <div class="card mb-4">
        <div class="card-body text-center">

            <h5 class="mb-2">
                Grupo do <?php echo htmlspecialchars($pessoa['nome']); ?> – Teresa Surita
            </h5>

            <span class="badge badge-official mb-3">
                ✅ Grupo oficial Teresa Surita
            </span>

            <div class="d-grid gap-2 mt-3">
                <a href="<?php echo htmlspecialchars($grupo['link']); ?>" target="_blank"
                   class="btn btn-primary btn-app">
                    🔗 Abrir grupo
                </a>

                <a href="<?php echo $linkConvite; ?>" target="_blank"
                   class="btn btn-success btn-app">
                    ➕ Convidar pessoas
                </a>

                <a href="<?php echo $linkConvite; ?>" target="_blank"
                   class="btn btn-outline-success btn-app">
                    🔁 Reenviar convite
                </a>

                <button class="btn btn-outline-danger btn-app"
                        onclick="confirmarEncerrar()">
                    ⛔ Encerrar grupo
                </button>
            </div>

        </div>
    </div>

<?php endif; ?>

<!-- ================= KPIs ================= -->
<div class="row g-3 text-center mb-4">

    <div class="col-3">
        <div class="card"><div class="card-body">
            <div class="kpi"><?php echo $totalIndicados; ?></div>
            <div class="kpi-label">Indicados</div>
        </div></div>
    </div>

    <div class="col-3">
        <div class="card"><div class="card-body">
            <div class="kpi"><?php echo $comWhatsapp; ?></div>
            <div class="kpi-label">Com WhatsApp</div>
        </div></div>
    </div>

    <div class="col-3">
        <div class="card"><div class="card-body">
            <div class="kpi"><?php echo $ativos24h; ?></div>
            <div class="kpi-label">Ativos 24h</div>
        </div></div>
    </div>

    <div class="col-3">
        <div class="card"><div class="card-body">
            <div class="kpi text-warning"><?php echo $potencial; ?></div>
            <div class="kpi-label">Potencial</div>
        </div></div>
    </div>

</div>

<div class="d-grid">
    <a href="/dashboard/index.php" class="btn btn-secondary btn-app">
        ⬅️ Voltar ao app
    </a>
</div>

</div>

<script>
function confirmarEncerrar(){
    if(!confirm('Tem certeza que deseja ENCERRAR este grupo? Essa ação não pode ser desfeita.')){
        return;
    }
    fetch('encerrar-grupo.php', { method:'POST' })
        .then(() => location.reload());
}
</script>

</body>
</html>