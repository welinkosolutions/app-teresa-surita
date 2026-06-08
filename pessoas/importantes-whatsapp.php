<?php
declare(strict_types=1);

/**
 * ======================================================
 * CAMINHO: app.elab.social/pessoas/importantes-whatsapp.php
 * NOME: Importantes WhatsApp
 * DESCRIÇÃO:
 * - Lista nomes importantes de aniversarios_importantes
 * - Busca telefone em pessoas.telefone
 * - Botão "Falar no WhatsApp"
 * - Ao clicar, marca CONTACTADO
 * - Persistência usando observacoes com marcador [WHATSAPP_CONTACTADO]
 * ======================================================
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

$pdo = dbRoraima();
$pessoa_id = (int) $_SESSION['pessoa_id'];

/* ================= USUÁRIO ================= */
$stmtPessoa = $pdo->prepare("
    SELECT id, nome, apelido, chamar_por, status
    FROM pessoas
    WHERE id = ?
    LIMIT 1
");
$stmtPessoa->execute([$pessoa_id]);
$pessoa = $stmtPessoa->fetch(PDO::FETCH_ASSOC) ?: [];

if (!$pessoa || ($pessoa['status'] ?? '') !== 'ativo') {
    header('Location: /publico/logout.php');
    exit;
}

/* ================= ACESSO ESPECIAL ================= */
$stmtAcesso = $pdo->prepare("
    SELECT id
    FROM acessos_especiais
    WHERE tenant_cliente_id = 2
      AND pessoa_id = ?
      AND recurso = 'gestao_pessoas'
      AND status = 'ativo'
    LIMIT 1
");
$stmtAcesso->execute([$pessoa_id]);
$temAcessoGestaoPessoas = (bool) $stmtAcesso->fetchColumn();

if (!$temAcessoGestaoPessoas) {
    header('Location: /dashboard/index.php');
    exit;
}

/* ================= NOME EXIBIÇÃO ================= */
$nomeCompleto = trim((string)($pessoa['nome'] ?? ''));
$nomeExibicao = '';

if (($pessoa['chamar_por'] ?? '') === 'apelido' && !empty($pessoa['apelido'])) {
    $nomeExibicao = trim((string)$pessoa['apelido']);
} else {
    $partes = preg_split('/\s+/', $nomeCompleto);
    $nomeExibicao = implode(' ', array_slice($partes ?: [], 0, 2));
}

if ($nomeExibicao === '') {
    $nomeExibicao = 'Usuário';
}

/* ================= HELPERS ================= */
function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function limparTelefone(?string $telefone): string
{
    return preg_replace('/\D+/', '', (string)$telefone) ?? '';
}

function formatarTelefone(?string $telefone): string
{
    $digits = limparTelefone($telefone);

    if ($digits === '') {
        return '';
    }

    if (strlen($digits) === 11) {
        return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7));
    }

    if (strlen($digits) === 10) {
        return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6));
    }

    return $digits;
}

function nomeExibicaoLinha(array $row): string
{
    $apelido = trim((string)($row['apelido'] ?? ''));
    $chamarPor = trim((string)($row['chamar_por'] ?? ''));
    $nome = trim((string)($row['nome'] ?? ''));
    $nomeReferencia = trim((string)($row['nome_referencia'] ?? ''));

    if ($chamarPor === 'apelido' && $apelido !== '') {
        return $apelido;
    }

    if ($apelido !== '') {
        return $apelido;
    }

    if ($nome !== '') {
        return $nome;
    }

    if ($nomeReferencia !== '') {
        return $nomeReferencia;
    }

    return 'Sem nome';
}

const MARCADOR_CONTACTADO = '[WHATSAPP_CONTACTADO]';

