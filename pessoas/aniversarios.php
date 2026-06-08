<?php
declare(strict_types=1);

/**
 * ======================================================
 * CAMINHO: app.elab.social/pessoas/aniversarios.php
 * NOME: Aniversários do Dia
 * DESCRIÇÃO:
 * - Tela mobile rápida para uso na rua
 * - Lista aniversariantes do dia/data selecionada
 * - Acesso controlado por acessos_especiais
 * - Recurso exigido: gestao_pessoas
 * - Botão Importante salva em lista_importante
 * - Botão Social Mídia abre modal compacto
 * - Labels visuais no card para importante e social mídia
 * - Navegação por dia anterior / hoje / próximo dia
 * - Datas em português
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
    SELECT id, nome, apelido, chamar_por, perfil, status
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

function canaisAtivos(string $json, string $acaoMidia = 'nenhum'): array
{
    $canais = json_decode($json, true);

    if (!is_array($canais)) {
        $canais = [];
    }

    $canais = array_values(array_unique(array_filter(array_map('strval', $canais))));

    if (!$canais) {
        if ($acaoMidia === 'stories') {
            $canais = ['instagram_stories'];
        } elseif ($acaoMidia === 'post') {
            $canais = ['instagram_feed'];
        }
    }

    return $canais;
}

function origemBancoApp(string $origemView): string
{
    return $origemView === 'nova' ? 'elab' : 'legacy';
}

function buscarRegistroAniversarioImportante(PDO $pdo, string $origemView, int $idRef): array|false
{
    if ($origemView === 'nova') {
        $stmt = $pdo->prepare("
            SELECT id, COALESCE(lista_importante, 'nao') AS lista_importante
            FROM aniversarios_importantes
            WHERE pessoa_id = :id
              AND (pessoa_raw_id IS NULL OR pessoa_raw_id = 0)
            ORDER BY id DESC
            LIMIT 1
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT id, COALESCE(lista_importante, 'nao') AS lista_importante
            FROM aniversarios_importantes
            WHERE pessoa_raw_id = :id
            ORDER BY id DESC
            LIMIT 1
        ");
    }

    $stmt->bindValue(':id', $idRef, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function socialIconPath(string $canal): string
{
    return match ($canal) {
        'instagram_feed', 'instagram_stories' => '/assets/img/social/instagram.png',
        'facebook_feed', 'facebook_stories'   => '/assets/img/social/facebook.png',
        'whatsapp'                            => '/assets/img/social/whatsapp.png',
        default                               => '/assets/img/social/reload.png',
    };
}

function socialLabel(string $canal): string
{
    return match ($canal) {
        'instagram_feed'    => 'Instagram Feed',
        'instagram_stories' => 'Instagram Stories',
        'facebook_feed'     => 'Facebook Feed',
        'facebook_stories'  => 'Facebook Stories',
        'whatsapp'          => 'WhatsApp',
        default             => $canal,
    };
}

/* ================= DATA SELECIONADA ================= */
$dataParam = trim((string)($_GET['data'] ?? ''));
$tz = new DateTimeZone('America/Boa_Vista');

try {
    $dataBase = $dataParam !== ''
        ? new DateTimeImmutable($dataParam, $tz)
        : new DateTimeImmutable('now', $tz);
} catch (Throwable $e) {
    $dataBase = new DateTimeImmutable('now', $tz);
}

$hoje = new DateTimeImmutable('now', $tz);
$dataAnterior = $dataBase->modify('-1 day');
$dataProximoDia = $dataBase->modify('+1 day');

$mes = (int)$dataBase->format('n');
$dia = (int)$dataBase->format('j');

$mesesPt = [
    1 => 'jan', 2 => 'fev', 3 => 'mar', 4 => 'abr', 5 => 'mai', 6 => 'jun',
    7 => 'jul', 8 => 'ago', 9 => 'set', 10 => 'out', 11 => 'nov', 12 => 'dez'
];

$mesesPtLongo = [
    1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril', 5 => 'maio', 6 => 'junho',
    7 => 'julho', 8 => 'agosto', 9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'
];

$diasSemanaPt = [
    1 => 'segunda', 2 => 'terça', 3 => 'quarta', 4 => 'quinta',
    5 => 'sexta', 6 => 'sábado', 7 => 'domingo'
];

$mesLabel = $mesesPt[(int)$dataBase->format('n')] ?? mb_strtolower($dataBase->format('M'));
$dataLabelCurta = $dataBase->format('d/m/Y');
$dataLabelCompleta = ucfirst($diasSemanaPt[(int)$dataBase->format('N')] ?? '') . ', ' . $dataBase->format('d') . ' de ' . ucfirst($mesesPtLongo[(int)$dataBase->format('n')] ?? '');
$ehHoje = $dataBase->format('Y-m-d') === $hoje->format('Y-m-d');

