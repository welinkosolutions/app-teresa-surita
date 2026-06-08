<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/api/exclusivo/aniversarios-importantes-salvar.php
 * NOME: API Exclusiva – Salvar Decisão Editorial
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

$pessoa_id = (int) $_SESSION['pessoa_id'];

$usuariosExclusivos = [7160, 6607];
if (!in_array($pessoa_id, $usuariosExclusivos, true)) {
    http_response_code(403);
    exit(json_encode(['erro'=>'Sem permissão']));
}

/* ================= CORE ================= */
require_once '/home/elab/public_html/core/data/config.php';

/* ================= METHOD ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método inválido');
}

/* ================= INPUT ================= */
$importante_id    = (int)($_POST['importante_id'] ?? 0);
$acao_midia       = $_POST['acao_midia'] ?? 'nenhum';
$comentario_midia = trim($_POST['comentario_midia'] ?? '');

if ($importante_id <= 0) {
    http_response_code(400);
    exit('ID inválido');
}

if (!in_array($acao_midia, ['stories','post','nenhum'], true)) {
    http_response_code(400);
    exit('Ação inválida');
}

/* ================= UPDATE ================= */

$sql = "
UPDATE aniversarios_importantes
SET
    acao_midia         = :acao,
    comentario_midia   = :comentario,
    acao_definida_por  = :usuario,
    acao_definida_em   = NOW()
WHERE id = :id
  AND ativo = 'sim'
LIMIT 1
";

$stmt = db()->prepare($sql);
$stmt->execute([
    ':acao'       => $acao_midia,
    ':comentario' => $comentario_midia !== '' ? $comentario_midia : null,
    ':usuario'    => $pessoa_id,
    ':id'         => $importante_id
]);

/* ================= RESPONSE ================= */
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'ok'            => true,
    'importante_id' => $importante_id,
    'acao_midia'    => $acao_midia,
    'definido_por'  => $pessoa_id
], JSON_UNESCAPED_UNICODE);

exit;