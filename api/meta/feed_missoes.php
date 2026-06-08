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
FUNÇÃO: Verificar se missão está concluída
=====================================================
*/
function missaoConcluida($pdo, $pessoa_id, $media_id) {

    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM social_meta_acoes
        WHERE pessoa_id=?
          AND instagram_media_id=?
          AND status='validado'
    ");
    $stmt->execute([$pessoa_id,$media_id]);
    $acoes = (int)$stmt->fetchColumn();

    // 3 ações = curtir + comentar + compartilhar
    return $acoes >= 3;
}

/*
=====================================================
1️⃣ Buscar Desafio Estratégico
=====================================================
*/

$stmt = $pdo->query("
    SELECT m.instagram_media_id,
           p.caption,
           p.media_url,
           p.permalink,
           m.xp_curtir,
           m.xp_comentar,
           m.xp_compartilhar
    FROM social_meta_missoes m
    JOIN meta_posts p 
      ON p.instagram_media_id=m.instagram_media_id
    WHERE m.ativa='sim'
      AND m.tipo='estrategico'
    ORDER BY m.prioridade DESC
    LIMIT 1
");

$desafio = $stmt->fetch(PDO::FETCH_ASSOC);

/*
=====================================================
2️⃣ Se não houver estratégico, pegar automático
=====================================================
*/

if (!$desafio) {

    $stmt = $pdo->query("
        SELECT p.instagram_media_id,
               p.caption,
               p.media_url,
               p.permalink,
               5  AS xp_curtir,
               15 AS xp_comentar,
               10 AS xp_compartilhar
        FROM meta_posts p
        ORDER BY p.publicado_em DESC
        LIMIT 1
    ");

    $desafio = $stmt->fetch(PDO::FETCH_ASSOC);
}

/*
=====================================================
3️⃣ Verificar status do desafio
=====================================================
*/

if ($desafio && missaoConcluida($pdo,$pessoa_id,$desafio['instagram_media_id'])) {
    $desafio = null;
}

/*
=====================================================
4️⃣ Buscar até 3 missões pendentes
=====================================================
*/

$stmt = $pdo->query("
    SELECT instagram_media_id,
           caption,
           media_url,
           permalink
    FROM meta_posts
    ORDER BY publicado_em DESC
    LIMIT 10
");

$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$feed = [];

foreach ($posts as $post) {

    if (count($feed) >= 3) break;

    if ($desafio && $post['instagram_media_id']===$desafio['instagram_media_id']) {
        continue;
    }

    if (!missaoConcluida($pdo,$pessoa_id,$post['instagram_media_id'])) {

        $feed[] = [
            'instagram_media_id'=>$post['instagram_media_id'],
            'caption'=>$post['caption'],
            'media_url'=>$post['media_url'],
            'permalink'=>$post['permalink']
        ];
    }
}

/*
=====================================================
5️⃣ Retorno final
=====================================================
*/

echo json_encode([
    'success'=>true,
    'desafio_do_dia'=>$desafio,
    'missoes_pendentes'=>$feed
], JSON_PRETTY_PRINT);