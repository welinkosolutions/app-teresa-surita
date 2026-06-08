<?php
/**
 * ==========================================================
 * ARQUIVO: app.elab.social/lideranca/transferir.php
 * FUNÇÃO: Transferência formal de responsabilidade da demanda
 * MODELO: Histórico via demandas_responsaveis
 * AJUSTES:
 * - aceita lider/admin/gestor_lideres
 * - valida escopo de acesso
 * - registra eventos compatíveis com demandas_eventos
 * - registra motivo como comentario técnico
 * - mantém histórico correto de responsável
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
$liderLogado = (int)($_SESSION['pessoa_id'] ?? 0);

/* ================= CONTEXTO ================= */

$tenantClienteId = 0;

if (isset($_SESSION['tenant_cliente_id'])) {
    $tenantClienteId = (int)$_SESSION['tenant_cliente_id'];
} elseif (isset($_SESSION['cliente_id'])) {
    $tenantClienteId = (int)$_SESSION['cliente_id'];
} elseif (isset($_SESSION['tenant_id'])) {
    $tenantClienteId = (int)$_SESSION['tenant_id'];
}

/* ================= VALIDAR PERFIL ================= */

$stmt = $pdo->prepare("
    SELECT perfil
    FROM pessoas
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$liderLogado]);

$perfil = trim((string)($stmt->fetchColumn() ?? ''));

if (!in_array($perfil, ['lider', 'admin', 'gestor_lideres'], true)) {
    header('Location:/interno/admin.php');
    exit;
}

/* ================= HELPER DE ACESSO ================= */

function possuiAcessoEspecialTransferir(PDO $pdo, int $tenantClienteId, int $pessoaId, string $recurso): bool
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

$temAcessoDemandas = possuiAcessoEspecialTransferir($pdo, $tenantClienteId, $liderLogado, 'demandas');
$temAcessoTotal = in_array($perfil, ['admin', 'gestor_lideres'], true);

/* ================= VALIDAR DEMANDA ================= */

$demandaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($demandaId <= 0) {
    header('Location: demandas.php');
    exit;
}

$sqlDemanda = "
    SELECT
        d.id,
        d.titulo,
        d.status,
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
    $paramsDemanda[] = $liderLogado;
    $paramsDemanda[] = $liderLogado;
    $paramsDemanda[] = $liderLogado;
}

$sqlDemanda .= " LIMIT 1";

$stmt = $pdo->prepare($sqlDemanda);
$stmt->execute($paramsDemanda);
$demanda = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$demanda) {
    header('Location: demandas.php');
    exit;
}

