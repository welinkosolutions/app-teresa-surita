<?php
/**
 * ======================================================
 * CAMINHO: /home/elab/app.elab.social/api/trabalho/verificar-inatividade.php
 * NOME: Verificador de Inatividade de Sessões
 * DESCRIÇÃO:
 * - 30 min sem ping → pessoa OFFLINE
 * - 60 min sem ping → encerra sessão (timeout)
 * ======================================================
 */

declare(strict_types=1);
date_default_timezone_set('America/Boa_Vista');

ini_set('display_errors', '1');
error_reporting(E_ALL);

/* ================= CORE ================= */
$CORE = '/home/elab/public_html/core';
require_once $CORE . '/data/config.php';
require_once $CORE . '/data/data.php';

/* ================= BANCO ================= */
$pdo = dbRoraima();

/* ================= 30 MIN → OFFLINE ================= */
$pdo->exec("
    UPDATE pessoas
    SET
        online_status = 'offline'
    WHERE online_status = 'online'
      AND ultimo_ping < (NOW() - INTERVAL 30 MINUTE)
");

/* ================= 60 MIN → ENCERRA SESSÃO ================= */
$pdo->exec("
    UPDATE trabalho_sessoes
    SET
        status = 'encerrado',
        fim_em = NOW(),
        encerrada_motivo = 'timeout',
        atualizado_em = NOW()
    WHERE status = 'ativo'
      AND ultimo_ping < (NOW() - INTERVAL 60 MINUTE)
");

/* ================= LOG ================= */
$pdo->prepare("
    INSERT INTO cron_logs (job, status, mensagem)
    VALUES (?, 'ok', ?)
")->execute([
    'verificar-inatividade',
    'Sessões e usuários inativos processados'
]);

echo "OK\n";