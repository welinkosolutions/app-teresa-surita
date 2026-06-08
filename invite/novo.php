<?php
declare(strict_types=1);

date_default_timezone_set('America/Boa_Vista');

ini_set('display_errors', '1');
error_reporting(E_ALL);

/*
=====================================================
CORE
=====================================================
*/

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
        : explode(' ', trim((string) ($pessoa['nome'] ?? 'Usuário')))[0];

/*
=====================================================
LINK DO PRÓXIMO PASSO
=====================================================
*/

if ($modo === 'codigo') {
    $linkCadastro = '/invite/cadastro.php?codigo=' . urlencode((string) ($origem['codigo'] ?? ''));
} else {
    $linkCadastro = '/invite/cadastro.php?invite=' . urlencode((string) ($origem['token'] ?? ''));
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>

<meta charset="UTF-8">
<title>CONVITE ESPECIAL</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
background:#f5f7fb;
font-family:system-ui;
}

.container{
max-width:520px;
margin:auto;
padding:20px;
}

.card-elab{
background:#fff;
border-radius:18px;
padding:24px;
box-shadow:0 15px 40px rgba(0,0,0,.12);
}

.logo{
font-size:22px;
font-weight:700;
color:#198754;
}

.convite-avatar{
width:72px;
height:72px;
border-radius:50%;
background:#198754;
color:#fff;
display:flex;
align-items:center;
justify-content:center;
font-size:26px;
font-weight:600;
margin:auto;
}

.btn-elab{
background:#198754;
border:none;
font-weight:600;
padding:14px;
}

.btn-elab:hover{
background:#157347;
}

</style>

</head>

<body>

<div class="container">

<div class="card-elab text-center">

<div class="logo mb-3">
CONVITE ESPECIAL
</div>

<h4 class="fw-bold mb-2">
<?= htmlspecialchars($nomeIndicador) ?>
</h4>

<p class="text-muted mb-4">
Está <strong>convidando você</strong> para participar da rede social da <strong>Teresa Surita</strong>.
</p>

<div class="mb-4">
  <div class="alert alert-success">
    <strong>Seu convite foi CONFIRMADO</strong><br>
    O cadastro levará menos de 1 minuto, clique no botão para começar.
  </div>
</div>

<a href="<?= htmlspecialchars($linkCadastro) ?>" class="btn btn-danger w-100">
  INICIAR AGORA
</a>

</div>

<div class="text-center mt-3 text-muted" style="font-size:13px">
ELAB SOCIAL · Mobilização Inteligente
</div>

</div>

</body>
</html>