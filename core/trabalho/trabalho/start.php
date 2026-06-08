<?php
/**
 * ============================================================
 * CAMINHO: app.elab.social/core/trabalho/start.php
 * NOME: Trabalho – Start (CORE)
 * DESCRIÇÃO:
 * - Marca usuário como online
 * - Inicia sessão de trabalho
 * - NÃO emite saída
 * - NÃO usa header / echo / exit
 * ============================================================
 */

declare(strict_types=1);

if (!isset($pdo, $pessoa_id)) {
    throw new RuntimeException('Contexto inválido para iniciar trabalho');
}

/* ================= MARCAR ONLINE ================= */
$pdo->prepare("
    UPDATE pessoas
    SET
        online_status = 'online',
        online_desde  = COALESCE(online_desde, NOW()),
        ultimo_ping   = NOW()
    WHERE id = ?
")->execute([$pessoa_id]);

/* ================= REGISTRAR INÍCIO ================= */
$pdo->prepare("
    INSERT INTO trabalho_sessoes
        (pessoa_id, iniciado_em, ip, user_agent)
    VALUES
        (?, NOW(), ?, ?)
")->execute([
    $pessoa_id,
    $_SERVER['REMOTE_ADDR'] ?? null,
    $_SERVER['HTTP_USER_AGENT'] ?? null
]);

/* ================= FLAG DE SESSÃO ================= */
$_SESSION['trabalho_ativo'] = true;
$_SESSION['trabalho_inicio'] = time();
