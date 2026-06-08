<?php
/**
 * ======================================================
 * CAMINHO: crm.elab.social/api/public/aniversarios-importantes-contato.php
 * NOME: Registrar contato – Aniversários Importantes
 * ======================================================
 */

declare(strict_types=1);

/* ================= ERROS (DEV) ================= */
ini_set('display_errors','1');
error_reporting(E_ALL);

/* ================= CORE ================= */
require_once '/home/elab/public_html/core/data/config.php';

/* ================= HEADER ================= */
header('Content-Type: application/json; charset=utf-8');

/* ================= MÉTODO ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

/* ================= INPUT ================= */
$aniversarioId = (int)($_POST['aniversario_importante_id'] ?? 0);
$origem        = $_POST['origem'] ?? '';
$tipoContato   = $_POST['tipo_contato'] ?? '';

$pessoaId      = isset($_POST['pessoa_id']) ? (int)$_POST['pessoa_id'] : null;
$pessoaRawId   = isset($_POST['pessoa_raw_id']) ? (int)$_POST['pessoa_raw_id'] : null;

/* ================= VALIDAÇÃO ================= */
if (
    $aniversarioId <= 0 ||
    !in_array($origem, ['elab','legacy'], true) ||
    !in_array($tipoContato, ['whatsapp_msg','whatsapp_call','call'], true)
) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos']);
    exit;
}

if ($origem === 'elab' && empty($pessoaId)) {
    http_response_code(400);
    echo json_encode(['error' => 'pessoa_id obrigatório para origem elab']);
    exit;
}

if ($origem === 'legacy' && empty($pessoaRawId)) {
    http_response_code(400);
    echo json_encode(['error' => 'pessoa_raw_id obrigatório para origem legacy']);
    exit;
}

/* ================= CONTEXTO ================= */
$contatadoPor = 6607; // Teresa (fixo por enquanto)

/* ================= BANCO ================= */
$pdo = dbRoraima();

$stmt = $pdo->prepare("
    INSERT INTO aniversarios_contatos (
        aniversario_importante_id,
        pessoa_id,
        pessoa_raw_id,
        origem,
        tipo_contato,
        contatado_por
    ) VALUES (
        :aniversario,
        :pessoa,
        :pessoa_raw,
        :origem,
        :tipo,
        :por
    )
");

$stmt->execute([
    ':aniversario' => $aniversarioId,
    ':pessoa'      => $pessoaId,
    ':pessoa_raw'  => $pessoaRawId,
    ':origem'      => $origem,
    ':tipo'        => $tipoContato,
    ':por'         => $contatadoPor
]);

echo json_encode([
    'ok' => true,
    'registrado_em' => date('Y-m-d H:i:s')
]);
