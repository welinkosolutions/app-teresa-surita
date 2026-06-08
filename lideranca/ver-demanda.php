<?php
/**
 * ==========================================================
 * ARQUIVO: app.elab.social/lideranca/ver-demanda.php
 * FUNÇÃO: Visualização completa da demanda com histórico institucional
 * AJUSTES:
 * - resposta alinhada com respostas_demandas
 * - eventos alinhados com enum real de demandas_eventos
 * - autor_tipo alinhado com schema (admin|sistema)
 * - histórico traduz evento real do banco
 * - exclusão também remove mídias
 * ==========================================================
 */

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

$perfil = trim((string)($stmt->fetchColumn() ?? 'pessoa'));

/* ================= HELPERS DE ACESSO ================= */

function possuiAcessoEspecial(PDO $pdo, int $tenantClienteId, int $pessoaId, string $recurso): bool
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

function montarUrlVoltaDemandas(): string
{
    $query = [];

    $fontes = [
        'busca' => $_GET['ret_busca'] ?? $_POST['ret_busca'] ?? '',
        'status' => $_GET['ret_status'] ?? $_POST['ret_status'] ?? '',
        'prioridade' => $_GET['ret_prioridade'] ?? $_POST['ret_prioridade'] ?? '',
        'pagina' => $_GET['ret_pagina'] ?? $_POST['ret_pagina'] ?? '',
        'excluida' => $_GET['excluida'] ?? '',
        'erro_excluir' => $_GET['erro_excluir'] ?? '',
    ];

    foreach ($fontes as $chave => $valor) {
        $valor = is_string($valor) ? trim($valor) : '';
        if ($valor !== '') {
            $query[$chave] = $valor;
        }
    }

    $url = '/lideranca/demandas.php';

    if ($query) {
        $url .= '?' . http_build_query($query);
    }

    return $url;
}

function redirecionarParaVoltaDemandas(array $extras = []): never
{
    $base = [];

    $fontes = [
        'busca' => $_GET['ret_busca'] ?? $_POST['ret_busca'] ?? '',
        'status' => $_GET['ret_status'] ?? $_POST['ret_status'] ?? '',
        'prioridade' => $_GET['ret_prioridade'] ?? $_POST['ret_prioridade'] ?? '',
        'pagina' => $_GET['ret_pagina'] ?? $_POST['ret_pagina'] ?? '',
    ];

    foreach ($fontes as $chave => $valor) {
        $valor = is_string($valor) ? trim($valor) : '';
        if ($valor !== '') {
            $base[$chave] = $valor;
        }
    }

    foreach ($extras as $chave => $valor) {
        if ($valor === null || $valor === '') {
            unset($base[$chave]);
            continue;
        }

        $base[(string)$chave] = (string)$valor;
    }

    $url = '/lideranca/demandas.php';
    if ($base) {
        $url .= '?' . http_build_query($base);
    }

    header('Location: ' . $url);
    exit;
}

/* ================= HELPERS VISUAIS ================= */

function badgeStatus(?string $status): string
{
    return match ($status) {
        'aberto' => '<span class="badge bg-danger">Aberta</span>',
        'em_atendimento' => '<span class="badge bg-warning text-dark">Em Atendimento</span>',
        'fechado' => '<span class="badge bg-success">Fechada</span>',
        'transferido' => '<span class="badge bg-info text-dark">Transferida</span>',
        default => '<span class="badge bg-secondary">Indefinido</span>',
    };
}

function badgePrioridade(?string $prioridade): string
{
    return match ($prioridade) {
        'urgente' => '<span class="badge bg-danger">Urgente</span>',
        'alta' => '<span class="badge bg-warning text-dark">Alta</span>',
        default => '<span class="badge bg-secondary">Normal</span>',
    };
}

function gerarLinkWhats(?string $telefone): ?string
{
    if (!$telefone) {
        return null;
    }

    $n = preg_replace('/\D/', '', $telefone);

    if (strlen($n) < 10) {
        return null;
    }

    if (!str_starts_with($n, '55')) {
        $n = '55' . $n;
    }

    return 'https://wa.me/' . $n;
}

function formatarDataHumana(?string $data): string
{
    if (!$data) {
        return '-';
    }

    $ts = strtotime($data);
    if (!$ts) {
        return '-';
    }

    return date('d/m/Y H:i', $ts);
}

