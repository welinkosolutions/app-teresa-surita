<?php
declare(strict_types=1);

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$inviteEnginePath = '/home/elab/public_html/core/invite/engine.php';
if (is_file($inviteEnginePath)) {
    require_once $inviteEnginePath;
}

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

$pdo = dbRoraima();
$pessoaId = (int) $_SESSION['pessoa_id'];

$linkConvitePublico = '';
$codigoConvitePublico = '';

try {
    if (function_exists('inviteObterOuCriarLinkPublico')) {
        $linkPublicoConvite = inviteObterOuCriarLinkPublico($pdo, $pessoaId);
        $codigoConvitePublico = trim((string) ($linkPublicoConvite['codigo_convite_publico'] ?? ''));
        $linkConvitePublico = trim((string) ($linkPublicoConvite['url_curta'] ?? ''));

        if ($linkConvitePublico === '' && $codigoConvitePublico !== '') {
            $linkConvitePublico = 'https://app.elab.social/i/' . rawurlencode($codigoConvitePublico);
        }
    }
} catch (Throwable $e) {
    error_log('[missao-painel] Falha ao obter link público de convite: ' . $e->getMessage());
}

function h(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function semana_inicio_sql(): string
{
    return "DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)";
}

function progresso_convites(PDO $pdo, int $pessoaId): array
{
    $meta = 5;
    $feitos = 0;
    $source = 'fallback';

    /*
     * Mesma base usada no Perfil:
     * cada clique/compartilhamento de convite registra em convites_compartilhamentos.
     * A missão semanal considera os registros dos últimos 7 dias.
     */
    if (table_exists($pdo, 'convites_compartilhamentos')) {
        $ownerCol = column_exists($pdo, 'convites_compartilhamentos', 'pessoa_id') ? 'pessoa_id' : null;
        $dateCol = column_exists($pdo, 'convites_compartilhamentos', 'criado_em') ? 'criado_em' : null;

        if (!$dateCol && column_exists($pdo, 'convites_compartilhamentos', 'created_at')) {
            $dateCol = 'created_at';
        }

        if ($ownerCol && $dateCol) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM convites_compartilhamentos
                WHERE {$ownerCol} = ?
                  AND {$dateCol} >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$pessoaId]);
            $feitos = (int) $stmt->fetchColumn();
            $source = 'convites_compartilhamentos';
        }
    }

    /*
     * Fallback apenas se a tabela principal não estiver disponível.
     */
    if ($source === 'fallback' && table_exists($pdo, 'rede_indicacoes')) {
        $ownerCol = column_exists($pdo, 'rede_indicacoes', 'pessoa_id') ? 'pessoa_id' : null;
        if (!$ownerCol && column_exists($pdo, 'rede_indicacoes', 'indicador_id')) {
            $ownerCol = 'indicador_id';
        }

        $dateCol = column_exists($pdo, 'rede_indicacoes', 'criado_em') ? 'criado_em' : null;
        if (!$dateCol && column_exists($pdo, 'rede_indicacoes', 'created_at')) {
            $dateCol = 'created_at';
        }

        if ($ownerCol && $dateCol) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM rede_indicacoes
                WHERE {$ownerCol} = ?
                  AND {$dateCol} >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$pessoaId]);
            $feitos = (int) $stmt->fetchColumn();
            $source = 'rede_indicacoes';
        }
    }

    if ($source === 'fallback' && table_exists($pdo, 'convites')) {
        $ownerCol = column_exists($pdo, 'convites', 'pessoa_id') ? 'pessoa_id' : null;
        if (!$ownerCol && column_exists($pdo, 'convites', 'convidador_id')) {
            $ownerCol = 'convidador_id';
        }
        if (!$ownerCol && column_exists($pdo, 'convites', 'convidante_id')) {
            $ownerCol = 'convidante_id';
        }

        $dateCol = column_exists($pdo, 'convites', 'criado_em') ? 'criado_em' : null;
        if (!$dateCol && column_exists($pdo, 'convites', 'created_at')) {
            $dateCol = 'created_at';
        }

        if ($ownerCol && $dateCol) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM convites
                WHERE {$ownerCol} = ?
                  AND {$dateCol} >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$pessoaId]);
            $feitos = (int) $stmt->fetchColumn();
            $source = 'convites';
        }
    }

    $feitos = max(0, min($meta, $feitos));
    $faltam = max(0, $meta - $feitos);

    return [
        'tipo' => 'convites',
        'classe' => 'is-invite',
        'icone' => '🤝',
        'titulo' => 'Convide 5 pessoas para o time',
        'subtitulo' => $faltam > 0 ? "Faltam {$faltam} convites para concluir." : 'Missão concluída.',
        'meta' => $meta,
        'feitos' => $feitos,
        'faltam' => $faltam,
        'percent' => (int) round(($feitos / $meta) * 100),
        'source' => $source,
    ];
}

