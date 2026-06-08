<?php
declare(strict_types=1);

date_default_timezone_set('America/Boa_Vista');

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$CORE = '/home/elab/public_html/core';

require_once $CORE . '/data/config.php';
require_once $CORE . '/data/data.php';
require_once $CORE . '/invite/engine.php';

$pdo = dbRoraima();

/*
=====================================================
RESOLVER ORIGEM
- legado: ?invite=TOKEN
- novo:   ?codigo=106607
=====================================================
*/
$token = trim((string) ($_GET['invite'] ?? ''));
$codigo = trim((string) ($_GET['codigo'] ?? ''));

$origem = inviteResolverOrigemEntrada($pdo, $token, $codigo);

if (!$origem) {
    exit('Convite inválido.');
}

$modo = (string) ($origem['modo'] ?? '');
$convidadorId = (int) ($origem['convidador_id'] ?? 0);
$pessoa = is_array($origem['pessoa'] ?? null) ? $origem['pessoa'] : null;

if ($convidadorId <= 0 || !$pessoa) {
    exit('Convite não encontrado.');
}

/*
=====================================================
VALIDAÇÕES POR MODO
=====================================================
*/
if ($modo === 'token') {
    $convite = is_array($origem['convite'] ?? null) ? $origem['convite'] : null;

    if (!$convite) {
        exit('Convite não encontrado.');
    }

    if (!inviteConviteAceitaCadastro($pdo, $convite)) {
        exit('Este convite não está disponível no momento.');
    }
}

if ($modo === 'codigo') {
    $linkPublico = is_array($origem['link_publico'] ?? null) ? $origem['link_publico'] : null;

    if (!$linkPublico || !inviteLinkPublicoEstaAtivo($linkPublico)) {
        exit('Este convite não está disponível no momento.');
    }
}

/*
=====================================================
NOME INDICADOR
=====================================================
*/
$nomeIndicador =
    (($pessoa['chamar_por'] ?? '') === 'apelido' && !empty($pessoa['apelido']))
        ? trim((string) $pessoa['apelido'])
        : explode(' ', trim((string) ($pessoa['nome'] ?? 'João')))[0];

/*
=====================================================
LINK DE VOLTA
=====================================================
*/
if ($modo === 'codigo') {
    $linkVolta = '/invite/novo.php?codigo=' . urlencode((string) ($origem['codigo'] ?? ''));
} else {
    $linkVolta = '/invite/novo.php?invite=' . urlencode((string) ($origem['token'] ?? ''));
}

