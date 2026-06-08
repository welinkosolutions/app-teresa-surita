<?php
declare(strict_types=1);

/**
 * ======================================================
 * CAMINHO: /home/elab/app.elab.social/pessoas/lider.php
 * NOME: Alterar Líder Direto V2
 *
 * DESCRIÇÃO:
 * - Permite alterar o líder direto da pessoa logada
 * - Move a subárvore junto com a pessoa
 * - Impede escolher a si mesmo
 * - Impede escolher alguém abaixo da própria rede
 * - Busca candidatos ativos por nome, apelido, telefone ou ID
 * - Layout V2 gamificado integrado ao footer fixo
 * - Modal com barra de carregamento durante a alteração
 * ======================================================
 */

date_default_timezone_set('America/Boa_Vista');

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
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

$pessoa_id = (int) $_SESSION['pessoa_id'];

/*
=====================================================
CORE
=====================================================
*/
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';
require_once '/home/elab/public_html/core/invite/engine.php';

$pdo = dbRoraima();

/*
=====================================================
HELPERS
=====================================================
*/
function liderEsc(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function liderNomeExibicao(array $pessoa): string
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

function liderFlashSet(string $tipo, string $mensagem): void
{
    $_SESSION['alterar_lider_flash'] = [
        'tipo' => $tipo,
        'mensagem' => $mensagem,
    ];
}

function liderFlashGet(): ?array
{
    if (empty($_SESSION['alterar_lider_flash']) || !is_array($_SESSION['alterar_lider_flash'])) {
        return null;
    }

    $flash = $_SESSION['alterar_lider_flash'];
    unset($_SESSION['alterar_lider_flash']);

    return $flash;
}

function liderCsrfToken(): string
{
    if (empty($_SESSION['alterar_lider_csrf']) || !is_string($_SESSION['alterar_lider_csrf'])) {
        $_SESSION['alterar_lider_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['alterar_lider_csrf'];
}

function liderCsrfValido(?string $token): bool
{
    $sess = (string) ($_SESSION['alterar_lider_csrf'] ?? '');
    $token = (string) $token;

    if ($sess === '' || $token === '') {
        return false;
    }

    return hash_equals($sess, $token);
}

function liderBuscarPessoa(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            id,
            nome,
            apelido,
            chamar_por,
            status,
            perfil,
            COALESCE(pontos, 0) AS pontos
        FROM pessoas
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function liderBuscarLiderDireto(PDO $pdo, int $pessoaId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.nome,
            p.apelido,
            p.chamar_por,
            p.perfil,
            COALESCE(p.pontos, 0) AS pontos
        FROM rede_indicacoes r
        JOIN pessoas p
          ON p.id = r.indicador_id
        WHERE r.indicado_id = ?
          AND r.nivel = 1
        LIMIT 1
    ");
    $stmt->execute([$pessoaId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function liderBuscarDescendentesIds(PDO $pdo, int $pessoaId): array
{
    $stmt = $pdo->prepare("
        SELECT indicado_id
        FROM rede_indicacoes
        WHERE indicador_id = ?
        ORDER BY nivel, indicado_id
    ");
    $stmt->execute([$pessoaId]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function liderBuscarSubarvore(PDO $pdo, int $raizId): array
{
    $stmt = $pdo->prepare("
        SELECT indicado_id, nivel
        FROM rede_indicacoes
        WHERE indicador_id = ?
        ORDER BY nivel ASC, indicado_id ASC
    ");
    $stmt->execute([$raizId]);

    $subarvore = [
        [
            'id' => $raizId,
            'nivel' => 0,
        ],
    ];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $subarvore[] = [
            'id' => (int) $row['indicado_id'],
            'nivel' => (int) $row['nivel'],
        ];
    }

    return $subarvore;
}

function liderBuscarAncestrais(PDO $pdo, int $pessoaId): array
{
    $stmt = $pdo->prepare("
        SELECT indicador_id, nivel
        FROM rede_indicacoes
        WHERE indicado_id = ?
        ORDER BY nivel ASC, indicador_id ASC
    ");
    $stmt->execute([$pessoaId]);

    $anc = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $anc[] = [
            'id' => (int) $row['indicador_id'],
            'nivel' => (int) $row['nivel'],
        ];
    }

    return $anc;
}

function liderBuscarNovosAncestraisComSelf(PDO $pdo, int $novoLiderId): array
{
    $saida = [
        [
            'id' => $novoLiderId,
            'nivel' => 0,
        ],
    ];

    $stmt = $pdo->prepare("
        SELECT indicador_id, nivel
        FROM rede_indicacoes
        WHERE indicado_id = ?
        ORDER BY nivel ASC, indicador_id ASC
    ");
    $stmt->execute([$novoLiderId]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $saida[] = [
            'id' => (int) $row['indicador_id'],
            'nivel' => (int) $row['nivel'],
        ];
    }

    return $saida;
}

function liderIdPertenceSubarvore(PDO $pdo, int $raizId, int $alvoId): bool
{
    if ($raizId === $alvoId) {
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

    return (bool) $stmt->fetchColumn();
}

function liderBuscarColunaLiderDiretoPessoas(PDO $pdo): ?string
{
    $candidatas = [
        'convidador_id',
        'indicador_id',
        'lider_id',
        'lider_direto_id',
    ];

    foreach ($candidatas as $col) {
        if (inviteHasColumn($pdo, 'pessoas', $col)) {
            return $col;
        }
    }

    return null;
}

function liderAlterarDiretoComSubarvore(PDO $pdo, int $pessoaId, int $novoLiderId): void
{
    if ($pessoaId <= 0 || $novoLiderId <= 0) {
        throw new InvalidArgumentException('IDs inválidos para alteração de líder.');
    }

    if ($pessoaId === $novoLiderId) {
        throw new RuntimeException('Você não pode se tornar líder de si mesmo.');
    }

    if (liderIdPertenceSubarvore($pdo, $pessoaId, $novoLiderId)) {
        throw new RuntimeException('O novo líder não pode estar dentro da sua própria rede.');
    }

    $novoLider = liderBuscarPessoa($pdo, $novoLiderId);

    if (!$novoLider || ($novoLider['status'] ?? '') !== 'ativo') {
        throw new RuntimeException('O novo líder precisa estar ativo.');
    }

    $liderAtual = liderBuscarLiderDireto($pdo, $pessoaId);

    if ($liderAtual && (int) $liderAtual['id'] === $novoLiderId) {
        throw new RuntimeException('Esse usuário já é seu líder direto.');
    }

    $subarvore = liderBuscarSubarvore($pdo, $pessoaId);
    $ancestraisAntigos = liderBuscarAncestrais($pdo, $pessoaId);
    $novosAncestrais = liderBuscarNovosAncestraisComSelf($pdo, $novoLiderId);

    $pdo->beginTransaction();

    try {
        $idsAncestraisAntigos = array_values(array_unique(array_map(
            static fn(array $r): int => (int) $r['id'],
            $ancestraisAntigos
        )));

        $idsSubarvore = array_values(array_unique(array_map(
            static fn(array $r): int => (int) $r['id'],
            $subarvore
        )));

        if ($idsAncestraisAntigos && $idsSubarvore) {
            $inAnc = implode(',', array_fill(0, count($idsAncestraisAntigos), '?'));
            $inSub = implode(',', array_fill(0, count($idsSubarvore), '?'));

            $sqlDelete = "
                DELETE FROM rede_indicacoes
                WHERE indicador_id IN ($inAnc)
                  AND indicado_id IN ($inSub)
            ";

            $stmtDelete = $pdo->prepare($sqlDelete);
            $stmtDelete->execute(array_merge($idsAncestraisAntigos, $idsSubarvore));
        }

        $stmtInsert = $pdo->prepare("
            INSERT IGNORE INTO rede_indicacoes
                (indicador_id, indicado_id, nivel, origem, criado_em)
            VALUES
                (?, ?, ?, 'manual', NOW())
        ");

        foreach ($novosAncestrais as $anc) {
            $ancId = (int) $anc['id'];
            $ancNivel = (int) $anc['nivel'];

            foreach ($subarvore as $node) {
                $nodeId = (int) $node['id'];
                $nodeNivel = (int) $node['nivel'];

                $novoNivel = $ancNivel + 1 + $nodeNivel;

                $stmtInsert->execute([
                    $ancId,
                    $nodeId,
                    $novoNivel,
                ]);
            }
        }

        $colLider = liderBuscarColunaLiderDiretoPessoas($pdo);

        if ($colLider !== null) {
            $stmtUpPessoa = $pdo->prepare("
                UPDATE pessoas
                SET `$colLider` = ?,
                    atualizado_em = NOW()
                WHERE id = ?
                LIMIT 1
            ");
            $stmtUpPessoa->execute([$novoLiderId, $pessoaId]);
        } elseif (inviteHasColumn($pdo, 'pessoas', 'atualizado_em')) {
            $stmtTouch = $pdo->prepare("
                UPDATE pessoas
                SET atualizado_em = NOW()
                WHERE id = ?
                LIMIT 1
            ");
            $stmtTouch->execute([$pessoaId]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

/*
=====================================================
USUÁRIO LOGADO
=====================================================
*/
$usuario = liderBuscarPessoa($pdo, $pessoa_id) ?: [];

if (!$usuario || ($usuario['status'] ?? '') !== 'ativo') {
    header('Location: /publico/logout.php');
    exit;
}

$nomeUsuario = liderNomeExibicao($usuario);
$liderAtual = liderBuscarLiderDireto($pdo, $pessoa_id);
$liderAtualNome = $liderAtual ? liderNomeExibicao($liderAtual) : 'Sem líder';
$totalDescendentes = count(liderBuscarDescendentesIds($pdo, $pessoa_id));
$csrfToken = liderCsrfToken();

/*
=====================================================
POST - ALTERAR LÍDER
=====================================================
*/
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!liderCsrfValido($_POST['csrf_token'] ?? null)) {
        liderFlashSet('erro', 'Falha de segurança. Recarregue a página e tente novamente.');
        header('Location: /pessoas/lider.php');
        exit;
    }

    $novoLiderId = (int) ($_POST['novo_lider_id'] ?? 0);

    try {
        liderAlterarDiretoComSubarvore($pdo, $pessoa_id, $novoLiderId);

        $novoLider = liderBuscarPessoa($pdo, $novoLiderId) ?: [];

        liderFlashSet(
            'sucesso',
            'Seu líder direto foi alterado para ' . liderNomeExibicao($novoLider) . '. Sua rede foi movida junto com você.'
        );
    } catch (Throwable $e) {
        error_log('[LIDER_V2] ' . $e->getMessage());
        liderFlashSet('erro', $e->getMessage());
    }

    header('Location: /pessoas/lider.php');
    exit;
}

$flash = liderFlashGet();

/*
=====================================================
BUSCA DE CANDIDATOS
=====================================================
*/
$q = trim((string) ($_GET['q'] ?? ''));
$candidatos = [];

$idsBloqueados = liderBuscarDescendentesIds($pdo, $pessoa_id);
$idsBloqueados[] = $pessoa_id;
$idsBloqueados = array_values(array_unique(array_map('intval', $idsBloqueados)));

if ($q !== '') {
    $whereBloqueados = '';
    $params = [];

    if ($idsBloqueados) {
        $whereBloqueados = ' AND p.id NOT IN (' . implode(',', array_fill(0, count($idsBloqueados), '?')) . ') ';
        $params = array_merge($params, $idsBloqueados);
    }

    $sql = "
        SELECT
            p.id,
            p.nome,
            p.apelido,
            p.chamar_por,
            p.perfil,
            COALESCE(p.pontos, 0) AS pontos
        FROM pessoas p
        WHERE p.status = 'ativo'
          $whereBloqueados
          AND (
                p.nome LIKE ?
             OR p.apelido LIKE ?
             OR CAST(p.id AS CHAR) = ?
             OR p.telefone LIKE ?
          )
        ORDER BY p.nome
        LIMIT 30
    ";

    $like = '%' . $q . '%';

    $params[] = $like;
    $params[] = $like;
    $params[] = $q;
    $params[] = $like;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Alterar líder | elab.social</title>
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

        body.lider-page-body {
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

        .lider-page {
            width: 100%;
            max-width: 520px;
            min-height: 100vh;
            margin: 0 auto;
            padding-bottom: 112px;
            background: transparent;
        }

        .lider-hero {
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

        .lider-hero::before {
            content: "";
            position: absolute;
            left: -42px;
            bottom: -62px;
            width: 168px;
            height: 168px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .16);
        }

        .lider-hero::after {
            content: "";
            position: absolute;
            right: -30px;
            top: 78px;
            width: 112px;
            height: 112px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .11);
        }

        .lider-hero-top {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
        }

        .lider-kicker {
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

        .lider-hero h1 {
            margin: 0;
            max-width: 320px;
            color: #ffffff;
            font-size: 31px;
            line-height: .98;
            font-weight: 950;
            letter-spacing: -0.065em;
            text-shadow: 0 4px 18px rgba(120, 54, 0, .18);
        }

        .lider-hero p {
            position: relative;
            z-index: 2;
            max-width: 330px;
            margin: 10px 0 0;
            color: rgba(255, 255, 255, .92);
            font-size: 13px;
            line-height: 1.35;
            font-weight: 800;
        }

        .lider-back-btn {
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

        .lider-back-btn:active {
            transform: scale(.94);
        }

        .lider-hero-card {
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

        .lider-hero-stat {
            min-height: 62px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            border-right: 1px solid #f0e6d9;
            text-align: center;
            min-width: 0;
        }

        .lider-hero-stat:last-child {
            border-right: 0;
        }

        .lider-hero-stat strong {
            display: block;
            max-width: 100%;
            color: #172033;
            font-size: 16px;
            line-height: 1.08;
            font-weight: 950;
            letter-spacing: -0.04em;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .lider-hero-stat span {
            display: block;
            color: #8a95a8;
            font-size: 10.5px;
            line-height: 1.1;
            font-weight: 850;
        }

        .lider-content {
            padding: 18px 20px 0;
        }

        .lider-flash {
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

        .lider-flash.is-success {
            background: #ecfdf3;
            color: #166534;
            border-color: #c7f0d3;
        }

        .lider-flash.is-error {
            background: #fff1f2;
            color: #991b1b;
            border-color: #fecdd3;
        }

        .lider-card {
            position: relative;
            margin-bottom: 15px;
            padding: 16px;
            border-radius: 28px;
            background:
                radial-gradient(circle at 90% 8%, rgba(34, 197, 94, .08), transparent 28%),
                linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border: 2px solid #edf0f5;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .055);
            overflow: hidden;
        }

        .lider-card.is-warning {
            background:
                radial-gradient(circle at 90% 8%, rgba(245, 158, 11, .14), transparent 28%),
                linear-gradient(180deg, #ffffff 0%, #fffdf4 100%);
        }

        .lider-card-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 14px;
        }

        .lider-card-icon {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 17px;
            background: linear-gradient(180deg, #fff8dc 0%, #fff1b7 100%);
            box-shadow: inset 0 -4px 0 rgba(180, 119, 0, .08);
            font-size: 22px;
        }

        .lider-card-title h2 {
            margin: 0;
            color: #172033;
            font-size: 20px;
            line-height: 1.05;
            font-weight: 950;
            letter-spacing: -0.045em;
        }

        .lider-card-title p {
            margin: 5px 0 0;
            color: #8a95a8;
            font-size: 12px;
            line-height: 1.28;
            font-weight: 750;
        }

        .lider-rule-box {
            margin-top: 12px;
            padding: 12px;
            border-radius: 18px;
            background: #fff7d6;
            color: #7c5a00;
            border: 2px solid #fde68a;
            font-size: 12px;
            line-height: 1.38;
            font-weight: 800;
        }

        .lider-search-form {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            margin-top: 6px;
        }

        .lider-input {
            width: 100%;
            min-height: 52px;
            padding: 0 14px;
            border: 2px solid #e5eaf2;
            border-radius: 17px;
            background: #ffffff;
            color: #172033;
            font-size: 14px;
            font-weight: 850;
            outline: none;
            box-shadow: inset 0 -2px 0 rgba(15, 23, 42, .02);
        }

        .lider-input:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 4px rgba(96, 165, 250, .16);
        }

        .lider-btn {
            appearance: none;
            min-height: 52px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 0;
            border-radius: 17px;
            padding: 0 16px;
            font-size: 13px;
            line-height: 1.05;
            font-weight: 950;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
            transition: transform .14s ease, box-shadow .14s ease, filter .14s ease;
        }

        .lider-btn:active {
            transform: translateY(3px);
        }

        .lider-btn.is-search,
        .lider-btn.is-confirm {
            background: linear-gradient(180deg, #25d366 0%, #16a34a 100%);
            color: #ffffff;
            box-shadow: 0 5px 0 #0f7f38, 0 10px 20px rgba(22, 163, 74, .22);
        }

        .lider-btn.is-search:active,
        .lider-btn.is-confirm:active {
            box-shadow: 0 2px 0 #0f7f38, 0 8px 16px rgba(22, 163, 74, .18);
        }

        .lider-empty {
            padding: 28px 18px;
            border-radius: 28px;
            background:
                radial-gradient(circle at 88% 5%, rgba(34, 197, 94, .13), transparent 34%),
                linear-gradient(180deg, #ffffff 0%, #f8fffb 100%);
            border: 2px solid #edf0f5;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .055);
            text-align: center;
        }

        .lider-empty-icon {
            width: 76px;
            height: 76px;
            display: grid;
            place-items: center;
            margin: 0 auto 14px;
            border-radius: 999px;
            background: #eafbea;
            font-size: 35px;
        }

        .lider-empty h3 {
            margin: 0;
            color: #172033;
            font-size: 21px;
            line-height: 1.05;
            font-weight: 950;
            letter-spacing: -0.04em;
        }

        .lider-empty p {
            margin: 8px auto 0;
            max-width: 310px;
            color: #8a95a8;
            font-size: 13px;
            line-height: 1.4;
            font-weight: 750;
        }

        .lider-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .lider-person-card {
            position: relative;
            margin-bottom: 13px;
            padding: 16px;
            border-radius: 28px;
            background:
                radial-gradient(circle at 90% 8%, rgba(34, 197, 94, .11), transparent 28%),
                linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border: 2px solid #edf0f5;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .055);
            overflow: hidden;
        }

        .lider-person-top {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .lider-avatar {
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

        .lider-person-info {
            min-width: 0;
            flex: 1;
        }

        .lider-person-info h3 {
            margin: 0;
            color: #172033;
            font-size: 20px;
            line-height: 1.05;
            font-weight: 950;
            letter-spacing: -0.045em;
        }

        .lider-person-info p {
            margin: 6px 0 0;
            color: #728096;
            font-size: 12px;
            line-height: 1.35;
            font-weight: 800;
        }

        .lider-meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 9px;
            margin-top: 14px;
        }

        .lider-meta-item {
            min-height: 58px;
            padding: 10px;
            border-radius: 18px;
            background: rgba(255, 255, 255, .78);
            border: 2px solid #edf0f5;
        }

        .lider-meta-item span {
            display: block;
            color: #8a95a8;
            font-size: 10px;
            line-height: 1;
            font-weight: 950;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .lider-meta-item strong {
            display: block;
            margin-top: 6px;
            color: #172033;
            font-size: 12px;
            line-height: 1.2;
            font-weight: 850;
            word-break: break-word;
        }

        .lider-card-actions {
            margin-top: 13px;
        }

        .lider-action-btn {
            appearance: none;
            width: 100%;
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
            background: linear-gradient(180deg, #25d366 0%, #16a34a 100%);
            color: #ffffff;
            box-shadow: 0 5px 0 #0f7f38, 0 10px 20px rgba(22, 163, 74, .22);
        }

        .lider-action-btn:active {
            transform: translateY(3px);
            box-shadow: 0 2px 0 #0f7f38, 0 8px 16px rgba(22, 163, 74, .18);
        }

        .lider-confirm-overlay {
            position: fixed;
            inset: 0;
            z-index: 10040;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(15, 23, 42, .48);
        }

        .lider-confirm-overlay.is-open {
            display: flex;
        }

        .lider-confirm-box {
            width: 100%;
            max-width: 380px;
            padding: 22px;
            border-radius: 28px;
            background: #ffffff;
            box-shadow: 0 24px 60px rgba(15, 23, 42, .24);
            text-align: center;
            animation: liderConfirmIn .18s ease;
        }

        .lider-confirm-icon {
            width: 72px;
            height: 72px;
            display: grid;
            place-items: center;
            margin: 0 auto 14px;
            border-radius: 999px;
            background: #fff7d6;
            font-size: 34px;
        }

        .lider-confirm-box h3 {
            margin: 0;
            color: #172033;
            font-size: 23px;
            line-height: 1.05;
            font-weight: 950;
            letter-spacing: -0.05em;
        }

        .lider-confirm-box p {
            margin: 8px 0 0;
            color: #7b8798;
            font-size: 13px;
            line-height: 1.45;
            font-weight: 750;
        }

        .lider-confirm-box strong {
            color: #172033;
            font-weight: 950;
        }

        .lider-confirm-alert {
            margin-top: 14px;
            padding: 12px;
            border-radius: 18px;
            background: #fff7d6;
            color: #7c5a00;
            border: 2px solid #fde68a;
            font-size: 12px;
            line-height: 1.38;
            font-weight: 800;
            text-align: left;
        }

        .lider-loading-box {
            display: none;
            margin-top: 14px;
            padding: 12px;
            border-radius: 18px;
            background: #f8fafc;
            border: 2px solid #e5eaf2;
            text-align: left;
        }

        .lider-loading-box.is-visible {
            display: block;
        }

        .lider-loading-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 9px;
        }

        .lider-loading-top span {
            color: #172033;
            font-size: 12px;
            line-height: 1.1;
            font-weight: 950;
        }

        .lider-loading-top strong {
            color: #16a34a;
            font-size: 12px;
            line-height: 1;
            font-weight: 950;
        }

        .lider-loading-track {
            width: 100%;
            height: 12px;
            overflow: hidden;
            border-radius: 999px;
            background: #e5eaf2;
            box-shadow: inset 0 2px 4px rgba(15, 23, 42, .08);
        }

        .lider-loading-bar {
            width: 0%;
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #25d366 0%, #16a34a 55%, #86efac 100%);
            box-shadow: 0 0 18px rgba(34, 197, 94, .35);
            transition: width .28s ease;
        }

        .lider-loading-text {
            margin-top: 8px;
            color: #64748b;
            font-size: 11px;
            line-height: 1.35;
            font-weight: 800;
        }

        .lider-confirm-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 18px;
        }

        .lider-confirm-actions button {
            appearance: none;
            min-height: 48px;
            border: 0;
            border-radius: 16px;
            font-size: 13px;
            font-weight: 950;
            -webkit-tap-highlight-color: transparent;
        }

        .lider-confirm-cancel {
            background: #f1f5f9;
            color: #64748b;
        }

        .lider-confirm-cancel:disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        .lider-confirm-ok {
            background: linear-gradient(180deg, #25d366 0%, #16a34a 100%);
            color: #ffffff;
            box-shadow: 0 5px 0 #0f7f38;
        }

        .lider-confirm-ok.is-processing {
            opacity: .92;
            cursor: wait;
        }

        @keyframes liderConfirmIn {
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
            .lider-hero {
                min-height: 232px;
            }

            .lider-hero h1 {
                font-size: 27px;
            }

            .lider-content {
                padding-left: 16px;
                padding-right: 16px;
            }

            .lider-search-form,
            .lider-meta-grid,
            .lider-confirm-actions {
                grid-template-columns: 1fr;
            }

            .lider-btn.is-search {
                width: 100%;
            }
        }
    </style>
</head>

<body class="lider-page-body">

<main class="lider-page">

    <section class="lider-hero">
        <div class="lider-hero-top">
            <div>
                <span class="lider-kicker">TROCA DE LÍDER</span>
                <h1>Olá, <?= liderEsc($nomeUsuario) ?>.</h1>
                <p>Escolha um novo líder direto e mova sua rede com segurança, sem quebrar sua estrutura.</p>
            </div>

            <a href="/pessoas/perfil.php" class="lider-back-btn" aria-label="Voltar ao perfil">‹</a>
        </div>

        <div class="lider-hero-card" aria-label="Resumo da liderança">
            <div class="lider-hero-stat">
                <strong><?= liderEsc($nomeUsuario) ?></strong>
                <span>você</span>
            </div>

            <div class="lider-hero-stat">
                <strong><?= liderEsc($liderAtualNome) ?></strong>
                <span>líder atual</span>
            </div>

            <div class="lider-hero-stat">
                <strong><?= number_format($totalDescendentes, 0, ',', '.') ?></strong>
                <span>na sua rede</span>
            </div>
        </div>
    </section>

    <section class="lider-content">

        <?php if ($flash): ?>
            <div class="lider-flash <?= ($flash['tipo'] ?? '') === 'sucesso' ? 'is-success' : 'is-error' ?>">
                <?= liderEsc($flash['mensagem'] ?? '') ?>
            </div>
        <?php endif; ?>

        <section class="lider-card is-warning">
            <div class="lider-card-title">
                <div class="lider-card-icon">👑</div>
                <div>
                    <h2>Situação atual</h2>
                    <p>Entenda o que será movido.</p>
                </div>
            </div>

            <div class="lider-rule-box">
                Regras de segurança: você não pode escolher a si mesmo, nem alguém que esteja abaixo da sua própria rede.
                Quando a troca acontece, toda a sua estrutura desce junto com você.
            </div>
        </section>

        <section class="lider-card">
            <div class="lider-card-title">
                <div class="lider-card-icon">🔎</div>
                <div>
                    <h2>Buscar novo líder</h2>
                    <p>Pesquise por nome, apelido, telefone ou ID.</p>
                </div>
            </div>

            <form method="get" class="lider-search-form">
                <input
                    type="text"
                    name="q"
                    class="lider-input"
                    placeholder="Digite nome, apelido ou ID"
                    value="<?= liderEsc($q) ?>"
                    autocomplete="off"
                >

                <button type="submit" class="lider-btn is-search">
                    Buscar
                </button>
            </form>
        </section>

        <?php if ($q === ''): ?>
            <div class="lider-empty">
                <div class="lider-empty-icon">🔎</div>
                <h3>Busque um líder</h3>
                <p>Digite o nome, apelido, telefone ou ID da pessoa que será seu novo líder direto.</p>
            </div>
        <?php elseif (!$candidatos): ?>
            <div class="lider-empty">
                <div class="lider-empty-icon">🕵️</div>
                <h3>Nenhum candidato</h3>
                <p>Não encontramos uma pessoa ativa e válida para essa busca. Tente outro nome ou ID.</p>
            </div>
        <?php else: ?>

            <section class="lider-card">
                <div class="lider-card-title">
                    <div class="lider-card-icon">🤝</div>
                    <div>
                        <h2>Candidatos</h2>
                        <p><?= number_format(count($candidatos), 0, ',', '.') ?> opção(ões) encontrada(s).</p>
                    </div>
                </div>

                <ul class="lider-list">
                    <?php foreach ($candidatos as $cand): ?>
                        <?php
                            $nomeCand = liderNomeExibicao($cand);
                            $inicial = mb_strtoupper(mb_substr($nomeCand, 0, 1, 'UTF-8'), 'UTF-8');
                            $perfilCand = trim((string) ($cand['perfil'] ?? 'pessoa'));
                            $pontosCand = (int) ($cand['pontos'] ?? 0);
                        ?>

                        <li>
                            <article class="lider-person-card">
                                <div class="lider-person-top">
                                    <div class="lider-avatar" aria-hidden="true">
                                        <?= liderEsc($inicial) ?>
                                    </div>

                                    <div class="lider-person-info">
                                        <h3><?= liderEsc($nomeCand) ?></h3>
                                        <p>Disponível para receber sua rede como líder direto.</p>
                                    </div>
                                </div>

                                <div class="lider-meta-grid">
                                    <div class="lider-meta-item">
                                        <span>ID</span>
                                        <strong><?= (int) $cand['id'] ?></strong>
                                    </div>

                                    <div class="lider-meta-item">
                                        <span>Perfil</span>
                                        <strong><?= liderEsc($perfilCand) ?></strong>
                                    </div>

                                    <div class="lider-meta-item">
                                        <span>Moedas</span>
                                        <strong><?= number_format($pontosCand, 0, ',', '.') ?></strong>
                                    </div>

                                    <div class="lider-meta-item">
                                        <span>Status</span>
                                        <strong>Ativo</strong>
                                    </div>
                                </div>

                                <div class="lider-card-actions">
                                    <button
                                        type="button"
                                        class="lider-action-btn js-lider-confirm"
                                        data-lider-id="<?= (int) $cand['id'] ?>"
                                        data-lider-nome="<?= liderEsc($nomeCand) ?>"
                                    >
                                        Escolher como líder
                                    </button>
                                </div>
                            </article>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

        <?php endif; ?>

    </section>

</main>

<div id="liderConfirmOverlay" class="lider-confirm-overlay" aria-hidden="true">
    <div class="lider-confirm-box" role="dialog" aria-modal="true" aria-labelledby="liderConfirmTitle">
        <div class="lider-confirm-icon">👑</div>

        <h3 id="liderConfirmTitle">Confirmar novo líder</h3>

        <p id="liderConfirmText">
            Você está prestes a definir <strong>---</strong> como seu novo líder direto.
        </p>

        <div class="lider-confirm-alert">
            Sua rede abaixo será movida junto com você, mantendo sua subárvore conectada ao novo líder.
        </div>

        <div class="lider-loading-box" id="liderLoadingBox" aria-hidden="true">
            <div class="lider-loading-top">
                <span>Reorganizando sua rede...</span>
                <strong id="liderLoadingPercent">0%</strong>
            </div>

            <div class="lider-loading-track">
                <div class="lider-loading-bar" id="liderLoadingBar"></div>
            </div>

            <div class="lider-loading-text" id="liderLoadingText">
                Preparando alteração de líder.
            </div>
        </div>

        <form method="post" id="liderConfirmForm">
            <input type="hidden" name="csrf_token" value="<?= liderEsc($csrfToken) ?>">
            <input type="hidden" name="novo_lider_id" id="liderConfirmId" value="0">

            <div class="lider-confirm-actions">
                <button type="button" class="lider-confirm-cancel" id="liderConfirmCancel">
                    Cancelar
                </button>

                <button type="submit" class="lider-confirm-ok" id="liderConfirmOk">
                    Confirmar
                </button>
            </div>
        </form>
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
    const overlay = document.getElementById('liderConfirmOverlay');
    const text = document.getElementById('liderConfirmText');
    const inputId = document.getElementById('liderConfirmId');
    const cancelBtn = document.getElementById('liderConfirmCancel');
    const okBtn = document.getElementById('liderConfirmOk');
    const form = document.getElementById('liderConfirmForm');

    const loadingBox = document.getElementById('liderLoadingBox');
    const loadingBar = document.getElementById('liderLoadingBar');
    const loadingPercent = document.getElementById('liderLoadingPercent');
    const loadingText = document.getElementById('liderLoadingText');

    let processando = false;
    let intervaloLoading = null;

    const escapeHtml = (value) => {
        return String(value || '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    };

    const resetarLoading = () => {
        processando = false;

        if (intervaloLoading) {
            window.clearInterval(intervaloLoading);
            intervaloLoading = null;
        }

        if (loadingBox) {
            loadingBox.classList.remove('is-visible');
            loadingBox.setAttribute('aria-hidden', 'true');
        }

        if (loadingBar) {
            loadingBar.style.width = '0%';
        }

        if (loadingPercent) {
            loadingPercent.textContent = '0%';
        }

        if (loadingText) {
            loadingText.textContent = 'Preparando alteração de líder.';
        }

        if (okBtn) {
            okBtn.disabled = false;
            okBtn.textContent = 'Confirmar';
            okBtn.classList.remove('is-processing');
        }

        if (cancelBtn) {
            cancelBtn.disabled = false;
        }
    };

    const abrirConfirm = (id, nome) => {
        if (!overlay || !text || !inputId) {
            return;
        }

        resetarLoading();

        inputId.value = String(id || 0);

        text.innerHTML = 'Você está prestes a definir <strong>' + escapeHtml(nome || 'Participante') + '</strong> como seu novo líder direto.';

        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');

        document.body.style.overflow = 'hidden';
    };

    const fecharConfirm = () => {
        if (!overlay || !inputId) {
            return;
        }

        if (processando) {
            return;
        }

        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');

        inputId.value = '0';
        document.body.style.overflow = '';

        resetarLoading();
    };

    const iniciarLoadingESubmeter = () => {
        if (!form || !okBtn || processando) {
            return;
        }

        processando = true;

        okBtn.disabled = true;
        okBtn.textContent = 'Alterando...';
        okBtn.classList.add('is-processing');

        if (cancelBtn) {
            cancelBtn.disabled = true;
        }

        if (loadingBox) {
            loadingBox.classList.add('is-visible');
            loadingBox.setAttribute('aria-hidden', 'false');
        }

        let progresso = 0;

        const mensagens = [
            'Validando novo líder...',
            'Verificando regras de segurança...',
            'Movendo sua subárvore...',
            'Recalculando conexões da rede...',
            'Finalizando alteração...'
        ];

        const atualizarLoading = () => {
            progresso = Math.min(progresso + Math.floor(Math.random() * 13) + 7, 92);

            if (loadingBar) {
                loadingBar.style.width = progresso + '%';
            }

            if (loadingPercent) {
                loadingPercent.textContent = progresso + '%';
            }

            if (loadingText) {
                const index = Math.min(
                    Math.floor((progresso / 100) * mensagens.length),
                    mensagens.length - 1
                );

                loadingText.textContent = mensagens[index];
            }
        };

        atualizarLoading();

        intervaloLoading = window.setInterval(atualizarLoading, 520);

        window.setTimeout(() => {
            if (loadingBar) {
                loadingBar.style.width = '96%';
            }

            if (loadingPercent) {
                loadingPercent.textContent = '96%';
            }

            if (loadingText) {
                loadingText.textContent = 'Salvando alteração...';
            }

            if (intervaloLoading) {
                window.clearInterval(intervaloLoading);
                intervaloLoading = null;
            }

            form.submit();
        }, 450);
    };

    document.querySelectorAll('.js-lider-confirm').forEach((button) => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-lider-id') || '0';
            const nome = button.getAttribute('data-lider-nome') || 'Participante';

            abrirConfirm(id, nome);
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
        okBtn.addEventListener('click', (event) => {
            event.preventDefault();
            iniciarLoadingESubmeter();
        });
    }

    if (form) {
        form.addEventListener('submit', (event) => {
            if (!processando) {
                event.preventDefault();
                iniciarLoadingESubmeter();
            }
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