function progresso_comentarios(PDO $pdo, int $pessoaId): array
{
    $meta = 5;
    $feitos = 0;

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM missao_historico_usuario
        WHERE pessoa_id = ?
          AND (
            missao_tipo = 'comentario'
            OR missao_codigo LIKE '%coment%'
            OR JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.tipo_acao')) = 'comentar'
          )
          AND (
            status IN ('concluida', 'executada')
            OR status_final IN ('concluida', 'executada', 'ok')
            OR concluida_em IS NOT NULL
          )
          AND COALESCE(concluida_em, criado_em) >= " . semana_inicio_sql() . "
          AND COALESCE(concluida_em, criado_em) < DATE_ADD(" . semana_inicio_sql() . ", INTERVAL 7 DAY)
    ");
    $stmt->execute([$pessoaId]);
    $feitos = (int) $stmt->fetchColumn();

    $feitos = max(0, min($meta, $feitos));
    $faltam = max(0, $meta - $feitos);

    return [
        'tipo' => 'comentarios',
        'classe' => 'is-comments',
        'icone' => '💬',
        'titulo' => 'Comente 5 posts essa semana',
        'subtitulo' => $faltam > 0 ? "Faltam {$faltam} comentários para concluir." : 'Missão concluída.',
        'meta' => $meta,
        'feitos' => $feitos,
        'faltam' => $faltam,
        'percent' => (int) round(($feitos / $meta) * 100),
        'source' => 'missao_historico_usuario',
    ];
}

function progresso_compartilhar(PDO $pdo, int $pessoaId): array
{
    $meta = 5;
    $feitos = 0;
    $source = 'fallback';

    if (table_exists($pdo, 'missao_compartilhamentos')) {
        $ownerCol = column_exists($pdo, 'missao_compartilhamentos', 'pessoa_id') ? 'pessoa_id' : null;
        $dateCol = column_exists($pdo, 'missao_compartilhamentos', 'criado_em') ? 'criado_em' : null;

        if (!$dateCol && column_exists($pdo, 'missao_compartilhamentos', 'created_at')) {
            $dateCol = 'created_at';
        }

        if ($ownerCol && $dateCol) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM missao_compartilhamentos
                WHERE {$ownerCol} = ?
                  AND {$dateCol} >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$pessoaId]);
            $feitos = (int) $stmt->fetchColumn();
            $source = 'missao_compartilhamentos';
        }
    }

    if ($source === 'fallback') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM missao_historico_usuario
            WHERE pessoa_id = ?
              AND (
                missao_codigo LIKE '%compart%'
                OR missao_tipo LIKE '%compart%'
                OR JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.tipo_acao')) = 'compartilhar'
              )
              AND (
                status IN ('concluida', 'executada')
                OR status_final IN ('concluida', 'executada', 'ok')
                OR concluida_em IS NOT NULL
              )
              AND COALESCE(concluida_em, criado_em) >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute([$pessoaId]);
        $feitos = (int) $stmt->fetchColumn();
        $source = 'missao_historico_usuario';
    }

    $feitos = max(0, min($meta, $feitos));
    $faltam = max(0, $meta - $feitos);

    return [
        'tipo' => 'compartilhar',
        'classe' => 'is-share',
        'icone' => '📣',
        'titulo' => 'Compartilhe esse post com 5 amigos',
        'subtitulo' => $faltam > 0 ? "Faltam {$faltam} compartilhamentos para concluir." : 'Missão concluída.',
        'meta' => $meta,
        'feitos' => $feitos,
        'faltam' => $faltam,
        'percent' => (int) round(($feitos / $meta) * 100),
        'source' => $source,
    ];
}

