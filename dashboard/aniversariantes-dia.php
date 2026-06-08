<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/aniversariantes-dia.php
 * NOME: Aniversariantes do Dia
 * DESCRIÇÃO:
 * - Lista aniversariantes do dia
 * - Apenas pessoas indicadas pelo usuário
 * - Botão para parabenizar via parabenizar.php
 * ======================================================
 */

declare(strict_types=1);

/* ================= ERROS (DEV) ================= */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/* ================= SESSION ================= */
date_default_timezone_set('America/Boa_Vista');
session_name('ELAB_APP_SESSION');
session_start();

/* ================= CORE ================= */
$CORE = '/home/elab/public_html/core';
require_once $CORE . '/data/config.php';
require_once $CORE . '/data/data.php';

/* ================= SEGURANÇA ================= */
if (empty($_SESSION['pessoa_id'])) {
    header('Location: index.php');
    exit;
}
$usuario_id = (int) $_SESSION['pessoa_id'];

/* ================= BANCO ================= */
$pdo = dbRoraima();

/* ================= DATA HOJE ================= */
$dia  = (int) date('d');
$mes  = (int) date('m');
$dataHoje = date('d/m/Y');

/* ================= BUSCAR ANIVERSARIANTES ================= */
$stmt = $pdo->prepare("
    SELECT
        id,
        nome,
        data_nascimento
    FROM pessoas
    WHERE status = 'ativo'
      AND criado_por = ?
      AND DAY(data_nascimento) = ?
      AND MONTH(data_nascimento) = ?
    ORDER BY nome
");
$stmt->execute([$usuario_id, $dia, $mes]);
$aniversariantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= FUNÇÃO IDADE ================= */
function calcularIdade(string $data): int {
    $nasc = new DateTime($data);
    $hoje = new DateTime();
    return $nasc->diff($hoje)->y;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Aniversariantes do Dia</title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background:#f4f6f8;
    font-family:system-ui;
}
a { text-decoration:none; }

.top-bar {
    position:sticky;
    top:0;
    z-index:30;
    background:#f4f6f8;
    padding:8px 12px;
}

.card-box {
    background:#fff;
    border-radius:16px;
    padding:16px;
    box-shadow:0 12px 28px rgba(0,0,0,.12);
    margin-bottom:16px;
}

.person {
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:12px 0;
    border-bottom:1px solid #eee;
}
.person:last-child {
    border-bottom:none;
}

.name {
    font-weight:600;
    font-size:15px;
}
.sub {
    font-size:13px;
    color:#6c757d;
}
</style>
</head>

<body>

<div class="top-bar">
    <a href="/dashboard/index.php" class="btn btn-outline-success">← Voltar</a>
</div>

<div class="container mt-3 mb-5 pb-4">

    <div class="card-box">
        <h6 class="fw-bold mb-3">
            🎂 Aniversariantes de hoje — <?= $dataHoje ?>
        </h6>

        <?php if (!$aniversariantes): ?>
            <div class="alert alert-secondary mb-0">
                Nenhum aniversariante hoje 🎈
            </div>
        <?php endif; ?>

        <?php foreach ($aniversariantes as $p): ?>
            <div class="person">
                <div>
                    <div class="name"><?= htmlspecialchars($p['nome']) ?></div>
                    <div class="sub">
                        <?= calcularIdade($p['data_nascimento']) ?> anos
                    </div>
                </div>

                <a
                    href="parabenizar.php?id=<?= (int)$p['id'] ?>"
                    class="btn btn-success btn-sm"
                >
                    🎉 Parabenizar
                </a>
            </div>
        <?php endforeach; ?>

    </div>

</div>
</body>
</html>