/* ================= AJAX ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $action = trim((string)($_POST['action'] ?? ''));

    if ($action !== 'marcar_contactado') {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'erro' => 'Ação inválida'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $aiId = (int)($_POST['ai_id'] ?? 0);
    if ($aiId <= 0) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'erro' => 'ID inválido'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $stmtBusca = $pdo->prepare("
            SELECT id, COALESCE(observacoes, '') AS observacoes
            FROM aniversarios_importantes
            WHERE id = ?
              AND ativo = 'sim'
              AND lista_importante = 'sim'
            LIMIT 1
        ");
        $stmtBusca->execute([$aiId]);
        $registro = $stmtBusca->fetch(PDO::FETCH_ASSOC);

        if (!$registro) {
            throw new RuntimeException('Registro não encontrado.');
        }

        $observacoesAtuais = trim((string)($registro['observacoes'] ?? ''));
        $jaContactado = str_contains($observacoesAtuais, MARCADOR_CONTACTADO);

        if (!$jaContactado) {
            $carimbo = date('Y-m-d H:i:s');
            $bloco = MARCADOR_CONTACTADO . ' por ' . $pessoa_id . ' em ' . $carimbo;

            $novasObservacoes = $observacoesAtuais !== ''
                ? $observacoesAtuais . "\n" . $bloco
                : $bloco;

            $stmtUp = $pdo->prepare("
                UPDATE aniversarios_importantes
                SET observacoes = :observacoes,
                    atualizado_em = NOW()
                WHERE id = :id
                LIMIT 1
            ");
            $stmtUp->bindValue(':observacoes', $novasObservacoes, PDO::PARAM_STR);
            $stmtUp->bindValue(':id', $aiId, PDO::PARAM_INT);
            $stmtUp->execute();
        }

        echo json_encode([
            'ok' => true,
            'contactado' => 'sim'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'erro' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/* ================= LISTA ================= */
$lista = [];
$erroTela = '';
$totalImportantes = 0;
$totalContactados = 0;
$totalComWhatsapp = 0;

try {
    $sql = "
        SELECT
            ai.id,
            ai.origem,
            ai.pessoa_id,
            ai.pessoa_raw_id,
            ai.nome_referencia,
            ai.telefone_referencia,
            ai.prioridade,
            ai.ativo,
            ai.lista_importante,
            ai.observacoes,
            p.nome,
            p.apelido,
            p.chamar_por,
            p.telefone,
            p.status
        FROM aniversarios_importantes ai
        LEFT JOIN pessoas p
               ON p.id = ai.pessoa_id
        WHERE ai.ativo = 'sim'
          AND ai.lista_importante = 'sim'
          AND ai.pessoa_id IS NOT NULL
          AND ai.pessoa_id > 0
        ORDER BY ai.atualizado_em DESC, ai.id DESC
    ";

    $stmt = $pdo->query($sql);
    $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($lista as &$row) {
        $row['nome_exibicao'] = nomeExibicaoLinha($row);
        $row['telefone_final'] = trim((string)($row['telefone'] ?? '')) !== ''
            ? trim((string)$row['telefone'])
            : trim((string)($row['telefone_referencia'] ?? ''));

        $row['telefone_final_digits'] = limparTelefone($row['telefone_final']);
        $row['telefone_final_formatado'] = formatarTelefone($row['telefone_final']);
        $row['contactado'] = str_contains((string)($row['observacoes'] ?? ''), MARCADOR_CONTACTADO) ? 'sim' : 'nao';

        $totalImportantes++;

        if ($row['contactado'] === 'sim') {
            $totalContactados++;
        }

        if ($row['telefone_final_digits'] !== '') {
            $totalComWhatsapp++;
        }
    }
    unset($row);
} catch (Throwable $e) {
    $erroTela = $e->getMessage();
}
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Importantes WhatsApp • ELAB Social</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#1b2d52">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="ELAB Social">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root{
  --bg:#eef2f5;
  --card:#ffffff;
  --text:#102331;
  --muted:#5f7280;
  --brand1:#1b2d52;
  --brand2:#243b66;
  --brand3:#19b8c7;
  --shadow:0 12px 28px rgba(16,35,49,.10);
  --line:#dbe5ee;
  --soft:#edf3f8;
  --green:#16a34a;
  --green-soft:#ecfdf3;
  --green-line:#bbf7d0;
  --whats:#22c55e;
  --whats-dark:#16a34a;
  --danger-bg:#fff1f2;
  --danger-border:#fecdd3;
  --danger-text:#8b1e2d;
}

