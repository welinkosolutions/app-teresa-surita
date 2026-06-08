<?php
/**
 * ============================================================
 * CAMINHO: app.elab.social/endpoint/push_register.php
 * NOME: Push Register – App elab.social
 * DESCRIÇÃO:
 * - Registra/atualiza permissão de push do aparelho
 * - Compatível com:
 *   - PWA
 *   - Android WebView
 *   - iOS WebView
 * - Atualiza push_dispositivos
 * ============================================================
 */

declare(strict_types=1);

date_default_timezone_set('America/Boa_Vista');

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$LOG_PATH = '/home/elab/logs/app-push-register.log';

if (!function_exists('logPushRegister')) {
    function logPushRegister(string $tipo, string $msg): void
    {
        global $LOG_PATH;

        file_put_contents(
            $LOG_PATH,
            date('Y-m-d H:i:s') .
            " | {$tipo} | {$msg} | IP=" . ($_SERVER['REMOTE_ADDR'] ?? '-') . PHP_EOL,
            FILE_APPEND
        );
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'erro' => 'metodo_nao_permitido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);

if (!is_array($data)) {
    $data = $_POST;
}

$deviceId = trim((string)($data['device_id'] ?? ''));
$platform = trim((string)($data['platform'] ?? 'pwa'));
$pushPermissao = trim((string)($data['push_permissao'] ?? 'default'));
$pushProvider = trim((string)($data['push_provider'] ?? ''));
$pushToken = trim((string)($data['push_token'] ?? ''));
$pushSubscriptionJson = $data['push_subscription_json'] ?? null;

$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
$pessoaId = (int)($_SESSION['pessoa_id'] ?? 0);

if ($deviceId !== '') {
    $deviceId = preg_replace('/[^a-zA-Z0-9_\-:.]/', '', $deviceId);
    if (strlen($deviceId) > 255) {
        $deviceId = substr($deviceId, 0, 255);
    }
}

$platformPermitidas = ['pwa', 'android_webview', 'ios_webview'];
if (!in_array($platform, $platformPermitidas, true)) {
    $platform = 'pwa';
}

$permissoesPermitidas = ['default', 'granted', 'denied'];
if (!in_array($pushPermissao, $permissoesPermitidas, true)) {
    $pushPermissao = 'default';
}

$providersPermitidos = ['', 'webpush', 'fcm', 'apns'];
if (!in_array($pushProvider, $providersPermitidos, true)) {
    $pushProvider = '';
}

if ($pushToken !== '' && strlen($pushToken) > 65535) {
    $pushToken = substr($pushToken, 0, 65535);
}

if (is_array($pushSubscriptionJson) || is_object($pushSubscriptionJson)) {
    $pushSubscriptionJson = json_encode($pushSubscriptionJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    $pushSubscriptionJson = trim((string)$pushSubscriptionJson);
}

if ($pushSubscriptionJson !== '' && strlen($pushSubscriptionJson) > 4294967295) {
    $pushSubscriptionJson = '';
}

if ($deviceId === '') {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'erro' => 'device_id_obrigatorio'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = dbRoraima();

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('PDO_NAO_INICIALIZADO');
    }

    $plataformaMap = [
        'pwa' => 'web',
        'android_webview' => 'android',
        'ios_webview' => 'ios',
    ];
    $plataformaBanco = $plataformaMap[$platform] ?? 'web';

    $stmt = $pdo->prepare("
        SELECT id, pessoa_id, device_token
        FROM push_dispositivos
        WHERE device_id = :device_id
           OR device_token = :device_token
        LIMIT 1
    ");
    $stmt->execute([
        ':device_id' => $deviceId,
        ':device_token' => $deviceId,
    ]);
    $existente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existente) {
        $sql = "
            UPDATE push_dispositivos
            SET
                pessoa_id = :pessoa_id_atualizada,
                device_id = :device_id,
                plataforma = :plataforma,
                app_origem = :app_origem,
                push_token = :push_token,
                push_subscription_json = :push_subscription_json,
                push_permissao = :push_permissao,
                push_provider = :push_provider,
                ativo = 'sim',
                ultimo_seen_em = NOW(),
                atualizado_em = NOW()
            WHERE id = :id
        ";

        $pdo->prepare($sql)->execute([
            ':pessoa_id_atualizada'    => $pessoaId > 0 ? $pessoaId : (int)$existente['pessoa_id'],
            ':device_id'               => $deviceId,
            ':plataforma'              => $plataformaBanco,
            ':app_origem'              => $platform,
            ':push_token'              => $pushToken !== '' ? $pushToken : null,
            ':push_subscription_json'  => $pushSubscriptionJson !== '' ? $pushSubscriptionJson : null,
            ':push_permissao'          => $pushPermissao,
            ':push_provider'           => $pushProvider !== '' ? $pushProvider : null,
            ':id'                      => (int)$existente['id'],
        ]);

        logPushRegister(
            'OK',
            'UPDATE device_id=' . $deviceId .
            ' pessoa_id=' . ($pessoaId > 0 ? $pessoaId : (int)$existente['pessoa_id']) .
            ' origem=' . $platform .
            ' permissao=' . $pushPermissao
        );

        echo json_encode([
            'ok' => true,
            'acao' => 'update',
            'device_id' => $deviceId,
            'pessoa_id' => $pessoaId > 0 ? $pessoaId : (int)$existente['pessoa_id'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $deviceTokenLegado = $deviceId;
    if (strlen($deviceTokenLegado) > 255) {
        $deviceTokenLegado = substr($deviceTokenLegado, 0, 255);
    }

    $pdo->prepare("
        INSERT INTO push_dispositivos
        (
            pessoa_id,
            device_id,
            device_token,
            push_token,
            push_subscription_json,
            plataforma,
            app_origem,
            push_permissao,
            push_provider,
            ativo,
            ultimo_seen_em,
            criado_em,
            atualizado_em
        )
        VALUES
        (
            :pessoa_id,
            :device_id,
            :device_token,
            :push_token,
            :push_subscription_json,
            :plataforma,
            :app_origem,
            :push_permissao,
            :push_provider,
            'sim',
            NOW(),
            NOW(),
            NOW()
        )
    ")->execute([
        ':pessoa_id'               => $pessoaId,
        ':device_id'               => $deviceId,
        ':device_token'            => $deviceTokenLegado,
        ':push_token'              => $pushToken !== '' ? $pushToken : null,
        ':push_subscription_json'  => $pushSubscriptionJson !== '' ? $pushSubscriptionJson : null,
        ':plataforma'              => $plataformaBanco,
        ':app_origem'              => $platform,
        ':push_permissao'          => $pushPermissao,
        ':push_provider'           => $pushProvider !== '' ? $pushProvider : null,
    ]);

    logPushRegister(
        'OK',
        'INSERT device_id=' . $deviceId .
        ' pessoa_id=' . $pessoaId .
        ' origem=' . $platform .
        ' permissao=' . $pushPermissao
    );

    echo json_encode([
        'ok' => true,
        'acao' => 'insert',
        'device_id' => $deviceId,
        'pessoa_id' => $pessoaId,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    logPushRegister('ERRO', 'EXCEPTION ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'erro' => 'erro_interno'
    ], JSON_UNESCAPED_UNICODE);
}