<?php
declare(strict_types=1);

ini_set('display_errors','1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

date_default_timezone_set('America/Boa_Vista');
session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    echo json_encode([]);
    exit;
}

$pessoa_id = (int)$_SESSION['pessoa_id'];

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$q      = trim($_GET['q'] ?? '');
$offset = max(0,(int)($_GET['offset'] ?? 0));
$limit  = 20;

if ($q !== '' && mb_strlen($q) < 3) {
    echo json_encode([]);
    exit;
}

$pdo = dbRoraima();

$params = [
    ':lider' => $pessoa_id
];

$whereBusca = '';

if ($q !== '') {

    $condicoes = [];

    $qNorm = mb_strtolower($q);
    $params[':nome'] = $qNorm.'%';
    $condicoes[] = "LOWER(COALESCE(p.nome_busca,p.nome)) LIKE :nome";

    $params[':apelido'] = $qNorm.'%';
    $condicoes[] = "LOWER(p.apelido) LIKE :apelido";

    $digits = preg_replace('/\D/','',$q);
    if ($digits !== '') {
        $params[':tel'] = '%'.$digits.'%';
        $condicoes[] = "p.telefone LIKE :tel";
    }

    $whereBusca = 'AND ('.implode(' OR ',$condicoes).')';
}

$sql = "
SELECT
    p.id,
    p.nome,
    p.apelido,
    p.chamar_por,
    p.telefone,
    p.pontos,
    DATE_FORMAT(p.criado_em,'%d/%m/%Y %H:%i') as criado_em_formatado,
    p.latitude,
    p.longitude,

    (
        SELECT COUNT(*)
        FROM pessoas x
        WHERE x.criado_por = p.id
    ) as total_indicados

FROM pessoas p

WHERE p.status = 'ativo'
AND p.criado_por = :lider
$whereBusca

ORDER BY COALESCE(p.nome_busca,p.nome)
LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

$stmt->bindValue(':limit',$limit,PDO::PARAM_INT);
$stmt->bindValue(':offset',$offset,PDO::PARAM_INT);

foreach($params as $k=>$v){
    $stmt->bindValue($k,$v);
}

$stmt->execute();

echo json_encode(
    $stmt->fetchAll(PDO::FETCH_ASSOC),
    JSON_UNESCAPED_UNICODE
);

exit;