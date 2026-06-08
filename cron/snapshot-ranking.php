<?php
declare(strict_types=1);

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();

$dataHoje = date('Y-m-d');

/* Buscar ranking atual */
$stmt = $pdo->query("
    SELECT 
        r.pessoa_id,
        r.posicao,
        p.pontos
    FROM vw_ranking_geral r
    JOIN pessoas p ON p.id = r.pessoa_id
    WHERE p.status = 'ativo'
");

$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$insert = $pdo->prepare("
    INSERT INTO ranking_historico 
        (pessoa_id, pontos, posicao, snapshot_data)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        pontos = VALUES(pontos),
        posicao = VALUES(posicao)
");

foreach ($dados as $row) {
    $insert->execute([
        $row['pessoa_id'],
        $row['pontos'],
        $row['posicao'],
        $dataHoje
    ]);
}

echo "Snapshot concluído em {$dataHoje}\n";