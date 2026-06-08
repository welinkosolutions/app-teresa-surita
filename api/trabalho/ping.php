<?php
/**
 * ============================================================
 * CAMINHO: app.elab.social/api/trabalho/ping.php
 * NOME: Ping de Trabalho – V4 (REAL / MULTIMODAL)
 * DESCRIÇÃO:
 * - Telemetria real (a pé / moto / carro)
 * - Anti-ruído GPS
 * - Velocidade física correta
 * - Detecção real de deslocamento
 * - Inatividade verdadeira
 * - Encerramento automático
 * ============================================================
 */

declare(strict_types=1);

/* ================= CONFIG ================= */
ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('America/Boa_Vista');
header('Content-Type: application/json; charset=utf-8');

/* ================= LOG ================= */
$LOG = '/home/elab/logs/trabalho-ping-error.log';
set_exception_handler(function (Throwable $e) use ($LOG) {
    file_put_contents(
        $LOG,
        date('Y-m-d H:i:s') . " | {$e->getMessage()} | {$e->getFile()}:{$e->getLine()}\n",
        FILE_APPEND
    );
    http_response_code(500);
    exit;
});

/* ================= SESSION ================= */
session_name('ELAB_APP_SESSION');
session_start();

if (empty($_SESSION['pessoa_id']) || empty($_SESSION['device_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'unauthorized']);
    exit;
}

$pessoa_id = (int) $_SESSION['pessoa_id'];
$device_id = $_SESSION['device_id'];

/* ================= INPUT ================= */
$input = json_decode(file_get_contents('php://input'), true);

$lat  = $input['latitude']  ?? null;
$lng  = $input['longitude'] ?? null;
$prec = (int) ($input['precisao'] ?? 999);
$origem = $input['origem'] ?? 'gps';

if (!is_numeric($lat) || !is_numeric($lng)) {
    echo json_encode(['status' => 'coords_invalidas']);
    exit;
}

/* GPS lixo não entra */
if ($prec > 50) {
    echo json_encode(['status' => 'precisao_ruim']);
    exit;
}

/* ================= CORE ================= */
$CORE = '/home/elab/public_html/core';
require_once $CORE . '/data/config.php';
require_once $CORE . '/data/data.php';
$pdo = dbRoraima();

/* ================= SESSÃO ATIVA ================= */
$stmt = $pdo->prepare("
    SELECT id, ultimo_ping
    FROM trabalho_sessoes
    WHERE pessoa_id = ?
      AND device_id = ?
      AND status = 'ativo'
    LIMIT 1
");
$stmt->execute([$pessoa_id, $device_id]);
$sessao = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sessao) {
    echo json_encode(['status' => 'sessao_inexistente']);
    exit;
}

$sessao_id = (int) $sessao['id'];

/* ================= TIMEOUT TOTAL (60 MIN) ================= */
if ($sessao['ultimo_ping']) {
    $min = (int) $pdo->query("
        SELECT TIMESTAMPDIFF(MINUTE, '{$sessao['ultimo_ping']}', NOW())
    ")->fetchColumn();

    if ($min >= 60) {
        $pdo->prepare("
            UPDATE trabalho_sessoes
            SET status='encerrado', fim_em=NOW(), encerrada_motivo='timeout'
            WHERE id=?
        ")->execute([$sessao_id]);

        echo json_encode(['status' => 'sessao_encerrada']);
        exit;
    }
}

/* ================= ÚLTIMO PONTO ================= */
$stmt = $pdo->prepare("
    SELECT latitude, longitude, registrado_em
    FROM trabalho_rastro
    WHERE trabalho_sessao_id = ?
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$sessao_id]);
$ultimo = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= DISTÂNCIA (HAVERSINE) ================= */
function metros(float $la1, float $lo1, float $la2, float $lo2): float {
    $R = 6371000;
    $dLat = deg2rad($la2 - $la1);
    $dLon = deg2rad($lo2 - $lo1);
    $a = sin($dLat/2)**2 +
         cos(deg2rad($la1)) * cos(deg2rad($la2)) *
         sin($dLon/2)**2;
    return $R * (2 * atan2(sqrt($a), sqrt(1 - $a)));
}

$dist = 0.0;
$vel  = 0.0;
$mov  = 'parado';
$tipo = 'parado';

if ($ultimo) {
    $dist = metros(
        (float)$ultimo['latitude'],
        (float)$ultimo['longitude'],
        (float)$lat,
        (float)$lng
    );

    $seg = max(1, time() - strtotime($ultimo['registrado_em']));
    $vel = ($dist / $seg) * 3.6; // km/h

    /* CLASSIFICAÇÃO REAL */
    if ($vel >= 0.8 && $vel < 6) {
        $tipo = 'a_pe';
        if ($dist >= 5) $mov = 'deslocando';
    }
    elseif ($vel >= 6 && $vel < 35) {
        $tipo = 'moto';
        if ($dist >= 15) $mov = 'deslocando';
    }
    elseif ($vel >= 35) {
        $tipo = 'carro';
        if ($dist >= 25) $mov = 'deslocando';
    }
}

/* ================= REGISTRA RASTRO ================= */
$pdo->prepare("
    INSERT INTO trabalho_rastro
        (trabalho_sessao_id, pessoa_id, latitude, longitude, precisao,
         movimento, velocidade, distancia_metros, origem)
    VALUES (?,?,?,?,?,?,?,?,?)
")->execute([
    $sessao_id,
    $pessoa_id,
    $lat,
    $lng,
    $prec,
    $mov,
    round($vel, 2),
    round($dist, 2),
    $origem
]);

/* ================= ATUALIZA PESSOA ================= */
if ($mov === 'deslocando') {
    $pdo->prepare("
        UPDATE pessoas
        SET
            latitude=?,
            longitude=?,
            ultimo_ping=NOW(),
            ultimo_movimento=NOW(),
            ultima_velocidade=?
        WHERE id=?
    ")->execute([$lat, $lng, round($vel,2), $pessoa_id]);
} else {
    $pdo->prepare("
        UPDATE pessoas
        SET
            latitude=?,
            longitude=?,
            ultimo_ping=NOW()
        WHERE id=?
    ")->execute([$lat, $lng, $pessoa_id]);
}

/* ================= ATUALIZA SESSÃO ================= */
$pdo->prepare("
    UPDATE trabalho_sessoes
    SET
        ultimo_ping=NOW(),
        ultimo_lat=?,
        ultimo_lng=?,
        ultimo_movimento=?,
        ultima_velocidade=?
    WHERE id=?
")->execute([$lat, $lng, $mov, round($vel,2), $sessao_id]);

/* ================= INATIVIDADE REAL (30 MIN SEM DESLOCAR) ================= */
$stmt = $pdo->prepare("
    SELECT TIMESTAMPDIFF(
        MINUTE,
        COALESCE(ultimo_movimento, ultimo_ping),
        NOW()
    )
    FROM pessoas
    WHERE id=?
");
$stmt->execute([$pessoa_id]);
$minInativo = (int)$stmt->fetchColumn();

if ($minInativo >= 30) {
    $pdo->prepare("
        INSERT IGNORE INTO dev_alerts
            (tipo, codigo, mensagem, valor_atual)
        VALUES
            ('atencao','TRABALHO_INATIVO','Sem deslocamento > 30min',?)
    ")->execute([$pessoa_id]);
}

/* ================= RESPONSE ================= */
echo json_encode([
    'status'        => 'ok',
    'movimento'     => $mov,
    'tipo'          => $tipo,
    'velocidade_km' => round($vel,2),
    'distancia_m'   => round($dist,2)
]);
exit;