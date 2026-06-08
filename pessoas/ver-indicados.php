<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/pessoas/ver-indicados.php
 * NOME: Ver Indicados – Lista Direta
 * ======================================================
 */

declare(strict_types=1);

ini_set('display_errors','1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');
session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

$usuario_id = (int)$_SESSION['pessoa_id'];

$CORE = '/home/elab/public_html/core';
require_once $CORE.'/data/config.php';
require_once $CORE.'/data/data.php';

/* ================= INPUT ================= */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: /pessoas/');
    exit;
}

$pdo = dbRoraima();

/* ================= PERFIL LOGADO ================= */
$stmt = $pdo->prepare("SELECT perfil FROM pessoas WHERE id = ? LIMIT 1");
$stmt->execute([$usuario_id]);
$perfil = $stmt->fetchColumn() ?: 'pessoa';

/* ================= GOVERNANÇA ================= */
$where = '';
$params = [$id];

if (!in_array($perfil, ['admin','gestor','lider','agente','midia','candidato'], true)) {
    $where = 'AND p2.criado_por = ?';
    $params[] = $usuario_id;
}

/* ================= PESSOA BASE ================= */
$stmt = $pdo->prepare("
    SELECT id, nome, apelido, chamar_por
    FROM pessoas
    WHERE id = ?
      AND status = 'ativo'
    LIMIT 1
");
$stmt->execute([$id]);
$pessoaBase = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pessoaBase) {
    header('Location: /pessoas/');
    exit;
}

$nomeBase = ($pessoaBase['chamar_por']==='apelido' && $pessoaBase['apelido'])
    ? $pessoaBase['apelido']
    : $pessoaBase['nome'];

/* ================= LISTA DE INDICADOS ================= */
$stmt = $pdo->prepare("
    SELECT
        p2.id,
        p2.nome,
        p2.apelido,
        p2.chamar_por,
        p2.pontos,
        p2.criado_em
    FROM pessoas p2
    WHERE p2.criado_por = ?
      AND p2.status = 'ativo'
      $where
    ORDER BY p2.pontos DESC, p2.criado_em ASC
");
$stmt->execute($params);
$indicados = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= HELPERS ================= */
function iniciais(string $n): string {
    $p = preg_split('/\s+/', trim($n));
    return strtoupper(substr($p[0],0,1).substr($p[1] ?? '',0,1));
}

function nomeExibicao(array $p): string {
    if ($p['chamar_por']==='apelido' && !empty($p['apelido'])) {
        return $p['apelido'];
    }
    return $p['nome'];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Indicados de <?= htmlspecialchars($nomeBase) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#f4f6f8; font-family:system-ui; }
.card { border-radius:16px; border:0; box-shadow:0 10px 24px rgba(0,0,0,.08); }
.avatar {
    width:42px;height:42px;border-radius:50%;
    background:#0b6e7a;color:#fff;
    display:flex;align-items:center;justify-content:center;
    font-weight:700;flex-shrink:0;
}
.nome-main { font-weight:600; }
.nome-sub { font-size:12px;color:#6c757d; }
</style>
</head>
<body>

<div class="p-3">
    <a href="/pessoas/ver.php?id=<?= $id ?>" class="btn btn-outline-success">← Voltar</a>
</div>

<div class="container mb-5">

    <div class="card p-3 mb-3 text-center">
        <h6 class="fw-bold mb-1">👥 Indicados por</h6>
        <div><?= htmlspecialchars($nomeBase) ?></div>
        <div class="text-muted small">
            Total: <?= count($indicados) ?>
        </div>
    </div>

    <?php if (!$indicados): ?>
        <div class="alert alert-secondary text-center">
            Nenhum indicado cadastrado.
        </div>
    <?php else: ?>
        <?php foreach ($indicados as $p): ?>
            <div class="card p-3 mb-2">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar"><?= iniciais(nomeExibicao($p)) ?></div>
                        <div>
                            <div class="nome-main"><?= htmlspecialchars(nomeExibicao($p)) ?></div>
                            <?php if ($p['apelido'] && $p['chamar_por']!=='apelido'): ?>
                                <div class="nome-sub"><?= htmlspecialchars($p['apelido']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold"><?= (int)$p['pontos'] ?> pts</div>
                        <a href="/pessoas/ver.php?id=<?= (int)$p['id'] ?>"
                           class="btn btn-sm btn-outline-success mt-1">
                            Ver
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

</body>
</html>