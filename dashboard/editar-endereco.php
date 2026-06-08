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

/* ================= SALVAR ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $cep = preg_replace('/\D+/', '', $_POST['cep']);

    $existe = $pdo->prepare("SELECT id FROM pessoas_enderecos WHERE pessoa_id = ? LIMIT 1");
    $existe->execute([$pessoa_id]);
    $enderecoId = $existe->fetchColumn();

    if ($enderecoId) {
        $pdo->prepare("
            UPDATE pessoas_enderecos SET
                cep = ?,
                endereco = ?,
                numero = ?,
                complemento = ?,
                bairro = ?,
                cidade = ?,
                estado = ?
            WHERE pessoa_id = ?
        ")->execute([
            $cep,
            trim($_POST['endereco']),
            trim($_POST['numero']),
            trim($_POST['complemento']),
            trim($_POST['bairro']),
            trim($_POST['cidade']),
            trim($_POST['estado']),
            $pessoa_id
        ]);
    } else {
        $pdo->prepare("
            INSERT INTO pessoas_enderecos
                (pessoa_id, cep, endereco, numero, complemento, bairro, cidade, estado)
            VALUES (?,?,?,?,?,?,?,?)
        ")->execute([
            $pessoa_id,
            $cep,
            trim($_POST['endereco']),
            trim($_POST['numero']),
            trim($_POST['complemento']),
            trim($_POST['bairro']),
            trim($_POST['cidade']),
            trim($_POST['estado'])
        ]);
    }

    header('Location: perfil.php');
    exit;
}

/* ================= BUSCAR ENDEREÇO ================= */
$stmt = $pdo->prepare("
    SELECT cep, endereco, numero, complemento, bairro, cidade, estado
    FROM pessoas_enderecos
    WHERE pessoa_id = ?
    LIMIT 1
");
$stmt->execute([$pessoa_id]);
$end = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= CEP FORMATADO ================= */
$cepFmt = '';
if (!empty($end['cep'])) {
    $c = preg_replace('/\D+/', '', $end['cep']);
    if (strlen($c) === 8) {
        $cepFmt = substr($c,0,5).'-'.substr($c,5);
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Editar Endereço</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{background:#f4f6f8;font-family:system-ui}
.app{max-width:420px;margin:0 auto;padding:12px}
.cardx{background:#fff;border-radius:14px;padding:16px;margin-bottom:14px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.title{font-weight:600;font-size:1.1rem;margin-bottom:10px}
label{font-size:.85rem;font-weight:600;margin-bottom:4px}
input{border-radius:10px}
.btnx{display:block;width:100%;padding:12px;border-radius:10px;text-align:center;font-weight:600;text-decoration:none;margin-bottom:8px}
.btn-main{background:#128C7E;color:#fff;border:none}
.btn-out{border:2px solid #128C7E;color:#128C7E;background:#fff}
.loading{opacity:.6}
</style>
</head>

<body>
<div class="app">

<form method="post" autocomplete="off">

<div class="cardx">
<div class="title">Endereço</div>

<div class="mb-2">
<label>CEP</label>
<input type="text" name="cep" id="cep" class="form-control"
       inputmode="numeric"
       placeholder="00000-000"
       value="<?= htmlspecialchars($cepFmt) ?>">
</div>

<div class="mb-2">
<label>Endereço</label>
<input type="text" name="endereco" id="endereco" class="form-control"
       value="<?= htmlspecialchars($end['endereco'] ?? '') ?>" required>
</div>

<div class="mb-2">
<label>Número</label>
<input type="text" name="numero" class="form-control"
       value="<?= htmlspecialchars($end['numero'] ?? '') ?>">
</div>

<div class="mb-2">
<label>Complemento</label>
<input type="text" name="complemento" class="form-control"
       value="<?= htmlspecialchars($end['complemento'] ?? '') ?>">
</div>

<div class="mb-2">
<label>Bairro</label>
<input type="text" name="bairro" id="bairro" class="form-control"
       value="<?= htmlspecialchars($end['bairro'] ?? '') ?>">
</div>

<div class="mb-2">
<label>Cidade</label>
<input type="text" name="cidade" id="cidade" class="form-control"
       value="<?= htmlspecialchars($end['cidade'] ?? 'Boa Vista') ?>">
</div>

<div class="mb-2">
<label>Estado</label>
<input type="text" name="estado" id="estado" class="form-control"
       maxlength="2"
       value="<?= htmlspecialchars($end['estado'] ?? 'RR') ?>">
</div>

</div>

<div class="cardx">
<button type="submit" class="btnx btn-main">Salvar endereço</button>
<a href="perfil.php" class="btnx btn-out">Cancelar</a>
</div>

</form>

</div>

<script>
/* ================= MÁSCARA + VIA CEP ================= */
const cep = document.getElementById('cep');
const endereco = document.getElementById('endereco');
const bairro = document.getElementById('bairro');
const cidade = document.getElementById('cidade');
const estado = document.getElementById('estado');

cep.addEventListener('input', () => {
    let v = cep.value.replace(/\D/g,'').slice(0,8);
    if (v.length > 5) cep.value = v.slice(0,5)+'-'+v.slice(5);
    else cep.value = v;

    if (v.length === 8) buscarCEP(v);
});

function buscarCEP(c){
    cep.classList.add('loading');

    fetch(`https://viacep.com.br/ws/${c}/json/`)
        .then(r => r.json())
        .then(d => {
            if (d.erro) return;

            endereco.value = d.logradouro || '';
            bairro.value = d.bairro || '';
            cidade.value = d.localidade || '';
            estado.value = d.uf || '';
        })
        .finally(()=>cep.classList.remove('loading'));
}
</script>

</body>
</html>