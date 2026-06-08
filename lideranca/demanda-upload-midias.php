<?php
declare(strict_types=1);

ini_set('display_errors','1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');

session_name('ELAB_APP_SESSION');
session_start();

header('Content-Type: application/json');

if (empty($_SESSION['pessoa_id'])) {
    echo json_encode([
        'ok' => false,
        'erro' => 'nao_autenticado'
    ]);
    exit;
}

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();

$usuarioId = (int)$_SESSION['pessoa_id'];
$demandaId = (int)($_POST['demanda_id'] ?? 0);

if ($demandaId <= 0) {
    echo json_encode([
        'ok' => false,
        'erro' => 'demanda_invalida'
    ]);
    exit;
}

/* ================= VALIDAR SE DEMANDA EXISTE ================= */

$stmt = $pdo->prepare("SELECT id FROM demandas WHERE id = ? LIMIT 1");
$stmt->execute([$demandaId]);

if (!$stmt->fetch()) {
    echo json_encode([
        'ok' => false,
        'erro' => 'demanda_nao_encontrada'
    ]);
    exit;
}

if (empty($_FILES['midias'])) {
    echo json_encode([
        'ok' => false,
        'erro' => 'nenhum_arquivo'
    ]);
    exit;
}

/* ================= CONFIGURAÇÕES ================= */

$permitidos = [
    'jpg','jpeg','png','webp',
    'mp4','mov',
    'mp3','wav',
    'pdf'
];

$limiteTamanho = 10 * 1024 * 1024; // 10MB

$baseUpload = '/home/elab/public_html/uploads/demandas/';
$pastaDemanda = $baseUpload . $demandaId . '/';

if (!is_dir($pastaDemanda)) {
    mkdir($pastaDemanda, 0755, true);
}

$respostaArquivos = [];

foreach ($_FILES['midias']['tmp_name'] as $i => $tmpName) {

    if (!is_uploaded_file($tmpName)) continue;

    $nomeOriginal = $_FILES['midias']['name'][$i];
    $tamanho = (int)$_FILES['midias']['size'][$i];

    if ($tamanho <= 0 || $tamanho > $limiteTamanho) continue;

    $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

    if (!in_array($ext, $permitidos)) continue;

    $mime = mime_content_type($tmpName);

    $tipo = 'arquivo';

    if (str_starts_with($mime, 'image/')) {
        $tipo = 'imagem';
    } elseif (str_starts_with($mime, 'video/')) {
        $tipo = 'video';
    } elseif (str_starts_with($mime, 'audio/')) {
        $tipo = 'audio';
    }

    $novoNome = uniqid('midia_', true) . '.' . $ext;
    $destino = $pastaDemanda . $novoNome;

    if (!move_uploaded_file($tmpName, $destino)) {
        continue;
    }

    $caminhoBanco = 'uploads/demandas/' . $demandaId . '/' . $novoNome;

    $stmt = $pdo->prepare("
        INSERT INTO demandas_midias
        (demanda_id, tipo, arquivo, mime, tamanho, criado_por)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $demandaId,
        $tipo,
        $caminhoBanco,
        $mime,
        $tamanho,
        $usuarioId
    ]);

    $respostaArquivos[] = [
        'arquivo' => $caminhoBanco,
        'tipo' => $tipo
    ];
}

echo json_encode([
    'ok' => true,
    'arquivos' => $respostaArquivos
]);

exit;