$tokenHidden = $modo === 'token' ? (string) ($origem['token'] ?? '') : '';
$codigoHidden = $modo === 'codigo' ? (string) ($origem['codigo'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Cadastro</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
body{
  background:#f4f6f8;
  font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
}
#wizard{
  max-width:520px;
  margin:auto;
  padding:20px;
}
.step{display:none}
.step.active{display:block}
.card-box{
  background:#fff;
  border-radius:18px;
  padding:20px;
  box-shadow:0 12px 28px rgba(0,0,0,.12);
}
.progress{
  height:6px;
  margin-bottom:18px;
}
.progress-bar{
  transition:.35s ease;
}
.label-radio{
  border:1px solid #dee2e6;
  border-radius:12px;
  padding:12px;
  text-align:center;
  cursor:pointer;
}
.label-radio input{display:none}
.label-radio.active{
  border-color:#198754;
  background:#eaf7f0;
  color:#198754;
  font-weight:600;
}
.error-inline{
  display:none;
  color:#dc3545;
  font-size:12px;
  margin-top:4px;
}
.convite-badge{
  display:flex;
  align-items:center;
  gap:8px;
  font-size:13px;
  font-weight:700;
  color:#198754;
  margin-bottom:14px;
}
.convite-badge i{
  font-size:15px;
}
.toast-elab{
  position:fixed;
  left:50%;
  bottom:30px;
  transform:translateX(-50%) translateY(20px);
  background:#dc3545;
  color:#fff;
  padding:14px 18px;
  border-radius:12px;
  font-size:14px;
  box-shadow:0 10px 25px rgba(0,0,0,.25);
  opacity:0;
  pointer-events:none;
  transition:.3s ease;
  z-index:9999;
  max-width:90%;
  text-align:center;
}
.toast-elab.show{
  opacity:1;
  transform:translateX(-50%) translateY(0);
}
.toast-elab.success{
  background:#198754;
}
.toast-elab.warn{
  background:#cb0100;
}
</style>
</head>
<body>

<div id="wizard">

  <div class="progress">
    <div id="progressBar" class="progress-bar bg-success"></div>
  </div>

  <form id="cadastroForm" novalidate>
    <input type="hidden" name="invite_token" value="<?= htmlspecialchars($tokenHidden) ?>">
    <input type="hidden" name="invite_codigo" value="<?= htmlspecialchars($codigoHidden) ?>">
    <input type="hidden" name="latitude" id="latitude">
    <input type="hidden" name="longitude" id="longitude">

    <!-- STEP 1 -->
    <div class="step active">
      <div class="card-box">
        <div class="convite-badge">
          <i class="bi bi-person-heart"></i>
          PREENCHA SEUS DADOS PESSOAIS COMPLETOS
        </div>

        <label>Nome *</label>
        <input name="nome" class="form-control" onblur="normalizarCampo(this)" required>
        <div id="erro_nome" class="error-inline">Informe o nome.</div>

        <label class="mt-3">Apelido</label>
        <input name="apelido" class="form-control" onblur="normalizarCampo(this)">

        <label class="mt-3">Como prefere ser chamado?</label>
        <div class="row g-2">
          <div class="col-6">
            <label class="label-radio active w-100">
              <input type="radio" name="chamar_por" value="nome" checked> Nome
            </label>
          </div>
          <div class="col-6">
            <label class="label-radio w-100">
              <input type="radio" name="chamar_por" value="apelido"> Apelido
            </label>
          </div>
        </div>

        <label class="mt-3">Data de nascimento *</label>
        <input
          type="date"
          name="data_nascimento"
          id="data_nascimento"
          class="form-control"
          required
          max="<?= date('Y-m-d', strtotime('-16 years')) ?>"
        >
        <div id="erro_data" class="error-inline">Informe a data de nascimento.</div>

        <label class="mt-3">Nome da mãe *</label>
        <input name="nome_mae" class="form-control" onblur="normalizarCampo(this)" required>
        <div id="erro_mae" class="error-inline">Informe o nome da mãe.</div>

        <label class="mt-3">Sexo *</label>
        <div class="row g-2">
          <div class="col-4">
            <label class="label-radio w-100">
              <input type="radio" name="sexo" value="F"> Feminino
            </label>
          </div>
          <div class="col-4">
            <label class="label-radio w-100">
              <input type="radio" name="sexo" value="M"> Masculino
            </label>
          </div>
          <div class="col-4">
            <label class="label-radio w-100">
              <input type="radio" name="sexo" value="O"> Outro
            </label>
          </div>
        </div>
        <div id="erro_sexo" class="error-inline">Selecione o sexo.</div>

        <label class="mt-3">Telefone *</label>
        <input name="telefone" id="telefone" class="form-control" inputmode="numeric" required>
        <div id="erro_tel" class="error-inline">Telefone inválido.</div>

        <div class="d-flex gap-2 mt-3">
          <button type="button"
                  class="btn btn-outline-secondary w-50"
                  onclick="window.location.href='<?= htmlspecialchars($linkVolta) ?>'">
            Voltar
          </button>
          <button type="button" class="btn btn-success w-50" onclick="validarStep1()">Continuar</button>
        </div>
      </div>
    </div>

    <!-- STEP 2 -->
    <div class="step">
      <div class="card-box">
        <div class="convite-badge">
          <i class="bi bi-geo-alt-fill"></i>
          DIGITE O CEP PARA PREENCHER AUTOMÁTICO
        </div>

        <input name="cep" id="cep" class="form-control mb-2" placeholder="CEP *" inputmode="numeric" pattern="[0-9]*" autocomplete="postal-code" required>
        <input name="endereco" class="form-control mb-2" placeholder="Endereço *" required>
        <input name="numero" class="form-control mb-2" placeholder="Número *" inputmode="numeric" pattern="[0-9]*" required>
        <input name="complemento" class="form-control mb-2" placeholder="Complemento">
        <input name="bairro" class="form-control mb-2" placeholder="Bairro *" required>
        <input name="cidade" class="form-control mb-2" placeholder="Cidade *" required>
        <input name="estado" class="form-control mb-3" placeholder="Estado *" required>

        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-secondary w-50" onclick="prevStep()">Voltar</button>
          <button type="button" class="btn btn-success w-50" onclick="validarEndereco()">Continuar</button>
        </div>
      </div>
    </div>

    <!-- STEP 3 -->
    <div class="step">
      <div class="card-box">
        <label class="mb-2">Local de trabalho</label>
        <input name="local_trabalho" class="form-control mb-3" placeholder="Local de trabalho">

        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-secondary w-50" onclick="prevStep()">Voltar</button>
          <button type="button" class="btn btn-success w-50" onclick="nextStep()">Continuar</button>
        </div>
      </div>
    </div>

    <!-- STEP 4 -->
    <div class="step">
      <div class="card-box text-center">
        <h5 class="mb-3">Confirmar cadastro</h5>
        <p class="text-muted">Ao concluir, sua conta será criada.</p>
        <button type="submit" class="btn btn-success w-100">Concluir cadastro</button>
      </div>
    </div>

  </form>
</div>

<?php require __DIR__ . '/js.php'; ?>
<div id="toast" class="toast-elab"></div>
</body>
</html>