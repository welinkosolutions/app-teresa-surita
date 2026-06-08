<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/dashboard/index.php
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

/* ================= CAMINHO FOTO ================= */
$fotoPath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/perfil/' . $pessoa_id . '.jpg';

/* ================= EXCLUIR FOTO ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_foto'])) {
    if (file_exists($fotoPath)) {
        unlink($fotoPath);
    }
    header('Location: perfil.php');
    exit;
}

/* ================= UPLOAD FOTO ================= */
if (!empty($_FILES['foto']['tmp_name'])) {
    $dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/perfil';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    move_uploaded_file($_FILES['foto']['tmp_name'], $dir.'/'.$pessoa_id.'.jpg');
    header('Location: perfil.php');
    exit;
}

/* ================= ATUALIZAR CHAMAR_POR ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chamar_por'])) {
    if (in_array($_POST['chamar_por'], ['nome','apelido'], true)) {
        $pdo->prepare("
            UPDATE pessoas 
            SET chamar_por = ?, atualizado_em = NOW()
            WHERE id = ?
        ")->execute([$_POST['chamar_por'], $pessoa_id]);
    }
    header('Location: perfil.php');
    exit;
}

/* ================= BUSCAR DADOS ================= */
$stmt = $pdo->prepare("
    SELECT 
        p.nome,
        p.apelido,
        p.chamar_por,
        p.telefone,
        p.email,
        p.nome_mae,
        p.data_nascimento,
        p.instagram,
        p.facebook,
        p.criado_por,
        e.endereco,
        e.numero,
        e.bairro,
        e.cidade,
        e.estado,
        e.cep,
        ind.nome AS indicador_nome,
        ind.apelido AS indicador_apelido,
        ind.chamar_por AS indicador_chamar_por
    FROM pessoas p
    LEFT JOIN pessoas_enderecos e ON e.pessoa_id = p.id
    LEFT JOIN pessoas ind ON ind.id = p.criado_por
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$pessoa_id]);
$pessoa = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= NOME EXIBIDO ================= */
$nomeExibido = $pessoa['nome'];
if ($pessoa['chamar_por'] === 'apelido' && !empty($pessoa['apelido'])) {
    $nomeExibido = $pessoa['apelido'];
}

/* ================= INDICADOR ================= */
$indicador = 'Cadastro direto';
if ($pessoa['indicador_nome']) {
    $indicador = $pessoa['indicador_chamar_por']==='apelido' && $pessoa['indicador_apelido']
        ? $pessoa['indicador_apelido']
        : $pessoa['indicador_nome'];
}

/* ================= FOTO / INICIAIS ================= */
$foto = "/uploads/perfil/{$pessoa_id}.jpg";
$fotoExiste = file_exists($_SERVER['DOCUMENT_ROOT'].$foto);

$iniciais = '';
foreach (explode(' ', $nomeExibido) as $p) {
    $iniciais .= mb_strtoupper(mb_substr($p,0,1));
    if (mb_strlen($iniciais)>=2) break;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Meu Perfil</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{background:#f4f6f8;font-family:system-ui}
.app{max-width:420px;margin:0 auto;padding:12px}
.cardx{background:#fff;border-radius:14px;padding:16px;margin-bottom:14px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.center{text-align:center}
.avatar{width:110px;height:110px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#128C7E;color:#fff;font-size:2rem;font-weight:700;margin:0 auto 8px;cursor:pointer;position:relative}
.avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.avatar .del{position:absolute;bottom:-6px;right:-6px;background:#dc3545;color:#fff;border-radius:50%;width:28px;height:28px;font-size:14px;display:flex;align-items:center;justify-content:center;border:none}
.title{font-weight:600;font-size:1.2rem}
.muted{font-size:.85rem;color:#6c757d}
.rowx{font-size:.95rem;margin-bottom:6px}
.btnx{display:block;width:100%;padding:12px;border-radius:10px;text-align:center;font-weight:600;text-decoration:none;margin-bottom:8px}
.btn-main{background:#128C7E;color:#fff}
.btn-out{border:2px solid #128C7E;color:#128C7E}
.toggle{display:flex;gap:8px}
.toggle button{flex:1;border-radius:20px;padding:6px;border:1px solid #ccc;background:#eee}
.toggle .on{background:#128C7E;color:#fff;border-color:#128C7E}
</style>
</head>

<body>
<div class="app">

<!-- FOTO / UPLOAD -->
<form method="post" enctype="multipart/form-data" class="center">
<label class="avatar">
<?php if($fotoExiste): ?>
    <img src="<?= $foto ?>">
    <button type="submit" name="excluir_foto" class="del">✕</button>
<?php else: ?>
    <?= $iniciais ?>
<?php endif; ?>
<input type="file" name="foto" accept="image/*" hidden onchange="this.form.submit()">
</label>
</form>

<div class="cardx center">
    <div class="title"><?= htmlspecialchars($nomeExibido) ?></div>
    <div class="muted">Indicado por: <?= htmlspecialchars($indicador) ?></div>
</div>

<div class="cardx">
<strong>Como quer ser chamado</strong>
<form method="post" class="toggle mt-2">
<button name="chamar_por" value="nome" class="<?= $pessoa['chamar_por']==='nome'?'on':'' ?>">Nome</button>
<button name="chamar_por" value="apelido" class="<?= $pessoa['chamar_por']==='apelido'?'on':'' ?>">Apelido</button>
</form>
</div>

<div class="cardx">
<div class="rowx"><strong>Nome da mãe:</strong> <?= htmlspecialchars($pessoa['nome_mae'] ?? '-') ?></div>
<div class="rowx"><strong>Nascimento:</strong> <?= $pessoa['data_nascimento'] ? date('d/m/Y',strtotime($pessoa['data_nascimento'])) : '-' ?></div>
</div>

<div class="cardx">
<strong>Contatos</strong>
<div class="rowx">📞 <?= htmlspecialchars($pessoa['telefone']) ?></div>
<div class="rowx">✉️ <?= htmlspecialchars($pessoa['email']) ?></div>
</div>

<div class="cardx">
<strong>Redes sociais</strong>
<div class="rowx">📸 <?= $pessoa['instagram'] ?: '-' ?></div>
<div class="rowx">📘 <?= $pessoa['facebook'] ?: '-' ?></div>
</div>

<div class="cardx">
<strong>Endereço</strong>
<?php if($pessoa['endereco']): ?>
<div class="rowx"><?= htmlspecialchars($pessoa['endereco']) ?>, <?= htmlspecialchars($pessoa['numero']) ?></div>
<div class="rowx"><?= htmlspecialchars($pessoa['bairro']) ?> — <?= htmlspecialchars($pessoa['cidade']) ?>/<?= htmlspecialchars($pessoa['estado']) ?></div>
<div class="rowx"><strong>CEP:</strong> <?= htmlspecialchars($pessoa['cep']) ?></div>
<?php else: ?>
<div class="muted">Endereço não cadastrado</div>
<?php endif; ?>
</div>

<div class="cardx">
<a href="/pessoas/editar.php" class="btnx btn-main">Editar dados</a>
<a href="editar-endereco.php" class="btnx btn-main">Editar endereço</a>
<a href="/pessoas/lider.php" class="btnx btn-out">Solicitar troca de líder</a>
<a href="/dashboard/index.php" class="btnx btn-main">VOLTAR</a>
</div>

</div>
</body>
</html>