<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/suporte.php
 * NOME: Suporte — Abertura de Ticket
 * ======================================================
 */

declare(strict_types=1);
date_default_timezone_set('America/Boa_Vista');

session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    header('Location: index.php');
    exit;
}

$pessoa_id = (int) $_SESSION['pessoa_id'];

$CORE = '/home/elab/public_html/core';
require_once $CORE . '/data/config.php';
require_once $CORE . '/data/data.php';

$pdo = db();

/* ================= USUÁRIO ================= */
$stmt = $pdo->prepare("
SELECT id, nome, apelido, chamar_por, pontos
FROM pessoas
WHERE id = ?
");
$stmt->execute([$pessoa_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) die('Usuário inválido.');

$nome = ($user['chamar_por'] === 'apelido' && $user['apelido'])
    ? $user['apelido']
    : $user['nome'];

/* ================= CRIA TICKET ================= */
$assunto = 'Suporte via WhatsApp';

$stmt = $pdo->prepare("
INSERT INTO protocolo (pessoa_id, assunto, descricao, status)
VALUES (?, ?, ?, 'aberto')
");
$stmt->execute([
    $pessoa_id,
    $assunto,
    'Ticket iniciado via app'
]);

$ticketId = $pdo->lastInsertId();

/* ================= REGISTRA MENSAGEM ================= */
$stmt = $pdo->prepare("
INSERT INTO protocolo_mensagens
(protocolo_id, autor, mensagem)
VALUES (?, 'usuario', 'Solicitação iniciada via WhatsApp')
");
$stmt->execute([$ticketId]);

/* ================= WHATSAPP ================= */
$dataHora = date('d/m/Y H:i:s');

$mensagem = <<<TXT
Olá, sou {$nome} e preciso de suporte.

SUPORTE ABERTO

Ticket Nº: {$ticketId}
Data/Hora: {$dataHora}

Id do usuário: {$user['id']}
Pontos acumulados: {$user['pontos']}
TXT;

$link = 'https://wa.me/5595991288800?text=' . urlencode($mensagem);
header('Location: ' . $link);
exit;