function descreverEventoDemanda(array $item): string
{
    $tipo = (string)($item['conteudo'] ?? '');
    $anterior = trim((string)($item['valor_anterior'] ?? ''));
    $novo = trim((string)($item['valor_novo'] ?? ''));

    return match ($tipo) {
        'criada' => 'Criou a demanda',
        'comentario' => 'Registrou uma movimentação no histórico',
        'transferida' => 'Transferiu a demanda',
        'responsavel_alterado' => ($novo !== '' ? 'Responsável alterado para ' . $novo : 'Responsável alterado'),
        'prioridade_alterada' => ($novo !== '' ? 'Prioridade alterada para ' . $novo : 'Prioridade alterada'),
        'status_alterado' => (
            $anterior !== '' || $novo !== ''
                ? 'Status alterado'
                    . ($anterior !== '' ? ' de ' . $anterior : '')
                    . ($novo !== '' ? ' para ' . $novo : '')
                : 'Status alterado'
        ),
        'resolucao_alterada' => (
            $anterior !== '' || $novo !== ''
                ? 'Resolução alterada'
                    . ($anterior !== '' ? ' de ' . $anterior : '')
                    . ($novo !== '' ? ' para ' . $novo : '')
                : 'Resolução alterada'
        ),
        'visita_criada' => 'Visita criada',
        'visita_status' => (
            $anterior !== '' || $novo !== ''
                ? 'Status da visita alterado'
                    . ($anterior !== '' ? ' de ' . $anterior : '')
                    . ($novo !== '' ? ' para ' . $novo : '')
                : 'Status da visita alterado'
        ),
        'visita_realizada' => 'Visita realizada',
        default => ucfirst(str_replace('_', ' ', $tipo)),
    };
}

/* ================= ACESSOS ================= */

$temAcessoDemandas = possuiAcessoEspecial($pdo, $tenantClienteId, $liderId, 'demandas');

if (!$temAcessoDemandas && !in_array($perfil, ['admin', 'gestor_lideres'], true)) {
    header('Location: /interno/admin.php');
    exit;
}

$temAcessoTotal = in_array($perfil, ['admin', 'gestor_lideres'], true);

/* ================= ID ================= */

$demandaId = (int)($_GET['id'] ?? $_POST['demanda_id'] ?? 0);

if ($demandaId <= 0) {
    redirecionarParaVoltaDemandas();
}

/* ================= CSRF ================= */