$linkDiaAnterior = '/pessoas/aniversarios.php?data=' . $dataAnterior->format('Y-m-d');
$linkProximoDia = '/pessoas/aniversarios.php?data=' . $dataProximoDia->format('Y-m-d');
$linkHoje = '/pessoas/aniversarios.php?data=' . $hoje->format('Y-m-d');

/* ================= AJAX ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $action = trim((string)($_POST['action'] ?? ''));
    $origem = trim((string)($_POST['origem'] ?? ''));
    $idRef = (int)($_POST['id'] ?? 0);

    if (!in_array($origem, ['nova', 'antiga'], true) || $idRef <= 0) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'erro' => 'Parâmetros inválidos'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'toggle_importante') {
        $listaImportante = trim((string)($_POST['lista_importante'] ?? 'nao'));
        if (!in_array($listaImportante, ['sim', 'nao'], true)) {
            $listaImportante = 'nao';
        }

        $origemBanco = origemBancoApp($origem);

        try {
            $registro = buscarRegistroAniversarioImportante($pdo, $origem, $idRef);

            if ($registro) {
                $stmtUp = $pdo->prepare("
                    UPDATE aniversarios_importantes
                    SET ativo = 'sim',
                        lista_importante = :lista_importante,
                        origem = :origem,
                        atualizado_em = NOW()
                    WHERE id = :pk
                    LIMIT 1
                ");
                $stmtUp->bindValue(':lista_importante', $listaImportante, PDO::PARAM_STR);
                $stmtUp->bindValue(':origem', $origemBanco, PDO::PARAM_STR);
                $stmtUp->bindValue(':pk', (int)$registro['id'], PDO::PARAM_INT);
                $stmtUp->execute();
            } else {
                if ($origem === 'nova') {
                    $stmtIns = $pdo->prepare("
                        INSERT INTO aniversarios_importantes
                        (origem, pessoa_id, prioridade, ativo, lista_importante, acao_midia, comentario_midia, canais_social_json, criado_por, criado_em, atualizado_em)
                        VALUES
                        (:origem, :pessoa_id, 'normal', 'sim', :lista_importante, 'nenhum', '', '[]', :criado_por, NOW(), NOW())
                    ");
                    $stmtIns->bindValue(':origem', $origemBanco, PDO::PARAM_STR);
                    $stmtIns->bindValue(':pessoa_id', $idRef, PDO::PARAM_INT);
                    $stmtIns->bindValue(':lista_importante', $listaImportante, PDO::PARAM_STR);
                    $stmtIns->bindValue(':criado_por', $pessoa_id, PDO::PARAM_INT);
                    $stmtIns->execute();
                } else {
                    $stmtIns = $pdo->prepare("
                        INSERT INTO aniversarios_importantes
                        (origem, pessoa_id, pessoa_raw_id, prioridade, ativo, lista_importante, acao_midia, comentario_midia, canais_social_json, criado_por, criado_em, atualizado_em)
                        VALUES
                        (:origem, NULL, :pessoa_raw_id, 'normal', 'sim', :lista_importante, 'nenhum', '', '[]', :criado_por, NOW(), NOW())
                    ");
                    $stmtIns->bindValue(':origem', $origemBanco, PDO::PARAM_STR);
                    $stmtIns->bindValue(':pessoa_raw_id', $idRef, PDO::PARAM_INT);
                    $stmtIns->bindValue(':lista_importante', $listaImportante, PDO::PARAM_STR);
                    $stmtIns->bindValue(':criado_por', $pessoa_id, PDO::PARAM_INT);
                    $stmtIns->execute();
                }
            }

            echo json_encode([
                'ok' => true,
                'lista_importante' => $listaImportante
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

    if ($action === 'salvar_social') {
        $comentarioMidia = trim((string)($_POST['comentario_midia'] ?? ''));
        $canais = $_POST['canais'] ?? [];

        if (!is_array($canais)) {
            $canais = [];
        }

        $permitidos = [
            'instagram_feed',
            'instagram_stories',
            'facebook_feed',
            'facebook_stories',
        ];

        $canais = array_values(array_unique(array_filter(
            array_map(static fn($v) => trim((string)$v), $canais),
            static fn($v) => in_array($v, $permitidos, true)
        )));

        $canaisJson = json_encode($canais, JSON_UNESCAPED_UNICODE);
        $origemBanco = origemBancoApp($origem);

        try {
            $registro = buscarRegistroAniversarioImportante($pdo, $origem, $idRef);

            if ($registro) {
                $stmtUp = $pdo->prepare("
                    UPDATE aniversarios_importantes
                    SET ativo = 'sim',
                        origem = :origem,
                        comentario_midia = :comentario_midia,
                        canais_social_json = :canais_social_json,
                        acao_midia = 'nenhum',
                        atualizado_em = NOW()
                    WHERE id = :pk
                    LIMIT 1
                ");
                $stmtUp->bindValue(':origem', $origemBanco, PDO::PARAM_STR);
                $stmtUp->bindValue(':comentario_midia', $comentarioMidia, PDO::PARAM_STR);
                $stmtUp->bindValue(':canais_social_json', $canaisJson, PDO::PARAM_STR);
                $stmtUp->bindValue(':pk', (int)$registro['id'], PDO::PARAM_INT);
                $stmtUp->execute();

                $listaImportanteAtual = (string)($registro['lista_importante'] ?? 'nao');
            } else {
                $listaImportanteAtual = 'nao';

                if ($origem === 'nova') {
                    $stmtIns = $pdo->prepare("
                        INSERT INTO aniversarios_importantes
                        (origem, pessoa_id, prioridade, ativo, lista_importante, acao_midia, comentario_midia, canais_social_json, criado_por, criado_em, atualizado_em)
                        VALUES
                        (:origem, :pessoa_id, 'normal', 'sim', 'nao', 'nenhum', :comentario_midia, :canais_social_json, :criado_por, NOW(), NOW())
                    ");
                    $stmtIns->bindValue(':origem', $origemBanco, PDO::PARAM_STR);
                    $stmtIns->bindValue(':pessoa_id', $idRef, PDO::PARAM_INT);
                    $stmtIns->bindValue(':comentario_midia', $comentarioMidia, PDO::PARAM_STR);
                    $stmtIns->bindValue(':canais_social_json', $canaisJson, PDO::PARAM_STR);
                    $stmtIns->bindValue(':criado_por', $pessoa_id, PDO::PARAM_INT);
                    $stmtIns->execute();
                } else {
                    $stmtIns = $pdo->prepare("
                        INSERT INTO aniversarios_importantes
                        (origem, pessoa_id, pessoa_raw_id, prioridade, ativo, lista_importante, acao_midia, comentario_midia, canais_social_json, criado_por, criado_em, atualizado_em)
                        VALUES
                        (:origem, NULL, :pessoa_raw_id, 'normal', 'sim', 'nao', 'nenhum', :comentario_midia, :canais_social_json, :criado_por, NOW(), NOW())
                    ");
                    $stmtIns->bindValue(':origem', $origemBanco, PDO::PARAM_STR);
                    $stmtIns->bindValue(':pessoa_raw_id', $idRef, PDO::PARAM_INT);
                    $stmtIns->bindValue(':comentario_midia', $comentarioMidia, PDO::PARAM_STR);
                    $stmtIns->bindValue(':canais_social_json', $canaisJson, PDO::PARAM_STR);
                    $stmtIns->bindValue(':criado_por', $pessoa_id, PDO::PARAM_INT);
                    $stmtIns->execute();
                }
            }

            echo json_encode([
                'ok' => true,
                'comentario_midia' => $comentarioMidia,
                'canais_social_json' => $canais,
                'lista_importante' => $listaImportanteAtual
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

    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'erro' => 'Ação inválida'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ================= LISTA DO DIA ================= */
