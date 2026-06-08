<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/endpoint/verificar-telefone.php
 * NOME: Verificação de Telefone (Cadastro Público)
 * DESCRIÇÃO:
 * - Verifica se telefone já existe na tabela `pessoas`
 * - Usado no cadastro público do app
 * ======================================================
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$telefone = preg_replace('/\D+/', '', $_POST['telefone'] ?? '');

if (strlen($telefone) < 10) {
    echo json_encode(['status' => 'erro', 'msg' => 'Telefone inválido']);
    exit;
}

$pdo = dbRoraima();

$stmt = $pdo->prepare("
    SELECT id
    FROM pessoas
    WHERE telefone = :telefone
    LIMIT 1
");
$stmt->execute([':telefone' => $telefone]);

if ($stmt->fetch()) {
    echo json_encode([
        'status' => 'existe',
        'msg'    => 'Este telefone já possui cadastro'
    ]);
    exit;
}

echo json_encode(['status' => 'ok']);