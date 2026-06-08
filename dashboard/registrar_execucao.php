<?php
require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();
$pessoa_id = (int)$_SESSION['pessoa_id'];

$post_id = (int)($_POST['post_id'] ?? 0);
$tipo = $_POST['tipo'] ?? '';

if($post_id <= 0) exit;

if($tipo === 'engajamento'){
    $pdo->prepare("
        UPDATE meta_post_execucoes
        SET curtiu = 1,
            atualizado_em = NOW()
        WHERE pessoa_id = ?
        AND post_id = ?
    ")->execute([$pessoa_id, $post_id]);
}

if($tipo === 'compartilhamento'){
    $pdo->prepare("
        UPDATE meta_post_execucoes
        SET compartilhou = compartilhou + 1,
            atualizado_em = NOW()
        WHERE pessoa_id = ?
        AND post_id = ?
    ")->execute([$pessoa_id, $post_id]);
}