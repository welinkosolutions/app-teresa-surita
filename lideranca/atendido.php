<?php
/**
 * ==========================================================
 * ARQUIVO: app.elab.social/lideranca/atendido.php
 * FUNÇÃO: Finaliza demanda automaticamente
 * AJUSTES:
 * - aceita lider/admin/gestor_lideres
 * - valida acesso à demanda
 * - alinha eventos com enum real de demandas_eventos
 * - registra status_alterado e resolucao_alterada
 * - mantém responsável atual ou assume o líder logado
 * ==========================================================
 */

declare(strict_types=1);

ini_set('display_errors', '1');
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

/* ================= CONTEXTO ================= */

$tenantClienteId = 0;

if (isset($_SESSION['tenant_cliente_id'])) {
    $tenantClienteId = (int)$_SESSION['tenant_cliente_id'];
} elseif (isset($_SESSION['cliente_id'])) {
    $tenantClienteId = (int)$_SESSION['cliente_id'];
} elseif (isset($_SESSION['tenant_id'])) {
    $tenantClienteId = (int)$_SESSION['tenant_id'];
}

/* ================= PERFIL ================= */

$stmt = $pdo->prepare("
    SELECT perfil
    FROM pessoas
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$liderId]);

$perfil = trim((string)($stmt->fetchColumn() ?? ''));

if (!in_array($perfil, ['lider', 'admin', 'gestor_lideres'], true)) {
    header('Location:/interno/admin.php');
    exit;
}

/* ================= HELPER DE ACESSO ================= */

function possuiAcessoEspecialAtendido(PDO $pdo, int $tenantClienteId, int $pessoaId, string $recurso): bool
{
    if ($tenantClienteId > 0) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM acessos_especiais
            WHERE tenant_cliente_id = ?
              AND pessoa_id = ?
              AND recurso = ?
              AND status = 'ativo'
            LIMIT 1
        ");
        $stmt->execute([$tenantClienteId, $pessoaId, $recurso]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id
            FROM acessos_especiais
            WHERE pessoa_id = ?
              AND recurso = ?
              AND status = 'ativo'
            LIMIT 1
        ");
        $stmt->execute([$pessoaId, $recurso]);
    }

    return (bool)$stmt->fetchColumn();
}

$temAcessoDemandas = possuiAcessoEspecialAtendido($pdo, $tenantClienteId, $liderId, 'demandas');
$temAcessoTotal = in_array($perfil, ['admin', 'gestor_lideres'], true);

/* ID */

$demandaId = (int)($_POST['demanda_id'] ?? $_GET['id'] ?? 0);

if ($demandaId <= 0) {
    header('Location: demandas.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ver-demanda.php?id=' . $demandaId . '&erro=sem_resolucao');
    exit;
}

$token = (string)($_POST['csrf_token'] ?? '');
if ($token === '' || empty($_SESSION['ver_demanda_csrf']) || !hash_equals((string)$_SESSION['ver_demanda_csrf'], $token)) {
    header('Location: ver-demanda.php?id=' . $demandaId . '&erro=csrf');
    exit;
}

$resolucaoComentario = trim((string)($_POST['resolucao_texto'] ?? ''));
if ($resolucaoComentario === '') {
    header('Location: ver-demanda.php?id=' . $demandaId . '&erro=sem_resolucao');
    exit;
}

/* ================= BUSCA / ESCOPO ================= */

$sqlDemanda = "
    SELECT
        d.id,
        d.status,
        d.resolucao,
        d.responsavel_id,
        d.criado_por,
        dr.lider_id AS lider_responsavel_id
    FROM demandas d
    LEFT JOIN demandas_responsaveis dr
        ON dr.demanda_id = d.id
       AND dr.ativo = 'sim'
    WHERE d.id = ?
";

$paramsDemanda = [$demandaId];

