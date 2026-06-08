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

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();
$usuario_id = (int)$_SESSION['pessoa_id'];

$stmt = $pdo->prepare("SELECT perfil FROM pessoas WHERE id=? LIMIT 1");
$stmt->execute([$usuario_id]);

if ($stmt->fetchColumn() !== 'lider') {
    header('Location:/interno/admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Nova Demanda</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{background:#f4f6f8;font-family:system-ui}
.card-box{
    background:#fff;
    border-radius:18px;
    padding:22px;
    margin-bottom:16px;
    box-shadow:0 6px 16px rgba(0,0,0,.06)
}
.hidden{display:none}
.badge-endereco{
    background:#e9f5ff;
    color:#0d6efd;
    padding:8px 12px;
    border-radius:12px;
    font-size:.85rem;
    margin-bottom:12px;
    display:inline-block;
}
</style>
</head>
<body>

<div class="container py-4">

<a href="demandas.php" class="btn btn-outline-secondary mb-3">← Voltar</a>
<h5 class="fw-bold mb-3">📝 Registrar Nova Demanda</h5>

<!-- STEP 1 -->
<div class="card-box" id="step1">
<h6 class="fw-bold mb-3">🔎 Buscar Pessoa</h6>

<label class="fw-semibold">Telefone *</label>
<input type="text"
       id="telefone"
       class="form-control mb-3"
       maxlength="15"
       inputmode="numeric"
       placeholder="(95) 99999-9999">

<button type="button"
        class="btn btn-success w-100"
        onclick="validarTelefone()">
    Validar Telefone
</button>

<div id="resultadoTelefone" class="mt-3 hidden"></div>
</div>

<!-- STEP 2 -->
<div class="card-box hidden" id="step2">
<h6 class="fw-bold mb-3">👤 Dados da Pessoa</h6>

<div class="mb-3">
<label>Telefone</label>
<input type="text" id="telefone_visual" class="form-control" disabled>
</div>

<div class="mb-3">
<label>Nome *</label>
<input type="text" id="nome" class="form-control">
</div>

<div class="mb-3">
<label>Apelido</label>
<input type="text" id="apelido" class="form-control">
</div>

<div class="mb-3">
<label>Como quer ser chamado?</label>
<select id="chamar_por" class="form-select">
<option value="nome">Nome</option>
<option value="apelido">Apelido</option>
</select>
</div>

<div class="mb-4">
<label>Data de nascimento</label>
<input type="date" id="data_nascimento" class="form-control">
</div>

<div class="d-flex gap-2">
<button class="btn btn-outline-secondary w-50" onclick="voltarStep1()">Voltar</button>
<button class="btn btn-success w-50" onclick="validarStep2()">Continuar</button>
</div>
</div>

<!-- STEP 3 -->
<div class="card-box hidden" id="step3">
<h6 class="fw-bold mb-3">📍 Endereço</h6>

<div id="badgeEndereco" class="hidden badge-endereco">
Endereço já cadastrado — você pode editar se necessário.
</div>

<div class="mb-3">
<label>CEP *</label>
<input type="text" id="cep" class="form-control" maxlength="9">
</div>

<div class="mb-3">
<label>Logradouro *</label>
<input type="text" id="endereco" class="form-control">
</div>

<div class="row">
<div class="col-6 mb-3">
<label>Número *</label>
<input type="text" id="numero" class="form-control">
</div>
<div class="col-6 mb-3">
<label>Complemento</label>
<input type="text" id="complemento" class="form-control">
</div>
</div>

<div class="mb-3">
<label>Bairro *</label>
<input type="text" id="bairro" class="form-control">
</div>

<div class="row">
<div class="col-8 mb-3">
<label>Cidade *</label>
<input type="text" id="cidade" class="form-control">
</div>
<div class="col-4 mb-3">
<label>Estado *</label>
<input type="text" id="estado" class="form-control" maxlength="2">
</div>
</div>

<div class="d-flex gap-2">
<button class="btn btn-outline-secondary w-50" onclick="voltarStep2()">Voltar</button>
<button class="btn btn-success w-50" onclick="validarStep3()">Continuar</button>
</div>
</div>

</div>

<script>

let pessoaId = null;
let pessoaData = null;
const telefoneInput = document.getElementById('telefone');

/* ================= MÁSCARA + BLOQUEIO REAL ================= */
telefoneInput.addEventListener('input', function(e){

    let v = e.target.value.replace(/\D/g,'');

    if(v.length > 11) v = v.slice(0,11);

    // Bloqueia números repetidos tipo 00000000000
    if(/^(\d)\1{9,10}$/.test(v)){
        v = '';
    }

    // Máscara dinâmica
    if(v.length > 10){
        v = v.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
    }
    else if(v.length > 6){
        v = v.replace(/^(\d{2})(\d{4})(\d+).*/, '($1) $2-$3');
    }
    else if(v.length > 2){
        v = v.replace(/^(\d{2})(\d+).*/, '($1) $2');
    }

    e.target.value = v;
});


/* ================= VALIDAR TELEFONE ================= */
async function validarTelefone(){

    const telefone = telefoneInput.value.replace(/\D/g,'');

    if(telefone.length < 10){
        alert('Digite um telefone válido.');
        return;
    }

    // Bloqueia sequência repetida mesmo após máscara
    if(/^(\d)\1+$/.test(telefone)){
        alert('Telefone inválido.');
        return;
    }

    try{

        const formData = new FormData();
        formData.append('telefone', telefone);

        const resp = await fetch('ajax-verificar-telefone.php',{
            method:'POST',
            body: formData
        });

        if(!resp.ok){
            alert('Erro de comunicação.');
            return;
        }

        const data = await resp.json();

        if(data.existe){
            pessoaId = data.pessoa.id;
            pessoaData = data;
            montarPessoaExistente(data);
        } else {
            montarBotaoCriarNovaPessoa();
        }

    } catch(err){
        console.error(err);
        alert('Erro ao validar telefone.');
    }
}

</script>

</body>
</html>