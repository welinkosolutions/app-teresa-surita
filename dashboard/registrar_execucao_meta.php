<?php
declare(strict_types=1);

ini_set('display_errors','1');
error_reporting(E_ALL);
date_default_timezone_set('America/Boa_Vista');

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

header('Content-Type: application/json');

/* =========================
   1️⃣ VALIDA SESSÃO
========================= */
if (empty($_SESSION['pessoa_id'])) {
    echo json_encode(['ok' => false]);
    exit;
}

$pdo = dbRoraima();
$pessoa_id = (int)$_SESSION['pessoa_id'];

/* =========================
   2️⃣ 🔒 BLOQUEIO: EXIGE INSTAGRAM
========================= */
$stmt = $pdo->prepare("
    SELECT instagram_username, instagram
    FROM pessoas
    WHERE id=? 
    LIMIT 1
");
$stmt->execute([$pessoa_id]);
$pessoa = $stmt->fetch(PDO::FETCH_ASSOC);

$ig = trim((string)($pessoa['instagram_username'] ?? ''));
if ($ig === '') {
    $ig = trim((string)($pessoa['instagram'] ?? ''));
}

if ($ig === '') {
    echo json_encode(['ok' => false]);
    exit;
}

/* =========================
   3️⃣ VALIDA ENTRADA
========================= */
$post_id = (int)($_POST['post_id'] ?? 0);
$tipo    = $_POST['tipo'] ?? '';

if (!$post_id || !in_array($tipo, ['engajar','compartilhar'], true)) {
    echo json_encode(['ok' => false]);
    exit;
}

try {

    $pdo->beginTransaction();

    /* =========================
       4️⃣ BUSCA EXECUÇÃO
    ========================= */
    $stmt = $pdo->prepare("
        SELECT *
        FROM meta_post_execucoes
        WHERE pessoa_id=? AND post_id=?
        LIMIT 1
    ");
    $stmt->execute([$pessoa_id,$post_id]);
    $exec = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exec) {
        $pdo->rollBack();
        echo json_encode(['ok' => false]);
        exit;
    }

    $xpGanho = 0;

    /* =========================
       5️⃣ ENGAJAR
    ========================= */
    if ($tipo === 'engajar') {

        if ((int)$exec['curtiu'] === 0) {

            $xpGanho = 25;

            $pdo->prepare("
                UPDATE meta_post_execucoes
                SET curtiu = 1,
                    xp_ganho = xp_ganho + 25,
                    atualizado_em = NOW()
                WHERE pessoa_id=? AND post_id=?
            ")->execute([$pessoa_id,$post_id]);
        }
    }

    /* =========================
       6️⃣ COMPARTILHAR
    ========================= */
   if ($tipo === 'compartilhar') {

    $comp = (int)$exec['compartilhou'];

    if ($comp < 5) {

        $xpGanho = 15;

        $pdo->prepare("
            UPDATE meta_post_execucoes
            SET compartilhou = LEAST(compartilhou + 1, 5),
                xp_ganho = xp_ganho + 15,
                atualizado_em = NOW()
            WHERE pessoa_id=? AND post_id=?
        ")->execute([$pessoa_id,$post_id]);

    }

}
    /* =========================
       7️⃣ REGISTRA XP VITALÍCIO
    ========================= */
    if ($xpGanho > 0) {

        $origem = $tipo === 'engajar'
            ? 'missao_instagram_engajar'
            : 'missao_instagram_compartilhar';

        $pdo->prepare("
            INSERT INTO gamificacao_prestigio_historico
            (
                pessoa_id,
                pontos_vitalicios,
                origem,
                criado_em
            )
            VALUES (?, ?, ?, NOW())
        ")->execute([
            $pessoa_id,
            $xpGanho,
            $origem
        ]);
    }

    /* =========================
       8️⃣ BUSCA ESTADO FINAL
    ========================= */
    $stmt = $pdo->prepare("
        SELECT curtiu, comentou, compartilhou
        FROM meta_post_execucoes
        WHERE pessoa_id=? AND post_id=?
        LIMIT 1
    ");
    $stmt->execute([$pessoa_id,$post_id]);
    $final = $stmt->fetch(PDO::FETCH_ASSOC);

    $missaoConcluida = (
        (int)$final['curtiu'] === 1 &&
        (int)$final['comentou'] === 1 &&
        (int)$final['compartilhou'] >= 5
    );

if($missaoConcluida && empty($exec['concluida_em'])){

    $pdo->prepare("
        UPDATE meta_post_execucoes
        SET concluida_em = NOW()
        WHERE pessoa_id = ?
        AND post_id = ?
    ")->execute([$pessoa_id,$post_id]);
}

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'curtiu' => (int)$final['curtiu'],
        'comentou' => (int)$final['comentou'],
        'compartilhou' => (int)$final['compartilhou'],
        'xpGanho' => (int)$xpGanho,
        'missaoCompleta' => $missaoConcluida
    ]);
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode(['ok' => false]);
    exit;
}