if (!$temAcessoTotal) {
    if (!$temAcessoDemandas && $perfil !== 'lider') {
        header('Location:/interno/admin.php');
        exit;
    }

    $sqlDemanda .= "
      AND (
            d.responsavel_id = ?
            OR d.criado_por = ?
            OR dr.lider_id = ?
      )
    ";
    $paramsDemanda[] = $liderId;
    $paramsDemanda[] = $liderId;
    $paramsDemanda[] = $liderId;
}

$sqlDemanda .= " LIMIT 1";

$stmt = $pdo->prepare($sqlDemanda);
$stmt->execute($paramsDemanda);
$demanda = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$demanda) {
    header('Location: demandas.php');
    exit;
}

if (($demanda['status'] ?? '') === 'fechado') {
    header('Location: ver-demanda.php?id=' . $demandaId);
    exit;
}

/* ================= FINALIZA ================= */

try {
    $pdo->beginTransaction();

    $statusAnterior = (string)($demanda['status'] ?? '');
    $resolucaoAnterior = (string)($demanda['resolucao'] ?? '');
    $responsavelFinal = (int)($demanda['responsavel_id'] ?? 0);

    if ($responsavelFinal <= 0) {
        $responsavelFinal = $liderId;
    }

    $pdo->prepare("
        UPDATE demandas
        SET status = 'fechado',
            resolucao = 'resolvido',
            resolucao_comentario = ?,
            resolvida_em = NOW(),
            atualizado_em = NOW(),
            responsavel_id = ?,
            autor_acao_id = ?
        WHERE id = ?
        LIMIT 1
    ")->execute([$resolucaoComentario, $responsavelFinal, $liderId, $demandaId]);

    if ($statusAnterior !== 'fechado') {
        $pdo->prepare("
            INSERT INTO demandas_eventos
            (demanda_id, tipo, valor_anterior, valor_novo, autor_id, autor_tipo, criado_em)
            VALUES (?, 'status_alterado', ?, 'fechado', ?, 'admin', NOW())
        ")->execute([
            $demandaId,
            $statusAnterior !== '' ? $statusAnterior : null,
            $liderId
        ]);
    }

    if ($resolucaoAnterior !== 'resolvido') {
        $pdo->prepare("
            INSERT INTO demandas_eventos
            (demanda_id, tipo, valor_anterior, valor_novo, autor_id, autor_tipo, criado_em)
            VALUES (?, 'resolucao_alterada', ?, 'resolvido', ?, 'admin', NOW())
        ")->execute([
            $demandaId,
            $resolucaoAnterior !== '' ? $resolucaoAnterior : null,
            $liderId
        ]);
    }

    $stmtResponsavelAtivo = $pdo->prepare("
        SELECT id
        FROM demandas_responsaveis
        WHERE demanda_id = ?
          AND lider_id = ?
          AND ativo = 'sim'
        LIMIT 1
    ");
    $stmtResponsavelAtivo->execute([$demandaId, $responsavelFinal]);

    if (!(int)($stmtResponsavelAtivo->fetchColumn() ?? 0)) {
        $pdo->prepare("
            UPDATE demandas_responsaveis
            SET ativo = 'nao',
                encerrado_em = NOW()
            WHERE demanda_id = ?
              AND ativo = 'sim'
        ")->execute([$demandaId]);

        $pdo->prepare("
            INSERT INTO demandas_responsaveis
            (demanda_id, lider_id, ativo, assumido_em, definido_por)
            VALUES (?, ?, 'sim', NOW(), ?)
        ")->execute([$demandaId, $responsavelFinal, $liderId]);

        $pdo->prepare("
            INSERT INTO demandas_eventos
            (demanda_id, tipo, valor_novo, autor_id, autor_tipo, criado_em)
            VALUES (?, 'responsavel_alterado', ?, ?, 'admin', NOW())
        ")->execute([
            $demandaId,
            (string)$responsavelFinal,
            $liderId
        ]);
    }

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    exit('Erro ao finalizar: ' . $e->getMessage());
}

header('Location: ver-demanda.php?id=' . $demandaId);
exit;