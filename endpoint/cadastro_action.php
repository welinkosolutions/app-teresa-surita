<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/endpoint/cadastro_action.php
 * NOME: Cadastro – Action (FLUXO FINAL / PRODUÇÃO)
 * DESCRIÇÃO:
 * - Endpoint oficial do cadastro público
 * - Cria pessoa ATIVA
 * - Usuário = telefone
 * - Senha padrão = 1234 (hash fixa)
 * - GPS do device SEMPRE gravado
 * - Linha de endereço SEMPRE criada (residencial)
 * - NÃO envia WhatsApp
 * ======================================================
 */

declare(strict_types=1);
date_default_timezone_set('America/Boa_Vista');

/* ================= ERROS ================= */
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

/* ================= HEADERS ================= */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

/* ================= CORE ================= */
$CORE = '/home/elab/public_html/core';
require_once $CORE . '/data/config.php';
require_once $CORE . '/data/data.php';

/* ================= LOG ================= */
function logErro(string $msg): void {
    file_put_contents(
        '/home/elab/logs/cadastro-error.log',
        date('Y-m-d H:i:s') . ' | ' . $msg . PHP_EOL,
        FILE_APPEND
    );
}

/* ================= HELPERS ================= */
function normalizarNome(string $v): string {
    $v = mb_strtolower(trim($v), 'UTF-8');
    $v = preg_replace('/\s+/', ' ', $v);
    return mb_convert_case($v, MB_CASE_TITLE, 'UTF-8');
}

/* ================= INPUT ================= */
$d = $_POST;

$nome       = normalizarNome($d['nome'] ?? '');
$apelido    = normalizarNome($d['apelido'] ?? '');
$nome_mae   = normalizarNome($d['nome_mae'] ?? '');
$chamar_por = ($d['chamar_por'] ?? 'nome') === 'apelido' ? 'apelido' : 'nome';

$data_nascimento = trim($d['data_nascimento'] ?? '');
$sexo            = trim($d['sexo'] ?? '');
$telefone        = preg_replace('/\D/', '', $d['telefone'] ?? '');

$email       = trim($d['email'] ?? '');
$instagram   = trim($d['instagram'] ?? '');
$facebook    = trim($d['facebook'] ?? '');
$whatsAceite = ($d['whatsapp_aceite'] ?? 'nao') === 'sim' ? 'sim' : 'nao';

/* endereço (opcional) */
$cep      = preg_replace('/\D/', '', $d['cep'] ?? '');
$endereco = trim($d['endereco'] ?? '');
$numero   = trim($d['numero'] ?? '');
$complemento = trim($d['complemento'] ?? '');
$bairro   = trim($d['bairro'] ?? '');
$cidade   = trim($d['cidade'] ?? '');
$estado   = trim($d['estado'] ?? '');
$ponto_referencia = trim($d['ponto_referencia'] ?? '');

/* GPS DO DEVICE */
$latitude  = isset($d['latitude'])  && $d['latitude']  !== '' ? (float)$d['latitude']  : null;
$longitude = isset($d['longitude']) && $d['longitude'] !== '' ? (float)$d['longitude'] : null;

/* ================= VALIDAÇÕES ================= */
if (
    $nome === '' ||
    $nome_mae === '' ||
    $data_nascimento === '' ||
    $sexo === '' ||
    $telefone === '' ||
    $whatsAceite !== 'sim'
) {
    echo json_encode(['status' => 'erro', 'msg' => 'Dados obrigatórios ausentes']);
    exit;
}

if (strlen($telefone) !== 11) {
    echo json_encode(['status' => 'erro', 'msg' => 'Telefone inválido']);
    exit;
}

$dt = DateTime::createFromFormat('Y-m-d', $data_nascimento);
if (!$dt || $dt->format('Y-m-d') !== $data_nascimento) {
    echo json_encode(['status' => 'erro', 'msg' => 'Data de nascimento inválida']);
    exit;
}

/* ================= BANCO ================= */
try {
    $pdo = dbRoraima();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    logErro('DB: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'msg' => 'Erro de conexão']);
    exit;
}

/* TELEFONE ÚNICO */
$stmt = $pdo->prepare("SELECT id FROM pessoas WHERE telefone = ? LIMIT 1");
$stmt->execute([$telefone]);
if ($stmt->fetch()) {
    echo json_encode(['status' => 'erro', 'msg' => 'Telefone já cadastrado']);
    exit;
}

/* ================= SENHA FIXA ================= */
$senhaHash = '$2a$12$eh49SGizVZyXLyb0exf7belEopLyMjU76KkiBLgmM/wUvEaHyZrC.';

/* ================= TRANSAÇÃO ================= */
$pdo->beginTransaction();

try {

    /* ---------- PESSOA ---------- */
    $stmt = $pdo->prepare("
     INSERT INTO pessoas (
    nome, apelido, chamar_por, data_nascimento, sexo, nome_mae,
    telefone, email, instagram, facebook,
    whatsapp_aceite,
    origem, perfil, status, status_validacao,
    ponto_referencia, latitude, longitude, ultimo_ip, pin,
    pontos
) VALUES (
    ?, ?, ?, ?, ?, ?,
    ?, ?, ?, ?,
    ?,
    'app', 'pessoa', 'ativo', 'validado',
    ?, ?, ?, ?, ?,
    50
)
    ");

    $stmt->execute([
        $nome,
        $apelido ?: null,
        $chamar_por,
        $data_nascimento,
        $sexo,
        $nome_mae,
        $telefone,
        $email ?: null,
        $instagram ?: null,
        $facebook ?: null,
        $whatsAceite,
        $ponto_referencia ?: null,
        $latitude,
        $longitude,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $senhaHash
    ]);

    $pessoa_id = (int)$pdo->lastInsertId();

    /* ---------- ENDEREÇO (SEMPRE CRIA LINHA) ---------- */
    $pdo->prepare("
        INSERT INTO pessoas_enderecos (
            pessoa_id,
            cep,
            endereco,
            numero,
            complemento,
            bairro,
            cidade,
            estado,
            latitude,
            longitude,
            tipo
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'residencial'
        )
    ")->execute([
        $pessoa_id,
        $cep ?: null,
        $endereco ?: null,
        $numero ?: null,
        $complemento ?: null,
        $bairro ?: null,
        $cidade ?: null,
        $estado ?: null,
        $latitude,
        $longitude
    ]);

    $pdo->commit();

} catch (Throwable $e) {
    $pdo->rollBack();
    logErro('TX: ' . $e->getMessage());
    echo json_encode(['status' => 'erro', 'msg' => 'Erro interno ao cadastrar']);
    exit;
}

/* ================= RESPOSTA ================= */
echo json_encode([
    'status'     => 'ok',
    'pessoa_id' => $pessoa_id
]);
exit;