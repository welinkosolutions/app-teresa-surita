<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');

session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$pdo = dbRoraima();
$liderId = (int)($_SESSION['pessoa_id'] ?? 0);

function garantirColunaCodigoDemanda(PDO $pdo): void
{
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM demandas LIKE 'codigo_demanda'");
        if (!$stmt || !$stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE demandas ADD COLUMN codigo_demanda VARCHAR(5) NULL AFTER protocolo");
        }
    } catch (Throwable $e) {
        // Se o usuario do banco nao puder alterar tabela, use o SQL enviado junto no ZIP.
    }
}

garantirColunaCodigoDemanda($pdo);

$stmt = $pdo->prepare("SELECT perfil FROM pessoas WHERE id = ? LIMIT 1");
$stmt->execute([$liderId]);
$perfil = trim((string)($stmt->fetchColumn() ?? 'pessoa'));

$temAcessoTotal = in_array($perfil, ['admin', 'gestor_lideres'], true);

if (!$temAcessoTotal && $perfil !== 'lider') {
    header('Location: /interno/admin.php');
    exit;
}

if (empty($_SESSION['demandas_csrf']) || !is_string($_SESSION['demandas_csrf'])) {
    $_SESSION['demandas_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['demandas_csrf'];

function h(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function codigoDemandaClasse(?string $codigo): string {
    return match ($codigo) {
        'DM-01' => 'codigo-dm-01',
        'DM-02' => 'codigo-dm-02',
        'DM-03' => 'codigo-dm-03',
        'DM-04' => 'codigo-dm-04',
        'DM-05' => 'codigo-dm-05',
        'DM-06' => 'codigo-dm-06',
        'DM-07' => 'codigo-dm-07',
        'DM-08' => 'codigo-dm-08',
        'DM-09' => 'codigo-dm-09',
        'DM-10' => 'codigo-dm-10',
        default => 'codigo-dm-default',
    };
}

function somenteDigitos(?string $v): string {
    return preg_replace('/\D+/', '', (string)$v);
}

function gerarLinkWhats(?string $telefone): ?string {
    $n = somenteDigitos($telefone);
    if (strlen($n) < 10) return null;
    if (!str_starts_with($n, '55')) $n = '55' . $n;
    return 'https://wa.me/' . $n;
}

function dataHumana(?string $data): string {
    if (!$data) return '-';
    $ts = strtotime($data);
    if (!$ts) return '-';

    $hoje = date('Y-m-d');
    $ontem = date('Y-m-d', strtotime('-1 day'));
    $dia = date('Y-m-d', $ts);

    if ($dia === $hoje) return 'Hoje, ' . date('H:i', $ts);
    if ($dia === $ontem) return 'Ontem, ' . date('H:i', $ts);
    return date('d/m/Y H:i', $ts);
}

function tempoAberta(?string $criadoEm, ?string $status = null, ?string $resolvidaEm = null): string {
    if (!$criadoEm) return '-';

    $inicio = strtotime($criadoEm);
    if (!$inicio) return '-';

    $fim = time();
    if ($status === 'fechado' && $resolvidaEm) {
        $fimResolvida = strtotime($resolvidaEm);
        if ($fimResolvida) $fim = $fimResolvida;
    }

    $horas = max(0, (int)floor(($fim - $inicio) / 3600));

    if ($horas < 1) return 'agora';
    if ($horas < 24) return $horas . 'h';

    $dias = (int)floor($horas / 24);
    return $dias . ' dia' . ($dias > 1 ? 's' : '');
}

function statusLabel(?string $status): string {
    return match ($status) {
        'aberto' => 'Aberta',
        'em_atendimento' => 'Em atendimento',
        'transferido' => 'Transferida',
        'fechado' => 'Resolvida',
        default => 'Indefinida',
    };
}

function statusClasse(?string $status): string {
    return match ($status) {
        'aberto' => 'aberta',
        'em_atendimento' => 'andamento',
        'transferido' => 'transferida',
        'fechado' => 'resolvida',
        default => 'normal',
    };
}

function prioridadeLabel(?string $p): string {
    return match ($p) {
        'urgente' => 'Urgente',
        'alta' => 'Alta',
        default => 'Normal',
    };
}

function montarUrl(array $extras = []): string {
    $q = $_GET;
    foreach ($extras as $k => $v) {
        if ($v === null || $v === '') unset($q[$k]);
        else $q[$k] = $v;
    }
    return '/lideranca/demandas.php' . ($q ? '?' . http_build_query($q) : '');
}

function redirectComContexto(array $extras = []): never {
    header('Location: ' . montarUrl($extras));
    exit;
}

$busca = trim((string)($_GET['busca'] ?? ''));
$filtro = trim((string)($_GET['filtro'] ?? 'pendentes'));
$paginaAtual = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina = 15;

$filtrosValidos = ['todas', 'pendentes', 'resolvidas', 'urgentes', 'visita'];
if (!in_array($filtro, $filtrosValidos, true)) {
    $filtro = 'pendentes';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');
    $token = (string)($_POST['csrf_token'] ?? '');
    $demandaId = (int)($_POST['demanda_id'] ?? 0);

    if ($token === '' || !hash_equals((string)$_SESSION['demandas_csrf'], $token) || $demandaId <= 0) {
        redirectComContexto(['erro' => 'acao']);
    }

    $sqlPermissao = $temAcessoTotal
        ? "SELECT id FROM demandas WHERE id = ? LIMIT 1"
        : "
            SELECT d.id
            FROM demandas d
            LEFT JOIN demandas_responsaveis dr ON dr.demanda_id = d.id AND dr.ativo = 'sim'
            WHERE d.id = ?
              AND (d.criado_por = ? OR d.responsavel_id = ? OR dr.lider_id = ?)
            LIMIT 1
        ";

    $stmtPermissao = $pdo->prepare($sqlPermissao);
    $temAcessoTotal
        ? $stmtPermissao->execute([$demandaId])
        : $stmtPermissao->execute([$demandaId, $liderId, $liderId, $liderId]);

    if (!$stmtPermissao->fetchColumn()) {
        redirectComContexto(['erro' => 'permissao']);
    }

    if ($acao === 'resolver') {
        $resolucaoComentario = trim((string)($_POST['resolucao_texto'] ?? ''));

        if ($resolucaoComentario === '') {
            redirectComContexto(['erro' => 'sem_resolucao']);
        }

        $pdo->beginTransaction();

        try {
            $pdo->prepare("
                UPDATE demandas
                SET status = 'fechado',
                    resolucao = 'resolvido',
                    resolucao_comentario = ?,
                    resolvida_em = NOW(),
                    resolvido_em = NOW(),
                    atualizado_em = NOW(),
                    autor_acao_id = ?
                WHERE id = ?
                LIMIT 1
            ")->execute([$resolucaoComentario, $liderId, $demandaId]);

            $pdo->prepare("
                INSERT INTO demandas_eventos
                (demanda_id, tipo, valor_anterior, valor_novo, autor_id, autor_tipo, criado_em)
                VALUES (?, 'status_alterado', NULL, 'fechado', ?, 'admin', NOW())
            ")->execute([$demandaId, $liderId]);

            $pdo->prepare("
                INSERT INTO demandas_eventos
                (demanda_id, tipo, valor_anterior, valor_novo, autor_id, autor_tipo, criado_em)
                VALUES (?, 'resolucao_alterada', NULL, 'resolvido', ?, 'admin', NOW())
            ")->execute([$demandaId, $liderId]);

            $pdo->commit();
            redirectComContexto(['ok' => 'resolvida']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            redirectComContexto(['erro' => 'resolver']);
        }
    }

    if ($acao === 'visitar') {
        $stmtPessoa = $pdo->prepare("SELECT pessoa_id FROM demandas WHERE id = ? LIMIT 1");
        $stmtPessoa->execute([$demandaId]);
        $pessoaId = (int)($stmtPessoa->fetchColumn() ?: 0);

        if ($pessoaId > 0) {
            $stmtCheck = $pdo->prepare("
                SELECT id
                FROM demandas_visitas
                WHERE demanda_id = ?
                  AND status IN ('pendente','agendada')
                LIMIT 1
            ");
            $stmtCheck->execute([$demandaId]);

            if (!$stmtCheck->fetchColumn()) {
                $pdo->prepare("
                    INSERT INTO demandas_visitas
                    (demanda_id, pessoa_id, responsavel_id, status, criado_em)
                    VALUES (?, ?, ?, 'pendente', NOW())
                ")->execute([$demandaId, $pessoaId, $liderId]);
            }

            $pdo->prepare("
                UPDATE demandas
                SET importante_visitar = 'sim', atualizado_em = NOW()
                WHERE id = ?
                LIMIT 1
            ")->execute([$demandaId]);
        }

        redirectComContexto(['ok' => 'visita']);
    }
}

$where = [];
$params = [];

if ($temAcessoTotal) {
    $where[] = "1=1";
} else {
    $where[] = "(d.criado_por = ? OR d.responsavel_id = ? OR dr.lider_id = ?)";
    $params[] = $liderId;
    $params[] = $liderId;
    $params[] = $liderId;
}

if ($filtro === 'pendentes') {
    $where[] = "d.status <> 'fechado'";
} elseif ($filtro === 'resolvidas') {
    $where[] = "d.status = 'fechado'";
} elseif ($filtro === 'urgentes') {
    $where[] = "d.prioridade = 'urgente' AND d.status <> 'fechado'";
} elseif ($filtro === 'visita') {
    $where[] = "(d.importante_visitar = 'sim' OR dv.id IS NOT NULL) AND d.status <> 'fechado'";
}

if ($busca !== '') {
    $where[] = "
        (
            d.titulo LIKE ?
            OR d.descricao LIKE ?
            OR d.protocolo LIKE ?
            OR p.nome LIKE ?
            OR p.telefone LIKE ?
            OR pe.bairro LIKE ?
            OR pe.cidade LIKE ?
        )
    ";
    $like = '%' . $busca . '%';
    array_push($params, $like, $like, $like, $like, $like, $like, $like);
}

$whereSql = implode(' AND ', $where);

$sqlKpisBase = "
    FROM demandas d
    LEFT JOIN demandas_responsaveis dr ON dr.demanda_id = d.id AND dr.ativo = 'sim'
    LEFT JOIN pessoas p ON p.id = d.pessoa_id
    LEFT JOIN pessoas_enderecos pe ON pe.id = (
        SELECT pe2.id
        FROM pessoas_enderecos pe2
        WHERE pe2.pessoa_id = p.id
        ORDER BY pe2.id DESC
        LIMIT 1
    )
    LEFT JOIN demandas_visitas dv ON dv.demanda_id = d.id AND dv.status IN ('pendente','agendada')
    WHERE " . ($temAcessoTotal ? "1=1" : "(d.criado_por = ? OR d.responsavel_id = ? OR dr.lider_id = ?)");

$paramsKpi = $temAcessoTotal ? [] : [$liderId, $liderId, $liderId];

$stmtKpis = $pdo->prepare("
    SELECT
        COUNT(DISTINCT d.id) AS total,
        COUNT(DISTINCT CASE WHEN d.status <> 'fechado' THEN d.id END) AS pendentes,
        COUNT(DISTINCT CASE WHEN d.status = 'fechado' THEN d.id END) AS resolvidas,
        COUNT(DISTINCT CASE WHEN d.prioridade = 'urgente' AND d.status <> 'fechado' THEN d.id END) AS urgentes,
        COUNT(DISTINCT CASE WHEN (d.importante_visitar = 'sim' OR dv.id IS NOT NULL) AND d.status <> 'fechado' THEN d.id END) AS visitas
    {$sqlKpisBase}
");
$stmtKpis->execute($paramsKpi);
$kpis = $stmtKpis->fetch(PDO::FETCH_ASSOC) ?: [];

$totalDemandas = (int)($kpis['total'] ?? 0);
$totalPendentes = (int)($kpis['pendentes'] ?? 0);
$totalResolvidas = (int)($kpis['resolvidas'] ?? 0);
$totalUrgentes = (int)($kpis['urgentes'] ?? 0);
$totalVisitas = (int)($kpis['visitas'] ?? 0);

$stmtTotal = $pdo->prepare("
    SELECT COUNT(DISTINCT d.id)
    FROM demandas d
    LEFT JOIN demandas_responsaveis dr ON dr.demanda_id = d.id AND dr.ativo = 'sim'
    LEFT JOIN pessoas p ON p.id = d.pessoa_id
    LEFT JOIN pessoas_enderecos pe ON pe.id = (
        SELECT pe2.id
        FROM pessoas_enderecos pe2
        WHERE pe2.pessoa_id = p.id
        ORDER BY pe2.id DESC
        LIMIT 1
    )
    LEFT JOIN demandas_visitas dv ON dv.demanda_id = d.id AND dv.status IN ('pendente','agendada')
    WHERE {$whereSql}
");
$stmtTotal->execute($params);
$totalRegistros = (int)$stmtTotal->fetchColumn();
$totalPaginas = max(1, (int)ceil($totalRegistros / $porPagina));

if ($paginaAtual > $totalPaginas) $paginaAtual = $totalPaginas;
$offset = ($paginaAtual - 1) * $porPagina;

$stmtLista = $pdo->prepare("
    SELECT DISTINCT
        d.id,
        d.protocolo,
        d.codigo_demanda,
        d.titulo,
        d.descricao,
        d.status,
        d.prioridade,
        d.sla_status,
        d.categoria,
        d.origem,
        d.importante_visitar,
        d.criado_em,
        d.resolvida_em,
        d.resolvido_em,
        d.responsavel_id,

        p.nome AS pessoa_nome,
        p.telefone AS pessoa_telefone,

        pe.bairro,
        pe.cidade,
        pe.estado,
        pe.endereco,
        pe.numero,

        dr.lider_id AS lider_atual_id,
        resp.nome AS lider_atual_nome,
        resp_legacy.nome AS responsavel_legacy_nome,

        dv.id AS visita_id,
        dv.status AS visita_status,

        ult.ultima_resposta_em,
        ult.total_respostas

    FROM demandas d

    LEFT JOIN pessoas p ON p.id = d.pessoa_id

    LEFT JOIN pessoas_enderecos pe ON pe.id = (
        SELECT pe2.id
        FROM pessoas_enderecos pe2
        WHERE pe2.pessoa_id = p.id
        ORDER BY pe2.id DESC
        LIMIT 1
    )

    LEFT JOIN demandas_responsaveis dr ON dr.demanda_id = d.id AND dr.ativo = 'sim'
    LEFT JOIN pessoas resp ON resp.id = dr.lider_id
    LEFT JOIN pessoas resp_legacy ON resp_legacy.id = d.responsavel_id

    LEFT JOIN demandas_visitas dv ON dv.demanda_id = d.id AND dv.status IN ('pendente','agendada')

    LEFT JOIN (
        SELECT demanda_id, MAX(criado_em) AS ultima_resposta_em, COUNT(*) AS total_respostas
        FROM respostas_demandas
        GROUP BY demanda_id
    ) ult ON ult.demanda_id = d.id

    WHERE {$whereSql}

    ORDER BY
        CASE WHEN d.status = 'fechado' THEN 2 ELSE 1 END ASC,
        FIELD(d.prioridade, 'urgente', 'alta', 'normal') ASC,
        CASE
            WHEN d.status = 'aberto' THEN 1
            WHEN d.status = 'em_atendimento' THEN 2
            WHEN d.status = 'transferido' THEN 3
            WHEN d.status = 'fechado' THEN 4
            ELSE 5
        END ASC,
        d.criado_em DESC

    LIMIT {$porPagina} OFFSET {$offset}
");
$stmtLista->execute($params);
$demandas = $stmtLista->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Demandas</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
:root{
    --bg:#f4f6f8;
    --card:#ffffff;
    --line:#e5e7eb;
    --text:#111827;
    --muted:#6b7280;
    --green:#198754;
    --red:#dc3545;
    --orange:#f59e0b;
    --blue:#2563eb;
    --dark:#212529;
    --shadow:0 8px 24px rgba(15,23,42,.08);
}

body{
    background:var(--bg);
    color:var(--text);
    font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
}

.page{
    max-width:760px;
    margin:0 auto;
    padding:16px 12px 32px;
}

.topbar{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
    margin-bottom:14px;
}

.top-left{
    min-width:0;
}

.back-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    border-radius:999px;
    font-weight:900;
    padding:8px 13px;
    margin-bottom:10px;
    background:#fff;
    border:1px solid var(--line);
    color:#111827;
    text-decoration:none;
    box-shadow:0 4px 12px rgba(15,23,42,.04);
}

h1{
    font-size:24px;
    font-weight:900;
    margin:0;
    letter-spacing:-.04em;
}

.subtitle{
    font-size:13px;
    color:var(--muted);
    margin-top:3px;
}

.btn-main{
    border-radius:999px;
    font-weight:900;
    padding:11px 18px;
    white-space:nowrap;
}

.kpis{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:10px;
    margin-bottom:12px;
}

.kpi{
    background:#fff;
    border:1px solid var(--line);
    border-radius:18px;
    padding:14px 12px;
    box-shadow:var(--shadow);
}

.kpi-label{
    font-size:11px;
    color:var(--muted);
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
}

.kpi-value{
    font-size:28px;
    font-weight:950;
    line-height:1;
    margin-top:7px;
}

.filter-box{
    background:#fff;
    border:1px solid var(--line);
    border-radius:18px;
    padding:12px;
    box-shadow:var(--shadow);
    margin-bottom:12px;
}

.quick-filters{
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:8px;
    margin-top:10px;
    overflow:visible;
    padding-bottom:0;
}

.quick-filters a{
    white-space:normal;
    text-align:center;
    border-radius:999px;
    font-size:13px;
    font-weight:900;
    padding:10px 8px;
    text-decoration:none;
}

.list-info{
    font-size:13px;
    color:var(--muted);
    margin:12px 2px;
}

.demanda-card{
    background:#fff;
    border:1px solid var(--line);
    border-radius:22px;
    box-shadow:var(--shadow);
    padding:16px;
    margin-bottom:14px;
    position:relative;
    overflow:hidden;
}

.demanda-card:before{
    content:"";
    position:absolute;
    left:0;
    top:0;
    bottom:0;
    width:7px;
    background:#cbd5e1;
}

.demanda-card.aberta:before{background:var(--red)}
.demanda-card.andamento:before{background:var(--orange)}
.demanda-card.transferida:before{background:#0dcaf0}
.demanda-card.resolvida:before{background:var(--green)}

.person-name{
    font-size:22px;
    font-weight:950;
    line-height:1.08;
    letter-spacing:-.04em;
    margin-bottom:5px;
    padding-left:4px;
}

.meta-line{
    font-size:14px;
    color:#374151;
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    margin-bottom:8px;
    padding-left:4px;
}

.status-row{
    display:flex;
    flex-wrap:wrap;
    gap:7px;
    margin:10px 0;
}

.status-pill{
    border-radius:999px;
    padding:8px 12px;
    font-size:13px;
    font-weight:950;
}

.status-pill.aberta{background:#fee2e2;color:#991b1b}
.status-pill.andamento{background:#fef3c7;color:#92400e}
.status-pill.transferida{background:#cffafe;color:#155e75}
.status-pill.resolvida{background:#dcfce7;color:#166534}

.flag{
    border-radius:999px;
    padding:8px 12px;
    font-size:13px;
    font-weight:950;
    background:#f1f5f9;
    color:#334155;
}

.flag.urgent{background:#fee2e2;color:#991b1b}
.flag.sla{background:#ffedd5;color:#9a3412}
.flag.visit{background:#fef3c7;color:#854d0e}

.codigo-demanda{
    border:0;
    border-radius:999px;
    padding:7px 11px;
    font-size:12px;
    font-weight:950;
    color:#fff;
    line-height:1;
    cursor:default;
    box-shadow:0 5px 14px rgba(15,23,42,.14);
}
.codigo-dm-01{background:#dc2626}
.codigo-dm-02{background:#ea580c}
.codigo-dm-03{background:#d97706}
.codigo-dm-04{background:#65a30d}
.codigo-dm-05{background:#16a34a}
.codigo-dm-06{background:#0891b2}
.codigo-dm-07{background:#2563eb}
.codigo-dm-08{background:#7c3aed}
.codigo-dm-09{background:#c026d3}
.codigo-dm-10{background:#475569}
.codigo-dm-default{background:#6b7280}

.summary{
    background:#f8fafc;
    border:1px solid #edf2f7;
    border-radius:16px;
    padding:12px;
    margin:10px 0;
    font-size:14px;
    line-height:1.35;
    color:#1f2937;
}

.time-line{
    font-size:13px;
    color:var(--muted);
    margin-bottom:12px;
    display:flex;
    flex-wrap:wrap;
    gap:8px;
}

.actions{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:8px;
}

.actions .btn{
    min-height:46px;
    border-radius:14px;
    font-weight:900;
}

.btn-resolver{
    background:#dcfce7;
    color:#166534;
    border:1px solid #bbf7d0;
}

.btn-transferir{
    background:#eff6ff;
    color:#1d4ed8;
    border:1px solid #bfdbfe;
}

.protocol{
    font-size:11px;
    color:#94a3b8;
    margin-top:10px;
    padding-left:4px;
}

.empty{
    background:#fff;
    border:1px dashed #cbd5e1;
    border-radius:18px;
    padding:26px;
    text-align:center;
    color:var(--muted);
    font-weight:800;
}

.pagination .page-link{
    border-radius:12px;
    margin:0 3px;
    color:#212529;
    font-weight:800;
}

.pagination .active .page-link{
    background:#212529;
    border-color:#212529;
}

@media(max-width:480px){
    .page{
        padding:14px 10px 28px;
    }

    .kpis{
        gap:8px;
    }

    .kpi{
        padding:12px 10px;
    }

    .kpi-value{
        font-size:25px;
    }

    .person-name{
        font-size:21px;
    }

    .actions{
        grid-template-columns:1fr;
    }

    .quick-filters{
        grid-template-columns:1fr 1fr;
    }

    h1{
        font-size:23px;
    }
}
</style>
</head>

<body>
<div class="page">

    <div class="topbar">
        <div class="top-left">
            <a href="/interno/admin.php" class="back-btn">← Voltar</a>
            <h1><?= $temAcessoTotal ? 'Todas as demandas' : 'Minhas demandas' ?></h1>
            <div class="subtitle">Tela simples para acompanhar e resolver rápido.</div>
        </div>

        <a href="/lideranca/nova-demanda.php" class="btn btn-warning btn-main">Nova</a>
    </div>

    <?php if (($_GET['ok'] ?? '') === 'resolvida'): ?>
        <div class="alert alert-success border-0 rounded-4">Demanda marcada como resolvida.</div>
    <?php endif; ?>

    <?php if (($_GET['erro'] ?? '') === 'sem_resolucao'): ?>
        <div class="alert alert-danger border-0 rounded-4">Informe a resolução antes de fechar a demanda.</div>
    <?php endif; ?>

    <?php if (($_GET['erro'] ?? '') === 'resolver'): ?>
        <div class="alert alert-danger border-0 rounded-4">Não foi possível resolver a demanda. Verifique se a tabela possui as colunas resolucao e resolucao_comentario.</div>
    <?php endif; ?>

    <?php if (($_GET['ok'] ?? '') === 'visita'): ?>
        <div class="alert alert-warning border-0 rounded-4">Demanda marcada para visita.</div>
    <?php endif; ?>

    <div class="kpis">
        <div class="kpi">
            <div class="kpi-label">Total</div>
            <div class="kpi-value"><?= number_format($totalDemandas, 0, ',', '.') ?></div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Pendentes</div>
            <div class="kpi-value"><?= number_format($totalPendentes, 0, ',', '.') ?></div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Resolvidas</div>
            <div class="kpi-value"><?= number_format($totalResolvidas, 0, ',', '.') ?></div>
        </div>
    </div>

    <div class="filter-box">
        <form method="get">
            <input
                type="text"
                name="busca"
                value="<?= h($busca) ?>"
                class="form-control rounded-4"
                placeholder="Buscar nome, telefone, bairro, protocolo..."
            >
            <input type="hidden" name="filtro" value="<?= h($filtro) ?>">
        </form>

        <div class="quick-filters">
            <a class="<?= $filtro === 'pendentes' ? 'btn btn-dark' : 'btn btn-light border' ?>" href="<?= h(montarUrl(['filtro' => 'pendentes', 'pagina' => 1])) ?>">Pendentes</a>
            <a class="<?= $filtro === 'todas' ? 'btn btn-dark' : 'btn btn-light border' ?>" href="<?= h(montarUrl(['filtro' => 'todas', 'pagina' => 1])) ?>">Todas</a>
            <a class="<?= $filtro === 'resolvidas' ? 'btn btn-dark' : 'btn btn-light border' ?>" href="<?= h(montarUrl(['filtro' => 'resolvidas', 'pagina' => 1])) ?>">Resolvidas</a>
            <a class="<?= $filtro === 'urgentes' ? 'btn btn-dark' : 'btn btn-light border' ?>" href="<?= h(montarUrl(['filtro' => 'urgentes', 'pagina' => 1])) ?>">Urgentes <?= $totalUrgentes ? '(' . $totalUrgentes . ')' : '' ?></a>
            <a class="<?= $filtro === 'visita' ? 'btn btn-dark' : 'btn btn-light border' ?>" href="<?= h(montarUrl(['filtro' => 'visita', 'pagina' => 1])) ?>">Visita <?= $totalVisitas ? '(' . $totalVisitas . ')' : '' ?></a>
        </div>
    </div>

    <div class="list-info">
        <?= number_format($totalRegistros, 0, ',', '.') ?> demanda(s) encontrada(s)
        <?php if ($totalPaginas > 1): ?>
            • página <?= $paginaAtual ?> de <?= $totalPaginas ?>
        <?php endif; ?>
    </div>

    <?php if (!$demandas): ?>
        <div class="empty">Nenhuma demanda encontrada.</div>
    <?php endif; ?>

    <?php foreach ($demandas as $d): ?>
        <?php
            $status = (string)($d['status'] ?? 'aberto');
            $classe = statusClasse($status);
            $whats = gerarLinkWhats($d['pessoa_telefone'] ?? null);

            $nome = trim((string)($d['pessoa_nome'] ?? ''));
            if ($nome === '') $nome = 'Solicitante não informado';

            $telefone = trim((string)($d['pessoa_telefone'] ?? ''));
            $local = trim(implode(' / ', array_filter([
                (string)($d['bairro'] ?? ''),
                (string)($d['cidade'] ?? ''),
            ])));
            if ($local === '') $local = 'Local não informado';

            $resumo = trim((string)($d['descricao'] ?? ''));
            if ($resumo === '') $resumo = trim((string)($d['titulo'] ?? 'Sem descrição.'));
            if (mb_strlen($resumo, 'UTF-8') > 150) {
                $resumo = mb_substr($resumo, 0, 150, 'UTF-8') . '...';
            }

            $liderNome = $d['lider_atual_nome'] ?: ($d['responsavel_legacy_nome'] ?: 'Não definido');
            $sla = (string)($d['sla_status'] ?? 'dentro');
            $temVisita = !empty($d['visita_id']) || ($d['importante_visitar'] ?? '') === 'sim';
        ?>

        <div class="demanda-card <?= h($classe) ?>">
            <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                <div class="person-name mb-0"><?= h($nome) ?></div>
                <?php if (!empty($d['codigo_demanda'])): ?>
                    <button type="button" class="codigo-demanda <?= h(codigoDemandaClasse((string)$d['codigo_demanda'])) ?>">
                        <?= h((string)$d['codigo_demanda']) ?>
                    </button>
                <?php endif; ?>
            </div>

            <div class="meta-line">
                <span>📞 <?= h($telefone ?: 'sem telefone') ?></span>
                <span>📍 <?= h($local) ?></span>
            </div>

            <div class="status-row">
                <span class="status-pill <?= h($classe) ?>"><?= h(statusLabel($status)) ?></span>

                <?php if (($d['prioridade'] ?? 'normal') !== 'normal'): ?>
                    <span class="flag urgent"><?= h(prioridadeLabel($d['prioridade'])) ?></span>
                <?php endif; ?>

                <?php if ($sla !== 'dentro'): ?>
                    <span class="flag sla">SLA <?= h($sla) ?></span>
                <?php endif; ?>

                <?php if ($temVisita): ?>
                    <span class="flag visit">Visita</span>
                <?php endif; ?>
            </div>

            <div class="summary">
                🧾 <?= h($resumo) ?>
            </div>

            <div class="time-line">
                <span>⏱ <?= h(tempoAberta($d['criado_em'] ?? null, $status, $d['resolvida_em'] ?? $d['resolvido_em'] ?? null)) ?></span>
                <span>Responsável: <?= h((string)$liderNome) ?></span>
                <span><?= h(dataHumana($d['criado_em'] ?? null)) ?></span>
            </div>

            <div class="actions">
                <a href="/lideranca/ver-demanda.php?id=<?= (int)$d['id'] ?>" class="btn btn-dark">Abrir</a>

                <?php if ($whats): ?>
                    <a href="<?= h($whats) ?>" target="_blank" rel="noopener" class="btn btn-success">WhatsApp</a>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary" disabled>Sem WhatsApp</button>
                <?php endif; ?>

                <?php if ($status !== 'fechado'): ?>
                    <button
                        type="button"
                        class="btn btn-resolver w-100"
                        data-bs-toggle="modal"
                        data-bs-target="#modalResolver<?= (int)$d['id'] ?>"
                    >Resolver</button>
                <?php else: ?>
                    <button type="button" class="btn btn-resolver" disabled>Resolvida</button>
                <?php endif; ?>

                <a href="/lideranca/transferir.php?id=<?= (int)$d['id'] ?>" class="btn btn-transferir">Transferir</a>
            </div>

            <?php if ($status !== 'fechado'): ?>
                <div class="modal fade" id="modalResolver<?= (int)$d['id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <form method="post" class="modal-content rounded-4 border-0">
                            <div class="modal-header border-0 pb-0">
                                <h5 class="modal-title fw-bold text-success">Resolver demanda</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>

                            <div class="modal-body pt-2">
                                <div class="mb-2 text-muted">Informe a resolução para fechar esta demanda.</div>
                                <textarea
                                    name="resolucao_texto"
                                    class="form-control rounded-4"
                                    rows="5"
                                    required
                                    placeholder="Ex: Atendimento realizado, demanda resolvida com o solicitante..."
                                ></textarea>

                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="acao" value="resolver">
                                <input type="hidden" name="demanda_id" value="<?= (int)$d['id'] ?>">
                            </div>

                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-light border rounded-4 fw-bold" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-success rounded-4 fw-bold">Confirmar resolução</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$temVisita && $status !== 'fechado'): ?>
                <form method="post" class="mt-2">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="acao" value="visitar">
                    <input type="hidden" name="demanda_id" value="<?= (int)$d['id'] ?>">
                    <button type="submit" class="btn btn-outline-warning w-100 rounded-4 fw-bold">Marcar para visita</button>
                </form>
            <?php endif; ?>

            <div class="protocol">
                Protocolo <?= h((string)($d['protocolo'] ?? '-')) ?> • <?= h((string)($d['categoria'] ?? '-')) ?> • <?= h((string)($d['origem'] ?? '-')) ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($totalPaginas > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center flex-wrap">
                <li class="page-item <?= $paginaAtual <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= h(montarUrl(['pagina' => max(1, $paginaAtual - 1)])) ?>">Anterior</a>
                </li>

                <?php
                $ini = max(1, $paginaAtual - 2);
                $fim = min($totalPaginas, $paginaAtual + 2);
                for ($p = $ini; $p <= $fim; $p++):
                ?>
                    <li class="page-item <?= $p === $paginaAtual ? 'active' : '' ?>">
                        <a class="page-link" href="<?= h(montarUrl(['pagina' => $p])) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?= $paginaAtual >= $totalPaginas ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= h(montarUrl(['pagina' => min($totalPaginas, $paginaAtual + 1)])) ?>">Próxima</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>