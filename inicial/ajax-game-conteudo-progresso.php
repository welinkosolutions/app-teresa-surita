<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';
require_once '/home/elab/public_html/core/games/GameConteudoService.php';
require_once '/home/elab/public_html/core/games/GameEstadoService.php';

function responderJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function tenantIdAtual(PDO $pdo): int
{
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    $host = preg_replace('/:\d+$/', '', $host) ?: '';

    if ($host === '') {
        return 0;
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM clientes_elab
        WHERE dominio = ?
          AND status = 'ativo'
        LIMIT 1
    ");
    $stmt->execute([$host]);

    return (int) ($stmt->fetchColumn() ?: 0);
}

if (empty($_SESSION['pessoa_id'])) {
    responderJson([
        'ok' => false,
        'erro' => 'sessao_expirada',
    ], 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    responderJson([
        'ok' => false,
        'erro' => 'metodo_invalido',
    ], 405);
}

try {
    $pdo = dbRoraima();

    $tenantClienteId = tenantIdAtual($pdo);
    $pessoaId = (int) $_SESSION['pessoa_id'];

    if ($tenantClienteId <= 0 || $pessoaId <= 0) {
        responderJson([
            'ok' => false,
            'erro' => 'tenant_ou_pessoa_invalido',
        ], 400);
    }

    $conteudoId = (int) ($_POST['conteudo_id'] ?? 0);
    $percentual = (int) ($_POST['percentual'] ?? 0);
    $segundosAssistidos = (int) ($_POST['segundos_assistidos'] ?? 0);
    $duracaoSegundos = isset($_POST['duracao_segundos'])
        ? (int) $_POST['duracao_segundos']
        : null;

    if ($conteudoId <= 0) {
        responderJson([
            'ok' => false,
            'erro' => 'conteudo_id_obrigatorio',
        ], 400);
    }

    $service = new GameConteudoService($pdo);

    $resultado = $service->registrarProgressoYoutube(
        tenantClienteId: $tenantClienteId,
        pessoaId: $pessoaId,
        conteudoId: $conteudoId,
        percentual: $percentual,
        segundosAssistidos: $segundosAssistidos,
        duracaoSegundos: $duracaoSegundos
    );

    $estadoService = new GameEstadoService($pdo);
    $estado = $estadoService->obterOuCriarEstado($tenantClienteId, $pessoaId);

    responderJson([
        'ok' => true,
        'resultado' => $resultado,
        'estado' => $estado,
    ]);
} catch (Throwable $e) {
    error_log('[ajax-game-conteudo-progresso] ' . $e->getMessage());

    responderJson([
        'ok' => false,
        'erro' => 'exception',
        'mensagem' => $e->getMessage(),
    ], 500);
}
