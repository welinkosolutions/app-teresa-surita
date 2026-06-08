<?php

declare(strict_types=1);
ini_set('display_errors','1');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

if (empty($_SESSION['pessoa_id'])) {
    echo json_encode(['success'=>false,'error'=>'Não autenticado']);
    exit;
}

$pdo = dbRoraima();
$pessoa_id = (int)$_SESSION['pessoa_id'];

/*
=====================================================
1️⃣ Validar entrada
=====================================================
*/

$instagram_media_id = $_POST['instagram_media_id'] ?? '';
$tipo = $_POST['tipo'] ?? '';

$tiposValidos = ['curtir','comentar','compartilhar'];

if (!$instagram_media_id || !in_array($tipo,$tiposValidos)) {
    echo json_encode(['success'=>false,'error'=>'Parâmetros inválidos']);
    exit;
}

/*
=====================================================
2️⃣ Definir XP por tipo
=====================================================
*/

$xp = 0;
$status = 'validado';

if ($tipo === 'curtir') {
    $xp = 5;
}
elseif ($tipo === 'compartilhar') {
    $xp = 10;
}
elseif ($tipo === 'comentar') {
    $xp = 0; // só valida depois via sync
    $status = 'pendente';
}

/*
=====================================================
3️⃣ Inserir ação (evita duplicação)
=====================================================
*/

try {

    $stmt = $pdo->prepare("
        INSERT INTO social_meta_acoes
        (pessoa_id, instagram_media_id, tipo, status, xp_ganho)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $pessoa_id,
        $instagram_media_id,
        $tipo,
        $status,
        $xp
    ]);

} catch (PDOException $e) {

    // Código 23000 = duplicado
    if ($e->getCode() == 23000) {
        echo json_encode([
            'success'=>false,
            'error'=>'Ação já registrada'
        ]);
        exit;
    }

    echo json_encode(['success'=>false,'error'=>'Erro ao registrar ação']);
    exit;
}

/*
=====================================================
4️⃣ Atualizar XP se validado
=====================================================
*/

if ($status === 'validado' && $xp > 0) {

    $stmt = $pdo->prepare("
        INSERT INTO meta_usuario_xp (pessoa_id, xp_total, nivel)
        VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE
            xp_total = xp_total + VALUES(xp_total)
    ");

    $stmt->execute([$pessoa_id, $xp]);
}

/*
=====================================================
5️⃣ Retorno
=====================================================
*/

echo json_encode([
    'success'=>true,
    'tipo'=>$tipo,
    'xp_ganho'=>$xp,
    'status'=>$status
], JSON_PRETTY_PRINT);