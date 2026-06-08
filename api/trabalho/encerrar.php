<?php
/**
 * ============================================================
 * CAMINHO: /home/elab/public_html/api/trabalho/encerrar.php
 * NOME: Encerramento Automático de Trabalho
 * DESCRIÇÃO:
 * - Encerra sessão ativa de trabalho
 * - Marca usuário como offline
 * - Usado por inatividade, logout ou CRON
 * ============================================================
 */

declare(strict_types=1);
date_default_timezone_set('America/Boa_Vista');

/* ================= ERROS (DEV) ================= */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/* ================= HEADERS ================= */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

/* ================= LOG ================= */
$LOG_PATH = '/home/elab/logs/api-trabalho-encerrar.log';

function logErro(string $msg): void {
    global $LOG_PATH;
    file_put_contents(
        $LOG_PATH,
        date('Y-m-d H:i:s') . " | $msg" . PHP_EOL,
        FILE_APPEND
    );
}

/* ================= CORE ================= */
$CORE = '/home/elab/public_html/core';
require_once $CORE . '/data/config.php';

/* ================= SESSION ================= */
session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Usuário não autenticado']);
    exit;
}

$pessoa_id = (int) $_SESSION['pessoa_id'];

/* ================= PDO ================= */
try {
    $pdo = new PDO(
        "mysql:host=" . DB_RORAIMA_HOST . ";dbname=" . DB_RORAIMA_NAME . ";charset=" . DB_RORAIMA_CHARSET,
        DB_RORAIMA_USER,
        DB_RORAIMA_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (Throwable $e) {
    logErro("Erro PDO: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro de conexão']);
    exit;
}

try {
    $pdo->beginTransaction();

    /* ================= ENCERRAR SESSAO ATIVA ================= */
    $stmt = $pdo->prepare("
        UPDATE trabalho_sessoes
           SET status = 'encerrado',
               fim_em = NOW()
         WHERE pessoa_id = :pessoa_id
           AND status = 'ativo'
    ");
    $stmt->execute(['pessoa_id' => $pessoa_id]);

    /* ================= MARCAR OFFLINE ================= */
    $stmt = $pdo->prepare("
        UPDATE pessoas
           SET online_status = 'offline'
         WHERE id = :pessoa_id
    ");
    $stmt->execute(['pessoa_id' => $pessoa_id]);

    $pdo->commit();

    echo json_encode([
        'status' => 'ok',
        'encerrado_em' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    $pdo->rollBack();
    logErro("Erro encerrar | pessoa_id={$pessoa_id} | {$e->getMessage()}");

    http_response_code(500);
    echo json_encode(['erro' => 'Falha ao encerrar trabalho']);
}
