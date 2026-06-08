<?php
declare(strict_types=1);

/**
 * ============================================================
 * APP DASHBOARD app.elab.social/dashboard/index.php
 * ============================================================
 */

ini_set('display_errors','1');
error_reporting(E_ALL);

date_default_timezone_set('America/Boa_Vista');
require_once '/home/elab/public_html/core/sessao/app.php';

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}
require_once '/home/elab/public_html/core/gamificacao/feed_missoes_home.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';
require_once '/home/elab/public_html/core/game/bootstrap.php';


$pdo = dbRoraima();
$pessoa_id = (int)$_SESSION['pessoa_id'];

require_once '/home/elab/public_html/core/meta/auto_vincular_comentarios.php';

$stmt = $pdo->query("
SELECT valor
FROM gamificacao_estado_usuario
WHERE chave='auto_vinculo_meta'
LIMIT 1
");

$ultimo = $stmt->fetchColumn();

$executar = true;

if ($ultimo) {

    $ultimaExecucao = strtotime($ultimo);

    // 🔒 executa no máximo 1 vez por hora
    if (time() - $ultimaExecucao < 3600) {
        $executar = false;
    }
}

if ($executar) {

    elabAutoVincularComentarios($pdo);

    $pdo->prepare("
        INSERT INTO gamificacao_estado_usuario (pessoa_id, chave, valor)
        VALUES (0, 'auto_vinculo_meta', NOW())
        ON DUPLICATE KEY UPDATE valor = NOW()
    ")->execute();
}


$temporadaId = gameTemporadaAtiva();
$missoes = elabBuscarMissoesHome($pdo, $pessoa_id);
/* =====================================================
   🔧 CTA PADRÃO MISSÃO
===================================================== */
$ctaUrl = '/game/index.php';

if (!empty($missoes['missao_dia']['permalink'])) {
    $ctaUrl = $missoes['missao_dia']['permalink'];
}

/* =====================================================
   ✅ CONFIG (ajuste rápido)
===================================================== */
const IG_TUTORIAL_YOUTUBE_ID = 'VIDEO_ID_AQUI'; // troque pelo ID real do vídeo (somente o ID)

/* =====================================================
   ✅ POST: VINCULAR INSTAGRAM (modal)
   - salva instagram_username (normalizado)
   - salva instagram (legado) também
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'vincular_instagram') {

    $raw = (string)($_POST['instagram_username'] ?? '');
    $raw = trim($raw);

    // normaliza: remove @ e espaços, baixa, só [a-z0-9._]
    $ig = strtolower($raw);
    $ig = str_replace([' ', "\t", "\n", "\r"], '', $ig);
    $ig = ltrim($ig, '@');
    $ig = preg_replace('/[^a-z0-9._]/', '', $ig) ?? '';

    if ($ig !== '' && strlen($ig) >= 3 && strlen($ig) <= 30) {
        $stmtUp = $pdo->prepare("
            UPDATE pessoas
            SET instagram_username = ?,
                instagram = ?,
                instagram_confirmado = 'sim',
                atualizado_em = NOW()
            WHERE id = ?
            LIMIT 1
        ");
        $stmtUp->execute([$ig, $ig, $pessoa_id]);
    }

    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

/* =====================================================
    CONTROLE DE ACESSO
===================================================== */

$dispararEventoBackend = null;

$stmt = $pdo->prepare("
    SELECT primeiro_acesso_app, ultimo_acesso_app
    FROM pessoas
    WHERE id=? LIMIT 1
");
$stmt->execute([$pessoa_id]);
$acesso = $stmt->fetch(PDO::FETCH_ASSOC);

$agora = new DateTime();

if (empty($acesso['primeiro_acesso_app'])) {
    $pdo->prepare("
        UPDATE pessoas
        SET primeiro_acesso_app=NOW(),
            ultimo_acesso_app=NOW()
        WHERE id=?
    ")->execute([$pessoa_id]);

    $dispararEventoBackend = 'primeiro_acesso';
} else {
    $ultimo = new DateTime($acesso['ultimo_acesso_app']);
    $diffHoras = ($agora->getTimestamp() - $ultimo->getTimestamp()) / 3600;

    if ($diffHoras >= 4) {
        $dispararEventoBackend = 'sentimos_falta';
    }

    $pdo->prepare("
        UPDATE pessoas
        SET ultimo_acesso_app=NOW()
        WHERE id=?
    ")->execute([$pessoa_id]);
}

/* =====================================================
    DADOS PESSOA + XP + XP HOJE (QUERY OTIMIZADA)
===================================================== */

$stmt = $pdo->prepare("
SELECT
p.nome,
p.apelido,
p.chamar_por,
p.instagram_username,
p.instagram,

COALESCE(SUM(gph.pontos_vitalicios),0) AS xp_total,

COALESCE(SUM(
    CASE
        WHEN DATE(gph.criado_em)=CURDATE()
        THEN gph.pontos_vitalicios
        ELSE 0
    END
),0) AS xp_hoje

FROM pessoas p

LEFT JOIN gamificacao_prestigio_historico gph
ON gph.pessoa_id = p.id

WHERE p.id = ?
AND p.status='ativo'

GROUP BY p.id
LIMIT 1
");

$stmt->execute([$pessoa_id]);
$pessoa = $stmt->fetch(PDO::FETCH_ASSOC);

$xpTotal = (int)($pessoa['xp_total'] ?? 0);
$xpHoje  = (int)($pessoa['xp_hoje'] ?? 0);

/* =====================================================
   VARIÁVEIS DERIVADAS DO USUÁRIO
===================================================== */

$nomeCompleto = trim($pessoa['nome'] ?? '');
$nomeExibicao = '';

if (($pessoa['chamar_por'] ?? '') === 'apelido' && !empty($pessoa['apelido'])) {

    $nomeExibicao = trim($pessoa['apelido']);

} else {

    $partes = preg_split('/\s+/', $nomeCompleto);
    $nomeExibicao = implode(' ', array_slice($partes, 0, 2));

}

if ($nomeExibicao === '') {
    $nomeExibicao = 'Usuário';
}

$precisaInstagram = empty($pessoa['instagram_username']);

/* =====================================================
    PENDÊNCIAS
===================================================== */

$stmt = $pdo->prepare("
SELECT

7 AS total_max,

(
    (CASE WHEN IFNULL(mpe.curtiu,0)=0 THEN 1 ELSE 0 END) +
    (CASE WHEN IFNULL(mpe.comentou,0)=0 THEN 1 ELSE 0 END) +
    (CASE WHEN IFNULL(mpe.compartilhou,0) < 5 THEN 5 - IFNULL(mpe.compartilhou,0) ELSE 0 END)
) AS total_pendentes

FROM meta_posts mp

LEFT JOIN meta_post_execucoes mpe
    ON mpe.post_id = mp.id
    AND mpe.pessoa_id = :pessoa

WHERE mp.ativo = 'sim'

ORDER BY mp.publicado_em DESC
LIMIT 1
");

$stmt->execute([':pessoa' => $pessoa_id]);

$res = $stmt->fetch(PDO::FETCH_ASSOC);

$totalPendentes = (int)($res['total_pendentes'] ?? 0);
$totalMax = (int)($res['total_max'] ?? 0);

/* Progresso geral */
$progressoMissao = $totalMax > 0
    ? min(100, (int)round((($totalMax - $totalPendentes) / $totalMax) * 100))
    : 0;
/* =====================================================
    CONTROLE MISSÃO META
===================================================== */

$execucaoMeta = null;

if (!empty($missoes['missao_dia'])) {

    $postMissao = $missoes['missao_dia'];

    $stmt = $pdo->prepare("
        SELECT *
        FROM meta_post_execucoes
        WHERE pessoa_id = ?
        AND post_id = ?
        LIMIT 1
    ");
    $stmt->execute([$pessoa_id, $postMissao['id']]);
    $execucaoMeta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$execucaoMeta) {
        $pdo->prepare("
            INSERT INTO meta_post_execucoes
            (pessoa_id, post_id, curtiu, comentou, compartilhou, xp_ganho, criado_em, atualizado_em)
            VALUES (?, ?, 0, 0, 0, 0, NOW(), NOW())
        ")->execute([$pessoa_id, $postMissao['id']]);

        $stmt->execute([$pessoa_id, $postMissao['id']]);
        $execucaoMeta = $stmt->fetch(PDO::FETCH_ASSOC);
    }

// valida comentário real
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM meta_comentarios
    WHERE pessoa_id = ?
    AND instagram_media_id = ?
    AND ativo = 'sim'
");
$stmt->execute([$pessoa_id, $postMissao['instagram_media_id']]);
$comentariosCount = (int)$stmt->fetchColumn();

if (
    $comentariosCount >= 1
    && is_array($execucaoMeta)
    && (int)($execucaoMeta['comentou'] ?? 0) === 0
) {

    // 🔒 atualiza apenas se ainda não recebeu XP
    $stmtUpdate = $pdo->prepare("
       UPDATE meta_post_execucoes
SET comentou = 1,
    xp_ganho = xp_ganho + 45,
    atualizado_em = NOW()
WHERE pessoa_id = ?
AND post_id = ?
AND comentou = 0
    ");

    $stmtUpdate->execute([
        $pessoa_id,
        $postMissao['id']
    ]);

    // só continua se realmente atualizou
    if ($stmtUpdate->rowCount() > 0) {

        // registra XP na temporada sem duplicar
       // 1️⃣ XP da temporada
$pdo->prepare("
INSERT IGNORE INTO gamificacao_pontos_temporada
(
    temporada_id,
    pessoa_id,
    origem_tipo,
    conversao_id,
    pontos_base,
    multiplicador,
    pontos_final,
    criado_em
)
VALUES (?, ?, 'evento_social', ?, 45, 1.00, 45, NOW())
")->execute([
    $temporadaId,
    $pessoa_id,
    $postMissao['id']
]);

// 2️⃣ XP vitalício
$pdo->prepare("
INSERT INTO gamificacao_prestigio_historico
(pessoa_id,pontos_vitalicios,origem,criado_em)
VALUES (?,?,?,NOW())
")->execute([
    $pessoa_id,
    45,
    'comentario_instagram'
]);

        $execucaoMeta['comentou'] = 1;

        // evento visual
        $pdo->prepare("
            INSERT INTO gamificacao_estado_usuario (pessoa_id, chave, valor)
            VALUES (?, 'comentario_validado', '1')
            ON DUPLICATE KEY UPDATE valor='1'
        ")->execute([$pessoa_id]);

    }
}

} // 🔥 FECHA if (!empty($missoes['missao_dia']))

/* =====================================================
   6️⃣ DETECTAR SUBIDA NO RANKING
===================================================== */

$stmt = $pdo->prepare("
    SELECT posicao_seq
    FROM vw_ranking_geral_seq
    WHERE pessoa_id=?
    LIMIT 1
");
$stmt->execute([$pessoa_id]);
$posicaoAtual = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT valor
    FROM gamificacao_estado_usuario
    WHERE pessoa_id=? AND chave='ultima_posicao'
    LIMIT 1
");
$stmt->execute([$pessoa_id]);
$posicaoAnterior = $stmt->fetchColumn();

$subiuRanking = false;
if ($posicaoAnterior !== false && $posicaoAtual > 0) {
    if ($posicaoAtual < (int)$posicaoAnterior) {
        $subiuRanking = true;
    }
}

$pdo->prepare("
    INSERT INTO gamificacao_estado_usuario (pessoa_id, chave, valor)
    VALUES (?, 'ultima_posicao', ?)
    ON DUPLICATE KEY UPDATE valor=VALUES(valor)
")->execute([$pessoa_id, $posicaoAtual]);

/* =====================================================
   7️⃣ MOTOR DE EVENTOS + CENTRO DE NOTIFICAÇÕES (PERSISTENTE)
   ✅ regra nova:
   - se NÃO tem instagram: mostrar APENAS card de cadastrar instagram
   - se tem instagram: NÃO mostra esse card, mostra só mensagens
===================================================== */

$eventos = [];

// Carrega/garante estado JSON do Centro de Notificações
$stmt = $pdo->prepare("
    SELECT valor
    FROM gamificacao_estado_usuario
    WHERE pessoa_id=? AND chave='notificacoes_centro'
    LIMIT 1
");
$stmt->execute([$pessoa_id]);
$estadoCentroRaw = $stmt->fetchColumn();

$estadoCentro = [
    'last_id' => 0,
    'items' => []
];

if ($estadoCentroRaw) {
    $tmp = json_decode((string)$estadoCentroRaw, true);
    if (is_array($tmp)) {
        $estadoCentro = array_merge($estadoCentro, $tmp);
        if (!isset($estadoCentro['items']) || !is_array($estadoCentro['items'])) $estadoCentro['items'] = [];
        if (!isset($estadoCentro['last_id'])) $estadoCentro['last_id'] = 0;
    }
}

$nowIso = (new DateTime())->format('Y-m-d H:i:s');

$pushNotificacao = function(array $n) use (&$estadoCentro, $nowIso) {
    $estadoCentro['last_id'] = (int)$estadoCentro['last_id'] + 1;
    $id = (int)$estadoCentro['last_id'];

    $item = [
        'id' => $id,
        'key' => $n['key'] ?? ('k_'.$id),
        'title' => (string)($n['title'] ?? ''),
        'body' => (string)($n['body'] ?? ''),
        'cta_label' => (string)($n['cta_label'] ?? ''),
        'cta_url' => (string)($n['cta_url'] ?? ''),
        'type' => (string)($n['type'] ?? 'info'),
        'created_at' => $n['created_at'] ?? $nowIso,
        'read' => (bool)($n['read'] ?? false),
        'dismissed' => (bool)($n['dismissed'] ?? false),
        // payload extra opcional (para modal etc.)
        'payload' => $n['payload'] ?? null,
    ];

    array_unshift($estadoCentro['items'], $item);
    $estadoCentro['items'] = array_slice($estadoCentro['items'], 0, 25);
    return $item;
};

$upsertNotificacao = function(string $key, array $n) use (&$estadoCentro, $pushNotificacao, $nowIso) {
    foreach ($estadoCentro['items'] as &$it) {
        if (($it['key'] ?? '') === $key && empty($it['dismissed'])) {
            $it['title'] = (string)($n['title'] ?? $it['title']);
            $it['body'] = (string)($n['body'] ?? $it['body']);
            $it['cta_label'] = (string)($n['cta_label'] ?? $it['cta_label']);
            $it['cta_url'] = (string)($n['cta_url'] ?? $it['cta_url']);
            $it['type'] = (string)($n['type'] ?? $it['type']);
            $it['created_at'] = $n['created_at'] ?? $nowIso;
            $it['payload'] = $n['payload'] ?? ($it['payload'] ?? null);
            $it['read'] = false;
            return $it;
        }
    }
    unset($it);

    $n['key'] = $key;
    return $pushNotificacao($n);
};

$toast = null;

/* ================================
   ✅ 7.A) SE PRECISA INSTAGRAM => SÓ ESSE CARD
================================== */
if ($precisaInstagram) {

    // limpa outros cards visíveis (mantém histórico, mas marca como dismissed para sumir agora)
    foreach ($estadoCentro['items'] as &$it) {
        if (($it['key'] ?? '') !== 'instagram_required') {
            // não destrói pra sempre: só “some” do feed do app (pode reativar no futuro)
            $it['dismissed'] = true;
        }
    }
    unset($it);

    $eventos[] = [
        'tipo' => 'instagram_required',
        'prioridade' => 999,
        'hero' => 'intro',
        'duracao' => 5000
    ];

    $item = $upsertNotificacao('instagram_required', [
        'title' => 'CADASTRAR MEU INSTAGRAM',
        'body'  => 'Para liberar missões de curtir, comentar e compartilhar você precisa cadastrar o @ do seu Instagram.',
        'cta_label' => 'QUERO PARTICIPAR AGORA!',
        'cta_url'   => '#vincularInstagram',
        'type'      => 'warning',
        'payload'   => [
            'needsInstagram' => true
        ]
    ]);

    // toast (só esse)
    $toast = [
        'id' => $item['id'],
        'type' => 'warning',
        'title' => 'CADASTRAR MEU INSTAGRAM',
        'body'  => 'Cadastre seu @ para liberar as missões.',
        'cta_label' => 'Participar agora',
        'cta_url' => '#vincularInstagram',
        'duracao' => 5000
    ];

} else {

    /* ================================
       ✅ 7.B) TEM INSTAGRAM => SÓ MENSAGENS
       (não cria/mostra card de IG)
    ================================== */

    // se existir card antigo de IG, “some”
    foreach ($estadoCentro['items'] as &$it) {
        if (($it['key'] ?? '') === 'instagram_required') {
            $it['dismissed'] = true;
        }
    }
    unset($it);

    // Comentário validado (flag 1x)
    $stmt = $pdo->prepare("
        SELECT valor
        FROM gamificacao_estado_usuario
        WHERE pessoa_id=? AND chave='comentario_validado'
        LIMIT 1
    ");
    $stmt->execute([$pessoa_id]);
    $comentarioValidado = $stmt->fetchColumn();

    if ($comentarioValidado === '1') {
        $eventos[] = [
            'tipo' => 'comentario_validado',
            'prioridade' => 120,
            'hero' => 'happy',
            'duracao' => 5000
        ];

        $item = $pushNotificacao([
            'key' => 'comentario_validado_'.$nowIso,
            'title' => '💬 Comentário validado!',
            'body' => 'Ebaa! +45 XP caiu na sua conta. A Teresa ficou feliz 😄',
            'cta_label' => 'Ver ranking',
            'cta_url' => '/comunidade/ranking.php',
            'type' => 'success',
        ]);

        $toast = [
            'id' => $item['id'],
            'type' => 'success',
            'title' => '💬 Comentário validado!',
            'body'  => '+45 XP liberados agora.',
            'cta_label' => 'Abrir ranking',
            'cta_url' => '/comunidade/ranking.php',
            'duracao' => 5000
        ];

        $pdo->prepare("
            UPDATE gamificacao_estado_usuario
            SET valor='0'
            WHERE pessoa_id=? AND chave='comentario_validado'
        ")->execute([$pessoa_id]);
    }

    // Pendência alta => mensagem (não “card de ações”, é card de aviso)
    if ($totalPendentes >= 5) {
        $eventos[] = [
            'tipo' => 'pendencia_alta',
            'prioridade' => 50,
            'hero' => 'bad',
            'duracao' => 5000
        ];

        $bodyPend = "Poxa… você tem {$totalPendentes} pendências 😬 A Teresa fica triste quando a gente para. Bora compartilhar agora?";
        $item = $upsertNotificacao('mensagem_pendencias', [
            'title' => '😟 Muitas pendências',
            'body' => $bodyPend,
            'cta_label' => 'Vamos compartilhar!',
            'cta_url' => $ctaUrl,
            'type' => 'warning',
        ]);

        if ($toast === null) {
            $toast = [
                'id' => $item['id'],
                'type' => 'warning',
                'title' => '😟 Muitas pendências',
                'body'  => "Você tem {$totalPendentes} pendências.",
                'cta_label' => 'Fazer agora',
                'cta_url' => $ctaUrl,
                'duracao' => 5000
            ];
        }
    }

    if ($subiuRanking === true) {
        $eventos[] = [
            'tipo' => 'ranking_up',
            'prioridade' => 100,
            'hero' => 'happy',
            'duracao' => 5000
        ];

        $item = $pushNotificacao([
            'key' => 'ranking_up_'.$nowIso,
            'title' => '🏆 Você subiu no ranking!',
            'body' => 'Aêê! Isso é mérito seu. Vamos comemorar com mais uma missão? 🎉',
            'cta_label' => 'Ver ranking',
            'cta_url' => '/comunidade/ranking.php',
            'type' => 'success',
        ]);

        if ($toast === null) {
            $toast = [
                'id' => $item['id'],
                'type' => 'success',
                'title' => '🏆 Subiu no ranking!',
                'body'  => 'Boa! Continua que dá pra subir mais.',
                'cta_label' => 'Abrir ranking',
                'cta_url' => '/comunidade/ranking.php',
                'duracao' => 5000
            ];
        }
    }

    if ($dispararEventoBackend === 'primeiro_acesso') {
        $eventos[] = [
            'tipo' => 'intro',
            'prioridade' => 40,
            'hero' => 'intro',
            'duracao' => 5000
        ];

        $item = $pushNotificacao([
            'key' => 'primeiro_acesso',
            'title' => '✨ Bem-vindo!',
            'body' => 'Sua primeira missão te dá impulso de XP. Bora começar?',
            'cta_label' => 'Começar agora',
            'cta_url' => $ctaUrl,
            'type' => 'info',
        ]);

        if ($toast === null) {
            $toast = [
                'id' => $item['id'],
                'type' => 'info',
                'title' => '✨ Bem-vindo!',
                'body'  => 'Vamos fazer sua primeira missão?',
                'cta_label' => 'Começar',
                'cta_url' => $ctaUrl,
                'duracao' => 5000
            ];
        }
    }
}

usort($eventos, fn($a,$b) => $b['prioridade'] <=> $a['prioridade']);
$eventoAtivo = $eventos[0] ?? ['tipo'=>'default','hero'=>'dom','duracao'=>0];

// Persiste o centro
$pdo->prepare("
    INSERT INTO gamificacao_estado_usuario (pessoa_id, chave, valor)
    VALUES (?, 'notificacoes_centro', ?)
    ON DUPLICATE KEY UPDATE valor=VALUES(valor)
")->execute([$pessoa_id, json_encode($estadoCentro, JSON_UNESCAPED_UNICODE)]);

// Conta não-lidas (não descartadas)
$naoLidas = 0;
foreach ($estadoCentro['items'] as $it) {
    if (empty($it['dismissed']) && empty($it['read'])) $naoLidas++;
}

/* =====================================================
   8️⃣ SAUDAÇÃO
===================================================== */

$hora=(int)date('H');
$saudacao=$hora<12?'Bom dia':($hora<18?'Boa tarde':'Boa noite');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rede Social</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<link rel="preload" as="image" href="/assets/anime/animado-happy.webp">
<link rel="preload" as="image" href="/assets/anime/animado-bad.webp">
<link rel="preload" as="image" href="/assets/anime/animado-intro.webp">
<link rel="preload" as="image" href="/assets/anime/dom-static.webp">

<style>
body{background:#f4f6f8;font-family:system-ui;margin:0}
.header{
  background:linear-gradient(135deg,#0b6e7a,#169fa9);
  color:#fff;padding:18px 18px 30px;
  border-radius:0 0 32px 32px;
}
.hero{
  margin:-80px 16px 8px;
  border-radius:24px;
  overflow:hidden;
  position:relative;
}
.hero img{
  width:100%;height:320px;
  object-fit:cover;object-position:center top;
}

/* ===== TOAST PILL (mais “central”, sem pegar no rosto) ===== */
.toast-pill{
  position:absolute;
  left:12px; right:12px;
  top:200px;               /* ✅ desce para não ficar nos olhos */
  z-index:5;
  display:none;
  align-items:center;
  gap:10px;
  padding:12px 12px;
  border-radius:999px;
  background:rgba(255,255,255,.78);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  box-shadow:0 12px 26px rgba(0,0,0,.20);
  border:1px solid rgba(255,255,255,.55);
  transform: translateY(-8px);
  opacity:0;
}
.toast-pill.show{
  display:flex;
  animation:toastIn .22s ease forwards;
}
@keyframes toastIn{ to{transform:translateY(0);opacity:1} }

.toast-icon{
  width:34px;height:34px;border-radius:999px;
  display:flex;align-items:center;justify-content:center;
  font-size:16px;font-weight:900;
  box-shadow:0 6px 14px rgba(0,0,0,.12);
  border:1px solid rgba(0,0,0,.06);
}
.toast-body{flex:1;min-width:0}
.toast-title{
  font-size:13px;font-weight:900;line-height:1.1;
  color:#123;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.toast-text{
  font-size:12px;opacity:.82;line-height:1.1;margin-top:2px;
  color:#123;
  display:-webkit-box;
  -webkit-line-clamp:1;
  -webkit-box-orient:vertical;
  overflow:hidden;
}
.toast-actions{display:flex;gap:8px;align-items:center;flex:0 0 auto}
.toast-btn{
  border:0;border-radius:999px;
  padding:8px 12px;font-size:12px;font-weight:900;
  cursor:pointer;
}
.toast-btn.primary{background:#0b6e7a;color:#fff}
.toast-close{
  width:28px;height:28px;border-radius:999px;
  border:0;background:rgba(0,0,0,.06);
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;
}
.toast-progress{
  position:absolute;
  left:14px; right:14px;
  bottom:6px;
  height:3px;border-radius:999px;
  background:rgba(0,0,0,.08);
  overflow:hidden;
}
.toast-progress > div{
  height:100%;
  width:100%;
  transform-origin:left center;
  transform:scaleX(1);
}
.toast-pill.type-success .toast-icon{background:rgba(37,211,102,.18);color:#128c7e}
.toast-pill.type-warning .toast-icon{background:rgba(255,193,7,.22);color:#8a6d00}
.toast-pill.type-info .toast-icon{background:rgba(22,159,169,.18);color:#0b6e7a}
.toast-pill.type-success .toast-progress > div{background:rgba(37,211,102,.8)}
.toast-pill.type-warning .toast-progress > div{background:rgba(255,193,7,.9)}
.toast-pill.type-info .toast-progress > div{background:rgba(22,159,169,.85)}
@keyframes progressDown{from{transform:scaleX(1)}to{transform:scaleX(0)}}

/* ===== CENTRO DE NOTIFICAÇÕES ===== */
.notif-wrap{margin:0 16px 12px 16px;}
.notif-head{
  border-radius:18px;
  padding:12px 14px;
  background:linear-gradient(135deg,#0b6e7a,#169fa9);
  color:#fff;
  display:flex;align-items:center;justify-content:space-between;
  box-shadow:0 8px 18px rgba(0,0,0,.08);
}
.notif-badge{
  min-width:26px;height:22px;border-radius:999px;
  background:rgba(255,255,255,.22);
  display:flex;align-items:center;justify-content:center;
  font-weight:900;font-size:12px;
  padding:0 8px;
}
.notif-list{
  background:#fff;
  border-radius:18px;
  padding:10px;
  margin-top:10px;
  box-shadow:0 12px 26px rgba(0,0,0,.10);
}

/* CARD mais “impactante” */
.notif-item{
  border-radius:18px;
  padding:14px 14px;
  display:flex;
  gap:12px;
  align-items:flex-start;
  position:relative;
  overflow:hidden;
  background:linear-gradient(180deg, rgba(255,255,255,.98), rgba(255,255,255,.86));
  border:1px solid rgba(0,0,0,.06);
  box-shadow:0 10px 20px rgba(0,0,0,.06);
}
.notif-item::before{
  content:'';
  position:absolute;left:0;top:0;bottom:0;width:5px;
  background:rgba(22,159,169,.35);
}
.notif-item.unread{
  box-shadow:0 14px 28px rgba(0,0,0,.10);
  border:1px solid rgba(22,159,169,.22);
}
.notif-item.unread::before{background:rgba(43,124,255,.55);}
.notif-item + .notif-item{margin-top:12px}

.notif-dot{
  width:10px;height:10px;border-radius:999px;
  margin-top:6px;
  background:#2b7cff;
  box-shadow:0 0 0 5px rgba(43,124,255,.14);
  flex:0 0 10px;
}
.notif-dot.off{opacity:.22;box-shadow:none}

.notif-type{
  width:40px;height:40px;border-radius:14px;
  display:flex;align-items:center;justify-content:center;
  flex:0 0 40px;
  border:1px solid rgba(0,0,0,.06);
  box-shadow:0 8px 16px rgba(0,0,0,.10);
  font-size:18px;
}
.notif-type.success{background:rgba(37,211,102,.18);color:#128c7e}
.notif-type.warning{background:rgba(255,193,7,.22);color:#8a6d00}
.notif-type.info{background:rgba(22,159,169,.18);color:#0b6e7a}

.notif-main{flex:1;min-width:0}
.notif-topline{
  display:flex;align-items:center;justify-content:space-between;
  gap:10px;
}
.notif-title{
  font-weight:950;color:#102a2f;font-size:14px;line-height:1.15;
}
.notif-new{
  display:none;
  align-items:center;justify-content:center;
  height:20px;border-radius:999px;
  padding:0 10px;
  font-size:11px;font-weight:950;
  background:rgba(43,124,255,.14);
  color:#1b59d8;
  border:1px solid rgba(43,124,255,.20);
}
.notif-item.unread .notif-new{display:flex;}
.notif-body{
  font-size:12px;opacity:.82;margin-top:4px;color:#102a2f;
}
.notif-row{
  display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;align-items:center;
}
.notif-btn{
  border:0;border-radius:999px;
  padding:9px 14px;font-size:12px;font-weight:950;
}
.notif-btn.primary{
  background:linear-gradient(135deg,#0b6e7a,#169fa9);
  color:#fff;
  box-shadow:0 10px 16px rgba(11,110,122,.18);
}
.notif-btn.ghost{
  background:rgba(0,0,0,.06);
  color:#243b40;
}
.notif-meta{
  font-size:11px;opacity:.55;margin-top:8px
}
.notif-chevron{
  position:absolute;right:12px;top:14px;
  width:30px;height:30px;border-radius:999px;
  background:rgba(0,0,0,.05);
  display:flex;align-items:center;justify-content:center;
  opacity:.7;
}

/* ===== MODAIS (tutorial + vincular) ===== */
.modal-backdrop-elab{
  position:fixed;inset:0;
  background:rgba(0,0,0,.55);
  display:none;
  align-items:flex-end;
  z-index:9999;
}
.modal-backdrop-elab.show{display:flex;}
.elab-modal{
  width:100%;
  background:#fff;
  border-radius:18px 18px 0 0;
  padding:14px 14px 16px 14px;
  box-shadow:0 -18px 40px rgba(0,0,0,.22);
  max-height:86vh;
  overflow:auto;
}
.elab-modal h5{margin:0;font-weight:950}
.elab-modal .sub{opacity:.7;font-size:12px;margin-top:4px}
.elab-modal .rowbtn{display:flex;gap:10px;margin-top:14px;flex-wrap:wrap}
.elab-input{
  width:100%;
  border:1px solid rgba(0,0,0,.12);
  border-radius:14px;
  padding:12px 12px;
  font-weight:800;
}
.yt-wrap{
  margin-top:12px;
  border-radius:16px;
  overflow:hidden;
  background:#000;
  aspect-ratio: 16/9;
}
.yt-wrap iframe{width:100%;height:100%;border:0;display:block}

/* ===== MISSÃO ===== */
.missao-post-card{
  background:linear-gradient(180deg,#ffffff,#f7fbfc);
  border-radius:22px;
  padding:16px;
  margin-bottom:22px;
  box-shadow:0 18px 40px rgba(0,0,0,.12);
  border:1px solid rgba(0,0,0,.05);
  position:relative;
  overflow:hidden;
}

.missao-post-card::after{
  content:'';
  position:absolute;
  top:0;left:0;right:0;height:6px;
  background:linear-gradient(90deg,#FFD700,#FF9800,#25d366);
}

.missao-top{
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:12px;
}

.missao-xp{
  font-weight:900;
  font-size:18px;
  color:#ff9800;
  text-shadow:0 2px 6px rgba(255,152,0,.3);
}

.missao-progresso-label{
  font-size:12px;
  font-weight:700;
  background:#eef7f9;
  padding:6px 12px;
  border-radius:999px;
}

.missao-img{
  width:100%;
  border-radius:18px;
  margin-bottom:12px;
}

.missao-caption{
  font-size:14px;
  font-weight:800;
  margin-bottom:12px;
}

.missao-progress-bar{
  height:12px;
  background:#e9eef1;
  border-radius:999px;
  overflow:hidden;
  margin-bottom:14px;
}

.missao-progress-bar div{
  height:100%;
  background:linear-gradient(90deg,#FFD700,#FFB300);
  box-shadow:0 0 12px rgba(255,193,7,.6);
  transition:width .4s ease;
}

.missao-acoes{
  font-size:13px;
  margin-bottom:14px;
}

.acao-item{
  margin-bottom:6px;
  font-weight:700;
  padding:6px 10px;
  border-radius:999px;
  background:#f3f6f8;
  display:inline-block;
}

.acao-item.done{
  background:#e6f9f0;
  color:#25d366;
}

.missao-botoes{
  display:flex;
  flex-direction:column;
  gap:10px;
}

.btn-missao{
  border-radius:999px;
  padding:12px;
  text-align:center;
  font-weight:900;
  text-decoration:none;
  font-size:14px;
  transition:.2s;
}

.btn-missao.primary{
  background:linear-gradient(135deg,#0b6e7a,#169fa9);
  color:#fff;
  box-shadow:0 10px 20px rgba(11,110,122,.3);
}

.btn-missao.primary:hover{
  transform:scale(1.03);
}

.btn-missao.whatsapp{
  background:#25d366;
  color:#fff;
  box-shadow:0 10px 20px rgba(37,211,102,.35);
}

.btn-missao.whatsapp:hover{
  transform:scale(1.03);
}

.missao-post-card.destaque{
  border:2px solid #FFD700;
  box-shadow:0 20px 45px rgba(255,193,7,.25);
}

.missao-badge-dia{
  position:absolute;
  top:-10px;
  left:16px;
  background:linear-gradient(135deg,#FFD700,#FF9800);
  color:#000;
  font-weight:900;
  font-size:11px;
  padding:6px 12px;
  border-radius:999px;
}

.missao-post-card.extra{
  background:#f7fbfc;
}

.missao-img{
  width:100%;
  border-radius:18px;
  margin-bottom:12px;
}

.missao-caption{
  font-size:14px;
  font-weight:800;
  margin-bottom:14px;
}

.missao-acoes.simples{
  font-size:13px;
  font-weight:700;
  margin-bottom:14px;
  opacity:.8;
}

.btn-missao{
  display:block;
  text-align:center;
  padding:12px;
  border-radius:999px;
  font-weight:900;
  text-decoration:none;
  margin-bottom:10px;
}

.btn-missao.primary{
  background:linear-gradient(135deg,#0b6e7a,#169fa9);
  color:#fff;
}

.btn-missao.grande{
  font-size:15px;
  padding:14px;
}

.btn-missao.whatsapp{
  background:#25d366;
  color:#fff;
}

.btn-missao.ver-todas{
  margin-top:25px;
  background:#111;
  color:#fff;
}

.btn-missao.disabled {
  background: #cfd8dc !important;
  color: #7b8a8f !important;
  box-shadow: none !important;
  pointer-events: none;
  cursor: not-allowed;
  opacity: .7;
  filter: grayscale(40%);
}

@keyframes bellRing {
  0%   { transform: rotate(0); }
  5%   { transform: rotate(-18deg); }
  10%  { transform: rotate(15deg); }
  15%  { transform: rotate(-12deg); }
  20%  { transform: rotate(8deg); }
  25%  { transform: rotate(-4deg); }
  30%  { transform: rotate(0); }
  100% { transform: rotate(0); }
}
.sino{animation: bellRing 3.5s infinite;transform-origin: top center;}

.footer-nav{
  position:fixed;bottom:0;left:0;right:0;
  background:#fff;border-top:1px solid #ddd;
  display:flex;justify-content:space-around;
  padding:10px 0;
}
</style>
</head>

<body>

<div class="header">
  <div class="d-flex justify-content-between">
    <div>
      <h5><?=htmlspecialchars($saudacao)?></h5>
      <strong><?=htmlspecialchars($nomeExibicao)?></strong>
    </div>

    <div class="text-end position-relative">
      
      <!--  VITALICIO TOTAL -->
     <div 
  id="xpDisplay"
  data-xp="<?= (int)$xpTotal ?>"
  style="font-size:22px;font-weight:800">

  <?= number_format($xpTotal,0,',','.') ?> XP

</div>

<?php if($xpHoje > 0): ?>
<div style="font-size:12px;opacity:.9;font-weight:700">
🔥 +<?= number_format($xpHoje,0,',','.') ?> XP hoje
</div>
<?php endif; ?>

      <!-- RANKING -->
      <div style="font-size:12px;opacity:.85">
  Você está em<br> 
  <strong>
    <?= $posicaoAtual > 0 ? ''.number_format($posicaoAtual,0,',','.') : '--' ?> no Ranking
  </strong>
</div>

    </div>
  </div>
</div>

<div class="hero">
  <img id="heroImg" src="/assets/anime/dom-static.webp" alt="Hero">

  <div id="toastPill" class="toast-pill">
    <div id="toastLead" class="toast-icon"><i class="bi bi-bell-fill"></i></div>

    <div class="toast-body">
      <div id="toastTitle" class="toast-title">Notificação</div>
      <div id="toastText" class="toast-text"></div>
    </div>

    <div class="toast-actions">
      <a id="toastCta" class="toast-btn primary text-decoration-none" href="#">Abrir</a>
      <button id="toastClose" class="toast-close" aria-label="Fechar"><i class="bi bi-x"></i></button>
    </div>

    <div class="toast-progress"><div id="toastBar"></div></div>
  </div>
</div>

<div class="notif-wrap" id="notifWrap">
  <div class="notif-head">
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-bell"></i>
      <strong>Notificações</strong>
    </div>
    <div class="notif-badge" id="notifBadge"><?= (int)$naoLidas ?></div>
  </div>

  <div class="notif-list" id="notifList"></div>
</div>



<div style="margin:0 16px 90px 16px">

  <h6 style="font-weight:900;margin-bottom:12px">🎯 MISSÃO PRINCIPAL</h6>

  <?php if(!empty($missoes['missao_dia'])): 
      $post = $missoes['missao_dia'];
  ?>

<div id="cardMissaoPrincipal" class="missao-post-card destaque">

    
      <?php if(!empty($post['media_url'])): ?>
          <img src="<?=htmlspecialchars($post['media_url'])?>" class="missao-img">
      <?php endif; ?>

      <div class="missao-caption">
          <?=htmlspecialchars(mb_strimwidth($post['caption'],0,120,'...'))?>
      </div>

      <?php
$engajou = (int)($execucaoMeta['curtiu'] ?? 0) === 1;
$comentouMeta = (int)($execucaoMeta['comentou'] ?? 0) === 1;
$compartilhouMeta = (int)($execucaoMeta['compartilhou'] ?? 0);

$missaoConcluida = (
    $engajou &&
    $comentouMeta &&
    $compartilhouMeta >= 5
);

/* =====================================================
   🔒 BLOQUEIO DE MISSÃO (3h)
===================================================== */

$missaoBloqueada = false;

if ($missaoConcluida && !empty($execucaoMeta['concluida_em'])) {

    $fimBloqueio = strtotime($execucaoMeta['concluida_em']) + (3 * 3600);

    if (time() < $fimBloqueio) {
        $missaoBloqueada = true;
    }
}
?>

<div class="missao-acoes">

    <span class="acao-item <?= $engajou ? 'done' : '' ?>">
        <?= $engajou ? 'ENGAJAMENTO FEITO' : 'ENGAJAR' ?>
    </span><br>

    <span class="acao-item <?= $comentouMeta ? 'done' : '' ?>">
        <?= $comentouMeta ? 'COMENTÁRIO FEITO' : 'COMENTAR NO INSTAGRAM' ?>
    </span><br>

   <span class="acao-item <?= ($compartilhouMeta >= 5) ? 'done' : '' ?>">
    <?= ($compartilhouMeta >= 5)
        ? 'COMPARTILHAMENTO FEITO'
        : "COMPARTILHAR NO WHATSAPP ({$compartilhouMeta}/5)" ?>
</span>

<?php if($missaoConcluida): ?>
    <div style="margin-top:10px;font-size:12px;font-weight:900;color:#25d366">
        ✅ Missão concluída com sucesso
    </div>
<?php endif; ?>

</div>

      <div class="missao-botoes">

   <?php if($precisaInstagram): ?>

    <a href="#vincularInstagram"
       class="btn-missao primary grande disabled"
       onclick="event.preventDefault(); openModal('modalVincular');">
       Vincule seu Instagram para liberar
    </a>

<?php else: ?>

    <a href="<?=htmlspecialchars($post['permalink'])?>"
       target="_blank"
       class="btn-missao primary grande"
       onclick="handleInstagramClick(event, <?= (int)$post['id']?>)">
       IR CUMPRIR MISSÃO
    </a>

<?php endif; ?>

          <?php
          $waText = rawurlencode("Olha esse post 🔥\n\n".$post['permalink']);
          $waUrl  = "https://wa.me/?text=".$waText;
          ?>

      <?php if($precisaInstagram): ?>

    <a href="#vincularInstagram"
       class="btn-missao whatsapp disabled"
       onclick="event.preventDefault(); openModal('modalVincular');">
       Vincule seu Instagram para liberar
    </a>

<?php else: ?>

    <a href="<?=$waUrl?>"
       target="_blank"
       class="btn-missao whatsapp"
       onclick="handleWhatsappClick(event, <?= (int)$post['id']?>)">
       Compartilhar no WhatsApp
    </a>

<?php endif; ?>

      </div>

  </div>

  <?php else: ?>

      <div class="missao-concluida">
          🎉 Você já concluiu todas as missões do dia!
      </div>

  <?php endif; ?>


  <?php if(!empty($missoes['missao_extra'])): 
      $post = $missoes['missao_extra'];
  ?>

  <h6 style="font-weight:900;margin:26px 0 12px 0">🔥 Missão Extra</h6>

  <div class="missao-post-card extra">

      <?php if(!empty($post['media_url'])): ?>
          <img src="<?=htmlspecialchars($post['media_url'])?>" class="missao-img">
      <?php endif; ?>

      <div class="missao-caption">
          <?=htmlspecialchars(mb_strimwidth($post['caption'],0,100,'...'))?>
      </div>

      <div class="missao-botoes">

      <?php if($precisaInstagram): ?>

    <a href="#vincularInstagram"
       class="btn-missao primary disabled"
       onclick="event.preventDefault(); openModal('modalVincular');">
       Vincule seu Instagram para liberar
    </a>

<?php else: ?>

    <a href="<?=htmlspecialchars($post['permalink'])?>"
       target="_blank"
       class="btn-missao primary">
       Realizar Missão Extra
    </a>

<?php endif; ?>

      </div>

  </div>

  <?php endif; ?>

</div>

<div class="footer-nav">
  <a href="#"><i class="bi bi-house"></i><br>Início</a>
  <a href="#"><i class="bi bi-trophy"></i><br>Ranking</a>
  <a href="#"><i class="bi bi-person-circle"></i><br>Perfil</a>
  <a href="/publico/logout.php"><i class="bi bi-box-arrow-right"></i><br>Sair</a>
</div>

<!-- ✅ MODAL: Tutorial -->
<div id="modalTutorial" class="modal-backdrop-elab" role="dialog" aria-modal="true">
  <div class="elab-modal">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h5>Como ver meu usuário do Instagram</h5>
        <div class="sub">Tutorial rápido pra você achar seu @ certinho.</div>
      </div>
      <button class="toast-close" type="button" onclick="closeModal('modalTutorial')"><i class="bi bi-x"></i></button>
    </div>

    <div class="yt-wrap">
      <iframe
        src="https://www.youtube-nocookie.com/embed/<?=htmlspecialchars(IG_TUTORIAL_YOUTUBE_ID)?>"
        title="Tutorial Instagram"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
        allowfullscreen></iframe>
    </div>

    <div class="rowbtn">
      <button class="toast-btn primary" type="button" onclick="closeModal('modalTutorial')">Fechar</button>
    </div>
  </div>
</div>

<!-- ✅ MODAL: Vincular Instagram -->
<div id="modalVincular" class="modal-backdrop-elab" role="dialog" aria-modal="true">
  <div class="elab-modal">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h5>Vincular Instagram</h5>
        <div class="sub">Digite seu @ (somente usuário, sem link).</div>
      </div>
      <button class="toast-close" type="button" onclick="closeModal('modalVincular')"><i class="bi bi-x"></i></button>
    </div>

    <form method="post" action="<?=htmlspecialchars($_SERVER['PHP_SELF'])?>" style="margin-top:12px">
      <input type="hidden" name="acao" value="vincular_instagram">
      <input class="elab-input" name="instagram_username" placeholder="@seuusuario" autocomplete="off" required>
      <div class="rowbtn">
        <button class="toast-btn primary" type="submit">VINCULAR INSTAGRAM</button>
        <button class="toast-btn" type="button" style="background:rgba(0,0,0,.06);color:#243b40" onclick="closeModal('modalVincular')">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<script>
const EVENTO = <?= json_encode($eventoAtivo) ?>;
const TOAST  = <?= json_encode($toast) ?>;
const CENTRO = <?= json_encode($estadoCentro) ?>;
const PRECISA_IG = <?= json_encode($precisaInstagram) ?>;

const hero = document.getElementById("heroImg");
const HERO_STATIC = "/assets/anime/dom-static.webp";
const HERO_ANIM = (nome)=>"/assets/anime/animado-"+nome+".webp";

function setHeroEvento(evt){
  if(evt && evt.hero && evt.hero !== 'dom'){
    hero.src = HERO_ANIM(evt.hero);
    const dur = (evt.duracao && evt.duracao > 0) ? evt.duracao : 5000;
    setTimeout(()=>{ hero.src = HERO_STATIC; }, dur);
  } else {
    hero.src = HERO_STATIC;
  }
}
setHeroEvento(EVENTO);

/* ===== Toast pill ===== */
const toastEl = document.getElementById("toastPill");
const toastLead = document.getElementById("toastLead");
const toastTitle = document.getElementById("toastTitle");
const toastText = document.getElementById("toastText");
const toastCta = document.getElementById("toastCta");
const toastClose = document.getElementById("toastClose");
const toastBar = document.getElementById("toastBar");
let toastTimer = null;

function toastIconByType(type){
  if(type === 'success') return '<i class="bi bi-check2-circle"></i>';
  if(type === 'warning') return '<i class="bi bi-exclamation-triangle"></i>';
  return '<i class="bi bi-info-circle"></i>';
}
function showToast(t){
  if(!t) return;

  const type = (t.type || 'info');
  toastEl.classList.remove('type-success','type-warning','type-info');
  toastEl.classList.add('type-'+type);

  toastLead.className = "toast-icon";
  toastLead.innerHTML = toastIconByType(type);

  toastTitle.textContent = t.title || 'Notificação';
  toastText.textContent = t.body || '';

  toastCta.textContent = t.cta_label || 'Abrir';
  toastCta.href = t.cta_url || '#';

  const dur = (t.duracao && t.duracao > 0) ? t.duracao : 5000;

  toastBar.style.animation = 'none';
  void toastBar.offsetWidth;
  toastBar.style.animation = `progressDown ${dur}ms linear forwards`;

  toastEl.classList.add("show");

  if (toastTimer) clearTimeout(toastTimer);
  toastTimer = setTimeout(()=>hideToast(), dur);
}
function hideToast(){
  toastEl.classList.remove("show");
  if (toastTimer) clearTimeout(toastTimer);
  toastTimer = null;
}
(function enableToastSwipe(){
  let startX = 0, currentX = 0, dragging = false;

  const onStart = (e)=>{
    if(!toastEl.classList.contains('show')) return;
    dragging = true;
    startX = (e.touches ? e.touches[0].clientX : e.clientX);
    currentX = startX;
    toastEl.style.transition = 'none';
  };
  const onMove = (e)=>{
    if(!dragging) return;
    currentX = (e.touches ? e.touches[0].clientX : e.clientX);
    const dx = currentX - startX;
    toastEl.style.transform = `translateY(0) translateX(${dx}px)`;
    toastEl.style.opacity = String(Math.max(0.2, 1 - Math.abs(dx)/240));
  };
  const onEnd = ()=>{
    if(!dragging) return;
    dragging = false;
    const dx = currentX - startX;
    toastEl.style.transition = 'transform .18s ease, opacity .18s ease';

    if(Math.abs(dx) > 90){
      hideToast();
      toastEl.style.transform = `translateY(0) translateX(${dx > 0 ? 220 : -220}px)`;
      toastEl.style.opacity = '0';
      setTimeout(()=>{
        toastEl.style.transition = '';
        toastEl.style.transform = 'translateY(-8px)';
        toastEl.style.opacity = '';
      }, 220);
    } else {
      toastEl.style.transform = 'translateY(0) translateX(0)';
      toastEl.style.opacity = '1';
      setTimeout(()=>{ toastEl.style.transition = ''; }, 220);
    }
  };

  toastEl.addEventListener('touchstart', onStart, {passive:true});
  toastEl.addEventListener('touchmove', onMove, {passive:true});
  toastEl.addEventListener('touchend', onEnd);

  toastEl.addEventListener('mousedown', onStart);
  window.addEventListener('mousemove', onMove);
  window.addEventListener('mouseup', onEnd);
})();
toastClose.addEventListener("click", (e)=>{ e.preventDefault(); hideToast(); });

/* ===== Centro de Notificações ===== */
const notifList = document.getElementById("notifList");
const notifBadge = document.getElementById("notifBadge");

function fmtTime(ts){
  if(!ts) return '';
  try{
    const d = new Date(ts.replace(' ', 'T'));
    if(isNaN(d.getTime())) return '';
    const hh = String(d.getHours()).padStart(2,'0');
    const mm = String(d.getMinutes()).padStart(2,'0');
    return `${hh}:${mm}`;
  }catch(e){ return ''; }
}
function iconClassByType(type){
  if(type === 'success') return 'success';
  if(type === 'warning') return 'warning';
  return 'info';
}
function iconHtmlByType(type){
  if(type === 'success') return '<i class="bi bi-check2-circle"></i>';
  if(type === 'warning') return '<i class="bi bi-exclamation-triangle"></i>';
  return '<i class="bi bi-info-circle"></i>';
}
function countUnread(items){
  let n = 0;
  for(const it of items){
    if(!it.dismissed && !it.read) n++;
  }
  return n;
}
function escapeHtml(str){
  return String(str)
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'",'&#039;');
}

/* ===== Modais helpers ===== */
function openModal(id){
  const el = document.getElementById(id);
  if(el) el.classList.add('show');
}
function closeModal(id){
  const el = document.getElementById(id);
  if(el) el.classList.remove('show');
}
// fecha clicando fora
['modalTutorial','modalVincular'].forEach(id=>{
  const el = document.getElementById(id);
  if(!el) return;
  el.addEventListener('click', (e)=>{
    if(e.target === el) closeModal(id);
  });
});

// intercepta âncoras especiais do CTA (#vincularInstagram / #tutorialInstagram)
function handleSpecialCta(href){
  if(!href) return false;
  if(href === '#vincularInstagram'){
    openModal('modalVincular');
    return true;
  }
  if(href === '#tutorialInstagram'){
    openModal('modalTutorial');
    return true;
  }
  return false;
}

function renderCentro(){
  let items = (CENTRO.items || []).filter(it => !it.dismissed);

  // ✅ regra: se precisa IG, mostra só o card de IG
  if(PRECISA_IG){
    items = items.filter(it => (it.key || '') === 'instagram_required');
  } else {
    // se tem IG, não mostra esse card
    items = items.filter(it => (it.key || '') !== 'instagram_required');
  }

  const unread = countUnread(items);
  notifBadge.textContent = String(unread);

  if(items.length === 0){
    notifList.innerHTML = `
      <div class="text-center" style="padding:14px;opacity:.65">
        Sem notificações por enquanto.
      </div>
    `;
    return;
  }

  notifList.innerHTML = items.slice(0,6).map(it=>{
    const dotClass = it.read ? 'off' : '';
    const unreadClass = it.read ? '' : 'unread';
    const tclass = iconClassByType(it.type);
    const time = fmtTime(it.created_at);

    // ✅ card IG especial (com tutorial + modal)
    if((it.key || '') === 'instagram_required'){
      return `
        <div class="notif-item ${unreadClass}" data-id="${it.id}">
          <div class="notif-dot ${dotClass}"></div>
          <div class="notif-type warning"><i class="bi bi-instagram"></i></div>

          <div class="notif-main">
            <div class="notif-topline">
              <div class="notif-title">${escapeHtml(it.title || '')}</div>
              <div class="notif-new">NOVO</div>
            </div>

            <div class="notif-body">
              ${escapeHtml(it.body || '')}
              <div style="margin-top:10px;font-weight:900;opacity:.85">
                Veja o tutorial aqui —
                <a href="#tutorialInstagram" class="text-decoration-none" style="font-weight:950;color:#0b6e7a">Como ver o usuário do meu Instagram</a>
              </div>
            </div>

            <div class="notif-row">
              <button class="notif-btn primary" data-act="vincular">QUERO PARTICIPAR AGORA!</button>
                          </div>

            <div class="notif-meta">${time ? `Hoje ${time}` : ''}</div>
          </div>

          <div class="notif-chevron"><i class="bi bi-chevron-right"></i></div>
        </div>
      `;
    }

    const cta = (it.cta_url && it.cta_label) ? `
      <a class="notif-btn primary text-decoration-none" href="${it.cta_url}">${escapeHtml(it.cta_label)}</a>
    ` : '';

    return `
      <div class="notif-item ${unreadClass}" data-id="${it.id}">
        <div class="notif-dot ${dotClass}"></div>
        <div class="notif-type ${tclass}">${iconHtmlByType(it.type)}</div>

        <div class="notif-main">
          <div class="notif-topline">
            <div class="notif-title">${escapeHtml(it.title || '')}</div>
            <div class="notif-new">NOVO</div>
          </div>

          <div class="notif-body">${escapeHtml(it.body || '')}</div>

          <div class="notif-row">
            ${cta}
            <button class="notif-btn ghost" data-act="dismiss">Dispensar</button>
          </div>

          <div class="notif-meta">${time ? `Hoje ${time}` : ''}</div>
        </div>

        <div class="notif-chevron"><i class="bi bi-chevron-right"></i></div>
      </div>
    `;
  }).join('');

  // ações
  notifList.querySelectorAll('.notif-item').forEach(el=>{
    el.addEventListener('click', (ev)=>{
      const btn = ev.target?.closest?.('button');
      const a = ev.target?.closest?.('a');

      // CTA especial tutorial
      if(a && handleSpecialCta(a.getAttribute('href'))){
        ev.preventDefault();
        ev.stopPropagation();
        return;
      }

      // botões
      if(btn){
        const act = btn.dataset.act;
        if(act === 'dismiss'){
          ev.preventDefault();
          ev.stopPropagation();
          dismissNotifEl(el);
          return;
        }
        if(act === 'vincular'){
          ev.preventDefault();
          ev.stopPropagation();
          openModal('modalVincular');
          return;
        }
      }

      // marca como lida (UI)
      const id = Number(el.dataset.id);
      const it = (CENTRO.items || []).find(x => Number(x.id) === id);
      if(it){ it.read = true; renderCentro(); }
    });
  });

  enableSwipeToDismissList();
}

function dismissNotifEl(el){
  const id = Number(el.dataset.id);
  el.style.transition = 'transform .18s ease, opacity .18s ease';
  el.style.transform = 'translateX(-220px)';
  el.style.opacity = '0';
  setTimeout(()=>{
    const it = (CENTRO.items || []).find(x => Number(x.id) === id);
    if(it){ it.dismissed = true; }
    renderCentro();
  }, 200);
}

function enableSwipeToDismissList(){
  notifList.querySelectorAll('.notif-item').forEach(el=>{
    let sx=0,cx=0,drag=false;

    const start=(e)=>{
      drag=true;
      sx=(e.touches?e.touches[0].clientX:e.clientX);
      cx=sx;
      el.style.transition='none';
    };
    const move=(e)=>{
      if(!drag) return;
      cx=(e.touches?e.touches[0].clientX:e.clientX);
      const dx=cx-sx;
      const clamped = Math.min(0, dx);
      el.style.transform=`translateX(${clamped}px)`;
      el.style.opacity=String(Math.max(0.25, 1 - Math.abs(clamped)/240));
    };
    const end=()=>{
      if(!drag) return;
      drag=false;
      const dx=cx-sx;
      el.style.transition='transform .18s ease, opacity .18s ease';
      if(dx < -90){
        dismissNotifEl(el);
      } else {
        el.style.transform='translateX(0)';
        el.style.opacity='1';
      }
      setTimeout(()=>{ el.style.transition=''; }, 220);
    };

    el.addEventListener('touchstart', start, {passive:true});
    el.addEventListener('touchmove', move, {passive:true});
    el.addEventListener('touchend', end);

    el.addEventListener('mousedown', start);
    window.addEventListener('mousemove', move);
    window.addEventListener('mouseup', end);
  });
}

renderCentro();

/* ===== mostra toast 1x ===== */
if(TOAST){
  // CTA pode abrir modal
  const originalHref = TOAST.cta_url || '#';
  showToast(TOAST);

  // intercepta clique do CTA do toast para abrir modal
  toastCta.addEventListener('click', (e)=>{
    const href = toastCta.getAttribute('href');
    if(handleSpecialCta(href)){
      e.preventDefault();
      e.stopPropagation();
    }
  }, {once:false});
}

/* ===== patch: se evento, volta ao static depois de 5s ===== */
if(EVENTO && EVENTO.hero && EVENTO.hero !== 'dom'){
  const dur = (EVENTO.duracao && EVENTO.duracao > 0) ? EVENTO.duracao : 5000;
  setTimeout(()=>{ hero.src = HERO_STATIC; }, dur);
}

function atualizarUI(data){

    if(data.curtiu === 1){
        document.querySelectorAll('.acao-item')[0].classList.add('done');
        document.querySelectorAll('.acao-item')[0].innerText = 'ENGAJAMENTO FEITO';
    }

    // 🔥 ADICIONE ESTE BLOCO
    if(data.comentou === 1){
        document.querySelectorAll('.acao-item')[1].classList.add('done');
        document.querySelectorAll('.acao-item')[1].innerText = 'COMENTÁRIO FEITO';
    }

    document.querySelectorAll('.acao-item')[2].innerText =
        data.compartilhou >= 5
        ? 'COMPARTILHAMENTO FEITO'
        : `COMPARTILHAR NO WHATSAPP (${data.compartilhou}/5)`;

    if(data.compartilhou >= 5){
        document.querySelectorAll('.acao-item')[2].classList.add('done');
    }

    // ✅ AQUI SIM
    if(data.missaoCompleta){

        const card = document.getElementById('cardMissaoPrincipal');

        if(card){
            card.innerHTML = `
                <div style="text-align:center;padding:20px">
                    <h4 style="font-weight:900;margin-bottom:10px">
                        🎉 MISSÃO CONCLUÍDA!
                    </h4>

                    <div style="font-weight:700;margin-bottom:18px">
                        XP já liberado com sucesso 🚀
                    </div>

                    <a href="/game/index.php"
                       class="btn-missao primary grande">
                       COMPLETAR MAIS MISSÕES
                    </a>
                </div>
            `;
        }

        showToast({
            type:'success',
            title:'🔥 MISSÃO CONCLUÍDA!',
            body:'Missão do dia finalizada com sucesso!',
            cta_label:'Ver ranking',
            cta_url:'/comunidade/ranking.php',
            duracao:6000
        });

        setHeroEvento({hero:'happy',duracao:6000});
    }
}
function handleInstagramClick(e, postId){
    // deixa o link abrir normalmente
    registrarAcaoMeta(postId,'engajar');
}

function handleWhatsappClick(e, postId){
    registrarAcaoMeta(postId,'compartilhar');
}

function registrarAcaoMeta(postId, tipo){

    // aguarda 5 segundos para validar que a pessoa realmente saiu
    setTimeout(()=>{
        fetch('/dashboard/registrar_execucao_meta.php',{
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`post_id=${postId}&tipo=${tipo}`
        })
        .then(r=>r.json())
        .then(d=>{
            if(d.ok){
                atualizarUI(d);
                    atualizarXP(d.xpGanho); // 🔥 ADICIONAR ESTA LINHA
            }
        });
    },5000);
}

function atualizarXP(xpGanho){

    if(!xpGanho || xpGanho <= 0) return;

    const xpEl = document.getElementById('xpDisplay');
if(!xpEl) return;

    const xpInicial = parseInt(xpEl.dataset.xp || 0);
    const xpFinal   = xpInicial + xpGanho;

    const duracao = 800;
    const inicio = performance.now();

    function animar(now){

        const progresso = Math.min((now - inicio) / duracao, 1);

        const valorAtual = Math.floor(
            xpInicial + (xpFinal - xpInicial) * progresso
        );

        xpEl.innerText = valorAtual.toLocaleString('pt-BR') + ' XP';

        if(progresso < 1){
            requestAnimationFrame(animar);
        } else {
            xpEl.dataset.xp = xpFinal;
        }
    }

    requestAnimationFrame(animar);

    // efeito visual
    xpEl.style.transform = 'scale(1.15)';
    xpEl.style.transition = 'transform .2s ease';

    setTimeout(()=>{
        xpEl.style.transform = 'scale(1)';
    },200);
}
  
</script>

</body>
</html>