<?php
declare(strict_types=1);

ini_set('display_errors','1');
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
$liderId = (int)$_SESSION['pessoa_id'];

/* PERFIL */

$stmt = $pdo->prepare("SELECT perfil FROM pessoas WHERE id=? LIMIT 1");
$stmt->execute([$liderId]);

$perfil = trim((string)$stmt->fetchColumn());

if (!in_array($perfil, ['lider','admin','gestor_lideres'], true)) {
    header('Location:/interno/admin.php');
    exit;
}

/* ID */

$demandaId = (int)($_GET['id'] ?? 0);
if ($demandaId <= 0) {
    header('Location: demandas.php');
    exit;
}

/* BUSCA COM ESCOPO */

$stmt = $pdo->prepare("
SELECT *
FROM demandas
WHERE id=?
LIMIT 1
");
$stmt->execute([$demandaId]);
$demanda = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$demanda) {
    header('Location: demandas.php');
    exit;
}

/* ================= SALVAR ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $titulo = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $categoria = $_POST['categoria'] ?? 'outros';
    $prioridade = $_POST['prioridade'] ?? 'normal';
    $prazo = $_POST['prazo_limite'] ?: null;

    if ($titulo !== '' && $descricao !== '') {

        try {
            $pdo->beginTransaction();

            /* CAPTURA ANTES */

            $tituloAnt = $demanda['titulo'];
            $descAnt   = $demanda['descricao'];
            $catAnt    = $demanda['categoria'];
            $prioAnt   = $demanda['prioridade'];
            $prazoAnt  = $demanda['prazo_limite'];

            /* UPDATE */

            $pdo->prepare("
                UPDATE demandas
                SET titulo=?,
                    descricao=?,
                    categoria=?,
                    prioridade=?,
                    prazo_limite=?,
                    atualizado_em=NOW(),
                    autor_acao_id=?
                WHERE id=?
            ")->execute([
                $titulo,
                $descricao,
                $categoria,
                $prioridade,
                $prazo,
                $liderId,
                $demandaId
            ]);

            /* EVENTOS INTELIGENTES */

            // prioridade mudou
            if ($prioAnt !== $prioridade) {
                $pdo->prepare("
                    INSERT INTO demandas_eventos
                    (demanda_id, tipo, valor_anterior, valor_novo, autor_id, autor_tipo, criado_em)
                    VALUES (?, 'prioridade_alterada', ?, ?, ?, 'admin', NOW())
                ")->execute([
                    $demandaId,
                    $prioAnt,
                    $prioridade,
                    $liderId
                ]);
            }

            // categoria mudou (log como comentario técnico)
            if ($catAnt !== $categoria) {
                $pdo->prepare("
                    INSERT INTO demandas_eventos
                    (demanda_id, tipo, valor_novo, autor_id, autor_tipo, criado_em)
                    VALUES (?, 'comentario', ?, ?, 'admin', NOW())
                ")->execute([
                    $demandaId,
                    'Categoria alterada para: '.$categoria,
                    $liderId
                ]);
            }

            // título ou descrição mudou
            if ($tituloAnt !== $titulo || $descAnt !== $descricao) {
                $pdo->prepare("
                    INSERT INTO demandas_eventos
                    (demanda_id, tipo, valor_novo, autor_id, autor_tipo, criado_em)
                    VALUES (?, 'comentario', ?, ?, 'admin', NOW())
                ")->execute([
                    $demandaId,
                    'Conteúdo da demanda atualizado',
                    $liderId
                ]);
            }

            // prazo mudou
            if ($prazoAnt !== $prazo) {
                $pdo->prepare("
                    INSERT INTO demandas_eventos
                    (demanda_id, tipo, valor_anterior, valor_novo, autor_id, autor_tipo, criado_em)
                    VALUES (?, 'comentario', ?, ?, ?, 'admin', NOW())
                ")->execute([
                    $demandaId,
                    $prazoAnt ?: 'sem prazo',
                    $prazo ?: 'sem prazo',
                    $liderId
                ]);
            }

            $pdo->commit();

            header("Location: ver-demanda.php?id=".$demandaId);
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            exit('Erro ao editar: '.$e->getMessage());
        }
    }
}
?>