*{ box-sizing:border-box; }
html{ -webkit-text-size-adjust:100%; }

body{
  margin:0;
  background:linear-gradient(180deg,#eef2f5 0%, #e9eef2 100%);
  font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
  color:var(--text);
}

a, button, input, select, textarea{ font:inherit; }

.page-wrap{
  padding-bottom:110px;
}

.header{
  position:relative;
  z-index:1;
  background:
    radial-gradient(circle at top right, rgba(255,255,255,.14), transparent 32%),
    linear-gradient(135deg,var(--brand1),var(--brand2));
  color:#fff;
  padding:22px 16px 24px;
  border-radius:0 0 34px 34px;
  box-shadow:0 12px 36px rgba(27,45,82,.28);
}

.header-top{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:14px;
}

.header-left .eyebrow{
  font-size:13px;
  font-weight:900;
  opacity:.92;
  margin-bottom:4px;
}

.header-left h1{
  margin:0;
  font-size:22px;
  font-weight:1000;
  letter-spacing:-.25px;
  line-height:1.05;
}

.header-left .sub{
  margin-top:8px;
  font-size:14px;
  font-weight:700;
  opacity:.95;
  max-width:260px;
}

.btn-voltar{
  display:inline-flex;
  align-items:center;
  gap:8px;
  min-height:42px;
  padding:0 14px;
  border-radius:999px;
  color:#fff;
  text-decoration:none;
  background:rgba(255,255,255,.14);
  border:1px solid rgba(255,255,255,.16);
  font-size:13px;
  font-weight:900;
  white-space:nowrap;
}

.btn-voltar:hover{
  color:#fff;
  text-decoration:none;
}

.header-stats{
  margin-top:16px;
  display:grid;
  grid-template-columns:1fr 1fr 1fr;
  gap:10px;
}

.header-stat{
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.10);
  border-radius:18px;
  padding:14px 12px;
}

.header-stat strong{
  display:block;
  font-size:28px;
  line-height:1;
  font-weight:1000;
  margin-bottom:6px;
}

.header-stat span{
  display:block;
  font-size:13px;
  font-weight:700;
  color:rgba(255,255,255,.82);
}

.content{
  padding:16px;
}

.content-top{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  margin-bottom:12px;
}

.content-top h2{
  margin:0;
  font-size:18px;
  font-weight:1000;
  letter-spacing:-.2px;
}

.content-top .date{
  font-size:14px;
  font-weight:900;
  color:#718396;
}

.error-box{
  background:var(--danger-bg);
  border:1px solid var(--danger-border);
  color:var(--danger-text);
  border-radius:22px;
  padding:18px;
  box-shadow:var(--shadow);
}

.error-box h3{
  margin:0 0 8px;
  font-size:18px;
  font-weight:1000;
}

.error-box p{
  margin:0;
  font-size:14px;
  line-height:1.55;
  font-weight:700;
}

.empty-box{
  background:var(--card);
  border-radius:24px;
  padding:20px 18px;
  box-shadow:var(--shadow);
  border:1px solid #e8eef4;
}

.empty-box h3{
  margin:0 0 8px;
  font-size:18px;
  font-weight:1000;
}

.empty-box p{
  margin:0;
  font-size:14px;
  line-height:1.6;
  color:var(--muted);
  font-weight:700;
}

.list{
  display:grid;
  grid-template-columns:1fr;
  gap:14px;
}

.person-card{
  background:var(--card);
  border-radius:24px;
  padding:16px;
  box-shadow:var(--shadow);
  border:1px solid #e7edf3;
}

.person-card.is-contacted{
  border-color:var(--green-line);
  box-shadow:0 12px 30px rgba(34,197,94,.12);
}

.person-name{
  margin:0;
  font-size:18px;
  font-weight:1000;
  line-height:1.12;
  letter-spacing:-.2px;
}

.person-meta{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  margin-top:10px;
}

.pill{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:28px;
  padding:0 10px;
  border-radius:999px;
  font-size:11px;
  font-weight:900;
  border:1px solid #dbe5ef;
  background:var(--soft);
  color:#39526a;
}

