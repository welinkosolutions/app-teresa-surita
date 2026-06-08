<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/publico/cadastro.php
 * NOME: Cadastro Público – App (Wizard FINAL)
 * DESCRIÇÃO:
 * - Cadastro inicial de pessoas via app
 * - Wizard em 5 etapas
 * - Valida telefone (AJAX)
 * - Normaliza nome, apelido e nome da mãe
 * - Endereço opcional (ViaCEP)
 * - Envia para /endpoint/cadastro_action.php
 * - GPS garantido antes do submit
 * - Após gravar: remove botão e exibe credenciais
 * ======================================================
 */
declare(strict_types=1);
date_default_timezone_set('America/Boa_Vista');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Teresapp · Cadastro</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{background:#f4f6f8;font-family:system-ui}
#wizard{max-width:520px;margin:0 auto;padding:20px}
.step{display:none}
.step.active{display:block}
.card-box{background:#fff;border-radius:18px;padding:18px;box-shadow:0 12px 28px rgba(0,0,0,.12)}
.progress{height:6px;margin-bottom:18px}
label{font-weight:600}
.field-error{border-color:#dc3545!important}
.small-msg{font-size:13px}
</style>
</head>

<body>

<div id="wizard">

<div class="progress">
  <div id="progressBar" class="progress-bar bg-success"></div>
</div>

<form id="cadastroForm" novalidate>

<!-- STEP 1 -->
<div class="step active">
  <div class="card-box text-center">
    <h4 class="fw-bold mb-3">Olá! 👋</h4>
    <p class="text-muted">
      Cadastre-se para entrar na <strong>Rede Social da Teresa Surita</strong>.
    </p>
    <button type="button" class="btn btn-success w-100" onclick="nextStep()">Começar</button>
    <a href="logout.php" class="btn btn-outline-secondary w-100 mt-2">Voltar ao login</a>
  </div>
</div>

<!-- STEP 2 -->
<div class="step">
  <div class="card-box">
    <h5 class="fw-bold mb-3">Seus dados</h5>

    <label>Nome *</label>
    <input type="text" name="nome" class="form-control mb-2" required>

    <label>Apelido</label>
    <input type="text" name="apelido" class="form-control mb-2">

    <label>Como prefere ser chamado? *</label>
    <select name="chamar_por" class="form-select mb-2" required>
      <option value="nome">Nome</option>
      <option value="apelido">Apelido</option>
    </select>

    <label>Data de nascimento *</label>
    <div class="row g-2 mb-2">
      <div class="col-4"><select id="dob_day" class="form-select"></select></div>
      <div class="col-4"><select id="dob_month" class="form-select"></select></div>
      <div class="col-4"><select id="dob_year" class="form-select"></select></div>
    </div>
    <input type="hidden" name="data_nascimento" id="data_nascimento">

    <label>Nome da mãe *</label>
    <input type="text" name="nome_mae" class="form-control mb-2" required>

    <label>Sexo *</label>
    <select name="sexo" class="form-select mb-2" required>
      <option value="">Selecione</option>
      <option value="M">Masculino</option>
      <option value="F">Feminino</option>
      <option value="O">Outro</option>
    </select>

    <label>Telefone *</label>
    <input type="tel" name="telefone" id="telefone" class="form-control mb-1" required>
    <div id="telMsg" class="small-msg text-danger"></div>

    <div class="d-flex gap-2 mt-3">
      <button type="button" class="btn btn-outline-secondary w-50" onclick="prevStep()">Voltar</button>
      <button type="button" class="btn btn-success w-50" onclick="nextStep(true)">Continuar</button>
    </div>
  </div>
</div>

<!-- STEP 3 -->
<div class="step">
  <div class="card-box">
    <h5 class="fw-bold mb-3">Comunicação</h5>

    <label>Email</label>
    <input type="email" name="email" class="form-control mb-2">

    <label>Instagram</label>
    <input type="url" name="instagram" class="form-control mb-2">

    <label>Facebook</label>
    <input type="url" name="facebook" class="form-control mb-2">

    <div class="form-check mt-3">
      <input class="form-check-input" type="checkbox" id="whatsAceite" required>
      <label class="form-check-label">
        Aceito receber informações pelo WhatsApp *
      </label>
      <input type="hidden" name="whatsapp_aceite" value="nao">
    </div>

    <div class="d-flex gap-2 mt-3">
      <button type="button" class="btn btn-outline-secondary w-50" onclick="prevStep()">Voltar</button>
      <button type="button" class="btn btn-success w-50" onclick="nextStep(true)">Continuar</button>
    </div>
  </div>
</div>

<!-- STEP 4 -->
<div class="step">
  <div class="card-box">
    <h5 class="fw-bold mb-3">Endereço (opcional)</h5>

    <label>CEP</label>
    <input type="text" name="cep" id="cep" class="form-control mb-2">
    <div id="cepMsg" class="small-msg text-muted mb-2"></div>

    <label>Endereço</label>
    <input type="text" name="endereco" id="endereco" class="form-control mb-2">

    <label>Número</label>
    <input type="text" name="numero" class="form-control mb-2">

    <label>Complemento</label>
    <input type="text" name="complemento" class="form-control mb-2">

    <label>Bairro</label>
    <input type="text" name="bairro" id="bairro" class="form-control mb-2">

    <label>Cidade</label>
    <input type="text" name="cidade" id="cidade" class="form-control mb-2">

    <label>Estado</label>
    <input type="text" name="estado" id="estado" class="form-control mb-2">

    <input type="hidden" name="latitude" id="latitude">
    <input type="hidden" name="longitude" id="longitude">

    <div class="d-flex gap-2 mt-3">
      <button type="button" class="btn btn-outline-secondary w-50" onclick="prevStep()">Voltar</button>
      <button type="button" class="btn btn-success w-50" onclick="nextStep()">Continuar</button>
    </div>
  </div>
</div>

<!-- STEP 5 -->
<div class="step">
  <div class="card-box text-center">
    <h5 class="fw-bold mb-3" id="finalTitle">Confirmar cadastro</h5>
    <p class="text-muted" id="finalMsg">Ao concluir, seus dados serão gravados.</p>

    <button type="submit" id="btnConcluir" class="btn btn-success w-100 mb-2">
      Concluir cadastro
    </button>
<a href="/logout.php" class="btn btn-outline-secondary w-100">
      Ir para o app
    </a>
  </div>
</div>

<input type="hidden" name="perfil" value="pessoa">
<input type="hidden" name="status" value="ativo">
<input type="hidden" name="origem" value="app">
<input type="hidden" name="status_validacao" value="validado">

</form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

<script>
/* NORMALIZA NOMES */
function normalizarNome(v){
 return v.toLowerCase().trim().replace(/\s+/g,' ')
   .split(' ').map(p=>p.charAt(0).toUpperCase()+p.slice(1)).join(' ');
}
['nome','nome_mae','apelido'].forEach(c=>{
 const el=document.querySelector(`[name="${c}"]`);
 if(el) el.addEventListener('blur',()=>{ if(el.value) el.value=normalizarNome(el.value); });
});

/* DATA NASC */
const d=dob_day,m=dob_month,y=dob_year;
for(let i=1;i<=31;i++)d.innerHTML+=`<option>${i}</option>`;
['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez']
.forEach((n,i)=>m.innerHTML+=`<option value="${i+1}">${n}</option>`);
for(let i=1920;i<=new Date().getFullYear();i++)y.innerHTML+=`<option>${i}</option>`;
function syncDOB(){
 data_nascimento.value=`${y.value}-${String(m.value).padStart(2,'0')}-${String(d.value).padStart(2,'0')}`;
}
[d,m,y].forEach(e=>e.addEventListener('change',syncDOB));
syncDOB();

/* MÁSCARAS */
$('#telefone').mask('(00) 00000-0000');
$('#cep').mask('00000-000');

/* TELEFONE */
let telefoneOk=false;
$('#telefone').on('blur',async function(){
 const tel=this.value.replace(/\D/g,'');
 telefoneOk=false;
 $('#telMsg').text('');
 if(tel.length!==11) return;

 const fd=new FormData();
 fd.append('telefone',tel);
 const r=await fetch('/endpoint/verificar-telefone.php',{method:'POST',body:fd});
 const j=await r.json();

 if(j.status==='existe'){
   $('#telMsg').text('Este telefone já possui cadastro.');
   this.classList.add('field-error');
 }else{
   this.classList.remove('field-error');
   telefoneOk=true;
 }
});

/* VIA CEP */
$('#cep').on('blur',function(){
 const cep=this.value.replace(/\D/g,'');
 if(cep.length!==8) return;
 $('#cepMsg').text('Buscando endereço...');
 fetch(`https://viacep.com.br/ws/${cep}/json/`)
 .then(r=>r.json()).then(d=>{
   if(d.erro){$('#cepMsg').text('CEP não encontrado');return;}
   endereco.value=d.logradouro;
   bairro.value=d.bairro;
   cidade.value=d.localidade;
   estado.value=d.uf;
   $('#cepMsg').text('Endereço carregado');
 });
});

/* GPS GARANTIDO */
let gpsResolvido=false;
function obterGPS(){
 return new Promise(resolve=>{
   if(!navigator.geolocation){gpsResolvido=true;return resolve();}
   navigator.geolocation.getCurrentPosition(
     p=>{
       latitude.value=p.coords.latitude;
       longitude.value=p.coords.longitude;
       gpsResolvido=true;
       resolve();
     },
     ()=>{
       gpsResolvido=true;
       resolve();
     },
     {enableHighAccuracy:true,timeout:10000,maximumAge:0}
   );
 });
}

/* WIZARD */
let step=0;const steps=document.querySelectorAll('.step');
function show(i){
 steps.forEach(s=>s.classList.remove('active'));
 steps[i].classList.add('active');
 progressBar.style.width=((i+1)/steps.length*100)+'%';
}
function validateStep(){
 let ok=true;
 steps[step].querySelectorAll('[required]').forEach(e=>{
   if(e.type==='checkbox' && !e.checked) ok=false;
   if(e.type!=='checkbox' && !e.value.trim()) ok=false;
 });
 if(step===1 && !telefoneOk) ok=false;
 return ok;
}
function nextStep(v=false){ if(v && !validateStep())return; if(step<steps.length-1){step++;show(step);} }
function prevStep(){ if(step>0){step--;show(step);} }
show(step);

/* WHATSAPP */
whatsAceite.addEventListener('change',e=>{
 document.querySelector('[name=whatsapp_aceite]').value=e.target.checked?'sim':'nao';
});

/* SUBMIT FINAL */
document.getElementById('cadastroForm').addEventListener('submit',async e=>{
 e.preventDefault();
 if(!telefoneOk){alert('Telefone inválido ou já cadastrado');return;}

 if(!gpsResolvido) await obterGPS();

 const btn=document.getElementById('btnConcluir');
 btn.disabled=true;
 btn.innerText='Salvando...';

 const fd=new FormData(e.target);
 const r=await fetch('/endpoint/cadastro_action.php',{method:'POST',body:fd});
 const j=await r.json();

 if(j.status!=='ok'){
   alert(j.msg||'Erro ao cadastrar');
   btn.disabled=false;
   btn.innerText='Concluir cadastro';
   return;
 }

 btn.remove();

 const tel=document.querySelector('[name=telefone]').value;
 finalTitle.innerText='Cadastro concluído';
 finalMsg.innerHTML=
   `Bem-vind@ à rede social da Teresa Surita.<br>
    Seu usuário é <strong>${tel}</strong> e sua senha é <strong>1234</strong>`;
});
</script>

</body>
</html>