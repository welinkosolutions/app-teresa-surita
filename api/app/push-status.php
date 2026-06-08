<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/api/app/push-status.php
 * NOME: Push Status – Chat + Comunicados
 * DESCRIÇÃO:
 * - Retorna contadores de:
 *   - mensagens de chat não lidas
 *   - comunicados não lidos
 * - Usado pelo app.php (polling leve)
 * ======================================================
 */

declare(strict_types=1);
date_default_timezone_set('America/Boa_Vista');

/* ================= ERROS (DEV) ================= */
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

/* ================= SESSION ================= */
session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    http_response_code(401);
    echo json_encode(['erro'=>'nao_autenticado']);
    exit;
}

$pessoa_id = (int) $_SESSION['pessoa_id'];

/* ================= CORE ================= */
$CORE = '/home/elab/public_html/core';
require_once $CORE . '/data/config.php';
require_once $CORE . '/data/data.php';

/* ================= BANCO ================= */
$pdo = dbRoraima();

/* ================= CHAT NÃO LIDO ================= */
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(nao_lidas),0)
    FROM vw_chat_inbox
    WHERE pessoa_id = ?
");
$stmt->execute([$pessoa_id]);
$chatNaoLidas = (int) $stmt->fetchColumn();

/* ================= COMUNICADOS NÃO LIDOS ================= */
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM comunicados_destinatarios
    WHERE pessoa_id = ?
      AND status = 'pendente'
");
$stmt->execute([$pessoa_id]);
$comunicadosNaoLidos = (int) $stmt->fetchColumn();

/* ================= RESPONSE ================= */
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'chat'        => $chatNaoLidas,
    'comunicados' => $comunicadosNaoLidos
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