if (empty($_SESSION['ver_demanda_csrf']) || !is_string($_SESSION['ver_demanda_csrf'])) {
    $_SESSION['ver_demanda_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['ver_demanda_csrf'];

/* ================= BUSCA DEMANDA COM ESCOPO ================= */

$sqlDemanda = "
SELECT
    d.*,
    p.id AS pessoa_id,
    p.nome,
    p.telefone,
    p.criado_por AS pessoa_criado_por,
    pe.endereco,
    pe.numero,
    pe.complemento,
    pe.bairro,
    pe.cidade,
    pe.estado,
    pe.latitude,
    pe.longitude,
    pr.nome AS responsavel_nome,
    dr.lider_id AS lider_responsavel_id,
    pl.nome AS lider_responsavel_nome
FROM demandas d
INNER JOIN pessoas p
    ON p.id = d.pessoa_id
LEFT JOIN pessoas_enderecos pe
    ON pe.pessoa_id = p.id
   AND pe.tipo = 'residencial'
LEFT JOIN pessoas pr
    ON pr.id = d.responsavel_id
LEFT JOIN demandas_responsaveis dr
    ON dr.demanda_id = d.id
   AND dr.ativo = 'sim'
LEFT JOIN pessoas pl
    ON pl.id = dr.lider_id
WHERE d.id = ?
";

$paramsDemanda = [$demandaId];

if (!$temAcessoTotal) {
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

$sqlDemanda .= "
LIMIT 1
";

$stmt = $pdo->prepare($sqlDemanda);
$stmt->execute($paramsDemanda);
$demanda = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$demanda) {
    redirecionarParaVoltaDemandas();
}

$whats = gerarLinkWhats($demanda['telefone'] ?? null);
$urlVoltar = montarUrlVoltaDemandas();

/* ================= EXCLUIR DEMANDA ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['acao'] ?? '') === 'excluir_demanda') {
    $token = (string)($_POST['csrf_token'] ?? '');

    if (
        $token === ''
        || empty($_SESSION['ver_demanda_csrf'])
        || !hash_equals((string)$_SESSION['ver_demanda_csrf'], $token)
    ) {
        header('Location: ver-demanda.php?id=' . $demandaId . '&erro_excluir=1'
            . '&ret_busca=' . urlencode((string)($_POST['ret_busca'] ?? $_GET['ret_busca'] ?? ''))
            . '&ret_status=' . urlencode((string)($_POST['ret_status'] ?? $_GET['ret_status'] ?? ''))
            . '&ret_prioridade=' . urlencode((string)($_POST['ret_prioridade'] ?? $_GET['ret_prioridade'] ?? ''))
            . '&ret_pagina=' . urlencode((string)($_POST['ret_pagina'] ?? $_GET['ret_pagina'] ?? ''))
        );
        exit;
    }

    try {
        $sqlPodeExcluir = "
        SELECT d.id
        FROM demandas d
        LEFT JOIN demandas_responsaveis dr
            ON dr.demanda_id = d.id
           AND dr.ativo = 'sim'
        WHERE d.id = ?
        ";
        $paramsPodeExcluir = [$demandaId];

        if (!$temAcessoTotal) {
            $sqlPodeExcluir .= "
              AND (
                    d.responsavel_id = ?
                    OR d.criado_por = ?
                    OR dr.lider_id = ?
              )
            ";
            $paramsPodeExcluir[] = $liderId;
            $paramsPodeExcluir[] = $liderId;
            $paramsPodeExcluir[] = $liderId;
        }

        $sqlPodeExcluir .= " LIMIT 1";

        $stmtPodeExcluir = $pdo->prepare($sqlPodeExcluir);
        $stmtPodeExcluir->execute($paramsPodeExcluir);

        if (!(int)($stmtPodeExcluir->fetchColumn() ?? 0)) {
            header('Location: ver-demanda.php?id=' . $demandaId . '&erro_excluir=1'
                . '&ret_busca=' . urlencode((string)($_POST['ret_busca'] ?? $_GET['ret_busca'] ?? ''))
                . '&ret_status=' . urlencode((string)($_POST['ret_status'] ?? $_GET['ret_status'] ?? ''))
                . '&ret_prioridade=' . urlencode((string)($_POST['ret_prioridade'] ?? $_GET['ret_prioridade'] ?? ''))
                . '&ret_pagina=' . urlencode((string)($_POST['ret_pagina'] ?? $_GET['ret_pagina'] ?? ''))
            );
            exit;
        }

        $stmtMidias = $pdo->prepare("
            SELECT arquivo
            FROM demandas_midias
            WHERE demanda_id = ?
        ");
        $stmtMidias->execute([$demandaId]);
        $midiasExcluir = $stmtMidias->fetchAll(PDO::FETCH_COLUMN);

        $pdo->beginTransaction();

        $pdo->prepare("
            DELETE FROM respostas_demandas
            WHERE demanda_id = ?
        ")->execute([$demandaId]);

        $pdo->prepare("
            DELETE FROM demandas_eventos
            WHERE demanda_id = ?
        ")->execute([$demandaId]);

        $pdo->prepare("
            DELETE FROM demandas_visitas
            WHERE demanda_id = ?
        ")->execute([$demandaId]);

        $pdo->prepare("
            DELETE FROM demandas_responsaveis
            WHERE demanda_id = ?
        ")->execute([$demandaId]);

        $pdo->prepare("
            DELETE FROM demandas_midias
            WHERE demanda_id = ?
        ")->execute([$demandaId]);

        $pdo->prepare("
            DELETE FROM demandas
            WHERE id = ?
            LIMIT 1
        ")->execute([$demandaId]);

        $pdo->commit();

        foreach ($midiasExcluir as $arquivo) {
            $arquivo = trim((string)$arquivo);
            if ($arquivo === '') {
                continue;
            }

            $caminhoAbs = '/home/elab/public_html/' . ltrim($arquivo, '/');
            if (is_file($caminhoAbs)) {
                @unlink($caminhoAbs);
            }
        }

        $pastaDemanda = '/home/elab/public_html/uploads/demandas/' . $demandaId;
        if (is_dir($pastaDemanda)) {
            $itens = @scandir($pastaDemanda) ?: [];
            foreach ($itens as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $caminho = $pastaDemanda . '/' . $item;
                if (is_file($caminho)) {
                    @unlink($caminho);
                }
            }
            @rmdir($pastaDemanda);
        }

        redirecionarParaVoltaDemandas(['excluida' => '1']);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('[VER_DEMANDA_EXCLUIR] ' . $e->getMessage());

        header('Location: ver-demanda.php?id=' . $demandaId . '&erro_excluir=1'
            . '&ret_busca=' . urlencode((string)($_POST['ret_busca'] ?? $_GET['ret_busca'] ?? ''))
            . '&ret_status=' . urlencode((string)($_POST['ret_status'] ?? $_GET['ret_status'] ?? ''))
            . '&ret_prioridade=' . urlencode((string)($_POST['ret_prioridade'] ?? $_GET['ret_prioridade'] ?? ''))
            . '&ret_pagina=' . urlencode((string)($_POST['ret_pagina'] ?? $_GET['ret_pagina'] ?? ''))
        );
        exit;
    }
}

/* ================= NOVA RESPOSTA ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['acao'] ?? '') === 'responder_demanda') {
    $token = (string)($_POST['csrf_token'] ?? '');
    $mensagem = trim((string)($_POST['mensagem'] ?? ''));
    $visibilidade = trim((string)($_POST['visibilidade'] ?? 'publico'));

    if (
        $token === ''
        || empty($_SESSION['ver_demanda_csrf'])
        || !hash_equals((string)$_SESSION['ver_demanda_csrf'], $token)
    ) {
        header('Location: ver-demanda.php?id=' . $demandaId
            . '&ret_busca=' . urlencode((string)($_POST['ret_busca'] ?? $_GET['ret_busca'] ?? ''))
            . '&ret_status=' . urlencode((string)($_POST['ret_status'] ?? $_GET['ret_status'] ?? ''))
            . '&ret_prioridade=' . urlencode((string)($_POST['ret_prioridade'] ?? $_GET['ret_prioridade'] ?? ''))
            . '&ret_pagina=' . urlencode((string)($_POST['ret_pagina'] ?? $_GET['ret_pagina'] ?? ''))
        );
        exit;
    }

    if (!in_array($visibilidade, ['publico', 'interno'], true)) {
        $visibilidade = 'publico';
    }

    if ($mensagem !== '') {
        try {
            $sqlPodeResponder = "
            SELECT d.id, d.status
            FROM demandas d
            LEFT JOIN demandas_responsaveis dr
                ON dr.demanda_id = d.id
               AND dr.ativo = 'sim'
            WHERE d.id = ?
            ";
            $paramsPodeResponder = [$demandaId];

            if (!$temAcessoTotal) {
                $sqlPodeResponder .= "
                  AND (
                        d.responsavel_id = ?
                        OR d.criado_por = ?
                        OR dr.lider_id = ?
                  )
                ";
                $paramsPodeResponder[] = $liderId;
                $paramsPodeResponder[] = $liderId;
                $paramsPodeResponder[] = $liderId;
            }

            $sqlPodeResponder .= " LIMIT 1";

            $stmtPodeResponder = $pdo->prepare($sqlPodeResponder);
            $stmtPodeResponder->execute($paramsPodeResponder);
            $podeResponder = $stmtPodeResponder->fetch(PDO::FETCH_ASSOC);

            if ($podeResponder) {
                $statusAnterior = (string)($podeResponder['status'] ?? '');

                $pdo->beginTransaction();

                $pdo->prepare("
                    INSERT INTO respostas_demandas
                    (demanda_id, autor_id, autor_tipo, mensagem, visibilidade)
                    VALUES (?, ?, 'lider', ?, ?)
                ")->execute([$demandaId, $liderId, $mensagem, $visibilidade]);

                $pdo->prepare("
                    INSERT INTO demandas_eventos
                    (demanda_id, tipo, autor_id, autor_tipo, criado_em)
                    VALUES (?, 'comentario', ?, 'admin', NOW())
                ")->execute([$demandaId, $liderId]);

                if ($statusAnterior === 'aberto') {
                    $pdo->prepare("
                        UPDATE demandas
                        SET responsavel_id = ?,
                            autor_acao_id = ?,
                            status = 'em_atendimento',
                            atualizado_em = NOW()
                        WHERE id = ?
                    ")->execute([$liderId, $liderId, $demandaId]);

                    $pdo->prepare("
                        INSERT INTO demandas_eventos
                        (demanda_id, tipo, valor_anterior, valor_novo, autor_id, autor_tipo, criado_em)
                        VALUES (?, 'status_alterado', 'aberto', 'em_atendimento', ?, 'admin', NOW())
                    ")->execute([$demandaId, $liderId]);
                } else {
                    $pdo->prepare("
                        UPDATE demandas
                        SET responsavel_id = ?,
                            autor_acao_id = ?,
                            atualizado_em = NOW()
                        WHERE id = ?
                    ")->execute([$liderId, $liderId, $demandaId]);
                }

                $stmtResponsavelAtivo = $pdo->prepare("
                    SELECT id
                    FROM demandas_responsaveis
                    WHERE demanda_id = ?
                      AND lider_id = ?
                      AND ativo = 'sim'
                    LIMIT 1
                ");
                $stmtResponsavelAtivo->execute([$demandaId, $liderId]);

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
                    ")->execute([$demandaId, $liderId, $liderId]);

                    $pdo->prepare("
                        INSERT INTO demandas_eventos
                        (demanda_id, tipo, valor_novo, autor_id, autor_tipo, criado_em)
                        VALUES (?, 'responsavel_alterado', ?, ?, 'admin', NOW())
                    ")->execute([$demandaId, (string)$liderId, $liderId]);
                }

                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('[VER_DEMANDA_RESPOSTA] ' . $e->getMessage());
        }
    }

    header('Location: ver-demanda.php?id=' . $demandaId
        . '&ret_busca=' . urlencode((string)($_POST['ret_busca'] ?? $_GET['ret_busca'] ?? ''))
        . '&ret_status=' . urlencode((string)($_POST['ret_status'] ?? $_GET['ret_status'] ?? ''))
        . '&ret_prioridade=' . urlencode((string)($_POST['ret_prioridade'] ?? $_GET['ret_prioridade'] ?? ''))
        . '&ret_pagina=' . urlencode((string)($_POST['ret_pagina'] ?? $_GET['ret_pagina'] ?? ''))
    );
    exit;
}

/* ================= HISTÓRICO ================= */

$stmt = $pdo->prepare("
    SELECT
        e.criado_em,
        'evento' AS origem,
        e.tipo AS conteudo,
        e.valor_anterior,
        e.valor_novo,
        pe.nome AS autor_nome,
        NULL AS visibilidade
    FROM demandas_eventos e
    LEFT JOIN pessoas pe
        ON pe.id = e.autor_id
    WHERE e.demanda_id = ?

    UNION ALL

    SELECT
        r.criado_em,
        'resposta' AS origem,
        r.mensagem AS conteudo,
        NULL AS valor_anterior,
        NULL AS valor_novo,
        pr.nome AS autor_nome,
        r.visibilidade
    FROM respostas_demandas r
    LEFT JOIN pessoas pr
        ON pr.id = r.autor_id
    WHERE r.demanda_id = ?

    ORDER BY criado_em ASC
");
$stmt->execute([$demandaId, $demandaId]);
$historico = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= APOIOS VISUAIS ================= */

$responsavelAtualNome = trim((string)($demanda['lider_responsavel_nome'] ?? $demanda['responsavel_nome'] ?? ''));
$responsavelAtualId = (int)($demanda['lider_responsavel_id'] ?? $demanda['responsavel_id'] ?? 0);

$enderecoPartes = [];
if (!empty($demanda['endereco'])) {
    $enderecoPartes[] = (string)$demanda['endereco'];
}
if (!empty($demanda['numero'])) {
    $enderecoPartes[] = 'Nº ' . (string)$demanda['numero'];
}
if (!empty($demanda['complemento'])) {
    $enderecoPartes[] = (string)$demanda['complemento'];
}
if (!empty($demanda['bairro'])) {
    $enderecoPartes[] = (string)$demanda['bairro'];
}
if (!empty($demanda['cidade'])) {
    $enderecoPartes[] = (string)$demanda['cidade'];
}
if (!empty($demanda['estado'])) {
    $enderecoPartes[] = (string)$demanda['estado'];
}

$enderecoCompleto = $enderecoPartes ? implode(', ', $enderecoPartes) : 'Não informado';

$mapaUrl = null;
if (!empty($demanda['latitude']) && !empty($demanda['longitude'])) {
    $mapaUrl = 'https://www.google.com/maps?q='
        . rawurlencode((string)$demanda['latitude'] . ',' . (string)$demanda['longitude']);
} elseif ($enderecoCompleto !== 'Não informado') {
    $mapaUrl = 'https://www.google.com/maps?q=' . rawurlencode($enderecoCompleto);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Demanda</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#f4f6f8;
    font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
}

.card-box{
    background:#fff;
    border-radius:18px;
    padding:22px;
    margin-bottom:22px;
    box-shadow:0 8px 20px rgba(0,0,0,.05);
}

.page-wrap{
    max-width:1100px;
}

.top-actions{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    margin-bottom:18px;
}

.header-grid{
    display:grid;
    grid-template-columns:minmax(0,1.5fr) minmax(320px,1fr);
    gap:18px;
}

.header-title{
    font-size:26px;
    line-height:1.15;
    font-weight:800;
    margin:0 0 6px 0;
}

.header-subtitle{
    font-size:13px;
    color:#6c757d;
    margin-bottom:14px;
}

.meta-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0,1fr));
    gap:14px;
}

