<?php
declare(strict_types=1);
date_default_timezone_set('America/Boa_Vista');

ini_set('display_errors','1');
error_reporting(E_ALL);

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();

/* ================= DATA ================= */

$data = $_GET['data'] ?? date('Y-m-d');

$dt = DateTime::createFromFormat('Y-m-d', $data);
if (!$dt) {
    http_response_code(400);
    exit('Data inválida');
}

$mesDia = $dt->format('m-d');
$ano    = (int)$dt->format('Y');

/* ================= BASE NOVA ================= */

$sqlNova = "
SELECT 
    id,
    nome,
    apelido,
    chamar_por,
    data_nascimento,
    telefone,
    vinculo
FROM pessoas
WHERE status='ativo'
AND data_nascimento IS NOT NULL
AND DATE_FORMAT(data_nascimento,'%m-%d') = :mesdia
";

$stmt = $pdo->prepare($sqlNova);
$stmt->execute([':mesdia'=>$mesDia]);
$nova = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= BASE ANTIGA ================= */

$sqlAntiga = "
SELECT 
    pa.id,
    pa.pessoa_raw_id,
    pa.nome,
    pa.apelido,
    pa.chamar_por,
    pa.data_nascimento,
    pa.telefone,
    GROUP_CONCAT(va.descricao_raw) AS vinculos_raw
FROM pessoas_antigo pa
LEFT JOIN vinculos_antigo va
    ON va.pessoa_raw_id = pa.pessoa_raw_id
WHERE pa.data_nascimento IS NOT NULL
AND pa.data_nascimento != '0001-01-01'
AND DATE_FORMAT(pa.data_nascimento,'%m-%d') = :mesdia
GROUP BY pa.id
";

$stmt = $pdo->prepare($sqlAntiga);
$stmt->execute([':mesdia'=>$mesDia]);
$antiga = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= IMPORTANTES ================= */

$importantes = [];

$stmtImp = $pdo->query("
    SELECT origem, pessoa_id, pessoa_raw_id
    FROM aniversarios_importantes
    WHERE ativo='sim'
");

while ($row = $stmtImp->fetch(PDO::FETCH_ASSOC)) {

    if ($row['origem'] === 'elab' && $row['pessoa_id']) {
        $importantes['nova'][(int)$row['pessoa_id']] = true;
    }

    if ($row['origem'] === 'legacy' && $row['pessoa_raw_id']) {
        $importantes['antiga'][(int)$row['pessoa_raw_id']] = true;
    }
}

/* ================= FUNÇÃO NOME ================= */

function nomeExibicao(array $r): string {
    if (
        isset($r['chamar_por']) &&
        $r['chamar_por'] === 'apelido' &&
        !empty($r['apelido'])
    ) {
        return $r['apelido'];
    }
    return $r['nome'];
}

/* ================= MONTAGEM ================= */

$items = [];

/* ===== BASE NOVA ===== */

foreach ($nova as $r){

    $id   = (int)$r['id'];
    $nome = nomeExibicao($r);

    $idade = null;
    if (!empty($r['data_nascimento'])) {
        $anoNasc = (int)substr($r['data_nascimento'],0,4);
        if ($anoNasc > 1900) {
            $idade = $ano - $anoNasc;
        }
    }

    $items[] = [
        'id'         => $id,
        'base'       => 'nova',
        'nome'       => $nome,
        'data_aniversario' => $dt->format('d/m'),
        'idade'      => $idade,
        'telefone'   => $r['telefone'] ?? null,
        'whatsapp'   => $r['telefone'] ? '55'.$r['telefone'] : null,
        'vinculo'    => $r['vinculo'] ?? 'geral',
        'importante' => isset($importantes['nova'][$id])
    ];
}

/* ===== BASE ANTIGA ===== */

foreach ($antiga as $r){

    $id   = (int)$r['id'];
    $nome = nomeExibicao($r);

    $idade = null;
    if (!empty($r['data_nascimento'])) {
        $anoNasc = (int)substr($r['data_nascimento'],0,4);
        if ($anoNasc > 1900) {
            $idade = $ano - $anoNasc;
        }
    }

    $vinculosRaw = strtoupper($r['vinculos_raw'] ?? '');

    // Hierarquia estratégica
    if (strpos($vinculosRaw, 'AUTORIDADE') !== false) {
        $vinculo = 'autoridade';
    }
    elseif (
        strpos($vinculosRaw, 'SINDICATO') !== false ||
        strpos($vinculosRaw, 'COOPERATIVA') !== false ||
        strpos($vinculosRaw, 'RELIG') !== false
    ) {
        $vinculo = 'familia';
    }
    elseif (strpos($vinculosRaw, 'GERAL') !== false) {
        $vinculo = 'geral';
    }
    else {
        $vinculo = 'geral';
    }

    $items[] = [
        'id'         => $id,
        'base'       => 'antiga',
        'nome'       => $nome,
        'data_aniversario' => $dt->format('d/m'),
        'idade'      => $idade,
        'telefone'   => $r['telefone'] ?? null,
        'whatsapp'   => $r['telefone'] ? '55'.$r['telefone'] : null,
        'vinculo'    => $vinculo,
        'importante' => isset($importantes['antiga'][$r['pessoa_raw_id'] ?? 0])
    ];
}

/* ================= FILTRO ================= */

$filtro = $_GET['filtro'] ?? null;

if ($filtro) {

    $items = array_filter($items, function($item) use ($filtro){

        switch ($filtro) {

            case 'autoridades':
                return $item['vinculo'] === 'autoridade';

            case 'familia':
                return $item['vinculo'] === 'familia';

            case 'geral':
                return $item['vinculo'] === 'geral';

            case 'importantes':
                return $item['importante'] === true;

            default:
                return true;
        }
    });
}

/* ================= CONTATO REALIZADO ================= */

$dataRef = $dt->format('Y-m-d');

$stmtContato = $pdo->prepare("
    SELECT origem, pessoa_id, pessoa_raw_id
    FROM aniversarios_contatos
    WHERE DATE(contatado_em) = ?
");

$stmtContato->execute([$dataRef]);
$contatosHoje = $stmtContato->fetchAll(PDO::FETCH_ASSOC);

$mapContato = [];

foreach ($contatosHoje as $c) {

    if ($c['origem'] === 'elab' && $c['pessoa_id']) {
        $mapContato['nova_'.$c['pessoa_id']] = true;
    }

    if ($c['origem'] === 'legacy' && $c['pessoa_raw_id']) {
        $mapContato['antiga_'.$c['pessoa_raw_id']] = true;
    }
}

foreach ($items as &$item) {

    $key = $item['base'].'_'.$item['id'];

    $item['contactado'] = isset($mapContato[$key]);
}
unset($item);


/* ================= ORDENAÇÃO ESTRATÉGICA ================= */

usort($items, function($a,$b){

    // Não contactados primeiro
    if ($a['contactado'] === $b['contactado']) {
        return strcmp($a['nome'], $b['nome']);
    }

    return $a['contactado'] ? 1 : -1;
});

/* ================= RESPONSE ================= */

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'header'=>[
        'data_formatada'=>$dt->format('d/m/Y')
    ],
    'total' => count($items),
    'items'=> array_values($items)
], JSON_UNESCAPED_UNICODE);

exit;