<?php
declare(strict_types=1);

/**
 * ======================================================
 * CAMINHO: /home/elab/app.elab.social/pessoas/aprovar-pessoa.php
 * NOME: Aprovar Pessoa / Ativar Cadastro V2
 *
 * DESCRIÇÃO:
 * - Lista pendências do convidador logado
 * - Aprova usando core/invite/novo.php
 * - Recusa usando core/invite/novo.php
 * - Não paga recompensa diretamente nesta tela
 * - Recompensas passam pelo core do game
 * - Layout V2 gamificado integrado ao footer fixo
 * ======================================================
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';
require_once '/home/elab/public_html/core/invite/novo.php';

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

$pdo = dbRoraima();
$pessoaId = (int) $_SESSION['pessoa_id'];

function apFlashSet(string $tipo, string $mensagem): void
{
    $_SESSION['aprovar_pessoa_flash'] = [
        'tipo' => $tipo,
        'mensagem' => $mensagem,
    ];
}

function apFlashGet(): ?array
{
    if (empty($_SESSION['aprovar_pessoa_flash']) || !is_array($_SESSION['aprovar_pessoa_flash'])) {
        return null;
    }

    $flash = $_SESSION['aprovar_pessoa_flash'];
    unset($_SESSION['aprovar_pessoa_flash']);

    return $flash;
}

function apEsc(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function apNomeExibicao(array $pessoa): string
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
    $curto = trim(implode(' ', array_slice($partes ?: [], 0, 2)));

    return $curto !== '' ? $curto : $nome;
}

function apDataHora(?string $valor): string
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

function apJsonFlags(?string $json): array
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

function apGarantirCsrf(): string
{
    if (empty($_SESSION['aprovar_pessoa_csrf']) || !is_string($_SESSION['aprovar_pessoa_csrf'])) {
        $_SESSION['aprovar_pessoa_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['aprovar_pessoa_csrf'];
}

function apValidarCsrf(?string $token): bool
{
    $sess = (string) ($_SESSION['aprovar_pessoa_csrf'] ?? '');
    $token = (string) $token;

    if ($sess === '' || $token === '') {
        return false;
    }

    return hash_equals($sess, $token);
}

function apRecompensaResumo(?array $resultado): string
{
    if (!$resultado || empty($resultado['recompensa']) || !is_array($resultado['recompensa'])) {
        return '';
    }

    $recompensa = $resultado['recompensa'];

    $impacto = (int) (
        $recompensa['impacto_ganho']
        ?? $recompensa['impactos_ganhos']
        ?? $recompensa['impacto']
        ?? $recompensa['impactos']
        ?? 0
    );

    $moedas = (int) ($recompensa['moedas'] ?? 0);
    $xp = (int) ($recompensa['xp_ganho'] ?? 0);

    $partes = [];

    if ($impacto > 0) {
        $partes[] = '+' . number_format($impacto, 0, ',', '.') . ' impacto';
    }

    if ($moedas > 0) {
        $partes[] = '+' . number_format($moedas, 0, ',', '.') . ' moedas';
    }

    if ($xp > 0) {
        $partes[] = '+' . number_format($xp, 0, ',', '.') . ' XP';
    }

    if (!$partes) {
        return '';
    }

    return ' Recompensa: ' . implode(' · ', $partes) . '.';
}

function apRiscoClasse(int $score): string
{
    if ($score >= 70) {
        return 'is-high';
    }

    if ($score >= 35) {
        return 'is-medium';
    }

    return 'is-low';
}

$csrfToken = apGarantirCsrf();

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
$stmt->execute([$pessoaId]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

if (!$usuario || ($usuario['status'] ?? '') !== 'ativo') {
    header('Location: /publico/logout.php');
    exit;
}

$nomeUsuario = apNomeExibicao($usuario);

/*
========================================
POST - APROVAR / RECUSAR
========================================
*/
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!apValidarCsrf($_POST['csrf_token'] ?? null)) {
        apFlashSet('erro', 'Falha de segurança na ação. Recarregue a página e tente novamente.');
        header('Location: /pessoas/aprovar-pessoa.php');
        exit;
    }

    $acao = trim((string) ($_POST['acao'] ?? ''));
    $aprovacaoId = (int) ($_POST['aprovacao_id'] ?? 0);
    $motivoRecusa = trim((string) ($_POST['motivo_recusa'] ?? ''));

    if ($aprovacaoId <= 0 || !in_array($acao, ['aprovar', 'recusar'], true)) {
        apFlashSet('erro', 'Ação inválida.');
        header('Location: /pessoas/aprovar-pessoa.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                ca.id,
                ca.convidador_id,
                ca.convidado_id,
                ca.status,
                p.nome,
                p.apelido,
                p.chamar_por
            FROM convites_aprovacoes ca
            INNER JOIN pessoas p
                ON p.id = ca.convidado_id
            WHERE ca.id = ?
              AND ca.convidador_id = ?
            LIMIT 1
        ");
        $stmt->execute([$aprovacaoId, $pessoaId]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$registro) {
            throw new RuntimeException('Pendência não encontrada.');
        }

        if (($registro['status'] ?? '') !== 'pendente') {
            throw new RuntimeException('Essa pendência já foi tratada.');
        }

        $nomeConvidado = apNomeExibicao($registro);

        if ($acao === 'aprovar') {
            if (!function_exists('inviteNovoAprovarPessoa')) {
                throw new RuntimeException('Core de aprovação V2 não carregado.');
            }

            $resultado = inviteNovoAprovarPessoa($pdo, $aprovacaoId, $pessoaId);

            apFlashSet(
                'sucesso',
                'Cadastro de ' . $nomeConvidado . ' aprovado com sucesso.' . apRecompensaResumo($resultado)
            );
        } else {
            if (!function_exists('inviteNovoRecusarPessoa')) {
                throw new RuntimeException('Core de recusa V2 não carregado.');
            }

            inviteNovoRecusarPessoa($pdo, $aprovacaoId, $pessoaId, $motivoRecusa);

            apFlashSet(
                'sucesso',
                'Cadastro de ' . $nomeConvidado . ' foi recusado.'
            );
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('[APROVAR_PESSOA_V2] ' . $e->getMessage());

        apFlashSet(
            'erro',
            $e instanceof RuntimeException || $e instanceof InvalidArgumentException
                ? $e->getMessage()
                : 'Não foi possível concluir a ação agora.'
        );
    }

    header('Location: /pessoas/aprovar-pessoa.php');
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
$stmt->execute([$pessoaId]);
$totalPendentes = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM convites_aprovacoes
    WHERE convidador_id = ?
      AND status = 'aprovado'
");
$stmt->execute([$pessoaId]);
$totalAprovados = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM rede_indicacoes
    WHERE indicador_id = ?
");
$stmt->execute([$pessoaId]);
$totalTime = (int) $stmt->fetchColumn();

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
$stmt->execute([$pessoaId]);
$pendencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flash = apFlashGet();

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Aprovar amigos | elab.social</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <link rel="stylesheet" href="/assets/css/footer-v2.css">

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
        }

        body.aprovar-page-body {
            margin: 0;
            background:
                radial-gradient(circle at 20% -10%, rgba(255, 193, 7, .14), transparent 32%),
                linear-gradient(180deg, #fff8ec 0%, #ffffff 34%, #f7f9fc 100%);
            color: #172033;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        button,
        a,
        input {
            font: inherit;
        }

        .aprovar-page {
            width: 100%;
            max-width: 520px;
            min-height: 100vh;
            margin: 0 auto;
            padding-bottom: 112px;
            background: transparent;
        }

        .aprovar-hero {
            position: relative;
            min-height: 246px;
            padding: calc(18px + env(safe-area-inset-top)) 20px 22px;
            overflow: hidden;
            background:
                radial-gradient(circle at 14% 16%, rgba(255, 255, 255, .32), transparent 28%),
                radial-gradient(circle at 88% 25%, rgba(255, 255, 255, .18), transparent 31%),
                linear-gradient(135deg, #ffb423 0%, #f28a12 42%, #ea6214 100%);
            border-radius: 0 0 34px 34px;
            box-shadow: 0 18px 42px rgba(236, 118, 20, .22);
        }

        .aprovar-hero::before {
            content: "";
            position: absolute;
            left: -42px;
            bottom: -62px;
            width: 168px;
            height: 168px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .16);
        }

        .aprovar-hero::after {
            content: "";
            position: absolute;
            right: -30px;
            top: 78px;
            width: 112px;
            height: 112px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .11);
        }

        .aprovar-hero-top {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
        }

        .aprovar-kicker {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 0 10px;
            margin-bottom: 9px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .20);
            color: rgba(255, 255, 255, .90);
            font-size: 10px;
            font-weight: 950;
            letter-spacing: .08em;
        }

        .aprovar-hero h1 {
            margin: 0;
            max-width: 320px;
            color: #ffffff;
            font-size: 31px;
            line-height: .98;
            font-weight: 950;
            letter-spacing: -0.065em;
            text-shadow: 0 4px 18px rgba(120, 54, 0, .18);
        }

        .aprovar-hero p {
            position: relative;
            z-index: 2;
            max-width: 330px;
            margin: 10px 0 0;
            color: rgba(255, 255, 255, .92);
            font-size: 13px;
            line-height: 1.35;
            font-weight: 800;
        }

        .aprovar-back-btn {
            position: relative;
            z-index: 2;
            appearance: none;
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border: 0;
            border-radius: 999px;
            background: rgba(255, 255, 255, .20);
            color: #ffffff;
            font-size: 25px;
            font-weight: 950;
            text-decoration: none;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            -webkit-tap-highlight-color: transparent;
        }

        .aprovar-back-btn:active {
            transform: scale(.94);
        }

        .aprovar-hero-card {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 18px;
            padding: 10px;
            border-radius: 24px;
            background: rgba(255, 255, 255, .94);
            box-shadow: 0 14px 28px rgba(128, 58, 0, .16);
        }

        .aprovar-hero-stat {
            min-height: 62px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            border-right: 1px solid #f0e6d9;
            text-align: center;
        }

        .aprovar-hero-stat:last-child {
            border-right: 0;
        }

        .aprovar-hero-stat strong {
            display: block;
            color: #172033;
            font-size: 18px;
            line-height: 1;
            font-weight: 950;
            letter-spacing: -0.04em;
        }

        .aprovar-hero-stat span {
            display: block;
            color: #8a95a8;
            font-size: 10.5px;
            line-height: 1.1;
            font-weight: 850;
        }

        .aprovar-content {
            padding: 18px 20px 0;
        }

        .aprovar-flash {
            margin: 0 0 14px;
            padding: 13px 14px;
            border-radius: 18px;
            font-size: 13px;
            line-height: 1.35;
            font-weight: 850;
            text-align: center;
            border: 2px solid transparent;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .045);
        }

        .aprovar-flash.is-success {
            background: #ecfdf3;
            color: #166534;
            border-color: #c7f0d3;
        }

        .aprovar-flash.is-error {
            background: #fff1f2;
            color: #991b1b;
            border-color: #fecdd3;
        }

        .aprovar-section-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin: 0 0 14px;
        }

        .aprovar-section-title h2 {
            margin: 0;
            color: #172033;
            font-size: 18px;
            line-height: 1;
            font-weight: 950;
            letter-spacing: -0.025em;
        }

        .aprovar-section-title p {
            margin: 6px 0 0;
            color: #8a95a8;
            font-size: 12px;
            line-height: 1.28;
            font-weight: 750;
        }

        .aprovar-counter-pill {
            min-width: 54px;
            height: 38px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            padding: 0 12px;
            border-radius: 999px;
            background: #eafbea;
            color: #16a34a;
            font-size: 14px;
            font-weight: 950;
            box-shadow: inset 0 -2px 0 rgba(22, 163, 74, .08);
        }

        .aprovar-empty {
            padding: 28px 18px;
            border-radius: 28px;
            background:
                radial-gradient(circle at 88% 5%, rgba(34, 197, 94, .13), transparent 34%),
                linear-gradient(180deg, #ffffff 0%, #f8fffb 100%);
            border: 2px solid #edf0f5;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .055);
            text-align: center;
        }

        .aprovar-empty-icon {
            width: 76px;
            height: 76px;
            display: grid;
            place-items: center;
            margin: 0 auto 14px;
            border-radius: 999px;
            background: #eafbea;
            font-size: 35px;
        }

        .aprovar-empty h3 {
            margin: 0;
            color: #172033;
            font-size: 21px;
            line-height: 1.05;
            font-weight: 950;
            letter-spacing: -0.04em;
        }

        .aprovar-empty p {
            margin: 8px auto 0;
            max-width: 310px;
            color: #8a95a8;
            font-size: 13px;
            line-height: 1.4;
            font-weight: 750;
        }

        .aprovar-card {
            position: relative;
            margin-bottom: 15px;
            padding: 16px;
            border-radius: 28px;
            background:
                radial-gradient(circle at 90% 8%, rgba(34, 197, 94, .11), transparent 28%),
                linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border: 2px solid #edf0f5;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .055);
            overflow: hidden;
        }

        .aprovar-card.is-risk-high {
            border-color: #fecdd3;
            background:
                radial-gradient(circle at 90% 8%, rgba(239, 68, 68, .10), transparent 28%),
                linear-gradient(180deg, #ffffff 0%, #fff8f8 100%);
        }

        .aprovar-card.is-risk-medium {
            border-color: #fde68a;
            background:
                radial-gradient(circle at 90% 8%, rgba(245, 158, 11, .12), transparent 28%),
                linear-gradient(180deg, #ffffff 0%, #fffdf4 100%);
        }

        .aprovar-person-top {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .aprovar-avatar {
            width: 54px;
            height: 54px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 19px;
            background: linear-gradient(180deg, #fff8dc 0%, #fff1b7 100%);
            box-shadow: inset 0 -4px 0 rgba(180, 119, 0, .08);
            font-size: 25px;
            font-weight: 950;
        }

        .aprovar-person-info {
            min-width: 0;
            flex: 1;
        }

        .aprovar-person-info h3 {
            margin: 0;
            color: #172033;
            font-size: 20px;
            line-height: 1.05;
            font-weight: 950;
            letter-spacing: -0.045em;
        }

        .aprovar-person-info p {
            margin: 6px 0 0;
            color: #728096;
            font-size: 12px;
            line-height: 1.35;
            font-weight: 800;
        }

        .aprovar-meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 9px;
            margin-top: 14px;
        }

        .aprovar-meta-item {
            min-height: 58px;
            padding: 10px;
            border-radius: 18px;
            background: rgba(255, 255, 255, .78);
            border: 2px solid #edf0f5;
        }

        .aprovar-meta-item span {
            display: block;
            color: #8a95a8;
            font-size: 10px;
            line-height: 1;
            font-weight: 950;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .aprovar-meta-item strong {
            display: block;
            margin-top: 6px;
            color: #172033;
            font-size: 12px;
            line-height: 1.2;
            font-weight: 850;
            word-break: break-word;
        }

        .aprovar-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .aprovar-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 28px;
            padding: 0 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 950;
            line-height: 1;
        }

        .aprovar-badge.is-risk {
            background: #fff7d6;
            color: #9a6700;
        }

        .aprovar-badge.is-risk.is-high {
            background: #fff1f2;
            color: #b42318;
        }

        .aprovar-badge.is-captcha {
            background: #eafbea;
            color: #166534;
        }

        .aprovar-badge.is-blocked {
            background: #fff1f2;
            color: #b42318;
        }

        .aprovar-risk-box,
        .aprovar-flags-box {
            margin-top: 12px;
            padding: 12px;
            border-radius: 18px;
            font-size: 12px;
            line-height: 1.38;
            font-weight: 800;
        }

        .aprovar-risk-box {
            background: #fff7d6;
            color: #7c5a00;
            border: 2px solid #fde68a;
        }

        .aprovar-risk-box strong,
        .aprovar-flags-box strong {
            display: block;
            margin-bottom: 5px;
            font-size: 10px;
            line-height: 1;
            font-weight: 950;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .aprovar-flags-box {
            background: #f8fafc;
            color: #64748b;
            border: 2px dashed #d7dee7;
        }

        .aprovar-flags-list {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .aprovar-flag {
            display: inline-flex;
            min-height: 25px;
            align-items: center;
            padding: 0 9px;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid #e7edf3;
            color: #334155;
            font-size: 10.5px;
            font-weight: 850;
        }

        .aprovar-form {
            margin-top: 13px;
        }

        .aprovar-input {
            width: 100%;
            min-height: 46px;
            padding: 0 14px;
            border: 2px solid #e5eaf2;
            border-radius: 16px;
            background: #ffffff;
            color: #172033;
            font-size: 13px;
            font-weight: 800;
            outline: none;
        }

        .aprovar-input:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 4px rgba(96, 165, 250, .16);
        }

        .aprovar-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 11px;
        }

        .aprovar-action-btn {
            appearance: none;
            min-height: 52px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 0;
            border-radius: 17px;
            font-size: 13px;
            line-height: 1.05;
            font-weight: 950;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
            transition: transform .14s ease, box-shadow .14s ease, filter .14s ease;
        }

        .aprovar-action-btn:active {
            transform: translateY(3px);
        }

        .aprovar-action-btn.is-approve {
            background: linear-gradient(180deg, #25d366 0%, #16a34a 100%);
            color: #ffffff;
            box-shadow: 0 5px 0 #0f7f38, 0 10px 20px rgba(22, 163, 74, .22);
        }

        .aprovar-action-btn.is-approve:active {
            box-shadow: 0 2px 0 #0f7f38, 0 8px 16px rgba(22, 163, 74, .18);
        }

        .aprovar-action-btn.is-decline {
            background: #ffffff;
            color: #b42318;
            border: 2px solid #fecdd3;
            box-shadow: 0 5px 0 #f4b8bf;
        }

        .aprovar-action-btn.is-decline:active {
            box-shadow: 0 2px 0 #f4b8bf;
        }

        .aprovar-confirm-overlay {
            position: fixed;
            inset: 0;
            z-index: 10040;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(15, 23, 42, .48);
        }

        .aprovar-confirm-overlay.is-open {
            display: flex;
        }

        .aprovar-confirm-box {
            width: 100%;
            max-width: 380px;
            padding: 22px;
            border-radius: 28px;
            background: #ffffff;
            box-shadow: 0 24px 60px rgba(15, 23, 42, .24);
            text-align: center;
            animation: aprovarConfirmIn .18s ease;
        }

        .aprovar-confirm-icon {
            width: 72px;
            height: 72px;
            display: grid;
            place-items: center;
            margin: 0 auto 14px;
            border-radius: 999px;
            background: #eafbea;
            font-size: 34px;
        }

        .aprovar-confirm-box h3 {
            margin: 0;
            color: #172033;
            font-size: 23px;
            line-height: 1.05;
            font-weight: 950;
            letter-spacing: -0.05em;
        }

        .aprovar-confirm-box p {
            margin: 8px 0 0;
            color: #7b8798;
            font-size: 13px;
            line-height: 1.45;
            font-weight: 750;
        }

        .aprovar-confirm-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 18px;
        }

        .aprovar-confirm-actions button {
            appearance: none;
            min-height: 48px;
            border: 0;
            border-radius: 16px;
            font-size: 13px;
            font-weight: 950;
            -webkit-tap-highlight-color: transparent;
        }

        .aprovar-confirm-cancel {
            background: #f1f5f9;
            color: #64748b;
        }

        .aprovar-confirm-ok {
            background: linear-gradient(180deg, #25d366 0%, #16a34a 100%);
            color: #ffffff;
            box-shadow: 0 5px 0 #0f7f38;
        }

        .aprovar-confirm-ok.is-danger {
            background: linear-gradient(180deg, #ff5c6a 0%, #ef233c 100%);
            box-shadow: 0 5px 0 #b51225;
        }

        @keyframes aprovarConfirmIn {
            from {
                opacity: 0;
                transform: translateY(8px) scale(.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @media (max-width: 380px) {
            .aprovar-hero {
                min-height: 232px;
            }

            .aprovar-hero h1 {
                font-size: 27px;
            }

            .aprovar-content {
                padding-left: 16px;
                padding-right: 16px;
            }

            .aprovar-actions {
                grid-template-columns: 1fr;
            }

            .aprovar-meta-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body class="aprovar-page-body">

<main class="aprovar-page">

    <section class="aprovar-hero">
        <div class="aprovar-hero-top">
            <div>
                <span class="aprovar-kicker">ATIVAÇÃO DE AMIGOS</span>
                <h1>Olá, <?= apEsc($nomeUsuario) ?>.</h1>
                <p>Aprove quem entrou pelo seu convite e transforme sua rede em time ativo.</p>
            </div>

            <a href="/pessoas/perfil.php" class="aprovar-back-btn" aria-label="Voltar ao perfil">‹</a>
        </div>

        <div class="aprovar-hero-card" aria-label="Resumo de ativações">
            <div class="aprovar-hero-stat">
                <strong><?= number_format($totalPendentes, 0, ',', '.') ?></strong>
                <span>pendentes</span>
            </div>

            <div class="aprovar-hero-stat">
                <strong><?= number_format($totalAprovados, 0, ',', '.') ?></strong>
                <span>aprovados</span>
            </div>

            <div class="aprovar-hero-stat">
                <strong><?= number_format($totalTime, 0, ',', '.') ?></strong>
                <span>no time</span>
            </div>
        </div>
    </section>

    <section class="aprovar-content">

        <?php if ($flash): ?>
            <div class="aprovar-flash <?= ($flash['tipo'] ?? '') === 'sucesso' ? 'is-success' : 'is-error' ?>">
                <?= apEsc($flash['mensagem'] ?? '') ?>
            </div>
        <?php endif; ?>

        <div class="aprovar-section-title">
            <div>
                <h2>Pendências para ativar</h2>
                <p>Revise os cadastros recebidos e aprove apenas quem você reconhece.</p>
            </div>

            <div class="aprovar-counter-pill">
                <?= number_format($totalPendentes, 0, ',', '.') ?>
            </div>
        </div>

        <?php if (!$pendencias): ?>
            <div class="aprovar-empty">
                <div class="aprovar-empty-icon">✅</div>
                <h3>Nada pendente agora</h3>
                <p>Quando alguém se cadastrar pelo seu link, o pedido de ativação vai aparecer aqui.</p>
            </div>
        <?php else: ?>

            <?php foreach ($pendencias as $item): ?>
                <?php
                    $nomeConvidado = apNomeExibicao($item);
                    $flags = apJsonFlags($item['flags_risco'] ?? null);
                    $scoreRisco = (int) ($item['score_risco'] ?? 0);
                    $riscoClasse = apRiscoClasse($scoreRisco);
                    $telefone = trim((string) ($item['telefone'] ?? ''));
                    $telefoneInformado = trim((string) ($item['telefone_informado'] ?? ''));
                    $telefoneLinha = $telefone !== '' ? $telefone : ($telefoneInformado !== '' ? $telefoneInformado : 'Não informado');
                    $origemLinha = trim((string) ($item['origem'] ?? 'link_convite'));
                    $captchaOk = (string) ($item['captcha_ok'] ?? 'nao') === 'sim';
                    $bloqueadoAutomacao = (string) ($item['bloqueado_automacao'] ?? 'nao') === 'sim';
                    $cardRiskClass = $scoreRisco >= 70 ? 'is-risk-high' : ($scoreRisco >= 35 ? 'is-risk-medium' : '');
                ?>

                <article class="aprovar-card <?= apEsc($cardRiskClass) ?>">
                    <div class="aprovar-person-top">
                        <div class="aprovar-avatar" aria-hidden="true">
                            <?= apEsc(mb_strtoupper(mb_substr($nomeConvidado, 0, 1, 'UTF-8'), 'UTF-8')) ?>
                        </div>

                        <div class="aprovar-person-info">
                            <h3><?= apEsc($nomeConvidado) ?></h3>
                            <p>Cadastro recebido em <?= apEsc(apDataHora($item['criado_em'] ?? null)) ?></p>
                        </div>
                    </div>

                    <div class="aprovar-meta-grid">
                        <div class="aprovar-meta-item">
                            <span>Origem</span>
                            <strong><?= apEsc($origemLinha) ?></strong>
                        </div>

                        <div class="aprovar-meta-item">
                            <span>WhatsApp</span>
                            <strong><?= apEsc($telefoneLinha) ?></strong>
                        </div>
                    </div>

                    <?php if ($scoreRisco > 0 || $captchaOk || $bloqueadoAutomacao): ?>
                        <div class="aprovar-badges">
                            <?php if ($scoreRisco > 0): ?>
                                <span class="aprovar-badge is-risk <?= $riscoClasse === 'is-high' ? 'is-high' : '' ?>">
                                    ⚠ Risco <?= number_format($scoreRisco, 0, ',', '.') ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($captchaOk): ?>
                                <span class="aprovar-badge is-captcha">✓ Captcha ok</span>
                            <?php endif; ?>

                            <?php if ($bloqueadoAutomacao): ?>
                                <span class="aprovar-badge is-blocked">🔒 Automação</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($item['motivo_risco'])): ?>
                        <div class="aprovar-risk-box">
                            <strong>Motivo de risco</strong>
                            <?= apEsc((string) $item['motivo_risco']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($flags): ?>
                        <div class="aprovar-flags-box">
                            <strong>Flags técnicas</strong>

                            <div class="aprovar-flags-list">
                                <?php foreach ($flags as $flag): ?>
                                    <span class="aprovar-flag"><?= apEsc($flag) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="aprovar-form">
                        <input type="hidden" name="csrf_token" value="<?= apEsc($csrfToken) ?>">
                        <input type="hidden" name="aprovacao_id" value="<?= (int) $item['id'] ?>">
                        <input type="hidden" name="acao" value="" class="js-aprovar-acao">

                        <input
                            type="text"
                            name="motivo_recusa"
                            class="aprovar-input"
                            placeholder="Motivo da recusa (opcional)"
                            autocomplete="off"
                        >

                        <div class="aprovar-actions">
                            <button
                                type="button"
                                class="aprovar-action-btn is-approve js-confirm-action"
                                data-action="aprovar"
                                data-name="<?= apEsc($nomeConvidado) ?>"
                            >
                                ✓ Aprovar
                            </button>

                            <button
                                type="button"
                                class="aprovar-action-btn is-decline js-confirm-action"
                                data-action="recusar"
                                data-name="<?= apEsc($nomeConvidado) ?>"
                            >
                                × Recusar
                            </button>
                        </div>
                    </form>
                </article>
            <?php endforeach; ?>

        <?php endif; ?>

    </section>

</main>

<div id="aprovarConfirmOverlay" class="aprovar-confirm-overlay" aria-hidden="true">
    <div class="aprovar-confirm-box" role="dialog" aria-modal="true" aria-labelledby="aprovarConfirmTitle">
        <div class="aprovar-confirm-icon" id="aprovarConfirmIcon">✅</div>

        <h3 id="aprovarConfirmTitle">Confirmar ação</h3>
        <p id="aprovarConfirmText">Deseja continuar?</p>

        <div class="aprovar-confirm-actions">
            <button type="button" class="aprovar-confirm-cancel" id="aprovarConfirmCancel">
                Cancelar
            </button>

            <button type="button" class="aprovar-confirm-ok" id="aprovarConfirmOk">
                Confirmar
            </button>
        </div>
    </div>
</div>

<?php
$footerPath = __DIR__ . '/../assets/footer/menu.php';

if (is_file($footerPath)) {
    require_once $footerPath;
}
?>

<script>
(() => {
    let formConfirmAlvo = null;
    let acaoConfirmAlvo = null;

    const overlay = document.getElementById('aprovarConfirmOverlay');
    const icon = document.getElementById('aprovarConfirmIcon');
    const title = document.getElementById('aprovarConfirmTitle');
    const text = document.getElementById('aprovarConfirmText');
    const okBtn = document.getElementById('aprovarConfirmOk');
    const cancelBtn = document.getElementById('aprovarConfirmCancel');

    const escapeHtml = (value) => {
        return String(value || '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    };

    const fecharConfirm = () => {
        if (overlay) {
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden', 'true');
        }

        if (okBtn) {
            okBtn.disabled = false;
            okBtn.classList.remove('is-danger');
            okBtn.textContent = 'Confirmar';
        }

        formConfirmAlvo = null;
        acaoConfirmAlvo = null;
    };

    const abrirConfirm = (form, tipo, nome) => {
        if (!overlay || !icon || !title || !text || !okBtn || !form) {
            return;
        }

        formConfirmAlvo = form;
        acaoConfirmAlvo = tipo;

        const nomeSeguro = escapeHtml(nome || 'Participante');

        okBtn.disabled = false;
        okBtn.classList.remove('is-danger');

        if (tipo === 'aprovar') {
            icon.textContent = '✅';
            icon.style.background = '#eafbea';
            title.textContent = 'Confirmar ativação';
            text.innerHTML = 'Você está prestes a ativar <strong>' + nomeSeguro + '</strong> no seu time. A recompensa será processada pelo game após a confirmação.';
            okBtn.textContent = 'Aprovar';
        } else {
            icon.textContent = '⚠️';
            icon.style.background = '#fff1f2';
            title.textContent = 'Confirmar recusa';
            text.innerHTML = 'Deseja realmente recusar <strong>' + nomeSeguro + '</strong>? Esse cadastro não será ativado na sua rede.';
            okBtn.textContent = 'Recusar';
            okBtn.classList.add('is-danger');
        }

        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
    };

    document.querySelectorAll('.js-confirm-action').forEach((button) => {
        button.addEventListener('click', () => {
            const form = button.closest('form');
            const tipo = button.getAttribute('data-action') || '';
            const nome = button.getAttribute('data-name') || 'Participante';

            if (!['aprovar', 'recusar'].includes(tipo)) {
                return;
            }

            abrirConfirm(form, tipo, nome);
        });
    });

    if (cancelBtn) {
        cancelBtn.addEventListener('click', fecharConfirm);
    }

    if (overlay) {
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) {
                fecharConfirm();
            }
        });
    }

    if (okBtn) {
        okBtn.addEventListener('click', () => {
            if (!formConfirmAlvo || !acaoConfirmAlvo || okBtn.disabled) {
                return;
            }

            const inputAcao = formConfirmAlvo.querySelector('.js-aprovar-acao');

            if (inputAcao) {
                inputAcao.value = acaoConfirmAlvo;
            }

            okBtn.disabled = true;
            okBtn.textContent = acaoConfirmAlvo === 'aprovar' ? 'Aprovando...' : 'Recusando...';

            formConfirmAlvo.submit();
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            fecharConfirm();
        }
    });
})();
</script>

</body>
</html>