<?php
/**
 * ======================================================
 * CAMINHO: app.elab.social/pessoas/ver.php
 * NOME: Ver Pessoa – Ficha Individual (APP)
 * PADRÃO: Ranking via vw_ranking_geral (OFICIAL)
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

/* ================= CORE ================= */
$CORE = '/home/elab/public_html/core';
require_once $CORE.'/data/config.php';
require_once $CORE.'/data/data.php';

$id = (int)($_GET['id'] ?? 0);
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
    $where = 'AND p.criado_por = ?';
    $params[] = $usuario_id;
}

/* ================= DADOS PRINCIPAIS ================= */
$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.nome,
        p.apelido,
        p.chamar_por,
        p.telefone,
        p.pontos,
        p.criado_em,
        p.criado_por,
        (
            SELECT MAX(logado_em)
            FROM pessoas_logins pl
            WHERE pl.pessoa_id = p.id
        ) AS ultimo_login
    FROM pessoas p
    WHERE p.id = ?
      AND p.status = 'ativo'
      $where
    LIMIT 1
");
$stmt->execute($params);
$pessoa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pessoa) {
    header('Location: /pessoas/');
    exit;
}

/* ================= RANKING OFICIAL ================= */
$stmt = $pdo->prepare("
    SELECT posicao
    FROM vw_ranking_geral
    WHERE pessoa_id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$rankingGeral = (int)$stmt->fetchColumn();

/* ================= INDICADOR ================= */
$indicador = null;
if (!empty($pessoa['criado_por'])) {
    $stmt = $pdo->prepare("
        SELECT nome, apelido, chamar_por
        FROM pessoas
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$pessoa['criado_por']]);
    $indicador = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* ================= ENDEREÇO ================= */
$stmt = $pdo->prepare("
    SELECT endereco, numero, bairro, cidade, estado, latitude, longitude
    FROM pessoas_enderecos
    WHERE pessoa_id = ?
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$id]);
$end = $stmt->fetch(PDO::FETCH_ASSOC);

$enderecoTxt = null;
$mapaUrl = null;

if ($end) {
    $enderecoTxt = trim(
        ($end['endereco'] ?? '') .
        ($end['numero'] ? ', '.$end['numero'] : '') .
        ($end['bairro'] ? ' - '.$end['bairro'] : '') .
        ($end['cidade'] ? ' - '.$end['cidade'].'/'.$end['estado'] : '')
    );

    if ($enderecoTxt) {
        $mapaUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($enderecoTxt);
    } elseif (!empty($end['latitude']) && !empty($end['longitude'])) {
        $mapaUrl = 'https://www.google.com/maps?q='.$end['latitude'].','.$end['longitude'];
    }
}

/* ================= FOTO ================= */
$fotoBasePath = "/home/elab/app.elab.social/uploads/perfil/";
$fotoBaseUrl  = "https://app.elab.social/uploads/perfil/";
$fotoUrl = null;

foreach (['png','jpg','jpeg'] as $ext) {
    if (is_file($fotoBasePath.$id.'.'.$ext)) {
        $fotoUrl = $fotoBaseUrl.$id.'.'.$ext;
        break;
    }
}

/* ================= HELPERS ================= */
function iniciais(string $n): string {
    $p = preg_split('/\s+/', trim($n));
    return strtoupper(substr($p[0],0,1).substr($p[1] ?? '',0,1));
}

function tempo(?string $dt): string {
    if (!$dt) return '—';
    $d = time() - strtotime($dt);
    if ($d < 60) return 'agora';
    if ($d < 3600) return floor($d/60).' min';
    if ($d < 86400) return floor($d/3600).' h';
    return date('d/m/Y', strtotime($dt));
}

function telefone(?string $t): string {
    if (!$t) return '—';
    $n = preg_replace('/\D/','',$t);
    if (strlen($n) === 11) {
        return sprintf('(%s) %s-%s',substr($n,0,2),substr($n,2,5),substr($n,7));
    }
    return $t;
}

$nomePrincipal = ($pessoa['chamar_por']==='apelido' && $pessoa['apelido'])
    ? $pessoa['apelido']
    : $pessoa['nome'];