$erroTela = '';

$aniversariantes = [];
$totalHoje = 0;
$totalImportantes = 0;
$totalComAcao = 0;

try {
    $sql = "
        SELECT
            CASE
                WHEN HEX(v.origem) = '6E6F7661' THEN 'nova'
                ELSE 'antiga'
            END AS origem,
            v.pessoa_id,
            v.pessoa_raw_id,
            COALESCE(v.pessoa_raw_id, v.pessoa_id) AS pessoa_chave,
            COALESCE(
                NULLIF(v.apelido, ''),
                CASE
                    WHEN HEX(COALESCE(v.chamar_por, '')) = '6170656C69646F'
                         AND COALESCE(v.apelido, '') <> ''
                    THEN v.apelido
                    ELSE v.nome
                END,
                v.nome
            ) AS nome,
            CAST(v.idade_atual AS UNSIGNED) AS idade,
            COALESCE(i.lista_importante, 'nao') AS lista_importante,
            COALESCE(i.acao_midia, 'nenhum') AS acao_midia,
            COALESCE(i.comentario_midia, '') AS comentario_midia,
            COALESCE(i.canais_social_json, '') AS canais_social_json
        FROM vw_aniversarios_all v
        LEFT JOIN aniversarios_importantes i
            ON i.ativo = 'sim'
           AND (
                (HEX(v.origem) = '6E6F7661' AND i.pessoa_id = v.pessoa_id)
             OR (HEX(v.origem) = '616E74696761' AND i.pessoa_raw_id = v.pessoa_raw_id)
           )
        WHERE v.mes = :mes
          AND v.dia = :dia
        ORDER BY
            CASE WHEN COALESCE(i.lista_importante, 'nao') = 'sim' THEN 1 ELSE 0 END DESC,
            CASE
                WHEN (
                    (COALESCE(i.canais_social_json, '') <> '' AND COALESCE(i.canais_social_json, '[]') <> '[]')
                    OR COALESCE(i.acao_midia, 'nenhum') IN ('stories', 'post')
                ) THEN 1 ELSE 0
            END DESC,
            CASE WHEN HEX(v.origem) = '6E6F7661' THEN 1 ELSE 0 END DESC,
            nome ASC
    ";

    $stmtLista = $pdo->prepare($sql);
    $stmtLista->bindValue(':mes', $mes, PDO::PARAM_INT);
    $stmtLista->bindValue(':dia', $dia, PDO::PARAM_INT);
    $stmtLista->execute();
    $aniversariantes = $stmtLista->fetchAll(PDO::FETCH_ASSOC);

    foreach ($aniversariantes as &$item) {
        $item['origem'] = strtolower(trim((string)($item['origem'] ?? '')));
        $item['idade'] = isset($item['idade']) && (int)$item['idade'] > 0 ? (int)$item['idade'] : null;
        $item['lista_importante'] = (($item['lista_importante'] ?? 'nao') === 'sim') ? 'sim' : 'nao';
        $item['comentario_midia'] = (string)($item['comentario_midia'] ?? '');
        $item['canais'] = canaisAtivos(
            (string)($item['canais_social_json'] ?? ''),
            (string)($item['acao_midia'] ?? 'nenhum')
        );

        $item['id_ref'] = ($item['origem'] === 'nova')
            ? (int)($item['pessoa_id'] ?? 0)
            : (int)($item['pessoa_raw_id'] ?? 0);

        $totalHoje++;

        if ($item['lista_importante'] === 'sim') {
            $totalImportantes++;
        }

        if (!empty($item['canais']) || $item['comentario_midia'] !== '') {
            $totalComAcao++;
        }
    }
    unset($item);
} catch (Throwable $e) {
    $erroTela = $e->getMessage();
}
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Aniversários do Dia • ELAB Social</title>
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
  --danger-bg:#fff1f2;
  --danger-border:#fecdd3;
  --danger-text:#8b1e2d;
  --soft:#edf3f8;
  --green:#16a34a;
  --green-soft:#ecfdf3;
  --green-line:#bbf7d0;
  --blue-soft:#eef6ff;
  --blue-line:#d7e8fb;
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
  max-width:250px;
}

