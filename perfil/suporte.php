<?php
declare(strict_types=1);

session_start();

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';
require_once '/home/elab/public_html/core/missao/bootstrap.php';
require_once '/home/elab/public_html/core/missao/planner.php';
require_once '/home/elab/public_html/core/missao/ciclo.php';

$pdo = dbRoraima();

if (empty($_SESSION['pessoa_id'])) {
    exit('Sessão inválida');
}

/*
=====================================
HELPERS
=====================================
*/
function e($v){ return htmlspecialchars((string)$v); }

function gerarProtocolo(int $id): string {
    return 'SUP-' . date('Ymd-His') . '-' . $id;
}

/*
=====================================
AÇÕES
=====================================
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pessoaId = (int)($_POST['pessoa_id'] ?? 0);
    $acao     = $_POST['acao'] ?? '';

    if ($pessoaId <= 0) exit('ID inválido');

    try {

        /*
        ===============================
        RESET MISSÃO (REAL)
        ===============================
        */
        if ($acao === 'resetar_missao') {

            // 1. Limpa missão do dia
            $stmt = $pdo->prepare("
                DELETE FROM missao_estado_usuario
                WHERE pessoa_id = ?
                  AND data_referencia = CURDATE()
            ");
            $stmt->execute([$pessoaId]);

            // 2. Reset ciclo
            missaoCicloResetar($pdo, $pessoaId, 0, 'instagram', '');

            // 3. Regenera missão
            missaoGerarMissaoDoDia($pdo, $pessoaId, true);

            $msg = "Missão resetada com sucesso 🚀";
        }

        /*
        ===============================
        LIMPAR CACHE APP
        ===============================
        */
        if ($acao === 'limpar_cache') {

            $pdo->prepare("DELETE FROM app_push_subscriptions WHERE pessoa_id=?")->execute([$pessoaId]);
            $pdo->prepare("DELETE FROM push_dispositivos WHERE pessoa_id=?")->execute([$pessoaId]);
            $pdo->prepare("DELETE FROM pessoas_login_persistente WHERE pessoa_id=?")->execute([$pessoaId]);
            $pdo->prepare("DELETE FROM pessoas_tokens WHERE pessoa_id=? AND tipo='login'")->execute([$pessoaId]);

            $msg = "Cache limpo / dispositivo resetado 📱";
        }

        /*
        ===============================
        APROVAR PUSH
        ===============================
        */
        if ($acao === 'aprovar_push') {
            $pdo->prepare("
                UPDATE push_dispositivos
                SET ativo='sim', atualizado_em=NOW()
                WHERE pessoa_id=?
            ")->execute([$pessoaId]);

            $msg = "Push reativado 🔔";
        }

        /*
        ===============================
        APROVAR NOTIFICAÇÃO
        ===============================
        */
        if ($acao === 'aprovar_notificacao') {
            $pdo->prepare("
                UPDATE push_dispositivos
                SET push_permissao='granted', ativo='sim'
                WHERE pessoa_id=?
            ")->execute([$pessoaId]);

            $msg = "Permissão de notificação aplicada 🔔";
        }

        /*
        ===============================
        INSTAGRAM
        ===============================
        */
        if ($acao === 'add_instagram') {

            $insta = trim($_POST['valor'] ?? '');

            $pdo->prepare("
                UPDATE pessoas
                SET instagram=?,
                    instagram_username=?,
                    usa_instagram='sim',
                    instagram_confirmado='sim'
                WHERE id=?
            ")->execute([$insta, str_replace('@','',$insta), $pessoaId]);

            $msg = "Instagram atualizado 📸";
        }

        if ($acao === 'del_instagram') {
            $pdo->prepare("
                UPDATE pessoas
                SET instagram=NULL,
                    instagram_username=NULL,
                    usa_instagram='nao'
                WHERE id=?
            ")->execute([$pessoaId]);

            $msg = "Instagram removido ❌";
        }

        /*
        ===============================
        FACEBOOK
        ===============================
        */
        if ($acao === 'add_facebook') {

            $fb = trim($_POST['valor'] ?? '');

            $pdo->prepare("
                UPDATE pessoas
                SET facebook=?,
                    facebook_username=?,
                    usa_facebook='sim',
                    facebook_confirmado='sim'
                WHERE id=?
            ")->execute([$fb, $fb, $pessoaId]);

            $msg = "Facebook atualizado 👍";
        }

        if ($acao === 'del_facebook') {
            $pdo->prepare("
                UPDATE pessoas
                SET facebook=NULL,
                    facebook_username=NULL,
                    usa_facebook='nao'
                WHERE id=?
            ")->execute([$pessoaId]);

            $msg = "Facebook removido ❌";
        }

    } catch (Throwable $e) {
        $msg = "Erro: " . $e->getMessage();
    }
}

/*
=====================================
BUSCA USUÁRIO
=====================================
*/
$pessoaId = (int)($_GET['pessoa_id'] ?? 0);
$user = null;

if ($pessoaId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM pessoas WHERE id=?");
    $stmt->execute([$pessoaId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Suporte ELAB</title>

<style>
body{background:#0f172a;color:#fff;font-family:Arial;padding:20px;}
.card{background:#1e293b;padding:20px;border-radius:12px;margin-bottom:15px;}
button{padding:10px 14px;border:0;border-radius:8px;cursor:pointer;font-weight:bold;}
.ok{background:#16a34a}
.warn{background:#f59e0b}
.danger{background:#dc2626}
.info{background:#2563eb}
input{padding:10px;border-radius:8px;border:1px solid #334155;background:#020617;color:#fff;}
</style>

</head>
<body>

<h2>Painel de Suporte</h2>

<form method="get">
<input type="number" name="pessoa_id" placeholder="ID usuário">
<button class="info">Buscar</button>
</form>

<?php if (!empty($msg)): ?>
<div class="card"><?= e($msg) ?></div>
<?php endif; ?>

<?php if ($user): ?>

<div class="card">
<h3><?= e($user['nome']) ?> (#<?= $user['id'] ?>)</h3>
<p>Instagram: <?= e($user['instagram'] ?? '-') ?></p>
<p>Facebook: <?= e($user['facebook'] ?? '-') ?></p>
</div>

<div class="card">
<h3>Missão</h3>
<form method="post">
<input type="hidden" name="pessoa_id" value="<?= $user['id'] ?>">
<input type="hidden" name="acao" value="resetar_missao">
<button class="warn">LIBERAR MISSÃO</button>
</form>
</div>

<div class="card">
<h3>Push / App</h3>

<form method="post">
<input type="hidden" name="pessoa_id" value="<?= $user['id'] ?>">
<input type="hidden" name="acao" value="aprovar_push">
<button class="ok">Aprovar Push</button>
</form>

<form method="post">
<input type="hidden" name="pessoa_id" value="<?= $user['id'] ?>">
<input type="hidden" name="acao" value="aprovar_notificacao">
<button class="info">Aprovar Notificação</button>
</form>

<form method="post">
<input type="hidden" name="pessoa_id" value="<?= $user['id'] ?>">
<input type="hidden" name="acao" value="limpar_cache">
<button class="danger">Limpar Cache App</button>
</form>

</div>

<div class="card">
<h3>Instagram</h3>

<form method="post">
<input type="hidden" name="pessoa_id" value="<?= $user['id'] ?>">
<input type="hidden" name="acao" value="add_instagram">
<input name="valor" placeholder="@usuario">
<button class="ok">Salvar</button>
</form>

<form method="post">
<input type="hidden" name="pessoa_id" value="<?= $user['id'] ?>">
<input type="hidden" name="acao" value="del_instagram">
<button class="danger">Remover</button>
</form>

</div>

<div class="card">
<h3>Facebook</h3>

<form method="post">
<input type="hidden" name="pessoa_id" value="<?= $user['id'] ?>">
<input type="hidden" name="acao" value="add_facebook">
<input name="valor" placeholder="usuario">
<button class="ok">Salvar</button>
</form>

<form method="post">
<input type="hidden" name="pessoa_id" value="<?= $user['id'] ?>">
<input type="hidden" name="acao" value="del_facebook">
<button class="danger">Remover</button>
</form>

</div>

<div class="card">
<h3>Suporte</h3>

<?php
$protocolo = gerarProtocolo($user['id']);
$link = "https://wa.me/5595981288800?text=" .
    urlencode("Nome: {$user['nome']}\nID: {$user['id']}\nProtocolo: {$protocolo}");
?>

<a href="<?= $link ?>" target="_blank">
<button class="ok">Abrir WhatsApp</button>
</a>

</div>

<?php endif; ?>

</body>
</html>