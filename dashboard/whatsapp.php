<?php
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

$pessoa_id = (int) $_SESSION['pessoa_id'];

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();

/* ================= POSTS ================= */
$stmt = $pdo->prepare("
    SELECT 
        sp.id,
        sp.titulo,
        sp.descricao,
        sp.link_instagram,
        sp.texto_compartilhamento,
        sp.pontos_base,
        sc.clicado_em,
        CASE WHEN sc.id IS NOT NULL THEN 1 ELSE 0 END AS ja_clicado,
        (
            SELECT COUNT(*) 
            FROM social_posts_cliques_app x 
            WHERE x.post_id = sp.id
              AND x.rede = 'whatsapp'
        ) AS total_visualizacoes
    FROM social_posts sp
    LEFT JOIN social_posts_cliques_app sc
        ON sc.post_id = sp.id
        AND sc.pessoa_id = ?
        AND sc.rede = 'whatsapp'
    WHERE sp.ativo = 'sim'
      AND sp.rede IN ('instagram','multiplas')
    ORDER BY ja_clicado ASC, sp.criado_em DESC
");
$stmt->execute([$pessoa_id]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

function resumoTexto(string $texto, int $limite = 150): string {
    $texto = trim(strip_tags($texto));
    if (mb_strlen($texto) <= $limite) return $texto;
    $cortado = mb_substr($texto, 0, $limite);
    $ultimoEspaco = mb_strrpos($cortado, ' ');
    if ($ultimoEspaco !== false) {
        $cortado = mb_substr($cortado, 0, $ultimoEspaco);
    }
    return $cortado . '...';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>WhatsApp</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#f4f6f9; font-family:system-ui; }
.card-post { border-radius:16px; overflow:hidden; }
.thumbnail { width:100%; height:220px; object-fit:cover; background:#ddd; }
.post-visto { opacity:0.8; background:#f8f9fa; }
.progress { height:6px; }
.titulo-post { font-weight:600; font-size:1rem; margin-bottom:6px; }
.descricao-post { font-size:0.9rem; color:#555; }
.meta-info { font-size:0.8rem; color:#888; }
</style>
</head>
<body>

<div class="container py-4">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Compartilhar no WhatsApp</h5>
    <a href="/dashboard/index.php" class="btn btn-sm btn-outline-secondary">
        ← Voltar
    </a>
</div>

<?php if (empty($posts)): ?>
    <div class="alert alert-info">
        Nenhuma postagem disponível no momento.
    </div>
<?php endif; ?>

<?php foreach ($posts as $post):

    $imagemPath = '';
    $possiblePath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/social/' . $post['id'] . '.jpg';

    if (file_exists($possiblePath)) {
        $imagemPath = '/uploads/social/' . $post['id'] . '.jpg';
    }

    $percentual = min(100, round(($post['total_visualizacoes'] / 50) * 100));

    $texto = $post['texto_compartilhamento'] ?: $post['titulo'];
    $mensagem = $texto . "\n\n" . $post['link_instagram'];
?>

<div class="card card-post mb-4 shadow-sm <?= $post['ja_clicado'] ? 'post-visto' : '' ?>">

    <?php if ($imagemPath): ?>
        <img src="<?= $imagemPath ?>" class="thumbnail">
    <?php endif; ?>

    <div class="card-body">

        <div class="d-flex justify-content-between align-items-start">
            <div class="titulo-post">
                <?= htmlspecialchars($post['titulo']) ?>
            </div>
            <span class="badge bg-success">
                +<?= (int)$post['pontos_base'] ?> pts
            </span>
        </div>

        <div class="descricao-post mb-2">
            <?= htmlspecialchars(resumoTexto($post['descricao'] ?? '')) ?>
        </div>

        <div class="meta-info mb-2">
            👁 <?= (int)$post['total_visualizacoes'] ?> compartilhamentos via app
        </div>

        <div class="progress mb-3">
            <div class="progress-bar bg-success" style="width: <?= $percentual ?>%"></div>
        </div>

        <?php if (!$post['ja_clicado']): ?>

            <button
                onclick="compartilharWhats(this, <?= (int)$post['id'] ?>, `<?= htmlspecialchars($mensagem, ENT_QUOTES) ?>`, <?= (int)$post['pontos_base'] ?>)"
                class="btn btn-success w-100">
                Compartilhar no WhatsApp e ganhar pontos
            </button>

        <?php else: ?>

            <div class="alert alert-success py-2 mb-2">
                ✅ Você ganhou <?= (int)$post['pontos_base'] ?> pontos.
            </div>

            <button class="btn btn-secondary w-100" disabled>
                Post já compartilhado
            </button>

        <?php endif; ?>

    </div>
</div>

<?php endforeach; ?>

</div>

<script>
function compartilharWhats(botao, postId, mensagem, pontos) {

    botao.disabled = true;
    botao.innerText = 'Processando...';

    fetch('whatsapp-click.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ id: postId })
    })
    .then(r => r.json())
    .then(resp => {

        if (resp.status === 'ok' || resp.status === 'ja_pontuado') {

            const alerta = document.createElement('div');
            alerta.className = 'alert alert-success py-2 mb-2';
            alerta.innerHTML = '✅ Você ganhou ' + pontos + ' pontos.';
            botao.parentNode.insertBefore(alerta, botao);

            botao.classList.remove('btn-success');
            botao.classList.add('btn-secondary');
            botao.innerText = 'Post já compartilhado';
            botao.disabled = true;

            window.open('https://wa.me/?text=' + encodeURIComponent(mensagem), '_blank');

        } else {
            botao.innerText = 'Erro ao registrar';
            botao.disabled = false;
        }

    })
    .catch(() => {
        botao.innerText = 'Erro de conexão';
        botao.disabled = false;
    });
}
</script>

</body>
</html>