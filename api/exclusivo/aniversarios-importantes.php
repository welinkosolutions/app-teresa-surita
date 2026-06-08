<?php
/**
 * ======================================================
 * CAMINHO: crm.elab.social/api/public/aniversarios-importantes.php
 * NOME: API – Lista Pública de Aniversários Importantes
 * ======================================================
 */

declare(strict_types=1);

/* ================= ERROS (DEV) ================= */
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

/* ================= CORE ================= */
require_once '/home/elab/public_html/core/data/config.php';

/* ================= HEADERS ================= */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

date_default_timezone_set('America/Boa_Vista');

/* ================= PARAMS ================= */
$anchor    = $_GET['anchor']    ?? date('Y-m-d');
$direction = $_GET['direction'] ?? 'forward';
$limit     = 50;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchor)) {
    http_response_code(400);
    echo json_encode(['error'=>'anchor inválido']);
    exit;
}

$yearBase = (int)substr($anchor, 0, 4);
$op       = $direction === 'backward' ? '<' : '>';
$order    = $direction === 'backward' ? 'DESC' : 'ASC';

$pdo = db();

/* ======================================================
 * PREPARE — CONTATO HOJE
 * ====================================================== */
$stmtContatoHoje = $pdo->prepare("
    SELECT tipo_contato
    FROM aniversarios_contatos
    WHERE aniversario_importante_id = :id
      AND DATE(contatado_em) = CURDATE()
    ORDER BY contatado_em DESC
    LIMIT 1
");

/* ======================================================
 * ELAB
 * ====================================================== */
$sqlElab = "
SELECT
    ai.id AS aniversario_importante_id,
    'elab' AS origem,
    p.id AS pessoa_id,
    COALESCE(NULLIF(p.apelido,''), p.nome) AS nome,
    p.telefone,
    pi.nome AS indicador,
    ai.acao_midia,
    ai.comentario_midia,

    STR_TO_DATE(
        CONCAT(:year,'-',LPAD(pa.mes,2,'0'),'-',LPAD(pa.dia,2,'0')),
        '%Y-%m-%d'
    ) AS data_evento

FROM aniversarios_importantes ai
JOIN pessoas_aniversarios pa ON pa.pessoa_id = ai.pessoa_id
JOIN pessoas p ON p.id = ai.pessoa_id
LEFT JOIN pessoas pi ON pi.id = p.criado_por

WHERE ai.ativo = 'sim'
  AND ai.origem = 'elab'

HAVING data_evento $op :anchor
";

$stmt = $pdo->prepare($sqlElab);
$stmt->execute([
    ':year'   => $yearBase,
    ':anchor' => $anchor
]);
$elab = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ======================================================
 * LEGACY (sem acesso direto)
 * ====================================================== */
$sqlLegacy = "
SELECT
    id AS aniversario_importante_id,
    'legacy' AS origem,
    pessoa_raw_id AS pessoa_id,
    CONCAT('Registro Legacy #', pessoa_raw_id) AS nome,
    NULL AS telefone,
    NULL AS indicador,
    acao_midia,
    comentario_midia,
    marcado_em AS data_evento
FROM aniversarios_importantes
WHERE ativo='sim'
  AND origem='legacy'
  AND marcado_em $op :anchor
";

$stmt = $pdo->prepare($sqlLegacy);
$stmt->execute([':anchor'=>$anchor]);
$legacy = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ======================================================
 * UNIFICAR + ORDENAR
 * ====================================================== */
$lista = array_merge($elab, $legacy);

usort($lista, fn($a,$b) =>
    $order === 'ASC'
        ? strcmp($a['data_evento'],$b['data_evento'])
        : strcmp($b['data_evento'],$a['data_evento'])
);

$fat = array_slice($lista, 0, $limit);

/* ======================================================
 * OUTPUT
 * ====================================================== */
$items = [];

foreach ($fat as $r) {

    $stmtContatoHoje->execute([
        ':id' => (int)$r['aniversario_importante_id']
    ]);
    $tipoContatoHoje = $stmtContatoHoje->fetchColumn();

    $items[] = [
        'data'                => $r['data_evento'],
        'origem'              => $r['origem'],
        'pessoa_id'           => $r['pessoa_id'],
        'aniversario_id'      => $r['aniversario_importante_id'],
        'nome'                => $r['nome'],
        'telefone'            => $r['telefone'],
        'indicador'           => $r['indicador'],
        'badge'               => 'IMPORTANTE · '.($r['origem']==='elab'?'BASE ATUAL':'BASE ANTIGA'),
        'acao_midia'          => $r['acao_midia'],
        'comentario_midia'    => $r['comentario_midia'],

        // CONTATO
        'ja_contatado_hoje'   => $tipoContatoHoje ? true : false,
        'ultimo_contato_hoje' => $tipoContatoHoje ?: null
    ];
}

echo json_encode([
    'anchor'    => $anchor,
    'direction' => $direction,
    'count'     => count($items),
    'items'     => $items,
    'has_more'  => count($lista) > $limit
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);