/* ================= PROCESSAR TRANSFERÊNCIA ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $novoLiderId = (int)($_POST['lider_id'] ?? 0);
    $motivo = trim((string)($_POST['motivo'] ?? ''));

    if ($novoLiderId <= 0) {
        header('Location: transferir.php?id=' . $demandaId);
        exit;
    }

    if ($novoLiderId === (int)($demanda['responsavel_id'] ?? 0)) {
        header('Location: ver-demanda.php?id=' . $demandaId);
        exit;
    }

    $stmtNovoLider = $pdo->prepare("
        SELECT id, nome
        FROM pessoas
        WHERE id = ?
          AND perfil = 'lider'
          AND status = 'ativo'
        LIMIT 1
    ");
    $stmtNovoLider->execute([$novoLiderId]);
    $novoLider = $stmtNovoLider->fetch(PDO::FETCH_ASSOC);

    if (!$novoLider) {
        header('Location: transferir.php?id=' . $demandaId);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $responsavelAnterior = (string)($demanda['responsavel_id'] ?? '');
        $statusAnterior = (string)($demanda['status'] ?? '');

        /* 1. Encerrar responsável atual */
        $pdo->prepare("
            UPDATE demandas_responsaveis
            SET ativo = 'nao',
                encerrado_em = NOW()
            WHERE demanda_id = ?
              AND ativo = 'sim'
        ")->execute([$demandaId]);

        /* 2. Inserir novo responsável */
        $pdo->prepare("
            INSERT INTO demandas_responsaveis
            (demanda_id, lider_id, ativo, assumido_em, definido_por)
            VALUES (?, ?, 'sim', NOW(), ?)
        ")->execute([
            $demandaId,
            $novoLiderId,
            $liderLogado
        ]);

        /* 3. Atualizar espelho + status */
        $pdo->prepare("
            UPDATE demandas
            SET responsavel_id = ?,
                status = 'transferido',
                autor_acao_id = ?,
                atualizado_em = NOW()
            WHERE id = ?
            LIMIT 1
        ")->execute([
            $novoLiderId,
            $liderLogado,
            $demandaId
        ]);

        /* 4. Evento: responsável alterado */
        $pdo->prepare("
            INSERT INTO demandas_eventos
            (demanda_id, tipo, valor_anterior, valor_novo, autor_id, autor_tipo, criado_em)
            VALUES (?, 'responsavel_alterado', ?, ?, ?, 'admin', NOW())
        ")->execute([
            $demandaId,
            $responsavelAnterior !== '' ? $responsavelAnterior : null,
            (string)$novoLiderId,
            $liderLogado
        ]);

        /* 5. Evento: status alterado */
        if ($statusAnterior !== 'transferido') {
            $pdo->prepare("
                INSERT INTO demandas_eventos
                (demanda_id, tipo, valor_anterior, valor_novo, autor_id, autor_tipo, criado_em)
                VALUES (?, 'status_alterado', ?, 'transferido', ?, 'admin', NOW())
            ")->execute([
                $demandaId,
                $statusAnterior !== '' ? $statusAnterior : null,
                $liderLogado
            ]);
        }

        /* 6. Evento: transferida */
        $pdo->prepare("
            INSERT INTO demandas_eventos
            (demanda_id, tipo, valor_novo, autor_id, autor_tipo, criado_em)
            VALUES (?, 'transferida', ?, ?, 'admin', NOW())
        ")->execute([
            $demandaId,
            'Transferida para o líder ID ' . $novoLiderId,
            $liderLogado
        ]);

        /* 7. Motivo opcional */
        if ($motivo !== '') {
            $pdo->prepare("
                INSERT INTO demandas_eventos
                (demanda_id, tipo, valor_novo, autor_id, autor_tipo, criado_em)
                VALUES (?, 'comentario', ?, ?, 'admin', NOW())
            ")->execute([
                $demandaId,
                'Motivo da transferência: ' . $motivo,
                $liderLogado
            ]);
        }

        $pdo->commit();

        header('Location: ver-demanda.php?id=' . $demandaId);
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die('Erro ao transferir demanda: ' . $e->getMessage());
    }
}

/* ================= LISTAR LÍDERES ================= */

$stmt = $pdo->prepare("
    SELECT id, nome
    FROM pessoas
    WHERE perfil = 'lider'
      AND status = 'ativo'
    ORDER BY nome
");
$stmt->execute();
$lideres = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Transferir Demanda</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f4f6f8;font-family:system-ui}
.card{
    border-radius:18px;
    box-shadow:0 8px 18px rgba(0,0,0,.06);
}
</style>
</head>
<body>
<div class="container py-5" style="max-width:650px">

    <div class="card p-4">
        <h5 class="fw-bold mb-3">Transferir Demanda</h5>

        <div class="mb-3 text-muted">
            <strong>Demanda:</strong> <?= htmlspecialchars((string)$demanda['titulo']) ?>
        </div>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Novo líder responsável</label>
                <select name="lider_id" class="form-select" required>
                    <option value="">Selecione</option>
                    <?php foreach ($lideres as $l): ?>
                        <option value="<?= (int)$l['id'] ?>" <?= ((int)$l['id'] === (int)($demanda['responsavel_id'] ?? 0) ? 'disabled' : '') ?>>
                            <?= htmlspecialchars((string)$l['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Motivo da transferência (opcional)</label>
                <textarea name="motivo" class="form-control" rows="3"></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    Confirmar Transferência
                </button>

                <a href="ver-demanda.php?id=<?= $demandaId ?>"
                   class="btn btn-outline-secondary">
                    Cancelar
                </a>
            </div>
        </form>
    </div>

</div>
</body>
</html>