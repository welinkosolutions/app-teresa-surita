<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/pessoas/buscar_pessoas.php
 * NOME: Buscar Pessoas – Infinite Scroll
 * ======================================================
 */

declare(strict_types=1);

/* ================= ERROS (DEV) ================= */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/* ================= HEADERS ================= */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

/* ================= SESSION ================= */
date_default_timezone_set('America/Boa_Vista');
session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    echo json_encode([]);
    exit;
}

$pessoa_id = (int) $_SESSION['pessoa_id'];

/* ================= CORE ================= */
$CORE = '/home/elab/public_html/core';
require_once $CORE . '/data/config.php';
require_once $CORE . '/data/data.php';

/* ================= INPUT ================= */
$q      = trim($_GET['q'] ?? '');
$offset = max(0, (int)($_GET['offset'] ?? 0));
$limit  = 30;

/* menos de 3 letras → não busca */
if ($q !== '' && mb_strlen($q) < 3) {
    echo json_encode([]);
    exit;
}

$pdo = dbRoraima();

/* ================= PERFIL REAL ================= */
$stmtPerfil = $pdo->prepare("
    SELECT perfil
    FROM pessoas
    WHERE id = ?
    LIMIT 1
");
$stmtPerfil->execute([$pessoa_id]);
$perfil = $stmtPerfil->fetchColumn() ?: 'pessoa';

/* ================= GOVERNANÇA ================= */
$perfil_full = ['lider','agente','midia','candidato','gestor','admin'];

$wherePerfil = '';
$params = [];

if (!in_array($perfil, $perfil_full, true)) {
    $wherePerfil = 'AND criado_por = :me';
    $params[':me'] = $pessoa_id;
}

/* ================= BUSCA ROBUSTA ================= */
$whereBusca = '';

if ($q !== '') {

    $condicoes = [];

    $qNorm = mb_strtolower($q);

    // nome_busca OU nome
    $params[':nome_prefixo'] = $qNorm . '%';
    $condicoes[] = "LOWER(COALESCE(nome_busca, nome)) LIKE :nome_prefixo";

    // apelido
    $params[':apelido_prefixo'] = $qNorm . '%';
    $condicoes[] = "LOWER(apelido) LIKE :apelido_prefixo";

    // telefone (só se tiver dígitos)
    $digits = preg_replace('/\D/', '', $q);
    if ($digits !== '') {
        $params[':tel'] = '%' . $digits . '%';
        $condicoes[] = 'telefone LIKE :tel';
    }

    $whereBusca = 'AND (' . implode(' OR ', $condicoes) . ')';
}

/* ================= SQL FINAL ================= */
$sql = "
    SELECT
        id,
        nome,
        apelido,
        chamar_por
    FROM pessoas
    WHERE status = 'ativo'
    $wherePerfil
    $whereBusca
    ORDER BY COALESCE(nome_busca, nome)
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}

$stmt->execute();

echo json_encode(
    $stmt->fetchAll(PDO::FETCH_ASSOC),
    JSON_UNESCAPED_UNICODE
);
exit;