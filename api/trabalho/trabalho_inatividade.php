<?php
/**
 * ============================================================
 * CAMINHO: /home/elab/cron/trabalho_inatividade.php
 * NOME: CRON - Encerramento por Inatividade
 * DESCRIÇÃO:
 * - Encerra sessões de trabalho após 15 min de inatividade
 * - Marca usuário como offline
 * - Uso exclusivo via CRON
 * ============================================================
 */

declare(strict_types=1);
date_default_timezone_set('America/Boa_Vista');

/* ================= ERROS ================= */
ini_set('display_errors', '0');
error_reporting(E_ALL);

/* ================= LOG ================= */
$LOG_PATH = '/home/elab/logs/cron-trabalho-inatividade.log';

function logCron(string $msg): void {
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
    logCron("ERRO PDO: " . $e->getMessage());
    exit;
}

try {
    $pdo->beginTransaction();

    /* ================= BUSCAR USUÁRIOS OCIOSOS ================= */
    $sql = "
        SELECT id
          FROM pessoas
         WHERE online_status = 'online'
           AND ultimo_ping < (NOW() - INTERVAL 15 MINUTE)
    ";

    $ocios = $pdo->query($sql)->fetchAll();

    if (!$ocios) {
        logCron("Nenhum usuário ocioso");
        $pdo->commit();
        exit;
    }

    $ids = array_column($ocios, 'id');
    $idsIn = implode(',', array_map('intval', $ids));

    /* ================= ENCERRAR SESSÕES ================= */
    $pdo->exec("
        UPDATE trabalho_sessoes
           SET status = 'encerrado',
               fim_em = NOW()
         WHERE pessoa_id IN ($idsIn)
           AND status = 'ativo'
    ");

    /* ================= MARCAR OFFLINE ================= */
    $pdo->exec("
        UPDATE pessoas
           SET online_status = 'offline'
         WHERE id IN ($idsIn)
    ");

    $pdo->commit();

    logCron("Encerrados por inatividade: " . implode(',', $ids));

} catch (Throwable $e) {
    $pdo->rollBack();
    logCron("ERRO: " . $e->getMessage());
}
