<?php
declare(strict_types=1);

date_default_timezone_set('America/Boa_Vista');

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$CORE = '/home/elab/public_html/core';

require_once $CORE . '/data/config.php';
require_once $CORE . '/data/data.php';
require_once $CORE . '/invite/engine.php';

$pdo = dbRoraima();

/*
=====================================================
RESOLVER CÓDIGO DA URL
SUPORTA:
- /i/?codigo=106607
- /i/106607 (via rewrite)
=====================================================
*/

$codigo = trim((string) ($_GET['codigo'] ?? ''));

if ($codigo === '') {
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $path = parse_url($requestUri, PHP_URL_PATH);
    $path = is_string($path) ? trim($path, '/') : '';

    if ($path !== '') {
        $partes = explode('/', $path);
        if (count($partes) >= 2 && $partes[0] === 'i') {
            $codigo = trim((string) ($partes[1] ?? ''));
        }
    }
}

if ($codigo === '') {
    http_response_code(404);
    exit('Link de convite inválido.');
}

/*
=====================================================
BUSCAR LINK PÚBLICO
=====================================================
*/

$linkPublico = inviteBuscarLinkPublicoPorCodigo($pdo, $codigo);

if (!$linkPublico) {
    http_response_code(404);
    exit('Convite não encontrado.');
}

if (!inviteLinkPublicoEstaAtivo($linkPublico)) {
    http_response_code(403);
    exit('Este convite não está disponível no momento.');
}

$linkPublicoId = (int) ($linkPublico['id'] ?? 0);
$pessoaId = (int) ($linkPublico['pessoa_id'] ?? 0);
$codigoConvite = trim((string) ($linkPublico['codigo_convite_publico'] ?? $codigo));

/*
=====================================================
TRACKING DE CLIQUE
=====================================================
*/

$canal = trim((string) ($_GET['ch'] ?? ''));
$origem = trim((string) ($_GET['src'] ?? ''));

inviteRegistrarCliqueLinkPublico(
    $pdo,
    $linkPublicoId,
    $pessoaId,
    $codigoConvite,
    $canal !== '' ? $canal : null,
    $origem !== '' ? $origem : null
);

/*
=====================================================
REDIRECIONAR PARA A LANDING OFICIAL
=====================================================
*/

header('Location: /invite/novo.php?codigo=' . urlencode($codigoConvite));
exit;