<?php
declare(strict_types=1);

/**
 * ======================================================
 * CAMINHO: app.elab.social/pessoas/aceitar-convites.php
 * NOME: Aprovar Convites / Ativar Cadastros
 * DESCRIÇÃO:
 * - Lista pendências de ativação do convidador logado
 * - Permite aprovar ou recusar manualmente
 * - Ao aprovar:
 *   -> usa core/invite/engine.php
 *   -> aprova cadastro
 *   -> cria rede_indicacoes
 *   -> distribui XP multinível
 *   -> aplica bônus diário
 * - Ao recusar:
 *   -> marca aprovação como recusada
 *   -> marca status_validacao do convidado como recusado
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
require_once '/home/elab/public_html/core/invite/engine.php';

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

$pdo = dbRoraima();
$pessoa_id = (int) $_SESSION['pessoa_id'];

if (!function_exists('acFlashSet')) {
    function acFlashSet(string $tipo, string $mensagem): void
    {
        $_SESSION['aceitar_convites_flash'] = [
            'tipo' => $tipo,
            'mensagem' => $mensagem,
        ];
    }
}

if (!function_exists('acFlashGet')) {
    function acFlashGet(): ?array
    {
        if (empty($_SESSION['aceitar_convites_flash']) || !is_array($_SESSION['aceitar_convites_flash'])) {
            return null;
        }

        $flash = $_SESSION['aceitar_convites_flash'];
        unset($_SESSION['aceitar_convites_flash']);

        return $flash;
    }
}

if (!function_exists('acEsc')) {
    function acEsc(?string $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('acNomeExibicao')) {
    function acNomeExibicao(array $pessoa): string
    {
        $nome = trim((string) ($pessoa['nome'] ?? ''));
        $apelido = trim((string) ($pessoa['apelido'] ?? ''));
        $chamarPor = trim((string) ($pessoa['chamar_por'] ?? ''));

        if ($chamarPor === 'apelido' && $apelido !== '') {
            return $apelido;
        }

        if ($nome === '') {
            return 'Participante';
        }

        $partes = preg_split('/\s+/', $nome);
        return trim(implode(' ', array_slice($partes ?: [], 0, 2)));
    }
}

if (!function_exists('acDataHora')) {
    function acDataHora(?string $valor): string
    {
        if (!$valor) {
            return '—';
        }

        $ts = strtotime($valor);
        if ($ts === false) {
            return '—';
        }

        return date('d/m/Y H:i', $ts);
    }
}

if (!function_exists('acJsonFlags')) {
    function acJsonFlags(?string $json): array
    {
        if (!$json) {
            return [];
        }

        $arr = json_decode($json, true);
        if (!is_array($arr)) {
            return [];
        }

        $saida = [];
        foreach ($arr as $k => $v) {
            if (is_bool($v)) {
                $saida[] = $k . ': ' . ($v ? 'sim' : 'nao');
            } elseif (is_scalar($v)) {
                $saida[] = $k . ': ' . (string) $v;
            }
        }

        return $saida;
    }
}

if (!function_exists('acGarantirCsrf')) {
    function acGarantirCsrf(): string
    {
        if (empty($_SESSION['aceitar_convites_csrf']) || !is_string($_SESSION['aceitar_convites_csrf'])) {
            $_SESSION['aceitar_convites_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['aceitar_convites_csrf'];
    }
}

if (!function_exists('acValidarCsrf')) {
    function acValidarCsrf(?string $token): bool
    {
        $sess = (string) ($_SESSION['aceitar_convites_csrf'] ?? '');
        $token = (string) $token;

        if ($sess === '' || $token === '') {
            return false;
        }

        return hash_equals($sess, $token);
    }
}

$csrfToken = acGarantirCsrf();

/*
========================================
USUÁRIO LOGADO
========================================
*/
$stmt = $pdo->prepare("
    SELECT id, nome, apelido, chamar_por, status, perfil
    FROM pessoas
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$pessoa_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

if (!$usuario || ($usuario['status'] ?? '') !== 'ativo') {
    header('Location: /publico/logout.php');
    exit;
}

$nomeUsuario = acNomeExibicao($usuario);

/*
========================================
POST - APROVAR / RECUSAR
========================================
*/
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!acValidarCsrf($_POST['csrf_token'] ?? null)) {
        acFlashSet('erro', 'Falha de segurança na ação. Recarregue a página e tente novamente.');
        header('Location: /pessoas/aceitar-convites.php');
        exit;
    }

    $acao = trim((string) ($_POST['acao'] ?? ''));
    $aprovacaoId = (int) ($_POST['aprovacao_id'] ?? 0);
    $motivoRecusa = trim((string) ($_POST['motivo_recusa'] ?? ''));

    if ($aprovacaoId <= 0 || !in_array($acao, ['aprovar', 'recusar'], true)) {
        acFlashSet('erro', 'Ação inválida.');
        header('Location: /pessoas/aceitar-convites.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                ca.id,
                ca.convidador_id,
                ca.convidado_id,
                ca.status,
                ca.score_risco,
                ca.motivo_risco,
                ca.bloqueado_automacao,
                ca.captcha_ok,
                p.id AS pessoa_id,
                p.nome,
                p.apelido,
                p.chamar_por,
                p.status_validacao
            FROM convites_aprovacoes ca
            INNER JOIN pessoas p
                ON p.id = ca.convidado_id
            WHERE ca.id = ?
              AND ca.convidador_id = ?
            LIMIT 1
        ");
        $stmt->execute([$aprovacaoId, $pessoa_id]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$registro) {
            throw new RuntimeException('Pendência não encontrada.');
        }

        if (($registro['status'] ?? '') !== 'pendente') {
            throw new RuntimeException('Essa pendência já foi tratada.');
        }

        $convidadoId = (int) $registro['convidado_id'];

        if ($acao === 'aprovar') {
            $ok = inviteAprovarCadastro($pdo, $aprovacaoId, $pessoa_id);

            if (!$ok) {
                throw new RuntimeException('Falha ao aprovar cadastro pelo engine.');
            }

            $stmt = $pdo->prepare("
                UPDATE pessoas
                SET
                    status_validacao = 'validado',
                    atualizado_em = NOW()
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$convidadoId]);

            acFlashSet(
                'sucesso',
                'Cadastro de ' . acNomeExibicao($registro) . ' aprovado com sucesso. XP, bônus e rede processados.'
            );
        } else {
            $pdo->beginTransaction();

            if ($motivoRecusa === '') {
                $motivoRecusa = 'Cadastro recusado pelo convidador.';
            }

            $stmt = $pdo->prepare("
                UPDATE convites_aprovacoes
                SET
                    status = 'recusado',
                    recusado_em = NOW(),
                    recusado_por = ?,
                    motivo_recusa = ?
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$pessoa_id, $motivoRecusa, $aprovacaoId]);

            $stmt = $pdo->prepare("
                UPDATE pessoas
                SET
                    status_validacao = 'recusado',
                    atualizado_em = NOW()
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$convidadoId]);

            $pdo->commit();

            acFlashSet(
                'sucesso',
                'Cadastro de ' . acNomeExibicao($registro) . ' foi recusado.'
            );
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('[ACEITAR_CONVITES] ' . $e->getMessage());
        acFlashSet('erro', 'Não foi possível concluir a ação agora.');
    }

    header('Location: /pessoas/aceitar-convites.php');
    exit;
}