.meta-item{
    background:#f8fafc;
    border:1px solid #e9ecef;
    border-radius:14px;
    padding:14px;
}

.meta-label{
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.04em;
    color:#6c757d;
    font-weight:700;
    margin-bottom:4px;
}

.meta-value{
    font-size:14px;
    color:#111827;
    line-height:1.45;
    word-break:break-word;
}

.meta-value a{
    text-decoration:none;
}

.descricao{
    white-space:pre-line;
    word-break:break-word;
    font-size:15px;
    line-height:1.65;
}

.timeline-log{
    border:1px solid #e5e7eb;
    border-radius:14px;
    overflow:hidden;
    background:#ffffff;
}

.log-item{
    display:flex;
    gap:18px;
    padding:16px 20px;
    border-bottom:1px solid #f1f3f5;
}

.log-item:last-child{
    border-bottom:none;
}

.log-col-left{
    width:170px;
    min-width:170px;
}

.log-autor{
    font-weight:700;
    font-size:13px;
}

.log-date{
    font-size:12px;
    color:#6c757d;
}

.log-col-right{
    flex:1;
}

.log-tag{
    display:inline-block;
    font-size:11px;
    font-weight:700;
    padding:4px 8px;
    border-radius:6px;
    margin-bottom:6px;
}

.tag-evento{
    background:#eef2ff;
    color:#3730a3;
}

