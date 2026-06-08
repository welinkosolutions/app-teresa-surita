<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('America/Boa_Vista');

session_name('ELAB_APP_SESSION');
session_start();

header('Content-Type: application/json; charset=utf-8');

function jsonOut(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function somenteDigitos(?string $valor): string
{
    return preg_replace('/\D+/', '', (string)$valor);
}

if (empty($_SESSION['pessoa_id'])) {
    jsonOut([
        'ok' => false,
        'erro' => 'Sessão expirada.',
    ], 401);
}

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();
$liderId = (int)($_SESSION['pessoa_id'] ?? 0);

$stmtPerfil = $pdo->prepare("
    SELECT perfil
    FROM pessoas
    WHERE id = ?
    LIMIT 1
");
$stmtPerfil->execute([$liderId]);

$perfilLogado = trim((string)($stmtPerfil->fetchColumn() ?? ''));

if (!in_array($perfilLogado, ['lider', 'admin', 'gestor_lideres'], true)) {
    jsonOut([
        'ok' => false,
        'erro' => 'Sem permissão.',
    ], 403);
}

$telefone = somenteDigitos((string)($_POST['telefone'] ?? ''));

if (strlen($telefone) < 10) {
    jsonOut([
        'ok' => false,
        'erro' => 'Telefone inválido.',
    ], 400);
}

try {
    $stmt = $pdo->prepare("
        SELECT
            d.id,
            d.protocolo,
            d.titulo,
            d.status,
            d.categoria,
            d.origem,
            DATE_FORMAT(d.criado_em, '%d/%m/%Y %H:%i') AS criado_em,
            p.nome AS pessoa_nome,
            p.telefone
        FROM demandas d
        INNER JOIN pessoas p ON p.id = d.pessoa_id
        WHERE REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(p.telefone, ''), '(', ''), ')', ''), '-', ''), ' ', '') = ?
          AND d.criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY d.criado_em DESC, d.id DESC
        LIMIT 5
    ");

    $stmt->execute([$telefone]);
    $demandas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonOut([
        'ok' => true,
        'tem_recente' => count($demandas) > 0,
        'total' => count($demandas),
        'demandas' => array_map(static function (array $d): array {
            return [
                'id' => (int)$d['id'],
                'protocolo' => (string)($d['protocolo'] ?: $d['id']),
                'titulo' => (string)($d['titulo'] ?: 'Sem título'),
                'status' => (string)($d['status'] ?: '-'),
                'categoria' => (string)($d['categoria'] ?: '-'),
                'origem' => (string)($d['origem'] ?: '-'),
                'criado_em' => (string)($d['criado_em'] ?: '-'),
                'pessoa_nome' => (string)($d['pessoa_nome'] ?: '-'),
            ];
        }, $demandas),
    ]);
} catch (Throwable $e) {
    jsonOut([
        'ok' => false,
        'erro' => 'Erro ao consultar demandas recentes.',
    ], 500);
}