/*
========================================
RESUMO
========================================
*/
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM convites_aprovacoes
    WHERE convidador_id = ?
      AND status = 'pendente'
");
$stmt->execute([$pessoa_id]);
$totalPendentes = (int) $stmt->fetchColumn();

/*
========================================
LISTA PENDENTES
========================================
*/
$stmt = $pdo->prepare("
    SELECT
        ca.id,
        ca.status,
        ca.origem,
        ca.telefone_informado,
        ca.score_risco,
        ca.motivo_risco,
        ca.flags_risco,
        ca.captcha_ok,
        ca.bloqueado_automacao,
        ca.criado_em,
        p.id AS convidado_id,
        p.nome,
        p.apelido,
        p.chamar_por,
        p.telefone
    FROM convites_aprovacoes ca
    INNER JOIN pessoas p
        ON p.id = ca.convidado_id
    WHERE ca.convidador_id = ?
      AND ca.status = 'pendente'
    ORDER BY
        ca.score_risco DESC,
        ca.criado_em DESC
");
$stmt->execute([$pessoa_id]);
$pendencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flash = acFlashGet();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ativar Convites • ELAB Social</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{
  --bg:#eef2f5;
  --card:#ffffff;
  --text:#102331;
  --muted:#5f7280;
  --brand1:#0b6e7a;
  --brand2:#1aa8b2;
  --success:#22c55e;
  --danger:#e53935;
  --warning:#ffb400;
  --shadow:0 12px 28px rgba(16,35,49,.10);
  --radius:24px;
}

*{box-sizing:border-box}

body{
  margin:0;
  background:linear-gradient(180deg,#eef2f5 0%, #e9eef2 100%);
  font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
  color:var(--text);
}

.page{
  max-width:720px;
  margin:0 auto;
  padding:0 0 110px;
}

.header{
  background:
    radial-gradient(circle at top right, rgba(255,255,255,.18), transparent 32%),
    linear-gradient(135deg,var(--brand1),var(--brand2));
  color:#fff;
  padding:18px 12px 20px;
  border-radius:0 0 34px 34px;
  box-shadow:0 12px 36px rgba(11,110,122,.28);
}

.header-top{
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap:16px;
}

.header h1{
  margin:0;
  font-size:24px;
  font-weight:1000;
  line-height:1.05;
}

.header p{
  margin:8px 0 0;
  font-size:14px;
  font-weight:700;
  opacity:.95;
  line-height:1.45;
}

.btn-voltar{
  display:inline-flex;
  align-items:center;
  gap:8px;
  margin-top:14px;
  padding:10px 14px;
  border-radius:999px;
  background:rgba(255,255,255,.16);
  color:#fff;
  font-size:13px;
  font-weight:900;
  text-decoration:none;
  border:1px solid rgba(255,255,255,.18);
}

.btn-voltar:hover{
  color:#fff;
  text-decoration:none;
}

.flash{
  margin:18px 18px 0;
  padding:14px 16px;
  border-radius:18px;
  font-size:14px;
  font-weight:800;
  box-shadow:var(--shadow);
}

.flash-sucesso{
  background:#ecfdf3;
  color:#166534;
  border:1px solid #c7f0d3;
}

.flash-erro{
  background:#fff1f2;
  color:#991b1b;
  border:1px solid #fecdd3;
}

.section{
  margin:18px;
}

.section-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  margin-bottom:14px;
}

.section-title{
  margin:0;
  font-size:20px;
  font-weight:1000;
  line-height:1.1;
}

.section-sub{
  margin:4px 0 0;
  font-size:13px;
  color:var(--muted);
  font-weight:700;
}

.pill-pendente{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-width:48px;
  height:40px;
  padding:0 14px;
  border-radius:999px;
  background:linear-gradient(135deg,#22c55e 0%, #16a34a 100%);
  color:#fff;
  font-size:15px;
  font-weight:1000;
  box-shadow:0 10px 22px rgba(34,197,94,.22);
}

.card-pendencia{
  background:var(--card);
  border-radius:24px;
  padding:18px;
  box-shadow:var(--shadow);
  margin-bottom:14px;
}

.nome{
  font-size:20px;
  font-weight:1000;
  line-height:1.1;
  margin-bottom:4px;
}

.sub{
  font-size:14px;
  color:#334155;
  font-weight:800;
  line-height:1.4;
}

.sub-meta{
  margin-top:8px;
  font-size:14px;
  color:var(--muted);
  font-weight:800;
  line-height:1.45;
}

.badges{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  margin-top:12px;
}

.badge-pill{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:8px 10px;
  border-radius:999px;
  font-size:11px;
  font-weight:900;
  line-height:1;
}

.badge-risco{
  background:#fff7e8;
  color:#9a6700;
}

.badge-bloqueado{
  background:#fff1f2;
  color:#b42318;
}

.badge-captcha{
  background:#ecfdf3;
  color:#166534;
}

.flags{
  margin-top:12px;
  padding:12px 14px;
  border-radius:16px;
  background:#f9fafb;
  border:1px dashed #d7dee7;
}

.flags-title{
  font-size:12px;
  font-weight:1000;
  color:#5f7280;
  margin-bottom:8px;
}

.flags-list{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
}

.flag-item{
  display:inline-flex;
  align-items:center;
  padding:7px 10px;
  border-radius:999px;
  background:#fff;
  border:1px solid #e7edf3;
  font-size:11px;
  font-weight:800;
  color:#334155;
}

.motivo-risco{
  margin-top:12px;
  padding:12px 14px;
  border-radius:16px;
  background:#fff7e8;
  border:1px solid #fde5a6;
}

.motivo-risco strong{
  display:block;
  margin-bottom:4px;
  font-size:12px;
  color:#9a6700;
  text-transform:uppercase;
  letter-spacing:.3px;
}

.motivo-risco span{
  font-size:13px;
  line-height:1.45;
  color:#7c5a00;
  font-weight:800;
}

.form-recusar{
  margin-top:14px;
}

.input-motivo{
  width:100%;
  min-height:46px;
  padding:12px 14px;
  border-radius:14px;
  border:1px solid #d8e0e8;
  font-size:14px;
  outline:none;
  background:#fff;
}

.input-motivo:focus{
  border-color:#9bc9ce;
  box-shadow:0 0 0 3px rgba(26,168,178,.10);
}

.acoes{
  display:flex;
  gap:10px;
  margin-top:14px;
}

.btn-acao{
  flex:1;
  min-height:52px;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  border:none;
  border-radius:16px;
  font-size:15px;
  font-weight:1000;
  text-decoration:none;
  cursor:pointer;
  transition:transform .15s ease, opacity .15s ease, box-shadow .15s ease;
}

.btn-acao:active{
  transform:scale(.985);
}

.btn-aprovar{
  background:linear-gradient(135deg,#22c55e 0%, #16a34a 100%);
  color:#fff;
  box-shadow:0 10px 22px rgba(34,197,94,.22);
}

.btn-recusar{
  background:#fff;
  color:#b42318;
  border:1px solid #f3c3c3;
}

.empty{
  background:#fff;
  border-radius:26px;
  padding:28px 20px;
  box-shadow:var(--shadow);
  text-align:center;
}

.empty i{
  font-size:40px;
  color:#1aa8b2;
}

.empty h3{
  margin:12px 0 6px;
  font-size:22px;
  font-weight:1000;
}

.empty p{
  margin:0;
  color:var(--muted);
  font-size:14px;
  font-weight:700;
  line-height:1.5;
}

/* MODAL CONFIRMAÇÃO */
.confirm-overlay{
  position:fixed;
  inset:0;
  background:rgba(16,35,49,.48);
  display:flex;
  align-items:center;
  justify-content:center;
  padding:18px;
  z-index:9999;
}

.confirm-box{
  width:100%;
  max-width:380px;
  background:#fff;
  border-radius:24px;
  padding:24px 20px 18px;
  box-shadow:0 24px 60px rgba(16,35,49,.22);
  text-align:center;
  animation:confirmIn .18s ease;
}

.confirm-icon{
  width:68px;
  height:68px;
  margin:0 auto 14px;
  border-radius:50%;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:30px;
  background:linear-gradient(135deg,#ecfdf3 0%, #d9fbe6 100%);
}

.confirm-box h3{
  margin:0 0 8px;
  font-size:22px;
  font-weight:1000;
  color:#102331;
}

.confirm-box p{
  margin:0;
  font-size:15px;
  line-height:1.5;
  color:#5f7280;
}

.confirm-actions{
  display:flex;
  gap:10px;
  margin-top:20px;
}

.btn-confirm-cancel,
.btn-confirm-ok{
  flex:1;
  min-height:50px;
  border:none;
  border-radius:16px;
  font-size:15px;
  font-weight:900;
}

.btn-confirm-cancel{
  background:#f3f6f9;
  color:#5f7280;
}

.btn-confirm-ok{
  background:linear-gradient(135deg,#22c55e 0%, #16a34a 100%);
  color:#fff;
  box-shadow:0 10px 22px rgba(34,197,94,.22);
}

@keyframes confirmIn{
  from{opacity:0; transform:translateY(8px) scale(.98);}
  to{opacity:1; transform:translateY(0) scale(1);}
}

@media (max-width:560px){
  .acoes{
    flex-direction:column;
  }

  .section-head{
    align-items:flex-start;
  }
}
</style>
</head>
<body>
<div class="page">

  <div class="header">
    <div class="header-top">
      <div>
        <a href="/dashboard/index.php" class="btn-voltar">
          <i class="bi bi-chevron-left"></i>
          Voltar ao início
        </a>
      </div>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="flash <?= $flash['tipo'] === 'sucesso' ? 'flash-sucesso' : 'flash-erro' ?>">
      <?= acEsc($flash['mensagem'] ?? '') ?>
    </div>
  <?php endif; ?>

  <div class="section">
    <div class="section-head">
      <div>
        <h2 class="section-title">Pendências para ativar</h2>
        <p class="section-sub">Revise os cadastros recebidos e aprove apenas os que você reconhecer.</p>
      </div>
      <div class="pill-pendente"><?= $totalPendentes ?></div>
    </div>

    <?php if (!$pendencias): ?>
      <div class="empty">
        <i class="bi bi-person-check"></i>
        <h3>Nenhuma pendência agora</h3>
        <p>Quando alguém se cadastrar pelo seu link, o pedido de ativação vai aparecer aqui.</p>
      </div>
    <?php else: ?>

      <?php foreach ($pendencias as $item): ?>
        <?php
          $nomeConvidado = acNomeExibicao($item);
          $flags = acJsonFlags($item['flags_risco'] ?? null);
          $scoreRisco = (int) ($item['score_risco'] ?? 0);
          $telefone = trim((string) ($item['telefone'] ?? ''));
          $telefoneInformado = trim((string) ($item['telefone_informado'] ?? ''));
          $telefoneLinha = $telefone !== '' ? $telefone : ($telefoneInformado !== '' ? $telefoneInformado : 'Não informado');
          $origemLinha = trim((string) ($item['origem'] ?? 'link_convite'));
        ?>
        <div class="card-pendencia">
          <div class="nome"><?= acEsc($nomeConvidado) ?></div>
          <div class="sub">Cadastro recebido em <?= acDataHora($item['criado_em'] ?? null) ?></div>
          <div class="sub-meta">
            Origem: <?= acEsc($origemLinha) ?> &nbsp; | &nbsp; WhatsApp <?= acEsc($telefoneLinha) ?>
          </div>

          <?php if ($scoreRisco > 0 || ($item['captcha_ok'] ?? 'nao') === 'sim' || ($item['bloqueado_automacao'] ?? 'nao') === 'sim'): ?>
            <div class="badges">
              <?php if ($scoreRisco > 0): ?>
                <span class="badge-pill badge-risco">
                  <i class="bi bi-shield-exclamation"></i>
                  Risco <?= $scoreRisco ?>
                </span>
              <?php endif; ?>

              <?php if (($item['captcha_ok'] ?? 'nao') === 'sim'): ?>
                <span class="badge-pill badge-captcha">
                  <i class="bi bi-patch-check"></i>
                  Captcha ok
                </span>
              <?php endif; ?>

              <?php if (($item['bloqueado_automacao'] ?? 'nao') === 'sim'): ?>
                <span class="badge-pill badge-bloqueado">
                  <i class="bi bi-lock-fill"></i>
                  Marcado por automação
                </span>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($item['motivo_risco'])): ?>
            <div class="motivo-risco">
              <strong>Motivo de risco</strong>
              <span><?= acEsc((string) $item['motivo_risco']) ?></span>
            </div>
          <?php endif; ?>

          <?php if ($flags): ?>
            <div class="flags">
              <div class="flags-title">Flags técnicas</div>
              <div class="flags-list">
                <?php foreach ($flags as $flag): ?>
                  <span class="flag-item"><?= acEsc($flag) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <form method="post" class="form-recusar">
            <input type="hidden" name="csrf_token" value="<?= acEsc($csrfToken) ?>">
            <input type="hidden" name="aprovacao_id" value="<?= (int) $item['id'] ?>">
            <input type="hidden" name="acao" value="" class="acao-hidden">

            <input
              type="text"
              name="motivo_recusa"
              class="input-motivo"
              placeholder="Motivo da recusa (opcional)"
            >

            <div class="acoes">
              <button
                type="button"
                class="btn-acao btn-aprovar"
                onclick="abrirConfirm(this.form, 'aprovar', <?= acEsc(json_encode($nomeConvidado)) ?>)"
              >
                <i class="bi bi-check-circle-fill"></i>
                Aprovar e ativar
              </button>

              <button
                type="button"
                class="btn-acao btn-recusar"
                onclick="abrirConfirm(this.form, 'recusar', <?= acEsc(json_encode($nomeConvidado)) ?>)"
              >
                <i class="bi bi-x-circle"></i>
                Recusar
              </button>
            </div>
          </form>
        </div>
      <?php endforeach; ?>

    <?php endif; ?>
  </div>

</div>

<div id="confirmOverlay" class="confirm-overlay" style="display:none;">
  <div class="confirm-box">
    <div class="confirm-icon" id="confirmIcon">✅</div>
    <h3 id="confirmTitle">Confirmar ação</h3>
    <p id="confirmText">Deseja continuar?</p>

    <div class="confirm-actions">
      <button type="button" class="btn-confirm-cancel" onclick="fecharConfirm()">Cancelar</button>
      <button type="button" class="btn-confirm-ok" id="confirmOkBtn">Confirmar</button>
    </div>
  </div>
</div>

<script>
let formConfirmAlvo = null;
let acaoConfirmAlvo = null;

function abrirConfirm(form, tipo, nome){
  formConfirmAlvo = form;
  acaoConfirmAlvo = tipo;

  const overlay = document.getElementById('confirmOverlay');
  const icon = document.getElementById('confirmIcon');
  const title = document.getElementById('confirmTitle');
  const text = document.getElementById('confirmText');
  const okBtn = document.getElementById('confirmOkBtn');

  okBtn.style.background = '';

  if(tipo === 'aprovar'){
    icon.textContent = '✅';
    icon.style.background = 'linear-gradient(135deg,#ecfdf3 0%, #d9fbe6 100%)';
    title.textContent = 'Confirmar ativação';
    text.innerHTML = 'Você está prestes a ativar <strong>' + nome + '</strong> na sua rede.<br>O XP do convite será processado após a confirmação.';
    okBtn.textContent = 'Aprovar e ativar';
    okBtn.style.background = 'linear-gradient(135deg,#22c55e 0%, #16a34a 100%)';
  } else {
    icon.textContent = '⚠️';
    icon.style.background = 'linear-gradient(135deg,#fff1f2 0%, #ffe0e3 100%)';
    title.textContent = 'Confirmar recusa';
    text.innerHTML = 'Deseja realmente recusar <strong>' + nome + '</strong>?<br>Esse cadastro não será ativado na sua rede.';
    okBtn.textContent = 'Recusar cadastro';
    okBtn.style.background = 'linear-gradient(135deg,#ef4444 0%, #dc2626 100%)';
  }

  overlay.style.display = 'flex';
}

function fecharConfirm(){
  document.getElementById('confirmOverlay').style.display = 'none';
  formConfirmAlvo = null;
  acaoConfirmAlvo = null;
}

document.getElementById('confirmOverlay').addEventListener('click', function(e){
  if(e.target === this){
    fecharConfirm();
  }
});

document.getElementById('confirmOkBtn').addEventListener('click', function(){
  if(!formConfirmAlvo || !acaoConfirmAlvo){
    return;
  }

  const inputAcao = formConfirmAlvo.querySelector('.acao-hidden');
  if(inputAcao){
    inputAcao.value = acaoConfirmAlvo;
  }

  formConfirmAlvo.submit();
});
</script>
</body>
</html>