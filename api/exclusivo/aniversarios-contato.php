<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/api/exclusivo/aniversarios-contato.php
 * NOME: API Exclusiva – Marcar Contato Realizado
 * REGRA:
 * - NÃO decide mídia
 * - APENAS marca contato_realizado = sim
 * - Idempotente
 * ======================================================
 */

declare(strict_types=1);
date_default_timezone_set('America/Boa_Vista');

/* ================= ERROS (DEV) ================= */
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

/* ================= SESSION + SEGURANÇA ================= */
session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    http_response_code(403);
    exit(json_encode(['erro'=>'Acesso negado']));
}

$usuariosExclusivos = [7160, 6607];
if (!in_array((int)$_SESSION['pessoa_id'], $usuariosExclusivos, true)) {
    http_response_code(403);
    exit(json_encode(['erro'=>'Sem permissão']));
}

/* ================= CORE ================= */
require_once '/home/elab/public_html/core/data/config.php';

/* ================= MÉTODO ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método inválido');
}

/* ================= INPUT ================= */
$importante_id = (int)($_POST['importante_id'] ?? 0);

if ($importante_id <= 0) {
    http_response_code(400);
    exit('Parâmetro inválido');
}

/* ================= ATUALIZAÇÃO =================
   - Só marca contato_realizado
   - contato_em só grava na primeira vez
================================================ */

$sql = "
UPDATE aniversarios_importantes
SET
    contato_realizado = 'sim',
    contato_em = IF(contato_em IS NULL, NOW(), contato_em)
WHERE
    id = :id
  AND ativo = 'sim'
";

$stmt = db()->prepare($sql);
$stmt->execute([':id' => $importante_id]);

/* ================= RESPONSE ================= */
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'importante_id' => $importante_id,
    'contato_realizado' => true
], JSON_UNESCAPED_UNICODE);

exit;