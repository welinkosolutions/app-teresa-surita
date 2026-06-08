<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();

$telefone = preg_replace('/\D/', '', $_POST['telefone'] ?? '');

if (strlen($telefone) < 10) {
    echo json_encode([
        'ok' => false,
        'erro' => 'telefone_invalido'
    ]);
    exit;
}

/* ================= BUSCA FLEXÍVEL ================= */

$possibilidades = [$telefone];

if (strlen($telefone) === 10) {
    $possibilidades[] = '0' . $telefone;
}

if (strlen($telefone) === 11 && str_starts_with($telefone, '0')) {
    $possibilidades[] = substr($telefone, 1);
}

$stmt = $pdo->prepare("
    SELECT id, nome, apelido, chamar_por, data_nascimento, criado_em
    FROM pessoas
    WHERE telefone IN (" . implode(',', array_fill(0, count($possibilidades), '?')) . ")
    LIMIT 1
");

$stmt->execute($possibilidades);
$pessoa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pessoa) {
    echo json_encode([
        'ok' => true,
        'existe' => false
    ]);
    exit;
}

$pessoaId = (int)$pessoa['id'];

/* ================= ENDEREÇO ================= */

$stmtEndereco = $pdo->prepare("
    SELECT 
        id,
        cep,
        endereco,
        numero,
        complemento,
        bairro,
        cidade,
        estado,
        latitude,
        longitude
    FROM pessoas_enderecos
    WHERE pessoa_id = ?
    ORDER BY id DESC
    LIMIT 1
");
$stmtEndereco->execute([$pessoaId]);

$endereco = $stmtEndereco->fetch(PDO::FETCH_ASSOC);
$temEndereco = $endereco ? true : false;

/* ================= DEMANDAS POR STATUS ================= */

$stmtDemandas = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN status = 'aberto' THEN 1 ELSE 0 END) as abertas,
        SUM(CASE WHEN status = 'em_atendimento' THEN 1 ELSE 0 END) as atendimento,
        SUM(CASE WHEN status = 'fechado' THEN 1 ELSE 0 END) as finalizadas
    FROM demandas
    WHERE pessoa_id = ?
");

$stmtDemandas->execute([$pessoaId]);
$d = $stmtDemandas->fetch(PDO::FETCH_ASSOC);

$abertas = (int)($d['abertas'] ?? 0);
$atendimento = (int)($d['atendimento'] ?? 0);
$finalizadas = (int)($d['finalizadas'] ?? 0);

/* ================= NOME EXIBIÇÃO ================= */

$nomeExibicao = $pessoa['nome'];

if ($pessoa['chamar_por'] === 'apelido' && !empty($pessoa['apelido'])) {
    $nomeExibicao = $pessoa['apelido'];
}

/* ================= RETORNO FINAL ================= */

echo json_encode([
    'ok' => true,
    'existe' => true,
    'pessoa' => [
        'id' => $pessoaId,
        'nome' => $pessoa['nome'],
        'apelido' => $pessoa['apelido'],
        'chamar_por' => $pessoa['chamar_por'],
        'nome_exibicao' => $nomeExibicao,
        'data_nascimento' => $pessoa['data_nascimento'],
        'criado_em' => $pessoa['criado_em']
    ],
    'endereco' => [
        'possui_endereco' => $temEndereco,
        'dados' => $temEndereco ? [
            'id' => (int)$endereco['id'],
            'cep' => $endereco['cep'],
            'endereco' => $endereco['endereco'],
            'numero' => $endereco['numero'],
            'complemento' => $endereco['complemento'],
            'bairro' => $endereco['bairro'],
            'cidade' => $endereco['cidade'],
            'estado' => $endereco['estado'],
            'latitude' => $endereco['latitude'],
            'longitude' => $endereco['longitude']
        ] : null
    ],
    'demandas' => [
        'abertas' => $abertas,
        'atendimento' => $atendimento,
        'finalizadas' => $finalizadas
    ]
]);

exit;