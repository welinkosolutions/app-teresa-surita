<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/assets.php';

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

$tipo = sucesso_tipo_atual();
$origem = sucesso_param_texto('origem');
$acao = sucesso_param_texto('acao');

$impacto = sucesso_int_get('impacto', $tipo === 'impacto' ? 10 : 0);
$moedas = sucesso_int_get('moedas', $tipo === 'moedas' ? 10 : 0);
$xp = sucesso_int_get('xp', $tipo === 'xp' ? 1 : 0);
$nivel = sucesso_int_get('nivel', 4);

$cadastroId = sucesso_int_get('cadastro_id', 0);
$nomeCustom = trim((string) ($_GET['nome'] ?? ''));

$acaoContinuar = '/comunidade/missao.php';

if ($tipo === 'cadastrado') {
    $acaoContinuar = $cadastroId > 0
        ? '/comunidade/ativar.php?cadastro_id=' . $cadastroId
        : '/comunidade/missao.php?acao=ativar-cadastro';
}

$assetCentral = sucesso_asset_central($tipo, $origem, $acao);
$temAssetCentral = $assetCentral !== null;

$audioTipo = sucesso_audio_por_tipo($tipo);

header('Content-Type: text/html; charset=utf-8');