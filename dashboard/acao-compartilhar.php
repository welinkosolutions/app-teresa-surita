<?php
declare(strict_types=1);

ini_set('display_errors','1');
error_reporting(E_ALL);

header('Content-Type: application/json');

date_default_timezone_set('America/Boa_Vista');
session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    echo json_encode(['status'=>false,'msg'=>'Sessão inválida']);
    exit;
}

$pessoa_id = (int) $_SESSION['pessoa_id'];

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();

/* ================= INPUT ================= */
$input = json_decode(file_get_contents('php://input'), true);

$postId = isset($input['id']) ? (int)$input['id'] : 0;
$rede   = isset($input['rede']) ? trim($input['rede']) : '';

$redesValidas = ['facebook','instagram','threads','tiktok','youtube','whatsapp'];

if (!$postId || !in_array($rede, $redesValidas, true)) {
    echo json_encode(['status'=>false,'msg'=>'Dados inválidos']);
    exit;
}

/* ================= BUSCAR POST ================= */
$stmt = $pdo->prepare("
    SELECT id, rede, pontos_base, limite_execucoes, ativo
    FROM social_posts
    WHERE id = :id
    LIMIT 1
");
$stmt->bindValue(':id', $postId, PDO::PARAM_INT);
$stmt->execute();
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post || $post['ativo'] !== 'sim') {
    echo json_encode(['status'=>false,'msg'=>'Post inválido']);
    exit;
}

/* ================= VALIDAR REDE DO POST ================= */
if ($post['rede'] !== 'multiplas' && $post['rede'] !== $rede && $rede !== 'whatsapp') {
    echo json_encode(['status'=>false,'msg'=>'Rede não permitida para este post']);
    exit;
}

/* ================= DEFINIR LIMITE ================= */
$limite = ($rede === 'whatsapp')
    ? (int)$post['limite_execucoes']
    : 1;

/* ================= BUSCAR EXECUÇÃO ATUAL ================= */
$stmt = $pdo->prepare("
    SELECT id, quantidade_execucoes
    FROM social_posts_cliques_app
    WHERE post_id = :post
    AND pessoa_id = :pessoa
    AND rede = :rede
    LIMIT 1
");
$stmt->bindValue(':post', $postId, PDO::PARAM_INT);
$stmt->bindValue(':pessoa', $pessoa_id, PDO::PARAM_INT);
$stmt->bindValue(':rede', $rede, PDO::PARAM_STR);
$stmt->execute();
$registro = $stmt->fetch(PDO::FETCH_ASSOC);

$execAtual = $registro ? (int)$registro['quantidade_execucoes'] : 0;

if ($execAtual >= $limite) {
    echo json_encode(['status'=>false,'msg'=>'Limite atingido']);
    exit;
}

/* ================= TRANSAÇÃO ================= */
try {

    $pdo->beginTransaction();

    $novaExec = $execAtual + 1;
    $pontos   = (int)$post['pontos_base'];

    if ($registro) {

        $stmt = $pdo->prepare("
            UPDATE social_posts_cliques_app
            SET quantidade_execucoes = :qtd,
                pontos_creditados = pontos_creditados + :pontos,
                clicado_em = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':qtd' => $novaExec,
            ':pontos' => $pontos,
            ':id' => $registro['id']
        ]);

    } else {

        $stmt = $pdo->prepare("
            INSERT INTO social_posts_cliques_app
            (post_id, rede, pessoa_id, quantidade_execucoes, pontos_creditados, ip, user_agent)
            VALUES
            (:post, :rede, :pessoa, 1, :pontos, :ip, :ua)
        ");
        $stmt->execute([
            ':post' => $postId,
            ':rede' => $rede,
            ':pessoa' => $pessoa_id,
            ':pontos' => $pontos,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

    /* ================= CREDITAR PONTOS ================= */
    $stmt = $pdo->prepare("
        UPDATE pessoas
        SET pontos = pontos + :pontos
        WHERE id = :id
    ");
    $stmt->execute([
        ':pontos' => $pontos,
        ':id' => $pessoa_id
    ]);

    $pdo->commit();

    echo json_encode([
        'status'=>true,
        'nova_execucao'=>$novaExec,
        'pontos_creditados'=>$pontos
    ]);

} catch (Exception $e) {

    $pdo->rollBack();
    echo json_encode(['status'=>false,'msg'=>'Erro interno']);

}