<?php
declare(strict_types=1);

/*
=====================================================
ELAB SOCIAL — API CONVITE SEND V2
CAMINHO: /home/elab/app.elab.social/api/convite/send.php

Responsabilidade:
- Receber chamada do perfil V2
- Validar sessão
- Chamar core novo: /home/elab/public_html/core/invite/novo.php
- Retornar JSON para perfil.js
=====================================================
*/

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';
require_once '/home/elab/public_html/core/invite/novo.php';

header('Content-Type: application/json; charset=utf-8');

function conviteSendJson(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function conviteSendPayload(): array
{
    $raw = file_get_contents('php://input');

    if (is_string($raw) && trim($raw) !== '') {
        $json = json_decode($raw, true);

        if (is_array($json)) {
            return $json;
        }
    }

    return $_POST ?: [];
}

function conviteSendNormalizarCanal(?string $canal): string
{
    $canal = strtolower(trim((string) $canal));

    if ($canal === 'copiar') {
        return 'copiar_link';
    }

    $permitidos = [
        'whatsapp',
        'instagram_story',
        'facebook',
        'email',
        'copiar_link',
        'share_nativo',
    ];

    return in_array($canal, $permitidos, true) ? $canal : 'whatsapp';
}

function conviteSendNormalizarOrigem(?string $origem): string
{
    $origem = trim((string) $origem);

    if ($origem === '') {
        return 'perfil_v2_convite';
    }

    return mb_substr($origem, 0, 100);
}

function conviteSendNormalizarBackground(?string $background): ?string
{
    $background = trim((string) $background);

    if ($background === '') {
        return null;
    }

    return mb_substr($background, 0, 255);
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        conviteSendJson([
            'ok' => false,
            'erro' => 'Método não permitido.',
            'codigo' => 'metodo_nao_permitido',
        ], 405);
    }

    if (empty($_SESSION['pessoa_id'])) {
        conviteSendJson([
            'ok' => false,
            'erro' => 'Sessão expirada. Faça login novamente.',
            'codigo' => 'sessao_expirada',
        ], 401);
    }

    if (!function_exists('dbRoraima')) {
        conviteSendJson([
            'ok' => false,
            'erro' => 'Conexão com o banco indisponível.',
            'codigo' => 'db_indisponivel',
        ], 500);
    }

    if (!function_exists('inviteNovoEnviarConvite')) {
        conviteSendJson([
            'ok' => false,
            'erro' => 'Motor de convite V2 indisponível.',
            'codigo' => 'invite_novo_indisponivel',
        ], 500);
    }

    $pdo = dbRoraima();
    $pessoaId = (int) $_SESSION['pessoa_id'];
    $payload = conviteSendPayload();

    $canal = conviteSendNormalizarCanal((string) ($payload['canal'] ?? 'whatsapp'));
    $origem = conviteSendNormalizarOrigem((string) ($payload['origem'] ?? 'perfil_v2_convite'));
    $background = conviteSendNormalizarBackground((string) ($payload['background'] ?? 'perfil'));

    $resultado = inviteNovoEnviarConvite(
        $pdo,
        $pessoaId,
        $canal,
        $origem,
        $background
    );

    conviteSendJson($resultado, 200);
} catch (InvalidArgumentException $e) {
    error_log('[api/convite/send.php] Argumento inválido: ' . $e->getMessage());

    conviteSendJson([
        'ok' => false,
        'erro' => $e->getMessage(),
        'codigo' => 'argumento_invalido',
    ], 400);
} catch (RuntimeException $e) {
    error_log('[api/convite/send.php] Erro de fluxo: ' . $e->getMessage());

    conviteSendJson([
        'ok' => false,
        'erro' => $e->getMessage(),
        'codigo' => 'erro_fluxo_convite',
    ], 422);
} catch (Throwable $e) {
    error_log(
        '[api/convite/send.php] Erro fatal: '
        . $e->getMessage()
        . ' em '
        . $e->getFile()
        . ':'
        . $e->getLine()
    );

    conviteSendJson([
        'ok' => false,
        'erro' => 'Erro ao processar o convite.',
        'codigo' => 'erro_interno',
        'debug' => [
            'message' => $e->getMessage(),
        ],
    ], 500);
}