.tag-publico{
    background:#ecfdf5;
    color:#065f46;
}

.tag-interno{
    background:#fff7ed;
    color:#9a3412;
}

.log-text{
    white-space:pre-line;
    word-break:break-word;
    font-size:14px;
    line-height:1.55;
}

.log-item:hover{
    background:#f8fafc;
}

.section-title{
    font-size:18px;
    font-weight:800;
    margin-bottom:14px;
}

@media (max-width: 991.98px){
    .header-grid{
        grid-template-columns:1fr;
    }

    .meta-grid{
        grid-template-columns:1fr;
    }

    .log-item{
        flex-direction:column;
        gap:8px;
    }

    .log-col-left{
        width:auto;
        min-width:0;
    }
}
</style>
</head>
<body>

<div class="container py-4 page-wrap">

    <div class="top-actions">
        <a href="<?= htmlspecialchars($urlVoltar) ?>" class="btn btn-warning btn-sm text-black">Voltar</a>

        <?php if (($demanda['status'] ?? '') !== 'fechado'): ?>
            <a href="editar.php?id=<?= $demandaId ?>" class="btn btn-outline-dark btn-sm">Editar</a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalResponder">Responder</button>
            <button type="button" class="btn btn-success btn-sm text-white" data-bs-toggle="modal" data-bs-target="#modalResolverDemanda">Resolver</button>
        <?php else: ?>
            <a href="reabrir.php?id=<?= $demandaId ?>" class="btn btn-outline-success btn-sm" onclick="return confirm('Deseja reabrir esta demanda?')">Reabrir</a>
        <?php endif; ?>

        <a href="/pessoas/ver.php?id=<?= (int)$demanda['pessoa_id'] ?>" class="btn btn-outline-secondary btn-sm">Ver Ficha</a>

        <?php if ($whats): ?>
            <a href="<?= htmlspecialchars($whats) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-success btn-sm">WhatsApp</a>
        <?php endif; ?>

        <?php if ($mapaUrl): ?>
            <a href="<?= htmlspecialchars($mapaUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">Abrir no mapa</a>
        <?php endif; ?>

        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalExcluirDemanda">
            Excluir
        </button>
    </div>

    <?php if (isset($_GET['erro_excluir']) && $_GET['erro_excluir'] === '1'): ?>
        <div class="alert alert-danger border-0 shadow-sm">
            Não foi possível excluir esta demanda agora.
        </div>
    <?php endif; ?>

    <?php if (($_GET['erro'] ?? '') === 'sem_resolucao'): ?>
        <div class="alert alert-danger border-0 shadow-sm">
            Informe a resolução antes de fechar a demanda.
        </div>
    <?php endif; ?>

    <?php if (($_GET['erro'] ?? '') === 'csrf'): ?>
        <div class="alert alert-danger border-0 shadow-sm">
            Sessão expirada. Atualize a página e tente novamente.
        </div>
    <?php endif; ?>

    <div class="card-box">
        <div class="header-grid">
            <div>
                <h1 class="header-title"><?= htmlspecialchars((string)$demanda['titulo']) ?></h1>
                <div class="header-subtitle">
                    Protocolo <?= htmlspecialchars((string)$demanda['protocolo']) ?>
                    • Criado em <?= htmlspecialchars(formatarDataHumana($demanda['criado_em'] ?? null)) ?>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?= badgeStatus($demanda['status'] ?? null) ?>
                    <?= badgePrioridade($demanda['prioridade'] ?? null) ?>
                </div>

                <div class="section-title">Descrição da demanda</div>
                <div class="descricao">
                    <?= nl2br(htmlspecialchars((string)($demanda['descricao'] ?? ''))) ?>
                </div>
            </div>

            <div class="meta-grid">
                <div class="meta-item">
                    <div class="meta-label">Solicitante</div>
                    <div class="meta-value"><?= htmlspecialchars((string)($demanda['nome'] ?? 'Não informado')) ?></div>
                </div>

                <div class="meta-item">
                    <div class="meta-label">Responsável atual</div>
                    <div class="meta-value">
                        <?php if ($responsavelAtualId > 0): ?>
                            <a href="/pessoas/ver.php?id=<?= $responsavelAtualId ?>">
                                <?= htmlspecialchars($responsavelAtualNome !== '' ? $responsavelAtualNome : 'Ver pessoa') ?>
                            </a>
                        <?php else: ?>
                            <span class="text-secondary">Não definido</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="meta-item">
                    <div class="meta-label">Telefone</div>
                    <div class="meta-value"><?= htmlspecialchars((string)($demanda['telefone'] ?? 'Não informado')) ?></div>
                </div>

                <div class="meta-item">
                    <div class="meta-label">Endereço</div>
                    <div class="meta-value"><?= htmlspecialchars($enderecoCompleto) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card-box">
        <div class="section-title">Histórico</div>

        <div class="timeline-log">
            <?php if (!$historico): ?>
                <div class="p-4 text-secondary">
                    Nenhum histórico registrado ainda.
                </div>
            <?php endif; ?>

            <?php foreach ($historico as $item): ?>
                <?php
                    $texto = '';
                    $tagClasse = '';
                    $tagTexto = '';

                    if (($item['origem'] ?? '') === 'evento') {
                        $texto = htmlspecialchars(descreverEventoDemanda($item));
                        $tagClasse = 'tag-evento';
                        $tagTexto = 'Evento do Sistema';
                    } else {
                        $texto = nl2br(htmlspecialchars((string)($item['conteudo'] ?? '')));

                        if (($item['visibilidade'] ?? '') === 'interno') {
                            $tagClasse = 'tag-interno';
                            $tagTexto = 'Nota Interna';
                        } else {
                            $tagClasse = 'tag-publico';
                            $tagTexto = 'Resposta Pública';
                        }
                    }
                ?>

                <div class="log-item">
                    <div class="log-col-left">
                        <div class="log-autor"><?= htmlspecialchars((string)($item['autor_nome'] ?? 'Sistema')) ?></div>
                        <div class="log-date"><?= htmlspecialchars(formatarDataHumana($item['criado_em'] ?? null)) ?></div>
                    </div>

                    <div class="log-col-right">
                        <span class="log-tag <?= $tagClasse ?>"><?= $tagTexto ?></span>
                        <div class="log-text"><?= $texto ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>


