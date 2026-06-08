<?php
/**
 * ============================================================
 * CAMINHO: /home/elab/app.elab.social/api/trabalho/iniciar.php
 * NOME: Início Automático de Trabalho (Login)
 * DESCRIÇÃO:
 * - Marca usuário como online
 * - Encerra sessão ativa anterior
 * - Inicia nova sessão de trabalho
 * ============================================================
 */

declare(strict_types=1);
date_default_timezone_set('America/Boa_Vista');

ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

$LOG_PATH = '/home/elab/logs/api-trabalho-auto.log';

function logErro(string $msg): void {
    global $LOG_PATH;
    file_put_contents($LOG_PATH, date('Y-m-d H:i:s')." | $msg\n", FILE_APPEND);
}

$CORE = '/home/elab/public_html/core';
require_once $CORE . '/data/config.php';

session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    http_response_code(401);
    exit;
}

$pessoa_id = (int) $_SESSION['pessoa_id'];
$device_id = $_SESSION['device_id'] ?? 'unknown';

/* ================= PDO ================= */
$pdo = new PDO(
    "mysql:host=".DB_RORAIMA_HOST.";dbname=".DB_RORAIMA_NAME.";charset=".DB_RORAIMA_CHARSET,
    DB_RORAIMA_USER,
    DB_RORAIMA_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

try {
    $pdo->beginTransaction();

    // 1) Marca presença online
    $pdo->prepare("
        UPDATE pessoas
           SET online_status = 'online',
               online_desde = COALESCE(online_desde, NOW()),
               ultimo_ping = NOW()
         WHERE id = :id
    ")->execute(['id' => $pessoa_id]);

    // 2) Encerra sessão ativa anterior
    $pdo->prepare("
        UPDATE trabalho_sessoes
           SET status = 'encerrado', fim_em = NOW()
         WHERE pessoa_id = :id AND status = 'ativo'
    ")->execute(['id' => $pessoa_id]);

    // 3) Inicia nova sessão
    $pdo->prepare("
        INSERT INTO trabalho_sessoes
        (pessoa_id, device_id, inicio_em, status, ip, user_agent)
        VALUES
        (:pessoa_id, :device_id, NOW(), 'ativo', :ip, :ua)
    ")->execute([
        'pessoa_id' => $pessoa_id,
        'device_id' => $device_id,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);

    $pdo->commit();

    echo json_encode(['status' => 'ok']);

} catch (Throwable $e) {
    $pdo->rollBack();
    logErro($e->getMessage());
    http_response_code(500);
}