.pill.green{
  background:var(--green-soft);
  color:#128043;
  border-color:#c8f2d7;
}

.phone-box{
  margin-top:12px;
  padding:12px;
  border-radius:16px;
  background:#f7fbff;
  border:1px solid #dfeaf6;
}

.phone-box small{
  display:block;
  font-size:11px;
  font-weight:900;
  color:#698196;
  margin-bottom:4px;
}

.phone-box strong{
  display:block;
  font-size:16px;
  line-height:1.3;
}

.card-actions{
  display:grid;
  grid-template-columns:1fr;
  gap:10px;
  margin-top:12px;
}

.btn-action{
  appearance:none;
  border:none;
  min-height:48px;
  border-radius:16px;
  padding:0 14px;
  font-size:15px;
  font-weight:1000;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:10px;
  cursor:pointer;
  text-decoration:none;
}

.btn-action:disabled{
  opacity:.7;
  cursor:default;
}

.btn-whats{
  background:linear-gradient(135deg,var(--whats),var(--whats-dark));
  color:#fff;
}

.btn-whats.disabled{
  background:#dbe5ee;
  color:#607284;
  pointer-events:none;
}

.toast-app{
  position:fixed;
  left:50%;
  bottom:24px;
  transform:translateX(-50%);
  background:#16253d;
  color:#fff;
  padding:12px 14px;
  border-radius:16px;
  font-size:13px;
  font-weight:900;
  box-shadow:0 14px 30px rgba(0,0,0,.18);
  z-index:100;
  opacity:0;
  pointer-events:none;
  transition:opacity .18s ease, transform .18s ease;
}

.toast-app.show{
  opacity:1;
  transform:translateX(-50%) translateY(-4px);
}

.footer-nav{
  position:fixed;
  left:0;
  right:0;
  bottom:0;
  z-index:20;
  background:rgba(255,255,255,.96);
  backdrop-filter:blur(10px);
  -webkit-backdrop-filter:blur(10px);
  border-top:1px solid #e6ecf1;
  box-shadow:0 -6px 20px rgba(0,0,0,.06);
  display:flex;
  justify-content:space-around;
  padding:10px 6px calc(10px + env(safe-area-inset-bottom));
}

.footer-nav a{
  flex:1;
  text-align:center;
  text-decoration:none;
  color:#5b6c78;
  font-size:12px;
  font-weight:800;
}

.footer-nav i{
  display:block;
  font-size:20px;
  margin-bottom:4px;
}

.footer-nav a.active,
.footer-nav a:hover{
  color:var(--brand3);
}

@media (min-width:700px){
  .page-wrap{
    max-width:680px;
    margin:0 auto;
  }

  .footer-nav{
    max-width:680px;
    margin:0 auto;
    left:50%;
    transform:translateX(-50%);
    border-radius:20px 20px 0 0;
  }
}

@media (max-width:560px){
  .header-stats{
    grid-template-columns:1fr;
  }
}
</style>
</head>
<body>