<div class="modal fade" id="modalResolverDemanda" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius:18px;">
            <form method="POST" action="atendido.php?id=<?= (int)$demandaId ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="demanda_id" value="<?= (int)$demandaId ?>">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-success">Resolver demanda</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body pt-2">
                    <div class="mb-2" style="font-size:15px;">
                        Descreva a resolução antes de fechar esta demanda.
                    </div>

                    <textarea
                        name="resolucao_texto"
                        class="form-control"
                        rows="5"
                        required
                        placeholder="Ex: Atendimento realizado, demanda resolvida com o solicitante..."
                    ></textarea>
                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Confirmar resolução</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalResponder" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="acao" value="responder_demanda">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="demanda_id" value="<?= (int)$demandaId ?>">
                <input type="hidden" name="ret_busca" value="<?= htmlspecialchars((string)($_GET['ret_busca'] ?? $_POST['ret_busca'] ?? '')) ?>">
                <input type="hidden" name="ret_status" value="<?= htmlspecialchars((string)($_GET['ret_status'] ?? $_POST['ret_status'] ?? '')) ?>">
                <input type="hidden" name="ret_prioridade" value="<?= htmlspecialchars((string)($_GET['ret_prioridade'] ?? $_POST['ret_prioridade'] ?? '')) ?>">
                <input type="hidden" name="ret_pagina" value="<?= htmlspecialchars((string)($_GET['ret_pagina'] ?? $_POST['ret_pagina'] ?? '')) ?>">

                <div class="modal-header">
                    <h5 class="modal-title">Responder demanda</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <textarea name="mensagem" class="form-control mb-3" rows="5" required></textarea>

                    <select name="visibilidade" class="form-select">
                        <option value="publico">Resposta Pública</option>
                        <option value="interno">Nota Interna</option>
                    </select>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Enviar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalExcluirDemanda" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius:18px;">
            <form method="POST">
                <input type="hidden" name="acao" value="excluir_demanda">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="demanda_id" value="<?= (int)$demandaId ?>">
                <input type="hidden" name="ret_busca" value="<?= htmlspecialchars((string)($_GET['ret_busca'] ?? $_POST['ret_busca'] ?? '')) ?>">
                <input type="hidden" name="ret_status" value="<?= htmlspecialchars((string)($_GET['ret_status'] ?? $_POST['ret_status'] ?? '')) ?>">
                <input type="hidden" name="ret_prioridade" value="<?= htmlspecialchars((string)($_GET['ret_prioridade'] ?? $_POST['ret_prioridade'] ?? '')) ?>">
                <input type="hidden" name="ret_pagina" value="<?= htmlspecialchars((string)($_GET['ret_pagina'] ?? $_POST['ret_pagina'] ?? '')) ?>">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-danger">Excluir demanda</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body pt-2">
                    <div class="mb-2" style="font-size:15px;">
                        Tem certeza que deseja excluir esta demanda?
                    </div>

                    <div class="small text-muted">
                        <strong><?= htmlspecialchars((string)$demanda['titulo']) ?></strong><br>
                        Protocolo <?= htmlspecialchars((string)$demanda['protocolo']) ?>
                    </div>

                    <div class="mt-3 p-3 rounded" style="background:#fff5f5;border:1px solid #f1c0c0;font-size:13px;color:#842029;">
                        Essa ação remove a demanda e também histórico, respostas, visitas, responsáveis e mídias vinculadas.
                    </div>
                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Sim, excluir demanda</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>