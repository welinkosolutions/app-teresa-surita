<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once '/home/elab/public_html/core/sessao/app.php';
require_once '/home/elab/public_html/core/data/config.php';
require_once '/home/elab/public_html/core/data/data.php';

$inviteEnginePath = '/home/elab/public_html/core/invite/engine.php';
if (is_file($inviteEnginePath)) {
    require_once $inviteEnginePath;
}

function out(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function tabela_existe(PDO $pdo, string $tabela): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$tabela]);

    return (int) $stmt->fetchColumn() > 0;
}

function coluna_existe(PDO $pdo, string $tabela, string $coluna): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$tabela, $coluna]);

    return (int) $stmt->fetchColumn() > 0;
}

try {
    if (empty($_SESSION['pessoa_id'])) {
        out(['ok' => false, 'error' => 'unauthorized'], 401);
    }

    $pdo = dbRoraima();
    $pessoaId = (int) $_SESSION['pessoa_id'];

    $canal = trim((string) ($_POST['canal'] ?? $_GET['canal'] ?? 'painel_missao'));
    if ($canal === '') {
        $canal = 'painel_missao';
    }

    $linkConvitePublico = '';
    $codigoConvitePublico = '';

    if (function_exists('inviteObterOuCriarLinkPublico')) {
        $linkPublicoConvite = inviteObterOuCriarLinkPublico($pdo, $pessoaId);
        $codigoConvitePublico = trim((string) ($linkPublicoConvite['codigo_convite_publico'] ?? ''));
        $linkConvitePublico = trim((string) ($linkPublicoConvite['url_curta'] ?? ''));

        if ($linkConvitePublico === '' && $codigoConvitePublico !== '') {
            $linkConvitePublico = 'https://app.elab.social/i/' . rawurlencode($codigoConvitePublico);
        }
    }

    if ($linkConvitePublico === '') {
        out([
            'ok' => false,
            'error' => 'invite_link_not_available',
        ], 422);
    }

    if (tabela_existe($pdo, 'convites_compartilhamentos')) {
        $cols = [
            'pessoa_id' => $pessoaId,
            'canal' => $canal,
        ];

        if (coluna_existe($pdo, 'convites_compartilhamentos', 'codigo_convite_publico')) {
            $cols['codigo_convite_publico'] = $codigoConvitePublico;
        }

        if (coluna_existe($pdo, 'convites_compartilhamentos', 'url')) {
            $cols['url'] = $linkConvitePublico;
        } elseif (coluna_existe($pdo, 'convites_compartilhamentos', 'url_curta')) {
            $cols['url_curta'] = $linkConvitePublico;
        } elseif (coluna_existe($pdo, 'convites_compartilhamentos', 'link')) {
            $cols['link'] = $linkConvitePublico;
        }

        if (coluna_existe($pdo, 'convites_compartilhamentos', 'origem')) {
            $cols['origem'] = 'missao_painel';
        }

        if (coluna_existe($pdo, 'convites_compartilhamentos', 'user_agent')) {
            $cols['user_agent'] = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        }

        if (coluna_existe($pdo, 'convites_compartilhamentos', 'ip')) {
            $cols['ip'] = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 80);
        }

        if (coluna_existe($pdo, 'convites_compartilhamentos', 'criado_em')) {
            $cols['criado_em'] = date('Y-m-d H:i:s');
        }

        $campos = array_keys($cols);
        $sql = "
            INSERT INTO convites_compartilhamentos (" . implode(', ', array_map(static fn($c) => "`{$c}`", $campos)) . ")
            VALUES (" . implode(', ', array_fill(0, count($campos), '?')) . ")
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($cols));
    }

    out([
        'ok' => true,
        'url' => $linkConvitePublico,
        'codigo' => $codigoConvitePublico,
        'canal' => $canal,
    ]);
} catch (Throwable $e) {
    error_log('[convite-gerar] ' . $e->getMessage());

    out([
        'ok' => false,
        'error' => 'server_error',
    ], 500);
}