.date-chip{
  min-width:78px;
  text-align:center;
  padding:12px 10px;
  border-radius:18px;
  background:rgba(255,255,255,.10);
  border:1px solid rgba(255,255,255,.12);
}

.date-chip strong{
  display:block;
  font-size:18px;
  line-height:1;
  font-weight:1000;
}

.date-chip span{
  display:block;
  margin-top:4px;
  font-size:12px;
  font-weight:800;
  opacity:.9;
  text-transform:lowercase;
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

.header-nav-days{
  margin-top:12px;
  display:grid;
  grid-template-columns:1fr 1fr 1fr;
  gap:10px;
}

.btn-day-nav{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  min-height:42px;
  padding:0 12px;
  border-radius:14px;
  text-decoration:none;
  font-size:13px;
  font-weight:900;
  border:1px solid rgba(255,255,255,.14);
  background:rgba(255,255,255,.10);
  color:#fff;
}

.btn-day-nav:hover{
  color:#fff;
  text-decoration:none;
}

.btn-day-nav.today{
  background:rgba(255,255,255,.18);
}

.header-stats{
  margin-top:16px;
  display:grid;
  grid-template-columns:1fr;
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

.content-top-meta{
  display:flex;
  flex-direction:column;
  align-items:flex-end;
  gap:4px;
}

.content-top-meta .date{
  font-size:14px;
  font-weight:900;
  color:#718396;
}

.content-top-meta .date-long{
  font-size:12px;
  font-weight:800;
  color:#90a0b2;
  text-align:right;
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

.person-card.is-important{
  border-color:var(--green-line);
  box-shadow:0 12px 30px rgba(34,197,94,.12);
}

.person-head{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:12px;
  margin-bottom:12px;
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
  margin-top:8px;
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

.pill.current{ background:#e9f3ff; color:#1c5fb8; }
.pill.legacy{ background:#fff4db; color:#9a6500; }
.pill.green{
  background:var(--green-soft);
  color:#128043;
  border-color:#c8f2d7;
}

.label-row{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  margin:10px 0 0;
}

.label-chip{
  display:inline-flex;
  align-items:center;
  gap:8px;
  min-height:32px;
  padding:0 12px;
  border-radius:999px;
  font-size:12px;
  font-weight:900;
  border:1px solid var(--blue-line);
  background:var(--blue-soft);
  color:#24415c;
}

.label-chip.green{
  background:var(--green-soft);
  border-color:var(--green-line);
  color:#14793d;
}

.label-chip img{
  width:16px;
  height:16px;
  object-fit:contain;
  display:block;
}

.card-actions{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:10px;
  margin-top:12px;
}

.btn-action{
  appearance:none;
  border:none;
  min-height:46px;
  border-radius:16px;
  padding:0 14px;
  font-size:14px;
  font-weight:1000;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  cursor:pointer;
}

.btn-action:disabled{
  opacity:.7;
  cursor:default;
}

.btn-important{
  background:#effaf2;
  color:#176e39;
  border:1px solid #cdeed9;
}

.btn-important.is-on{
  background:linear-gradient(135deg,#22c55e,#16a34a);
  color:#fff;
  border:none;
}

.btn-social{
  background:linear-gradient(135deg,#18a6e4,#2c73ff);
  color:#fff;
}

.social-summary{
  margin-top:12px;
  padding:12px;
  border-radius:16px;
  background:#f7fbff;
  border:1px solid #dfeaf6;
}

.social-summary small{
  display:block;
  font-size:11px;
  font-weight:900;
  color:#698196;
  margin-bottom:4px;
}

.social-summary strong{
  display:block;
  font-size:13px;
  line-height:1.4;
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

.sheet-backdrop{
  position:fixed;
  inset:0;
  background:rgba(11,20,35,.48);
  z-index:80;
  display:none;
}

.sheet{
  position:fixed;
  left:0;
  right:0;
  bottom:0;
  z-index:81;
  background:#fff;
  border-radius:28px 28px 0 0;
  padding:14px 16px calc(18px + env(safe-area-inset-bottom));
  box-shadow:0 -18px 40px rgba(0,0,0,.18);
  transform:translateY(110%);
  transition:transform .22s ease;
  max-height:88vh;
  overflow:auto;
}

.sheet.is-open{
  transform:translateY(0);
}

.sheet-backdrop.is-open{
  display:block;
}

.sheet-handle{
  width:56px;
  height:6px;
  border-radius:999px;
  background:#d6e0ea;
  margin:0 auto 12px;
}

.sheet h3{
  margin:0;
  font-size:19px;
  font-weight:1000;
}

.sheet p{
  margin:6px 0 16px;
  font-size:13px;
  color:#62758a;
  font-weight:700;
}

.social-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:10px;
  margin-bottom:14px;
}

.social-item{
  border:1px solid #dbe5ef;
  background:#f7fbff;
  border-radius:18px;
  padding:12px;
  min-height:88px;
  display:flex;
  align-items:center;
  gap:10px;
  font-weight:900;
  cursor:pointer;
  text-align:left;
}

.social-item.active{
  border-color:#34b7ff;
  background:#eaf7ff;
  box-shadow:0 10px 20px rgba(52,183,255,.12);
}

.social-item img{
  width:28px;
  height:28px;
  object-fit:contain;
  display:block;
}

.social-item .label{
  font-size:12px;
  line-height:1.25;
  color:#24415c;
}

.form-label{
  display:block;
  margin-bottom:8px;
  font-size:13px;
  font-weight:900;
  color:#3a536b;
}

.form-control{
  width:100%;
  border-radius:16px;
  border:1px solid #dbe5ef;
  background:#fbfdff;
  min-height:96px;
  padding:12px 14px;
  resize:vertical;
}

.sheet-actions{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:10px;
  margin-top:14px;
}

.btn-sheet{
  min-height:46px;
  border:none;
  border-radius:16px;
  font-size:14px;
  font-weight:1000;
}

.btn-sheet.cancel{
  background:#eff4f8;
  color:#29445f;
}

.btn-sheet.save{
  background:linear-gradient(135deg,#13b5b0,#1c86f0);
  color:#fff;
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
  .header-nav-days,
  .header-stats,
  .card-actions,
  .sheet-actions,
  .social-grid{
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
        <div class="eyebrow">Gestão de Pessoas</div>
        <h1>Aniversários do dia</h1>
        <div class="sub">Uso rápido de rua: priorize, marque social mídia e siga para o próximo contato.</div>
      </div>

      <div class="date-chip">
        <strong><?= $dataBase->format('d') ?></strong>
        <span><?= h($mesLabel) ?></span>
      </div>
    </div>

    <div style="margin-top:12px;">
      <a href="/dashboard/index.php" class="btn-voltar">
        <i class="bi bi-chevron-left"></i>
        Voltar
      </a>
    </div>

    <div class="header-nav-days">
      <a href="<?= h($linkDiaAnterior) ?>" class="btn-day-nav">
        <i class="bi bi-chevron-left"></i>
        Dia anterior
      </a>

      <a href="<?= h($linkHoje) ?>" class="btn-day-nav today">
        <i class="bi bi-calendar-event"></i>
        <?= $ehHoje ? 'Hoje' : 'Ir para hoje' ?>
      </a>

      <a href="<?= h($linkProximoDia) ?>" class="btn-day-nav">
        Próximo dia
        <i class="bi bi-chevron-right"></i>
      </a>
    </div>

    <div class="header-stats">
      <div class="header-stat">
        <strong><?= (int)$totalHoje ?></strong>
        <span>Total do dia</span>
      </div>
    </div>
  </div>

  <div class="content">
    <div class="content-top">
      <h2>Boa tarde, <?= h($nomeExibicao) ?></h2>
      <div class="content-top-meta">
        <div class="date"><?= h($dataLabelCurta) ?></div>
        <div class="date-long"><?= h($dataLabelCompleta) ?></div>
      </div>
    </div>

    <?php if ($erroTela !== ''): ?>
      <div class="error-box">
        <h3>Não consegui montar a lista</h3>
        <p><?= h($erroTela) ?></p>
      </div>
    <?php elseif (!$aniversariantes): ?>
      <div class="empty-box">
        <h3>Nenhum aniversário neste dia</h3>
        <p>Nenhum aniversariante encontrado para <?= h($dataLabelCurta) ?>.</p>
      </div>
    <?php else: ?>
      <div class="list" id="birthdayList">
        <?php foreach ($aniversariantes as $item): ?>
          <?php
            $isNova = ($item['origem'] === 'nova');
            $resumo = [];
            foreach ($item['canais'] as $canal) {
                $resumo[] = socialLabel($canal);
            }
            if (!$resumo && $item['comentario_midia'] !== '') {
                $resumo[] = 'Comentário salvo';
            }
          ?>
          <article
            class="person-card <?= $item['lista_importante'] === 'sim' ? 'is-important' : '' ?>"
            data-id="<?= (int)$item['id_ref'] ?>"
            data-origem="<?= h($item['origem']) ?>"
            data-nome="<?= h((string)$item['nome']) ?>"
            data-lista-importante="<?= h((string)$item['lista_importante']) ?>"
            data-comentario="<?= h((string)$item['comentario_midia']) ?>"
            data-canais='<?= h(json_encode($item['canais'], JSON_UNESCAPED_UNICODE)) ?>'
          >
            <div class="person-head">
              <div>
                <h3 class="person-name"><?= h((string)$item['nome']) ?></h3>

                <div class="person-meta">
                  <?php if (!empty($item['idade'])): ?>
                    <span class="pill"><?= (int)$item['idade'] ?> anos</span>
                  <?php endif; ?>

                  <span class="pill <?= $isNova ? 'current' : 'legacy' ?>">
                    <?= $isNova ? 'Base atual' : 'Base antiga' ?>
                  </span>
                </div>

                <div class="label-row">
                  <?php if ($item['lista_importante'] === 'sim'): ?>
                    <span class="label-chip green badge-important-app">
                      <i class="bi bi-star-fill"></i>
                      Importante
                    </span>
                  <?php endif; ?>

                  <?php foreach ($item['canais'] as $canal): ?>
                    <span class="label-chip badge-social-app" data-social="<?= h($canal) ?>">
                      <img src="<?= h(socialIconPath($canal)) ?>" alt="">
                      <?= h(socialLabel($canal)) ?>
                    </span>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <div class="card-actions">
              <button type="button" class="btn-action btn-important <?= $item['lista_importante'] === 'sim' ? 'is-on' : '' ?>" data-action="toggle-important">
                <i class="bi bi-star-fill"></i>
                <?= $item['lista_importante'] === 'sim' ? 'Importante' : 'Marcar importante' ?>
              </button>

              <button type="button" class="btn-action btn-social" data-action="open-social">
                <i class="bi bi-megaphone-fill"></i>
                Social mídia
              </button>
            </div>

            <div class="social-summary">
              <small>Ações rápidas</small>
              <strong class="summary-text">
                <?= $resumo ? h(implode(' • ', $resumo)) : 'Nenhuma ação definida' ?>
              </strong>
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

<div class="sheet-backdrop" id="sheetBackdrop"></div>

<div class="sheet" id="socialSheet">
  <div class="sheet-handle"></div>
  <h3 id="sheetTitle">Social mídia</h3>
  <p>Selecione os canais, escreva um comentário rápido e salve.</p>

  <input type="hidden" id="sheetOrigem">
  <input type="hidden" id="sheetId">

  <div class="social-grid" id="socialGrid">
    <button type="button" class="social-item" data-value="instagram_feed">
      <img src="/assets/img/social/instagram.png" alt="">
      <span class="label">Instagram feed</span>
    </button>

    <button type="button" class="social-item" data-value="instagram_stories">
      <img src="/assets/img/social/instagram.png" alt="">
      <span class="label">Instagram stories</span>
    </button>

    <button type="button" class="social-item" data-value="facebook_feed">
      <img src="/assets/img/social/facebook.png" alt="">
      <span class="label">Facebook feed</span>
    </button>

    <button type="button" class="social-item" data-value="facebook_stories">
      <img src="/assets/img/social/facebook.png" alt="">
      <span class="label">Facebook stories</span>
    </button>
  </div>

  <label class="form-label" for="sheetComentario">Comentário</label>
  <textarea id="sheetComentario" class="form-control" placeholder="Ex.: story cedo, lembrar postagem, responder depois..."></textarea>

  <div class="sheet-actions">
    <button type="button" class="btn-sheet cancel" id="closeSheetBtn">Cancelar</button>
    <button type="button" class="btn-sheet save" id="saveSheetBtn">Salvar</button>
  </div>
</div>

<div class="toast-app" id="toastApp"></div>

<script>
(() => {
  const toast = document.getElementById('toastApp');
  const sheet = document.getElementById('socialSheet');
  const backdrop = document.getElementById('sheetBackdrop');
  const sheetTitle = document.getElementById('sheetTitle');
  const sheetOrigem = document.getElementById('sheetOrigem');
  const sheetId = document.getElementById('sheetId');
  const sheetComentario = document.getElementById('sheetComentario');
  const saveSheetBtn = document.getElementById('saveSheetBtn');
  const closeSheetBtn = document.getElementById('closeSheetBtn');
  const socialGrid = document.getElementById('socialGrid');

  let activeCard = null;
  let canaisSelecionados = [];
  let toastTimer = null;

  const SOCIAL_MAP = {
    instagram_feed: {
      label: 'Instagram Feed',
      icon: '/assets/img/social/instagram.png'
    },
    instagram_stories: {
      label: 'Instagram Stories',
      icon: '/assets/img/social/instagram.png'
    },
    facebook_feed: {
      label: 'Facebook Feed',
      icon: '/assets/img/social/facebook.png'
    },
    facebook_stories: {
      label: 'Facebook Stories',
      icon: '/assets/img/social/facebook.png'
    }
  };

  function showToast(message, isError = false) {
    toast.textContent = message;
    toast.style.background = isError ? '#8b1e2d' : '#16253d';
    toast.classList.add('show');

    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      toast.classList.remove('show');
    }, 2200);
  }

  function parseJsonSafe(value) {
    try {
      const parsed = JSON.parse(value || '[]');
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }

  function socialLabel(key) {
    return SOCIAL_MAP[key] ? SOCIAL_MAP[key].label : key;
  }

  function socialIcon(key) {
    return SOCIAL_MAP[key] ? SOCIAL_MAP[key].icon : '/assets/img/social/reload.png';
  }

  function openSheet(card) {
    activeCard = card;
    canaisSelecionados = parseJsonSafe(card.dataset.canais || '[]');

    sheetTitle.textContent = 'Social mídia • ' + (card.dataset.nome || 'Pessoa');
    sheetOrigem.value = card.dataset.origem || '';
    sheetId.value = card.dataset.id || '';
    sheetComentario.value = card.dataset.comentario || '';

    syncSocialGrid();
    backdrop.classList.add('is-open');
    sheet.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }

  function closeSheet() {
    backdrop.classList.remove('is-open');
    sheet.classList.remove('is-open');
    document.body.style.overflow = '';
    activeCard = null;
    canaisSelecionados = [];
  }

  function syncSocialGrid() {
    socialGrid.querySelectorAll('.social-item').forEach(btn => {
      const value = btn.dataset.value;
      btn.classList.toggle('active', canaisSelecionados.includes(value));
    });
  }

  function toggleCanal(value) {
    if (canaisSelecionados.includes(value)) {
      canaisSelecionados = canaisSelecionados.filter(item => item !== value);
    } else {
      canaisSelecionados.push(value);
    }
    syncSocialGrid();
  }

  function resumoCanais(canais, comentario) {
    const labels = canais.map(socialLabel);
    if (!labels.length && comentario) {
      return 'Comentário salvo';
    }
    return labels.length ? labels.join(' • ') : 'Nenhuma ação definida';
  }

  function syncImportantBadge(card, ativo) {
    const container = card.querySelector('.label-row');
    if (!container) return;

    let badge = container.querySelector('.badge-important-app');

    if (ativo && !badge) {
      badge = document.createElement('span');
      badge.className = 'label-chip green badge-important-app';
      badge.innerHTML = '<i class="bi bi-star-fill"></i> Importante';
      container.prepend(badge);
    }

    if (!ativo && badge) {
      badge.remove();
    }
  }

  function syncSocialBadges(card, canais) {
    const container = card.querySelector('.label-row');
    if (!container) return;

    container.querySelectorAll('.badge-social-app').forEach(el => el.remove());

    canais.forEach(canal => {
      const badge = document.createElement('span');
      badge.className = 'label-chip badge-social-app';
      badge.dataset.social = canal;
      badge.innerHTML = '<img src="' + socialIcon(canal) + '" alt="">' + socialLabel(canal);
      container.appendChild(badge);
    });
  }

  function updateCardState(card, payload) {
    if (!card) return;

    if (typeof payload.lista_importante !== 'undefined') {
      card.dataset.listaImportante = payload.lista_importante;
    }

    if (typeof payload.comentario_midia !== 'undefined') {
      card.dataset.comentario = payload.comentario_midia || '';
    }

    if (typeof payload.canais_social_json !== 'undefined') {
      card.dataset.canais = JSON.stringify(payload.canais_social_json || []);
    }

    const listaImportante = card.dataset.listaImportante === 'sim';
    const comentario = card.dataset.comentario || '';
    const canais = parseJsonSafe(card.dataset.canais || '[]');

    card.classList.toggle('is-important', listaImportante);

    const btnImportant = card.querySelector('[data-action="toggle-important"]');
    if (btnImportant) {
      btnImportant.classList.toggle('is-on', listaImportante);
      btnImportant.innerHTML = listaImportante
        ? '<i class="bi bi-star-fill"></i> Importante'
        : '<i class="bi bi-star-fill"></i> Marcar importante';
    }

    syncImportantBadge(card, listaImportante);
    syncSocialBadges(card, canais);

    const summary = card.querySelector('.summary-text');
    if (summary) {
      summary.textContent = resumoCanais(canais, comentario);
    }
  }

  async function postData(formData) {
    const response = await fetch(window.location.pathname + window.location.search, {
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
    const btnImportant = event.target.closest('[data-action="toggle-important"]');
    if (btnImportant) {
      const card = btnImportant.closest('.person-card');
      if (!card) return;

      const novoValor = card.dataset.listaImportante === 'sim' ? 'nao' : 'sim';
      const originalHtml = btnImportant.innerHTML;
      btnImportant.disabled = true;
      btnImportant.innerHTML = 'Processando...';

      try {
        const fd = new FormData();
        fd.append('action', 'toggle_importante');
        fd.append('origem', card.dataset.origem || '');
        fd.append('id', card.dataset.id || '');
        fd.append('lista_importante', novoValor);

        const resp = await postData(fd);
        updateCardState(card, resp);
        showToast(novoValor === 'sim' ? 'Marcado como importante.' : 'Removido dos importantes.');
      } catch (e) {
        showToast(e.message || 'Erro ao atualizar.', true);
      } finally {
        btnImportant.disabled = false;
        if (btnImportant.innerHTML === 'Processando...') {
          btnImportant.innerHTML = originalHtml;
        }
      }
      return;
    }

    const btnOpenSocial = event.target.closest('[data-action="open-social"]');
    if (btnOpenSocial) {
      const card = btnOpenSocial.closest('.person-card');
      if (card) {
        openSheet(card);
      }
      return;
    }

    const socialItem = event.target.closest('.social-item');
    if (socialItem) {
      toggleCanal(socialItem.dataset.value);
      return;
    }
  });

  saveSheetBtn.addEventListener('click', async () => {
    if (!activeCard) return;

    const originalText = saveSheetBtn.textContent;
    saveSheetBtn.disabled = true;
    saveSheetBtn.textContent = 'Salvando...';

    try {
      const fd = new FormData();
      fd.append('action', 'salvar_social');
      fd.append('origem', sheetOrigem.value);
      fd.append('id', sheetId.value);
      fd.append('comentario_midia', sheetComentario.value.trim());

      canaisSelecionados.forEach(canal => fd.append('canais[]', canal));

      const resp = await postData(fd);
      updateCardState(activeCard, resp);
      closeSheet();
      showToast('Social mídia salva.');
    } catch (e) {
      showToast(e.message || 'Erro ao salvar.', true);
    } finally {
      saveSheetBtn.disabled = false;
      saveSheetBtn.textContent = originalText;
    }
  });

  closeSheetBtn.addEventListener('click', closeSheet);
  backdrop.addEventListener('click', closeSheet);

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && sheet.classList.contains('is-open')) {
      closeSheet();
    }
  });
})();
</script>

</body>
</html>