<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$tipo = strtolower(trim((string) ($_GET['tipo'] ?? 'impacto')));

$rotasPermitidas = [
    'impacto' => '/home/elab/app.elab.social/comunidade/sucesso/impacto.php',
    'moedas' => '/home/elab/app.elab.social/comunidade/sucesso/moedas.php',
    'xp' => '/home/elab/app.elab.social/comunidade/sucesso/xp.php',
    'combo' => '/home/elab/app.elab.social/comunidade/sucesso/combo.php',
    'nivel' => '/home/elab/app.elab.social/comunidade/sucesso/nivel.php',
    'medalha' => '/home/elab/app.elab.social/comunidade/sucesso/medalha.php',
    'conquista' => '/home/elab/app.elab.social/comunidade/sucesso/conquista.php',
    'cadastrado' => '/home/elab/app.elab.social/comunidade/sucesso/cadastrado.php',
];

if (!array_key_exists($tipo, $rotasPermitidas)) {
    $tipo = 'impacto';
}

$arquivoTela = $rotasPermitidas[$tipo];

if (!is_file($arquivoTela)) {
    $arquivoTela = $rotasPermitidas['impacto'];
}

require $arquivoTela;