<div class="page-wrap">
  <div class="header">
    <div class="header-top">
      <div class="header-left">
                <h1>Lista pronta para Contato</h1>
           </div>

      <a href="/dashboard/index.php" class="btn-voltar">
        <i class="bi bi-chevron-left"></i>
        Voltar
      </a>
    </div>

    <div class="header-stats">
      <div class="header-stat">
        <strong><?= (int)$totalImportantes ?></strong>
        <span>Importantes</span>
      </div>

          <div class="header-stat">
        <strong><?= (int)$totalContactados ?></strong>
        <span>Contactados</span>
      </div>
    </div>
  </div>

  <div class="content">
    <div class="content-top">
      <h2>Boa tarde, <?= h($nomeExibicao) ?></h2>
      <div class="date"><?= date('d/m/Y') ?></div>
    </div>

    <?php if ($erroTela !== ''): ?>
      <div class="error-box">
        <h3>Não consegui montar a lista</h3>
        <p><?= h($erroTela) ?></p>
      </div>
    <?php elseif (!$lista): ?>
      <div class="empty-box">
        <h3>Nenhum importante no momento</h3>
        <p>Quando houver nomes marcados como importantes, eles aparecerão aqui para contato rápido no WhatsApp.</p>
      </div>
    <?php else: ?>
      <div class="list" id="whatsList">
        <?php foreach ($lista as $row): ?>
          <article
            class="person-card <?= $row['contactado'] === 'sim' ? 'is-contacted' : '' ?>"
            data-ai-id="<?= (int)$row['id'] ?>"
            data-contactado="<?= h($row['contactado']) ?>"
            data-telefone="<?= h($row['telefone_final_digits']) ?>"
          >
            <h3 class="person-name"><?= h($row['nome_exibicao']) ?></h3>

            <div class="person-meta">
              <span class="pill green">
                <i class="bi bi-star-fill"></i>&nbsp;Importante
              </span>

              <?php if ($row['contactado'] === 'sim'): ?>
                <span class="pill green badge-contactado">CONTACTADO</span>
              <?php endif; ?>
            </div>

            <div class="phone-box">
              <small>WhatsApp</small>
              <strong>
                <?= $row['telefone_final_formatado'] !== '' ? h($row['telefone_final_formatado']) : 'Sem telefone cadastrado' ?>
              </strong>
            </div>

            <div class="card-actions">
              <?php if ($row['telefone_final_digits'] !== ''): ?>
                <a
                  href="https://wa.me/55<?= h($row['telefone_final_digits']) ?>"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="btn-action btn-whats btn-falar-whatsapp"
                >
                  <i class="bi bi-whatsapp"></i>
                  Falar no WhatsApp
                </a>
              <?php else: ?>
                <span class="btn-action btn-whats disabled">
                  <i class="bi bi-slash-circle"></i>
                  Sem WhatsApp
                </span>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="footer-nav">
    <a href="/perfil/suporte.php">
      <i class="bi bi-headset"></i>
      Suporte
    </a>
    <a href="/comunidade/ranking.php">
      <i class="bi bi-trophy"></i>
      Ranking
    </a>
    <a href="/dashboard/index.php" class="active">
      <i class="bi bi-house"></i>
      Início
    </a>
    <a href="/pessoas/perfil.php">
      <i class="bi bi-person-circle"></i>
      Perfil
    </a>
    <a href="/publico/logout.php">
      <i class="bi bi-box-arrow-right"></i>
      Sair
    </a>
  </div>
</div>

<div class="toast-app" id="toastApp"></div>

<script>
(() => {
  const toast = document.getElementById('toastApp');
  let toastTimer = null;

  function showToast(message, isError = false) {
    toast.textContent = message;
    toast.style.background = isError ? '#8b1e2d' : '#16253d';
    toast.classList.add('show');

    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      toast.classList.remove('show');
    }, 2200);
  }

  function marcarVisualmente(card) {
    card.dataset.contactado = 'sim';
    card.classList.add('is-contacted');

    const meta = card.querySelector('.person-meta');
    let badge = card.querySelector('.badge-contactado');

    if (!badge && meta) {
      badge = document.createElement('span');
      badge.className = 'pill green badge-contactado';
      badge.textContent = 'CONTACTADO';
      meta.appendChild(badge);
    }
  }

  async function postData(formData) {
    const response = await fetch(window.location.pathname, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
      cache: 'no-store'
    });

    const json = await response.json().catch(() => null);

    if (!response.ok || !json || json.ok !== true) {
      throw new Error((json && json.erro) ? json.erro : 'Falha ao processar ação.');
    }

    return json;
  }

  document.addEventListener('click', async (event) => {
    const btn = event.target.closest('.btn-falar-whatsapp');
    if (!btn) return;

    const card = btn.closest('.person-card');
    if (!card) return;

    const aiId = card.dataset.aiId || '';
    if (!aiId) return;

    try {
      const fd = new FormData();
      fd.append('action', 'marcar_contactado');
      fd.append('ai_id', aiId);

      await postData(fd);
      marcarVisualmente(card);
      showToast('Marcado como CONTACTADO.');
    } catch (e) {
      event.preventDefault();
      showToast(e.message || 'Erro ao marcar contato.', true);
    }
  });
})();
</script>

</body>
</html>