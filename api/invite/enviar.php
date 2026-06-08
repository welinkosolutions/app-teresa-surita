<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';
require_once '/home/elab/public_html/core/invite/engine.php';
require_once '/home/elab/public_html/core/invite/enviar.php';

if (empty($_SESSION['pessoa_id'])) {
    echo json_encode([
        'ok'   => false,
        'erro' => 'Sua sessão expirou. Entre novamente no app para continuar.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$pdo = dbRoraima();
$pessoa_id = (int) $_SESSION['pessoa_id'];

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            nome,
            status,
            status_validacao,
            instagram_username
        FROM pessoas
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$pessoa_id]);
    $pessoa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pessoa) {
        echo json_encode([
            'ok'   => false,
            'erro' => 'Não foi possível localizar sua conta.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $status = strtolower(trim((string) ($pessoa['status'] ?? '')));
    $statusValidacao = strtolower(trim((string) ($pessoa['status_validacao'] ?? '')));

    if ($status !== 'ativo') {
        echo json_encode([
            'ok'       => false,
            'motivo'   => 'conta_inativa',
            'bloqueio' => true,
            'erro'     => 'Sua conta não está ativa no momento. Procure a equipe para regularizar seu acesso.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (!in_array($statusValidacao, ['validado', 'aprovado'], true)) {
        echo json_encode([
            'ok'       => false,
            'motivo'   => 'aguardando_ativacao',
            'bloqueio' => true,
            'erro'     => 'Seus convites ainda não foram liberados porque sua conta está aguardando ativação manual.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /*
    ========================================
    CANAL / ORIGEM
    ========================================
    */
    $canal = 'whatsapp';
    $origem = 'dashboard_home_card_convite';
    $background = null;

    $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));

    if ($contentType !== '' && str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw ?: '{}', true);

        if (is_array($json)) {
            $canal = trim((string) ($json['canal'] ?? $canal)) ?: $canal;
            $origem = trim((string) ($json['origem'] ?? $origem)) ?: $origem;
            $background = isset($json['background']) ? trim((string) $json['background']) : null;
        }
    } else {
        $canal = trim((string) ($_POST['canal'] ?? $canal)) ?: $canal;
        $origem = trim((string) ($_POST['origem'] ?? $origem)) ?: $origem;
        $background = isset($_POST['background']) ? trim((string) $_POST['background']) : null;
    }

    $result = inviteProcessarEnvio(
        $pdo,
        $pessoa_id,
        $canal,
        $origem,
        $background
    );

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    error_log('[API_INVITE_ENVIAR] ' . $e->getMessage());

    echo json_encode([
        'ok'   => false,
        'erro' => 'Não foi possível liberar seu convite agora. Tente novamente em instantes.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}