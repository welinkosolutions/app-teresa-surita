<?php
declare(strict_types=1);

date_default_timezone_set('America/Boa_Vista');

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

/*
=====================================================
SESSION APP
=====================================================
*/
require_once '/home/elab/public_html/core/sessao/app.php';

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

$pessoa_id = (int)$_SESSION['pessoa_id'];

/*
=====================================================
CORE
=====================================================
*/
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();

/*
=====================================================
HELPERS
=====================================================
*/
if (!function_exists('esc')) {
    function esc(?string $valor): string
    {
        return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('nomeExibicao')) {
    function nomeExibicao(array $pessoa): string
    {
        $nome = trim((string)($pessoa['nome'] ?? ''));
        $apelido = trim((string)($pessoa['apelido'] ?? ''));
        $chamarPor = trim((string)($pessoa['chamar_por'] ?? ''));

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

if (!function_exists('buscarPessoa')) {
    function buscarPessoa(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare("
            SELECT id, nome, apelido, chamar_por, status, COALESCE(pontos,0) AS pontos
            FROM pessoas
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('pessoaPertenceARede')) {
    function pessoaPertenceARede(PDO $pdo, int $raizId, int $alvoId): bool
    {
        if ($alvoId === $raizId) {
            return true;
        }

        $stmt = $pdo->prepare("
            SELECT 1
            FROM rede_indicacoes
            WHERE indicador_id = ?
              AND indicado_id = ?
            LIMIT 1
        ");
        $stmt->execute([$raizId, $alvoId]);

        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('buscarPaiDireto')) {
    function buscarPaiDireto(PDO $pdo, int $alvoId): int
    {
        $stmt = $pdo->prepare("
            SELECT indicador_id
            FROM rede_indicacoes
            WHERE indicado_id = ?
              AND nivel = 1
            LIMIT 1
        ");
        $stmt->execute([$alvoId]);

        return (int)($stmt->fetchColumn() ?? 0);
    }
}

if (!function_exists('buscarNivelNaRedeRaiz')) {
    function buscarNivelNaRedeRaiz(PDO $pdo, int $raizId, int $alvoId): int
    {
        if ($raizId === $alvoId) {
            return 0;
        }

        $stmt = $pdo->prepare("
            SELECT nivel
            FROM rede_indicacoes
            WHERE indicador_id = ?
              AND indicado_id = ?
            LIMIT 1
        ");
        $stmt->execute([$raizId, $alvoId]);

        return (int)($stmt->fetchColumn() ?? 0);
    }
}

if (!function_exists('montarTrilha')) {
    function montarTrilha(PDO $pdo, int $raizId, int $focoId): array
    {
        $trilha = [];

        if ($focoId <= 0 || $focoId === $raizId) {
            return $trilha;
        }

        $cursor = $focoId;
        $seguranca = 0;

        while ($cursor > 0 && $cursor !== $raizId && $seguranca < 20) {
            $pessoa = buscarPessoa($pdo, $cursor);
            if ($pessoa) {
                $trilha[] = [
                    'id'   => (int)$pessoa['id'],
                    'nome' => nomeExibicao($pessoa),
                ];
            }

            $cursor = buscarPaiDireto($pdo, $cursor);
            $seguranca++;
        }

        return array_reverse($trilha);
    }
}

if (!function_exists('decorarMetricasRede')) {
    function decorarMetricasRede(array $rows): array
    {
        $saida = [];

        foreach ($rows as $row) {
            $xp7d = (int)($row['xp_7d'] ?? 0);

            /*
            =====================================================
            RÉGUA SIMPLES DE ENGAJAMENTO
            - 200 XP em 7 dias = 100%
            - >= 60% = foguinho
            - < 60% = tartaruga
            =====================================================
            */
            $percentual = max(0, min(100, (int)round(($xp7d / 200) * 100)));
            $icone = $percentual >= 60 ? '🔥' : '🐢';
            $rotulo = $percentual >= 60 ? 'bom ritmo' : 'lento';

            $saida[] = [
                'id'                 => (int)$row['id'],
                'nome'               => nomeExibicao($row),
                'xp_total'           => (int)($row['xp_total'] ?? 0),
                'qtd_diretos_ativos' => (int)($row['qtd_diretos_ativos'] ?? 0),
                'xp_7d'              => $xp7d,
                'engajamento_pct'    => $percentual,
                'engajamento_icone'  => $icone,
                'engajamento_rotulo' => $rotulo,
            ];
        }

        return $saida;
    }
}

if (!function_exists('listarRedePorNivel')) {
    function listarRedePorNivel(PDO $pdo, int $raizId, int $nivel): array
    {
        $stmt = $pdo->prepare("
            SELECT
                p.id,
                p.nome,
                p.apelido,
                p.chamar_por,
                COALESCE(p.pontos, 0) AS xp_total,
                COALESCE(fd.qtd_diretos_ativos, 0) AS qtd_diretos_ativos,
                COALESCE(x7.xp_7d, 0) AS xp_7d
            FROM rede_indicacoes r
            JOIN pessoas p
              ON p.id = r.indicado_id
             AND p.status = 'ativo'
            LEFT JOIN (
                SELECT
                    r2.indicador_id,
                    COUNT(*) AS qtd_diretos_ativos
                FROM rede_indicacoes r2
                JOIN pessoas p2
                  ON p2.id = r2.indicado_id
                 AND p2.status = 'ativo'
                WHERE r2.nivel = 1
                GROUP BY r2.indicador_id
            ) fd
              ON fd.indicador_id = p.id
            LEFT JOIN (
                SELECT
                    pessoa_id,
                    SUM(pontos_final) AS xp_7d
                FROM gamificacao_pontos_temporada
                WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY pessoa_id
            ) x7
              ON x7.pessoa_id = p.id
            WHERE r.indicador_id = ?
              AND r.nivel = ?
            ORDER BY p.nome
        ");
        $stmt->execute([$raizId, $nivel]);

        return decorarMetricasRede($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}

if (!function_exists('listarFilhosDiretos')) {
    function listarFilhosDiretos(PDO $pdo, int $indicadorId): array
    {
        $stmt = $pdo->prepare("
            SELECT
                p.id,
                p.nome,
                p.apelido,
                p.chamar_por,
                COALESCE(p.pontos, 0) AS xp_total,
                COALESCE(fd.qtd_diretos_ativos, 0) AS qtd_diretos_ativos,
                COALESCE(x7.xp_7d, 0) AS xp_7d
            FROM rede_indicacoes r
            JOIN pessoas p
              ON p.id = r.indicado_id
             AND p.status = 'ativo'
            LEFT JOIN (
                SELECT
                    r2.indicador_id,
                    COUNT(*) AS qtd_diretos_ativos
                FROM rede_indicacoes r2
                JOIN pessoas p2
                  ON p2.id = r2.indicado_id
                 AND p2.status = 'ativo'
                WHERE r2.nivel = 1
                GROUP BY r2.indicador_id
            ) fd
              ON fd.indicador_id = p.id
            LEFT JOIN (
                SELECT
                    pessoa_id,
                    SUM(pontos_final) AS xp_7d
                FROM gamificacao_pontos_temporada
                WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY pessoa_id
            ) x7
              ON x7.pessoa_id = p.id
            WHERE r.indicador_id = ?
              AND r.nivel = 1
            ORDER BY p.nome
        ");
        $stmt->execute([$indicadorId]);

        return decorarMetricasRede($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}

/*
=====================================================
USUÁRIO
=====================================================
*/
$usuario = buscarPessoa($pdo, $pessoa_id) ?: [];
$nomeUsuario = nomeExibicao($usuario);

/*
=====================================================
RESUMOS
=====================================================
*/
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM rede_indicacoes
    WHERE indicador_id = ?
");
$stmt->execute([$pessoa_id]);
$totalRede = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT nivel, COUNT(*) AS qtd
    FROM rede_indicacoes
    WHERE indicador_id = ?
    GROUP BY nivel
    ORDER BY nivel
");
$stmt->execute([$pessoa_id]);
$niveisRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

$niveisMap = [];
foreach ($niveisRaw as $n) {
    $niveisMap[(int)$n['nivel']] = (int)$n['qtd'];
}

$niveis = [];
for ($i = 1; $i <= 7; $i++) {
    $niveis[] = [
        'nivel' => $i,
        'qtd'   => $niveisMap[$i] ?? 0,
    ];
}

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(pontos_final), 0)
    FROM gamificacao_pontos_temporada
    WHERE pessoa_id = ?
      AND origem_tipo IN ('nivel1','nivel2','nivel3','nivel4','nivel5','nivel6','nivel7')
");
$stmt->execute([$pessoa_id]);
$xpRede = (int)$stmt->fetchColumn();

/*
=====================================================
NAVEGAÇÃO
=====================================================
*/
$nivelSelecionado = (int)($_GET['nivel'] ?? 0);
$focoId = (int)($_GET['foco_id'] ?? 0);

$modo = 'resumo';
$tituloSecao = 'Rede por nível';
$subtituloSecao = 'Clique em um nível para abrir os cadastrados daquela camada.';
$listaPessoas = [];
$backLink = '';
$backLabel = '';
$trilha = [];

if ($focoId > 0 && pessoaPertenceARede($pdo, $pessoa_id, $focoId)) {
    $modo = 'foco';

    $pessoaFoco = buscarPessoa($pdo, $focoId) ?: [];
    $nomeFoco = nomeExibicao($pessoaFoco);
    $nivelFoco = buscarNivelNaRedeRaiz($pdo, $pessoa_id, $focoId);

    $tituloSecao = 'Indicados de ' . $nomeFoco;
    $subtituloSecao = $nivelFoco > 0
        ? 'Nome, pontos, indicados e engajamento de quem está abaixo dessa pessoa.'
        : 'Abaixo estão os diretos da sua rede com pontos, indicados e engajamento.';

    $listaPessoas = listarFilhosDiretos($pdo, $focoId);
    $trilha = montarTrilha($pdo, $pessoa_id, $focoId);

    if ($focoId !== $pessoa_id) {
        $paiDireto = buscarPaiDireto($pdo, $focoId);

        if ($paiDireto > 0 && $paiDireto !== $pessoa_id) {
            $backLink = '/rede/index.php?foco_id=' . $paiDireto;
            $backLabel = 'Voltar nível acima';
        } else {
            $backLink = '/rede/index.php';
            $backLabel = 'Voltar para minha rede';
        }
    }
} elseif ($nivelSelecionado >= 1 && $nivelSelecionado <= 7) {
    $modo = 'nivel';
    $tituloSecao = 'Cadastrados do nível ' . $nivelSelecionado;
    $subtituloSecao = 'Nome, pontos, indicados e engajamento. Toque no nome para abrir os indicados abaixo.';
    $listaPessoas = listarRedePorNivel($pdo, $pessoa_id, $nivelSelecionado);
    $backLink = '/rede/index.php';
    $backLabel = 'Voltar para minha rede';
}

/*
=====================================================
LISTA RESUMO NÍVEL 1
=====================================================
*/
$nivel1Resumo = listarRedePorNivel($pdo, $pessoa_id, 1);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Minha Rede • ELAB Social</title>

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
  padding:0 0 40px;
}

.header{
  background:
    radial-gradient(circle at top right, rgba(255,255,255,.18), transparent 32%),
    linear-gradient(135deg,var(--brand1),var(--brand2));
  color:#fff;
  padding:18px 14px 22px;
  border-radius:0 0 34px 34px;
  box-shadow:0 12px 36px rgba(11,110,122,.28);
}

.header-actions{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
}

.btn-topo{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:10px 14px;
  border-radius:999px;
  background:rgba(255,255,255,.16);
  color:#fff;
  font-size:13px;
  font-weight:900;
  text-decoration:none;
  border:1px solid rgba(255,255,255,.18);
}

.btn-topo:hover{
  color:#fff;
  text-decoration:none;
}

.header-title{
  margin-top:14px;
}

.header-title h1{
  margin:0;
  font-size:28px;
  font-weight:1000;
  line-height:1.05;
}

.header-title p{
  margin:8px 0 0;
  font-size:14px;
  font-weight:700;
  opacity:.95;
  line-height:1.45;
}

.section{
  margin:18px;
}

.card-elab{
  background:var(--card);
  border-radius:26px;
  padding:18px;
  box-shadow:var(--shadow);
  margin-bottom:14px;
}

.card-title{
  display:flex;
  align-items:center;
  gap:10px;
  margin:0 0 14px;
  font-size:18px;
  font-weight:1000;
  letter-spacing:-.2px;
}

.card-sub{
  margin-top:-6px;
  margin-bottom:14px;
  color:var(--muted);
  font-size:13px;
  font-weight:700;
  line-height:1.45;
}

.grid-resumo{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:8px;
}

.resumo-box{
  background:#f7fafc;
  border:1px solid #e7edf3;
  border-radius:16px;
  padding:10px 8px;
  min-height:74px;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
  text-align:center;
}

.resumo-numero{
  font-size:18px;
  line-height:1;
  font-weight:1000;
  color:var(--brand1);
}

.resumo-label{
  margin-top:6px;
  font-size:10px;
  color:var(--muted);
  font-weight:900;
  line-height:1.1;
}

.nivel-link{
  text-decoration:none;
  color:inherit;
  display:block;
}

.nivel-item{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  padding:12px 0;
  border-bottom:1px solid #edf1f4;
}

.nivel-item:last-child{
  border-bottom:none;
}

.nivel-item.is-clickable{
  transition:transform .16s ease, opacity .16s ease;
}

.nivel-item.is-clickable:active{
  transform:scale(.992);
}

.nivel-left{
  display:flex;
  align-items:center;
  gap:10px;
  min-width:0;
}

.nivel-badge{
  min-width:76px;
  text-align:center;
  background:linear-gradient(135deg,var(--brand1),var(--brand2));
  color:#fff;
  padding:8px 12px;
  border-radius:999px;
  font-size:12px;
  font-weight:1000;
}

.nivel-texto{
  font-size:14px;
  font-weight:800;
  color:#223846;
}

.nivel-qtd{
  font-size:15px;
  font-weight:1000;
  color:#102331;
  flex:0 0 auto;
}

.trilha{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  margin-bottom:12px;
}

.trilha-chip{
  display:inline-flex;
  align-items:center;
  gap:6px;
  min-height:32px;
  padding:0 12px;
  border-radius:999px;
  background:#eef5f7;
  border:1px solid #dbe9ed;
  color:#0b6e7a;
  font-size:12px;
  font-weight:900;
}

.lista-pessoas{
  list-style:none;
  padding:0;
  margin:0;
}

.lista-pessoas li{
  margin-bottom:10px;
}

.lista-pessoas li:last-child{
  margin-bottom:0;
}

.pessoa-link{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  padding:14px;
  border-radius:16px;
  background:#f7fafc;
  border:1px solid #e7edf3;
  text-decoration:none;
  color:#223846;
  transition:transform .16s ease, box-shadow .16s ease;
}

.pessoa-link:hover{
  color:#223846;
  text-decoration:none;
  transform:translateY(-1px);
  box-shadow:0 10px 18px rgba(16,35,49,.08);
}

.pessoa-main{
  flex:1;
  min-width:0;
}

.pessoa-top{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  margin-bottom:8px;
}

.pessoa-nome{
  font-size:15px;
  font-weight:1000;
  line-height:1.2;
  color:#102331;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}

.pessoa-acao{
  font-size:12px;
  font-weight:800;
  color:#5f7280;
  line-height:1.2;
}

.pessoa-metricas{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
}

.metric-chip{
  display:inline-flex;
  align-items:center;
  gap:6px;
  min-height:30px;
  padding:0 10px;
  border-radius:999px;
  font-size:12px;
  font-weight:1000;
  white-space:nowrap;
}

.metric-xp{
  background:#eef5ff;
  color:#2456c9;
}

.metric-indicados{
  background:#ecfdf3;
  color:#166534;
}

.metric-engajamento{
  background:#fff7e8;
  color:#8a5a00;
}

.pessoa-arrow{
  flex:0 0 auto;
  font-size:16px;
  color:#0b6e7a;
  line-height:1;
}

.empty{
  text-align:center;
  padding:10px 4px 2px;
  color:var(--muted);
  font-size:14px;
  font-weight:700;
}

@media (max-width:560px){
  .grid-resumo{
    grid-template-columns:repeat(3,1fr);
  }

  .header-title h1{
    font-size:24px;
  }

  .resumo-numero{
    font-size:16px;
  }

  .resumo-label{
    font-size:10px;
  }

  .pessoa-link{
    align-items:flex-start;
  }

  .pessoa-top{
    flex-direction:column;
    align-items:flex-start;
    gap:4px;
  }

  .pessoa-nome{
    white-space:normal;
  }

  .nivel-texto{
    font-size:13px;
  }
}
</style>
</head>
<body>

<div class="page">

  <div class="header">
    <div class="header-actions">
      <a href="/dashboard/index.php" class="btn-topo">
        <i class="bi bi-chevron-left"></i>
        Voltar ao início
      </a>

      <?php if ($backLink !== ''): ?>
        <a href="<?= esc($backLink) ?>" class="btn-topo">
          <i class="bi bi-arrow-up-left"></i>
          <?= esc($backLabel) ?>
        </a>
      <?php endif; ?>
    </div>

    <div class="header-title">
      <h1>Minha Rede</h1>
      <p><?= esc($nomeUsuario) ?>, acompanhe sua comunidade, navegue por nível e abra a estrutura de cada indicado.</p>
    </div>
  </div>

  <div class="section">

    <div class="card-elab">
      <div class="card-title">
        <i class="bi bi-diagram-3-fill"></i>
        Resumo da sua rede
      </div>

      <div class="grid-resumo">
        <div class="resumo-box">
          <div class="resumo-numero"><?= number_format($totalRede, 0, ',', '.') ?></div>
          <div class="resumo-label">SUA REDE</div>
        </div>

        <div class="resumo-box">
          <div class="resumo-numero"><?= number_format($niveisMap[1] ?? 0, 0, ',', '.') ?></div>
          <div class="resumo-label">SEUS DIRETOS</div>
        </div>

        <div class="resumo-box">
          <div class="resumo-numero"><?= number_format($xpRede, 0, ',', '.') ?></div>
          <div class="resumo-label">PONTOS DE REDE</div>
        </div>
      </div>
    </div>

    <div class="card-elab">
      <div class="card-title">
        <i class="bi bi-layers-fill"></i>
        <?= esc($tituloSecao) ?>
      </div>
      <div class="card-sub"><?= esc($subtituloSecao) ?></div>

      <?php if ($modo === 'resumo'): ?>

        <?php foreach ($niveis as $n): ?>
          <a class="nivel-link" href="/rede/index.php?nivel=<?= (int)$n['nivel'] ?>">
            <div class="nivel-item is-clickable">
              <div class="nivel-left">
                <span class="nivel-badge">Nível <?= (int)$n['nivel'] ?></span>
                <span class="nivel-texto">Abrir cadastrados desse nível</span>
              </div>

              <div class="nivel-qtd">
                <?= number_format((int)$n['qtd'], 0, ',', '.') ?>
              </div>
            </div>
          </a>
        <?php endforeach; ?>

      <?php else: ?>

        <?php if ($trilha): ?>
          <div class="trilha">
            <span class="trilha-chip">
              <i class="bi bi-house-door-fill"></i>
              <?= esc($nomeUsuario) ?>
            </span>

            <?php foreach ($trilha as $item): ?>
              <span class="trilha-chip">
                <i class="bi bi-arrow-right-short"></i>
                <?= esc($item['nome']) ?>
              </span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (!$listaPessoas): ?>
          <div class="empty">
            Nenhum cadastro ativo encontrado nesta visão.
          </div>
        <?php else: ?>
          <ul class="lista-pessoas">
            <?php foreach ($listaPessoas as $item): ?>
              <li>
                <a class="pessoa-link" href="/rede/index.php?foco_id=<?= (int)$item['id'] ?>">
                  <div class="pessoa-main">
                    <div class="pessoa-top">
                      <div class="pessoa-nome"><?= esc($item['nome']) ?></div>
                      <div class="pessoa-acao">clique para ver os indicados abaixo</div>
                    </div>

                    <div class="pessoa-metricas">
                      <span class="metric-chip metric-xp">
                        <i class="bi bi-stars"></i>
                        <?= number_format((int)$item['xp_total'], 0, ',', '.') ?> Pontos
                      </span>

                      <span class="metric-chip metric-indicados">
                        <i class="bi bi-people-fill"></i>
                        <?= number_format((int)$item['qtd_diretos_ativos'], 0, ',', '.') ?> indicados
                      </span>

                      <span class="metric-chip metric-engajamento">
                        <?= esc($item['engajamento_icone']) ?>
                        <?= number_format((int)$item['engajamento_pct'], 0, ',', '.') ?>% engajamento
                      </span>
                    </div>
                  </div>

                  <div class="pessoa-arrow">
                    <i class="bi bi-chevron-right"></i>
                  </div>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

      <?php endif; ?>
    </div>

    <?php if ($modo === 'resumo'): ?>
      <div class="card-elab">
        <div class="card-title">
          <i class="bi bi-people-fill"></i>
          Nível 1 • Convites diretos
        </div>
        <div class="card-sub">Nome, pontos, indicados e engajamento. Toque no nome para abrir a estrutura abaixo dele.</div>

        <?php if (!$nivel1Resumo): ?>
          <div class="empty">
            Você ainda não tem convites diretos ativos.
          </div>
        <?php else: ?>
          <ul class="lista-pessoas">
            <?php foreach ($nivel1Resumo as $item): ?>
              <li>
                <a class="pessoa-link" href="/rede/index.php?foco_id=<?= (int)$item['id'] ?>">
                  <div class="pessoa-main">
                    <div class="pessoa-top">
                      <div class="pessoa-nome"><?= esc($item['nome']) ?></div>
                      <div class="pessoa-acao">clique para ver os indicados abaixo</div>
                    </div>

                    <div class="pessoa-metricas">
                      <span class="metric-chip metric-xp">
                        <i class="bi bi-stars"></i>
                        <?= number_format((int)$item['xp_total'], 0, ',', '.') ?> Pontos
                      </span>

                      <span class="metric-chip metric-indicados">
                        <i class="bi bi-people-fill"></i>
                        <?= number_format((int)$item['qtd_diretos_ativos'], 0, ',', '.') ?> indicados
                      </span>

                      <span class="metric-chip metric-engajamento">
                        <?= esc($item['engajamento_icone']) ?>
                        <?= number_format((int)$item['engajamento_pct'], 0, ',', '.') ?>% 
                      </span>
                    </div>
                  </div>

                  <div class="pessoa-arrow">
                    <i class="bi bi-chevron-right"></i>
                  </div>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div>
</div>

</body>
</html>