$cards = [
    progresso_convites($pdo, $pessoaId),
    progresso_comentarios($pdo, $pessoaId),
    progresso_compartilhar($pdo, $pessoaId),
];

$totalFeitos = array_sum(array_map(static fn($c) => (int) $c['feitos'], $cards));
$totalMeta = array_sum(array_map(static fn($c) => (int) $c['meta'], $cards));
$totalFaltam = max(0, $totalMeta - $totalFeitos);
$totalPercent = $totalMeta > 0 ? (int) round(($totalFeitos / $totalMeta) * 100) : 0;

$activeMenu = 'missao';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, viewport-fit=cover, initial-scale=1.0">
  <title>Painel de Missões</title>
  <link rel="stylesheet" href="/assets/css/footer-v2.css?v=5">
  <style>
    :root {
      --ink: #071327;
      --muted: #8b95a8;
      --line: #e8edf5;
      --green: #21c866;
      --card: rgba(255,255,255,.9);
      --shadow: 0 18px 44px rgba(15,23,42,.09);
    }

    * { box-sizing: border-box; }

    html,
    body {
      min-height: 100%;
      margin: 0;
      background:
        radial-gradient(circle at 8% 0%, rgba(34,197,94,.10), transparent 28%),
        radial-gradient(circle at 92% 18%, rgba(25,197,176,.12), transparent 34%),
        linear-gradient(180deg, #f8fbff 0%, #eef4f8 100%);
      color: var(--ink);
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    body {
      padding-bottom: calc(104px + env(safe-area-inset-bottom));
    }

    .mission-page {
      width: min(100%, 430px);
      margin: 0 auto;
      padding: 18px 16px calc(108px + env(safe-area-inset-bottom));
    }

    .mission-hero {
      margin: 0 0 16px;
      padding: 20px 18px 18px;
      border-radius: 30px;
      background:
        radial-gradient(circle at 16% 0%, rgba(255,255,255,.55), transparent 35%),
        radial-gradient(circle at 94% 4%, rgba(255,255,255,.18), transparent 28%),
        linear-gradient(135deg, #ffb32d 0%, #ff7a00 100%);
      box-shadow: 0 18px 42px rgba(255,122,0,.20);
      color: #fff;
      overflow: hidden;
      position: relative;
    }

    .mission-hero::after {
      content: "";
      position: absolute;
      right: -42px;
      top: -50px;
      width: 160px;
      height: 160px;
      border-radius: 999px;
      background: rgba(255,255,255,.16);
    }

    .mission-kicker {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 7px 12px;
      border-radius: 999px;
      background: rgba(255,255,255,.22);
      font-size: 12px;
      line-height: 1;
      font-weight: 1000;
      text-transform: uppercase;
      letter-spacing: .05em;
      position: relative;
      z-index: 2;
    }

    .mission-hero h1 {
      margin: 14px 0 6px;
      font-size: 35px;
      line-height: .95;
      letter-spacing: -.075em;
      font-weight: 1000;
      position: relative;
      z-index: 2;
      text-shadow: 0 8px 18px rgba(0,0,0,.10);
    }

    .mission-hero p {
      margin: 0;
      max-width: 292px;
      font-size: 14px;
      line-height: 1.22;
      font-weight: 850;
      color: rgba(255,255,255,.86);
      position: relative;
      z-index: 2;
    }

    .mission-summary {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin: 0 0 15px;
    }

    .mission-mini {
      min-height: 74px;
      border-radius: 20px;
      background: rgba(255,255,255,.88);
      border: 1px solid rgba(226,232,240,.92);
      box-shadow: 0 10px 24px rgba(15,23,42,.05);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 10px 8px;
    }

    .mission-mini strong {
      display: block;
      font-size: 21px;
      line-height: 1;
      font-weight: 1000;
      letter-spacing: -.04em;
    }

    .mission-mini span {
      display: block;
      margin-top: 5px;
      color: var(--muted);
      font-size: 10.5px;
      line-height: 1.05;
      font-weight: 900;
    }

    .mission-card {
      position: relative;
      margin: 0 0 14px;
      padding: 18px;
      border-radius: 30px;
      background:
        radial-gradient(circle at 94% 8%, rgba(34,197,94,.16), transparent 30%),
        linear-gradient(135deg, rgba(255,255,255,.96), rgba(246,255,250,.92));
      border: 1px solid rgba(226,232,240,.95);
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    .mission-card.is-comments {
      background:
        radial-gradient(circle at 94% 8%, rgba(217,70,239,.13), transparent 30%),
        linear-gradient(135deg, rgba(255,255,255,.96), rgba(253,244,255,.90));
    }

    .mission-card.is-share {
      background:
        radial-gradient(circle at 94% 8%, rgba(59,130,246,.14), transparent 30%),
        linear-gradient(135deg, rgba(255,255,255,.96), rgba(239,246,255,.92));
    }

    .mission-card-head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 14px;
      margin-bottom: 13px;
    }

    .mission-card-title {
      display: grid;
      grid-template-columns: 44px minmax(0, 1fr);
      gap: 12px;
      align-items: center;
      min-width: 0;
    }

    .mission-icon {
      width: 44px;
      height: 44px;
      border-radius: 16px;
      display: grid;
      place-items: center;
      background: #ecfdf5;
      box-shadow: 0 10px 22px rgba(34,197,94,.12);
      font-size: 23px;
    }

    .mission-card.is-comments .mission-icon { background: #fdf4ff; }
    .mission-card.is-share .mission-icon { background: #eff6ff; }

    .mission-card h2 {
      margin: 0;
      font-size: 20px;
      line-height: 1.05;
      letter-spacing: -.055em;
      font-weight: 1000;
    }

    .mission-card p {
      margin: 5px 0 0;
      color: #8b95a8;
      font-size: 13px;
      line-height: 1.15;
      font-weight: 900;
    }

    .mission-pill {
      flex: 0 0 auto;
      min-width: 58px;
      padding: 9px 11px;
      border-radius: 999px;
      background: #dcfce7;
      color: #05a64f;
      text-align: center;
      font-size: 15px;
      line-height: 1;
      font-weight: 1000;
      box-shadow: inset 0 -2px 0 rgba(5,150,105,.08);
    }

    .mission-card.is-comments .mission-pill {
      background: #fae8ff;
      color: #c026d3;
    }

    .mission-card.is-share .mission-pill {
      background: #dbeafe;
      color: #1877f2;
    }

    .mission-progress {
      height: 15px;
      border-radius: 999px;
      background: #e8eef7;
      overflow: hidden;
      box-shadow: inset 0 2px 5px rgba(15,23,42,.08);
      margin: 0 0 17px;
    }

    .mission-progress i {
      display: block;
      height: 100%;
      width: var(--pct, 0%);
      border-radius: inherit;
      background: linear-gradient(90deg, #17b65e, #27d16d, #facc15);
    }

    .mission-card.is-comments .mission-progress i {
      background: linear-gradient(90deg, #ec4899, #a855f7, #6366f1);
    }

    .mission-card.is-share .mission-progress i {
      background: linear-gradient(90deg, #1877f2, #06b6d4, #22c55e);
    }

    .mission-steps {
      display: flex;
      justify-content: space-between;
      gap: 8px;
    }

    .mission-step {
      width: 47px;
      height: 47px;
      border-radius: 999px;
      display: grid;
      place-items: center;
      border: 3px dashed #22c55e;
      color: #0ea34f;
      background: #fff;
      font-size: 22px;
      font-weight: 1000;
      line-height: 1;
      box-shadow: 0 9px 20px rgba(34,197,94,.08);
    }

    .mission-step.is-done {
      border-style: solid;
      border-color: #062f25;
      color: #071327;
      background: linear-gradient(135deg, #ff3f74, #ffb020);
      box-shadow: 0 12px 24px rgba(34,197,94,.16);
    }

    .mission-card.is-comments .mission-step {
      border-color: #d946ef;
      color: #c026d3;
    }

    .mission-card.is-comments .mission-step.is-done {
      border-color: #3b0764;
      color: #fff;
      background: linear-gradient(135deg, #ec4899, #8b5cf6);
    }

    .mission-card.is-share .mission-step {
      border-color: #3b82f6;
      color: #1877f2;
    }

    .mission-card.is-share .mission-step.is-done {
      border-color: #082f49;
      color: #fff;
      background: linear-gradient(135deg, #1877f2, #06b6d4);
    }

    .mission-source {
      margin-top: 12px;
      color: rgba(139,149,168,.75);
      font-size: 10px;
      font-weight: 800;
      display: none;
    }


    .mission-card.is-clickable {
      cursor: pointer;
      transition: transform .16s ease, box-shadow .16s ease;
    }

    .mission-card.is-clickable:active {
      transform: scale(.985);
    }

    .mission-card.is-clickable::after {
      content: "TOQUE PARA AGIR";
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-top: 14px;
      min-height: 42px;
      width: 100%;
      border-radius: 18px;
      color: #fff;
      background: linear-gradient(135deg, #16a34a, #22c55e);
      font-size: 13px;
      font-weight: 1000;
      letter-spacing: .04em;
      box-shadow: 0 14px 24px rgba(34,197,94,.20);
    }


    .mission-card.is-invite.is-clickable::after {
      content: "TOQUE PARA CONVIDAR";
    }

    .mission-card.is-share.is-clickable::after {
      content: "TOQUE PARA COMPARTILHAR NO WHATSAPP";
      background: linear-gradient(135deg, #1877f2, #06b6d4);
      box-shadow: 0 14px 24px rgba(59,130,246,.18);
    }

    .mission-card.is-share.is-clickable.is-sending::after {
      content: "GERANDO POST...";
      background: linear-gradient(135deg, #0f172a, #1e293b);
    }

    .mission-card.is-share.is-clickable.is-complete::after {
      content: "COMPARTILHAMENTO REGISTRADO";
      background: linear-gradient(135deg, #1877f2, #22c55e);
    }

    .mission-card.is-clickable.is-sending::after {
      content: "GERANDO CONVITE...";
      background: linear-gradient(135deg, #0f172a, #1e293b);
    }

    .mission-card.is-clickable.is-complete::after {
      content: "CONVITE REGISTRADO";
      background: linear-gradient(135deg, #059669, #22c55e);
    }

    @media (max-width: 390px) {
      .mission-page {
        padding-left: 12px;
        padding-right: 12px;
      }

      .mission-hero h1 {
        font-size: 30px;
      }

      .mission-card {
        padding: 16px;
        border-radius: 26px;
      }

      .mission-card h2 {
        font-size: 18px;
      }

      .mission-card p {
        font-size: 12px;
      }

      .mission-step {
        width: 42px;
        height: 42px;
        font-size: 19px;
      }
    }
  </style>
</head>
<body>
<main class="mission-page">
  <section class="mission-hero">
    <span class="mission-kicker">🎯 Missão semanal</span>
    <h1>Complete sua semana</h1>
    <p>Avance nas ações, fortaleça o time e acompanhe seu progresso.</p>
  </section>

  <section class="mission-summary" aria-label="Resumo semanal" data-mission-summary>
    <div class="mission-mini">
      <strong><?= h($totalFeitos) ?></strong>
      <span>ações feitas</span>
    </div>
    <div class="mission-mini">
      <strong><?= h($totalFaltam) ?></strong>
      <span>faltam concluir</span>
    </div>
    <div class="mission-mini">
      <strong><?= h($totalPercent) ?>%</strong>
      <span>progresso total</span>
    </div>
  </section>

  <?php foreach ($cards as $card): ?>
    <section
      data-mission-panel-card="<?= h($card['tipo']) ?>"
      class="mission-card <?= h($card['classe']) ?> <?= in_array($card['tipo'], ['convites', 'compartilhar'], true) ? 'is-clickable' : '' ?>"
      <?php if ($card['tipo'] === 'convites'): ?>
        data-convite-card data-url="<?= h($linkConvitePublico) ?>"
      <?php elseif ($card['tipo'] === 'compartilhar'): ?>
        data-share-post-card
      <?php endif; ?>
    >
      <div class="mission-card-head">
        <div class="mission-card-title">
          <span class="mission-icon"><?= h($card['icone']) ?></span>
          <div>
            <h2><?= h($card['titulo']) ?></h2>
            <p><?= h($card['subtitulo']) ?></p>
          </div>
        </div>
        <span class="mission-pill"><?= h($card['feitos']) ?>/<?= h($card['meta']) ?></span>
      </div>

      <div class="mission-progress" style="--pct: <?= h($card['percent']) ?>%;">
        <i></i>
      </div>

      <div class="mission-steps" aria-hidden="true">
        <?php for ($i = 1; $i <= (int) $card['meta']; $i++): ?>
          <span class="mission-step <?= $i <= (int) $card['feitos'] ? 'is-done' : '' ?>"><?= h($i) ?></span>
        <?php endfor; ?>
      </div>

      <div class="mission-source"><?= h($card['source']) ?></div>
    </section>
  <?php endforeach; ?>
</main>


<?php
$footerActive = 'missao';
require '/home/elab/app.elab.social/assets/footer/menu.php';
?>


<script>
(function () {
  function textoConvite(url) {
    return [
      'Convite especial para o app *Teresa Surita*',
      '',
      'Oii! Estou te convidando para fazer parte do meu time no app. Juntos, nossa voz ganha muito mais força e a gente ainda ganha recompensas por participar! 🚀',
      '',
      '*Clique aqui para entrar no time:*',
      url,
      '',
      'Vamos transformar a rede? Te espero lá! ✨'
    ].join('\n');
  }

  async function registrarConvite(canal) {
    const body = new URLSearchParams();
    body.set('canal', canal || 'painel_missao');

    const res = await fetch('/endpoints/convite-gerar.php', {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
      },
      body
    });

    return res.json();
  }

  function abrirWhatsapp(url) {
    const text = textoConvite(url);
    window.location.href = 'https://wa.me/?text=' + encodeURIComponent(text);
  }

  function abrirCompartilhamento(url) {
    const text = textoConvite(url);

    if (navigator.share) {
      return navigator.share({
        title: 'Convite Teresa Surita',
        text: text
      });
    }

    abrirWhatsapp(url);
    return Promise.resolve();
  }

  document.addEventListener('click', function (ev) {
    const card = ev.target.closest('[data-convite-card]');
    if (!card || card.classList.contains('is-sending')) {
      return;
    }

    ev.preventDefault();

    const url = card.getAttribute('data-url') || '';

    if (!url) {
      alert('Não foi possível encontrar seu link de convite agora.');
      return;
    }

    card.classList.add('is-sending');

    // Importante: abrir o share imediatamente no gesto do usuário.
    const sharePromise = abrirCompartilhamento(url).catch(function (err) {
      console.warn('[MISSAO_CONVITE_SHARE]', err);
      abrirWhatsapp(url);
    });

    // Registro no banco em paralelo, sem bloquear abertura do compartilhamento.
    registrarConvite('painel_missao')
      .then(function (data) {
        if (data && data.ok) {
          card.classList.remove('is-sending');
          card.classList.add('is-complete');

          setTimeout(function () {
            window.location.reload();
          }, 1100);
        } else {
          throw new Error(data && data.error ? data.error : 'invite_register_error');
        }
      })
      .catch(function (err) {
        console.warn('[MISSAO_CONVITE_REGISTRO]', err);
        card.classList.remove('is-sending');
      });

    return sharePromise;
  });

  function textoPost(data) {
    const rede = data.network_label || 'rede social';
    const caption = data.caption ? '\n\n' + data.caption : '';

    return [
      'Olha esse post da Teresa Surita no ' + rede + ' 👇',
      caption,
      '',
      data.url,
      '',
      'Compartilhe também e ajude essa mensagem chegar mais longe! 🚀'
    ].join('\n');
  }

  function abrirWhatsappPost(data) {
    const text = textoPost(data);
    window.location.href = 'https://wa.me/?text=' + encodeURIComponent(text);
  }

  document.addEventListener('click', function (ev) {
    const card = ev.target.closest('[data-share-post-card]');
    if (!card || card.classList.contains('is-sending')) {
      return;
    }

    ev.preventDefault();
    card.classList.add('is-sending');

    const body = new URLSearchParams();
    body.set('canal', 'whatsapp');

    fetch('/endpoints/compartilhar-post.php', {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
      },
      body
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (data) {
        if (!data || !data.ok || !data.url) {
          throw new Error(data && data.error ? data.error : 'share_post_error');
        }

        card.classList.remove('is-sending');
        card.classList.add('is-complete');

        abrirWhatsappPost(data);

        setTimeout(function () {
          window.location.reload();
        }, 1100);
      })
      .catch(function (err) {
        console.warn('[MISSAO_SHARE_POST]', err);
        card.classList.remove('is-sending');
        alert('Não foi possível encontrar um post para compartilhar agora.');
      });
  });


})();
</script>


<script>
(function () {
  function renderSteps(box, meta, feitos) {
    if (!box) return;

    var html = '';
    meta = Math.max(1, Number(meta || 5));
    feitos = Math.max(0, Number(feitos || 0));

    for (var i = 1; i <= meta; i++) {
      html += '<span class="mission-step ' + (i <= feitos ? 'is-done' : '') + '">' + i + '</span>';
    }

    box.innerHTML = html;
  }

  function updateCard(tipo, data) {
    var card = document.querySelector('[data-mission-panel-card="' + tipo + '"]');
    if (!card || !data) return;

    var title = card.querySelector('h2');
    var sub = card.querySelector('p');
    var pill = card.querySelector('.mission-pill');
    var bar = card.querySelector('.mission-progress');
    var steps = card.querySelector('.mission-steps');

    if (title) title.textContent = data.titulo || title.textContent;
    if (sub) sub.textContent = data.subtitulo || '';
    if (pill) pill.textContent = data.feitos + '/' + data.meta;
    if (bar) bar.style.setProperty('--pct', String(data.percent || 0) + '%');

    renderSteps(steps, data.meta, data.feitos);

    card.classList.toggle('is-complete', Number(data.feitos || 0) >= Number(data.meta || 5));
  }

  function updateSummary(summary) {
    var box = document.querySelector('[data-mission-summary]');
    if (!box || !summary) return;

    var nums = box.querySelectorAll('.mission-mini strong');
    if (nums[0]) nums[0].textContent = String(summary.feitos || 0);
    if (nums[1]) nums[1].textContent = String(summary.faltam || 0);
    if (nums[2]) nums[2].textContent = String(summary.percent || 0) + '%';
  }

  async function refreshMissionPanel() {
    try {
      var res = await fetch('/endpoints/missao-painel-status.php?_=' + Date.now(), {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json' }
      });

      var data = await res.json();

      if (!data || !data.ok) return;

      updateSummary(data.summary);
      updateCard('convites', data.cards.convites);
      updateCard('comentarios', data.cards.comentarios);
      updateCard('compartilhar', data.cards.compartilhar);
    } catch (err) {
      console.warn('[MISSAO_PAINEL_STATUS]', err);
    }
  }

  window.refreshMissionPanel = refreshMissionPanel;

  setInterval(refreshMissionPanel, 5000);

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) refreshMissionPanel();
  });

  window.addEventListener('focus', refreshMissionPanel);

  refreshMissionPanel();
})();
</script>

</body>
</html>