$nomeSecundario = ($pessoa['chamar_por']==='apelido')
    ? $pessoa['nome']
    : $pessoa['apelido'];

$telLimpo = preg_replace('/\D/','',$pessoa['telefone'] ?? '');

/* ================= TOTAL DE INDICADOS ================= */
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM pessoas
    WHERE criado_por = ?
      AND status = 'ativo'
");
$stmt->execute([$id]);
$totalIndicados = (int)$stmt->fetchColumn();

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($nomePrincipal) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#f4f6f8; font-family:system-ui; }
.card { border-radius:18px; border:0; box-shadow:0 10px 24px rgba(0,0,0,.08); }
.avatar {
    width:96px;height:96px;border-radius:50%;
    background:#0b6e7a;color:#fff;
    display:flex;align-items:center;justify-content:center;
    font-size:34px;font-weight:800;
    margin:auto;overflow:hidden;
}
.avatar img { width:100%;height:100%;object-fit:cover; }
.name-main { font-size:20px;font-weight:700; }
.name-sub { font-size:13px;color:#6c757d; }
.meta { font-size:13px;color:#6c757d; }
.btn-action { padding:14px;font-weight:600;border-radius:12px; }
</style>
</head>
<body>

<div class="p-3">
    <a href="/pessoas/" class="btn btn-outline-success">← Voltar</a>
</div>

<div class="container mb-5">

    <div class="card p-4 text-center mb-3">
        <div class="avatar mb-2">
            <?php if ($fotoUrl): ?>
                <img src="<?= $fotoUrl ?>" alt="Foto">
            <?php else: ?>
                <?= iniciais($nomePrincipal) ?>
            <?php endif; ?>
        </div>

        <div class="name-main"><?= htmlspecialchars($nomePrincipal) ?></div>

        <?php if ($nomeSecundario): ?>
            <div class="name-sub"><?= htmlspecialchars($nomeSecundario) ?></div>
        <?php endif; ?>

        <div class="meta mt-2">
            🏆 Ranking Geral: <strong><?= $rankingGeral ?: '—' ?></strong><br>
            Último acesso: <?= tempo($pessoa['ultimo_login']) ?>
        </div>
    </div>

    <div class="card p-3 mb-3">
        <p><strong>Telefone:</strong> <?= telefone($pessoa['telefone']) ?></p>

        <?php if ($indicador): ?>
            <p><strong>Indicada por:</strong>
                <?= htmlspecialchars(
                    ($indicador['chamar_por']==='apelido' && $indicador['apelido'])
                        ? $indicador['apelido']
                        : $indicador['nome']
                ) ?>
            </p>
        <?php endif; ?>

        <?php if ($enderecoTxt): ?>
            <p><strong>Endereço:</strong> <?= htmlspecialchars($enderecoTxt) ?></p>
        <?php endif; ?>

        <p><strong>Cadastrada em:</strong> <?= date('d/m/Y', strtotime($pessoa['criado_em'])) ?></p>
       <p><strong>Pontos:</strong> <?= (int)$pessoa['pontos'] ?></p>
<p><strong>Indicados:</strong> <?= $totalIndicados ?></p>

    </div>

    <div class="card p-3">
        <div class="d-grid gap-2">
            <a href="/pessoas/ver-indicados.php?id=<?= $id ?>" class="btn btn-outline-primary btn-action">
                👥 Ver indicados
            </a>

            <a href="/chat/pessoa.php?id=<?= $id ?>" class="btn btn-outline-success btn-action">
                💬 Abrir chat
            </a>

            <?php if ($telLimpo): ?>
                <a href="https://wa.me/55<?= $telLimpo ?>" target="_blank"
                   class="btn btn-outline-success btn-action">
                    📲 WhatsApp
                </a>
            <?php endif; ?>

            <?php if ($mapaUrl): ?>
                <a href="<?= $mapaUrl ?>" target="_blank"
                   class="btn btn-outline-primary btn-action">
                    📍 Ver no mapa
                </a>
            <?php endif; ?>
        </div>
    </div>

</div>

</body>
</html>