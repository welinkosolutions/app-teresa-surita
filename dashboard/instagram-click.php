<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/dashboard/instagram-click.php
 * NOME: Instagram – Registrar Clique + Pontuação
 * ======================================================
 */

declare(strict_types=1);

ini_set('display_errors','1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');
session_name('ELAB_APP_SESSION');
session_start();

/* ================= SEGURANÇA ================= */
if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

$pessoa_id = (int) $_SESSION['pessoa_id'];
$post_id   = (int) ($_GET['id'] ?? 0);

if ($post_id <= 0) {
    header('Location: /dashboard/instagram.php');
    exit;
}

/* ================= CORE ================= */
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();

/* ================= BUSCAR POST ================= */
$stmt = $pdo->prepare("
    SELECT id, link_instagram, pontos_base
    FROM social_posts
    WHERE id = ?
      AND ativo = 'sim'
      AND rede IN ('instagram','multiplas')
    LIMIT 1
");
$stmt->execute([$post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    header('Location: /dashboard/instagram.php');
    exit;
}

$link   = $post['link_instagram'];
$pontos = (int) $post['pontos_base'];

/* ================= TRANSAÇÃO ================= */
$pdo->beginTransaction();

try {

    /* ===== VERIFICAR SE JÁ EXISTE PARA INSTAGRAM ===== */
    $stmt = $pdo->prepare("
        SELECT id
        FROM social_posts_cliques_app
        WHERE post_id = ?
          AND pessoa_id = ?
          AND rede = 'instagram'
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$post_id, $pessoa_id]);
    $existe = $stmt->fetchColumn();

    if (!$existe) {

        /* ===== INSERT COM REDE DEFINIDA ===== */
        $stmt = $pdo->prepare("
            INSERT INTO social_posts_cliques_app
            (post_id, pessoa_id, rede, pontos_creditados, ip, user_agent, clicado_em)
            VALUES (?, ?, 'instagram', ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $post_id,
            $pessoa_id,
            $pontos,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250)
        ]);

        /* ===== ATUALIZAR PONTOS ===== */
        $stmt = $pdo->prepare("
            UPDATE pessoas
            SET pontos = pontos + ?
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$pontos, $pessoa_id]);
    }

    $pdo->commit();

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: /dashboard/instagram.php');
    exit;
}

/* ================= REDIRECT SEGURO ================= */
if (filter_var($link, FILTER_VALIDATE_URL)) {
    header('Location: ' . $link);
    exit;
}

header('Location: /dashboard/instagram.php');
exit;
