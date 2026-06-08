<?php
declare(strict_types=1);

/**
 * ======================================================
 * CAMINHO: /home/elab/app.elab.social/comunidade/social.php
 * NOME: Comunidade / Feed Social V2 Clean
 *
 * DESCRIÇÃO:
 * - Feed social gamificado inspirado no Duolingo
 * - Header simples: "Comunidade"
 * - Abas: Amigos, Geral, Novidades
 * - Primeira aba aberta: Geral
 * - Consome APIs:
 *   /api/feed/amigos.php
 *   /api/feed/todos.php
 *   /api/feed/novos.php
 * - Registra interações em:
 *   /api/feed/interagir.php
 * - Comentários sociais usam CTA "Responder agora"
 * - Post oficial com título/subtítulo separados
 * - Botões sociais disparam confete
 * - Infinite scroll seguro até limite por sessão
 * - Se uma página posterior falhar, encerra paginação sem poluir a tela
 * - Integrado ao footer fixo V2
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

if (empty($_SESSION['pessoa_id'])) {
    header('Location: /index.php');
    exit;
}

$pdo = dbRoraima();
$pessoaId = (int) $_SESSION['pessoa_id'];

$stmt = $pdo->prepare("
    SELECT
        id,
        nome,
        apelido,
        chamar_por,
        status,
        perfil
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

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Comunidade | elab.social</title>
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

        body.comunidade-page-body {
            margin: 0;
            background: #ffffff;
            color: #172033;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        button,
        a {
            font: inherit;
        }

        .comunidade-page {
            width: 100%;
            max-width: 520px;
            min-height: 100vh;
            margin: 0 auto;
            padding-bottom: 116px;
            background: #ffffff;
        }

        .comunidade-header {
            position: sticky;
            top: 0;
            z-index: 40;
            padding: calc(22px + env(safe-area-inset-top)) 22px 14px;
            background: rgba(255, 255, 255, .96);
            border-bottom: 1px solid #edf0f4;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        .comunidade-header h1 {
            margin: 0;
            color: #3f4652;
            font-size: 29px;
            line-height: 1;
            font-weight: 950;
            letter-spacing: -0.045em;
        }

        .comunidade-header p {
            margin: 8px 0 0;
            color: #8b95a5;
            font-size: 13px;
            line-height: 1.35;
            font-weight: 750;
        }

        .comunidade-tabs-wrap {
            position: sticky;
            top: calc(86px + env(safe-area-inset-top));
            z-index: 35;
            padding: 12px 22px 10px;
            background: rgba(255, 255, 255, .96);
            border-bottom: 1px solid rgba(237, 240, 244, .72);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        .comunidade-tabs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 5px;
            padding: 5px;
            border-radius: 22px;
            background: #f5f7fa;
            border: 1px solid #edf0f4;
        }

        .comunidade-tab {
            appearance: none;
            min-height: 40px;
            border: 0;
            border-radius: 17px;
            background: transparent;
            color: #8b95a5;
            font-size: 13px;
            line-height: 1;
            font-weight: 950;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
        }

        .comunidade-tab.is-active {
            background: #ffffff;
            color: #16a34a;
            box-shadow: 0 5px 14px rgba(15, 23, 42, .08);
        }

        .comunidade-tab:active {
            transform: scale(.97);
        }

        .comunidade-content {
            padding: 14px 22px 0;
        }

        .comunidade-feed-info {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin: 2px 0 8px;
        }

        .comunidade-feed-info h2 {
            margin: 0;
            color: #172033;
            font-size: 16px;
            line-height: 1;
            font-weight: 950;
            letter-spacing: -0.02em;
        }

        .comunidade-feed-info p {
            margin: 5px 0 0;
            color: #8b95a5;
            font-size: 12px;
            line-height: 1.3;
            font-weight: 750;
        }

        .comunidade-feed-pill {
            min-height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            padding: 0 10px;
            border-radius: 999px;
            background: #fff7d6;
            color: #9a6700;
            font-size: 11px;
            font-weight: 950;
            white-space: nowrap;
        }

        .comunidade-feed {
            position: relative;
        }

        .feed-item,
        .official-card {
            opacity: 0;
            transform: translateY(10px);
            animation: feedItemIn .28s ease forwards;
        }

        .feed-item {
            position: relative;
            padding: 20px 0 20px;
            border-bottom: 1px solid #edf0f4;
        }

        .feed-item:last-child {
            border-bottom: 0;
        }

        .feed-body-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .feed-body-main {
            min-width: 0;
            flex: 1;
        }

        .feed-person-head {
            display: flex;
            align-items: flex-start;
            gap: 11px;
        }

        .feed-avatar {
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 999px;
            background: #f1f5f9;
            color: #172033;
            font-size: 20px;
            font-weight: 950;
            overflow: hidden;
        }

        .feed-avatar.is-social {
            background: #faf5ff;
            color: #7e22ce;
        }

        .feed-avatar.is-game {
            background: #fff7d6;
            color: #9a6700;
        }

        .feed-avatar img {
            width: 74%;
            height: 74%;
            object-fit: contain;
            display: block;
        }

        .feed-avatar-fallback {
            display: grid;
            place-items: center;
            width: 100%;
            height: 100%;
        }

        .feed-head-main {
            min-width: 0;
            flex: 1;
            padding-top: 1px;
        }

        .feed-person-line {
            color: #475569;
            font-size: 14px;
            line-height: 1.2;
            font-weight: 850;
        }

        .feed-person-line strong {
            color: #172033;
            font-weight: 950;
        }

        .feed-time {
            margin-top: 4px;
            color: #9aa4b2;
            font-size: 13px;
            line-height: 1.1;
            font-weight: 750;
        }

        .feed-title {
            margin: 12px 0 0;
            color: #334155;
            font-size: 21px;
            line-height: 1.32;
            font-weight: 550;
            letter-spacing: -0.02em;
        }

        .feed-title strong {
            color: #172033;
            font-weight: 900;
        }

        .feed-side-art {
            width: 92px;
            min-height: 92px;
            display: grid;
            place-items: center;
            flex: 0 0 92px;
            margin-left: 10px;
        }

        .feed-side-art img {
            max-width: 88px;
            max-height: 88px;
            object-fit: contain;
            display: block;
            animation: sideArtFloat 2.4s ease-in-out infinite;
        }

        .feed-reward-row {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 12px;
        }

        .feed-reward {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            min-height: 28px;
            padding: 0 10px;
            border-radius: 999px;
            background: #fff7d6;
            color: #9a6700;
            font-size: 12px;
            line-height: 1;
            font-weight: 950;
        }

        .feed-reward.is-xp {
            background: #eafbea;
            color: #166534;
        }

        .feed-actions,
        .official-actions {
            display: flex;
            align-items: center;
            gap: 11px;
            margin-top: 15px;
        }

        .feed-cta {
            appearance: none;
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 17px;
            border-radius: 16px;
            border: 2px solid #dfe5ed;
            background: #ffffff;
            color: #3f4652;
            font-size: 13px;
            line-height: 1;
            font-weight: 950;
            text-decoration: none;
            letter-spacing: .02em;
            cursor: pointer;
            box-shadow: 0 4px 0 #d8dee7;
            -webkit-tap-highlight-color: transparent;
        }

        .feed-cta.is-primary {
            border-color: transparent;
            background: linear-gradient(180deg, #25d366 0%, #16a34a 100%);
            color: #ffffff;
            box-shadow: 0 5px 0 #0f7f38, 0 10px 18px rgba(22, 163, 74, .16);
        }

        .feed-cta.is-small {
            min-height: 42px;
            padding: 0 14px;
            font-size: 12px;
        }

        .feed-cta:active {
            transform: translateY(3px);
            box-shadow: 0 2px 0 #d8dee7;
        }

        .feed-cta.is-primary:active {
            box-shadow: 0 2px 0 #0f7f38;
        }

        .feed-btn-icon {
            width: 21px;
            height: 21px;
            display: block;
            object-fit: contain;
            flex: 0 0 auto;
        }

        .feed-btn-emoji {
            width: 21px;
            height: 21px;
            display: inline-grid;
            place-items: center;
            flex: 0 0 auto;
            font-size: 18px;
            line-height: 1;
        }

        .feed-like {
            appearance: none;
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 13px;
            border-radius: 15px;
            border: 2px solid #e3e7ee;
            background: #ffffff;
            color: #4b5563;
            font-size: 14px;
            line-height: 1;
            font-weight: 950;
            cursor: pointer;
            box-shadow: 0 4px 0 #d8dee7;
            -webkit-tap-highlight-color: transparent;
        }

        .feed-like img {
            width: 24px;
            height: 24px;
            display: block;
            object-fit: contain;
        }

        .feed-like:active {
            transform: translateY(3px);
            box-shadow: 0 2px 0 #d8dee7;
        }

        .feed-like.is-active {
            color: #ef4444;
            border-color: #fecdd3;
            background: #fff1f2;
        }

        .official-card {
            padding: 22px 0 24px;
            border-bottom: 1px solid #edf0f4;
        }

        .official-banner {
            position: relative;
            width: 100%;
            min-height: 132px;
            display: grid;
            place-items: center;
            overflow: hidden;
            border-radius: 24px;
            background:
                radial-gradient(circle at 18% 12%, rgba(255, 255, 255, .32), transparent 30%),
                linear-gradient(135deg, #38dbc7 0%, #17c1b2 100%);
        }

        .official-banner.is-facebook {
            background:
                radial-gradient(circle at 18% 12%, rgba(255, 255, 255, .32), transparent 30%),
                linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        }

        .official-banner.is-instagram {
            background:
                radial-gradient(circle at 18% 12%, rgba(255, 255, 255, .25), transparent 30%),
                linear-gradient(135deg, #7c3aed 0%, #db2777 100%);
        }

        .official-banner.is-tiktok {
            background:
                radial-gradient(circle at 18% 12%, rgba(255, 255, 255, .22), transparent 30%),
                linear-gradient(135deg, #111827 0%, #0f172a 100%);
        }

        .official-banner img {
            max-width: 106px;
            max-height: 106px;
            object-fit: contain;
            animation: sideArtFloat 2.6s ease-in-out infinite;
        }

        .official-banner .official-network-icon {
            position: absolute;
            right: 16px;
            bottom: 16px;
            width: 44px;
            height: 44px;
            padding: 8px;
            border-radius: 999px;
            background: rgba(255,255,255,.9);
            box-shadow: 0 8px 18px rgba(15,23,42,.12);
            animation: none;
        }

        .official-banner-fallback {
            width: 86px;
            height: 86px;
            display: grid;
            place-items: center;
            border-radius: 999px;
            background: rgba(255, 255, 255, .22);
            color: #ffffff;
            font-size: 44px;
            font-weight: 950;
        }

        .official-meta {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-top: 14px;
        }

        .official-label {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 10px;
            border-radius: 9px;
            background: #fff3c4;
            color: #b77900;
            font-size: 12px;
            line-height: 1;
            font-weight: 950;
            letter-spacing: .06em;
        }

        .official-time {
            color: #9aa4b2;
            font-size: 14px;
            font-weight: 800;
        }

        .official-title {
            margin: 10px 0 0;
            color: #3f4652;
            font-size: 24px;
            line-height: 1.18;
            font-weight: 850;
            letter-spacing: -0.03em;
        }

        .official-subtitle {
            margin: 8px 0 0;
            color: #4b5563;
            font-size: 20px;
            line-height: 1.38;
            font-weight: 520;
            letter-spacing: -0.018em;
        }

        .feed-loading {
            display: none;
            padding: 22px 0 26px;
            text-align: center;
        }

        .feed-loading.is-visible {
            display: block;
        }

        .feed-loader {
            width: 52px;
            height: 52px;
            display: grid;
            place-items: center;
            margin: 0 auto 10px;
            border-radius: 999px;
            background: #ffffff;
            border: 2px solid #edf0f4;
            box-shadow: 0 9px 22px rgba(15, 23, 42, .055);
            animation: sideArtFloat 1.2s ease-in-out infinite;
            font-size: 25px;
        }

        .feed-loading p {
            margin: 0;
            color: #8b95a5;
            font-size: 12px;
            line-height: 1.3;
            font-weight: 850;
        }

        .feed-empty {
            display: none;
            padding: 30px 18px;
            text-align: center;
        }

        .feed-empty.is-visible {
            display: block;
        }

        .feed-empty-icon {
            width: 74px;
            height: 74px;
            display: grid;
            place-items: center;
            margin: 0 auto 14px;
            border-radius: 999px;
            background: #eafbea;
            font-size: 34px;
        }

        .feed-empty h3 {
            margin: 0;
            color: #172033;
            font-size: 21px;
            line-height: 1.05;
            font-weight: 950;
            letter-spacing: -0.04em;
        }

        .feed-empty p {
            margin: 8px auto 0;
            max-width: 310px;
            color: #8b95a5;
            font-size: 13px;
            line-height: 1.4;
            font-weight: 750;
        }

        .feed-end {
            display: none;
            padding: 20px 0 26px;
            text-align: center;
        }

        .feed-end.is-visible {
            display: block;
        }

        .feed-end strong {
            display: block;
            color: #596273;
            font-size: 14px;
            font-weight: 950;
        }

        .feed-end span {
            display: block;
            margin-top: 5px;
            color: #9aa4b2;
            font-size: 12px;
            line-height: 1.35;
            font-weight: 750;
        }

        .comunidade-toast {
            position: fixed;
            left: 50%;
            bottom: calc(92px + env(safe-area-inset-bottom));
            z-index: 10060;
            min-width: 220px;
            max-width: 320px;
            padding: 13px 15px;
            border-radius: 18px;
            background: #172033;
            color: #ffffff;
            font-size: 13px;
            line-height: 1.3;
            font-weight: 900;
            text-align: center;
            box-shadow: 0 18px 42px rgba(15, 23, 42, .24);
            transform: translate(-50%, 16px);
            opacity: 0;
            pointer-events: none;
            transition: opacity .18s ease, transform .18s ease;
        }

        .comunidade-toast.is-visible {
            opacity: 1;
            transform: translate(-50%, 0);
        }

        .float-emoji,
        .confetti-piece {
            position: fixed;
            z-index: 10080;
            pointer-events: none;
        }

        .float-emoji {
            font-size: 28px;
            animation: floatEmoji .75s ease forwards;
        }

        .confetti-piece {
            width: 8px;
            height: 12px;
            border-radius: 3px;
            opacity: 0;
            animation: confettiFall 980ms ease-out forwards;
        }

        @keyframes feedItemIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes sideArtFloat {
            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-5px);
            }
        }

        @keyframes floatEmoji {
            0% {
                opacity: 0;
                transform: translateY(0) scale(.8);
            }

            18% {
                opacity: 1;
            }

            100% {
                opacity: 0;
                transform: translateY(-54px) scale(1.15);
            }
        }

        @keyframes confettiFall {
            0% {
                opacity: 0;
                transform: translate3d(0, 0, 0) rotate(0deg) scale(.8);
            }

            12% {
                opacity: 1;
            }

            100% {
                opacity: 0;
                transform: translate3d(var(--confetti-x), var(--confetti-y), 0) rotate(var(--confetti-r)) scale(1);
            }
        }


        /* UX Timeline V2 */
        body.comunidade-page-body {
            background: #f8fafc;
        }

        .comunidade-page {
            background: #f8fafc;
        }

        .comunidade-live {
            margin: 2px 0 16px;
            padding: 14px;
            border-radius: 22px;
            border: 1px solid #dff7e8;
            background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
            box-shadow: 0 10px 24px rgba(15, 23, 42, .045);
        }

        .comunidade-live-title {
            margin: 0 0 10px;
            color: #16a34a;
            font-size: 12px;
            font-weight: 950;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .comunidade-live-row {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 2px;
            scrollbar-width: none;
        }

        .comunidade-live-row::-webkit-scrollbar {
            display: none;
        }

        .comunidade-live-chip {
            min-width: 128px;
            padding: 10px 12px;
            border-radius: 18px;
            background: #ffffff;
            border: 1px solid #e5eaf0;
            box-shadow: 0 6px 14px rgba(15, 23, 42, .045);
        }

        .comunidade-live-chip strong {
            display: block;
            color: #172033;
            font-size: 12px;
            line-height: 1.1;
            font-weight: 950;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .comunidade-live-chip span {
            display: block;
            margin-top: 5px;
            color: #8b95a5;
            font-size: 11px;
            font-weight: 850;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .feed-item {
            margin: 10px 0;
            padding: 16px;
            border: 1px solid #e8edf4;
            border-left: 5px solid #22c55e;
            border-radius: 22px;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .045);
        }

        .feed-item:last-child {
            border-bottom: 1px solid #e8edf4;
        }

        .feed-item.is-reward {
            border-left-color: #f59e0b;
            background: linear-gradient(135deg, #fffaf0 0%, #ffffff 70%);
        }

        .feed-item.is-comment {
            border-left-color: #0ea5e9;
        }

        .feed-item.is-reaction {
            border-left-color: #8b5cf6;
        }

        .feed-item.is-game {
            border-left-color: #22c55e;
        }

        .feed-body-row {
            display: block;
        }

        .feed-side-art {
            display: none !important;
        }

        .feed-avatar {
            width: 56px;
            height: 56px;
            box-shadow: inset 0 0 0 3px rgba(255,255,255,.7);
        }

        .feed-title {
            margin-top: 10px;
            color: #172033;
            font-size: 18px;
            line-height: 1.32;
            font-weight: 650;
        }

        .feed-actions {
            margin-top: 13px;
        }

        .feed-reward-row {
            margin-top: 12px;
        }

        .feed-reward {
            min-height: 30px;
            box-shadow: 0 5px 12px rgba(245, 158, 11, .12);
        }

        .feed-like {
            margin-left: auto;
            min-height: 40px;
        }

        .feed-cta {
            min-height: 40px;
            border-radius: 15px;
        }

        .official-card {
            margin: 10px 0;
            padding: 16px;
            border: 1px solid #e8edf4;
            border-left: 5px solid #16a34a;
            border-radius: 22px;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .045);
        }

        .official-banner {
            min-height: 104px;
            border-radius: 20px;
        }


        /* UX Timeline Compact V3 */
        .comunidade-page {
            max-width: 430px;
        }

        .comunidade-header {
            padding: calc(16px + env(safe-area-inset-top)) 18px 10px;
        }

        .comunidade-header h1 {
            font-size: 24px;
            letter-spacing: -0.04em;
        }

        .comunidade-header p {
            margin-top: 5px;
            font-size: 11px;
        }

        .comunidade-tabs-wrap {
            top: calc(68px + env(safe-area-inset-top));
            padding: 9px 18px;
        }

        .comunidade-tabs {
            border-radius: 18px;
            padding: 4px;
        }

        .comunidade-tab {
            min-height: 34px;
            border-radius: 14px;
            font-size: 11px;
        }

        .comunidade-content {
            padding: 10px 14px 0;
        }

        .comunidade-feed-info {
            margin: 0 4px 8px;
        }

        .comunidade-feed-info h2 {
            font-size: 15px;
        }

        .comunidade-feed-info p {
            margin-top: 3px;
            font-size: 11px;
            line-height: 1.25;
        }

        .comunidade-feed-pill {
            min-height: 24px;
            padding: 0 9px;
            font-size: 10px;
        }

        .comunidade-live {
            margin: 7px 0 10px;
            padding: 10px;
            border-radius: 18px;
        }

        .comunidade-live-title {
            margin-bottom: 8px;
            font-size: 10px;
        }

        .comunidade-live-chip {
            min-width: 118px;
            padding: 8px 10px;
            border-radius: 14px;
        }

        .comunidade-live-chip strong {
            font-size: 11px;
        }

        .comunidade-live-chip span {
            font-size: 10px;
        }

        .feed-item {
            margin: 8px 0;
            padding: 11px 12px;
            border-radius: 18px;
            border-left-width: 4px;
            box-shadow: 0 7px 18px rgba(15, 23, 42, .04);
        }

        .feed-person-head {
            gap: 9px;
            align-items: center;
        }

        .feed-avatar {
            width: 38px;
            height: 38px;
            font-size: 16px;
            flex-basis: 38px;
        }

        .feed-head-main {
            padding-top: 0;
        }

        .feed-person-line {
            font-size: 12px;
            line-height: 1.1;
        }

        .feed-time {
            margin-top: 3px;
            font-size: 11px;
        }

        .feed-title {
            margin-top: 9px;
            font-size: 14px;
            line-height: 1.28;
            font-weight: 850;
            letter-spacing: -0.02em;
        }

        .feed-title strong {
            font-weight: 950;
        }

        .feed-reward-row {
            margin-top: 9px;
            gap: 6px;
        }

        .feed-reward {
            min-height: 22px;
            padding: 0 8px;
            font-size: 10px;
            gap: 4px;
        }

        .feed-actions {
            margin-top: 10px;
            gap: 8px;
        }

        .feed-cta {
            min-height: 34px;
            padding: 0 11px;
            border-radius: 12px;
            border-width: 1px;
            font-size: 10px;
            letter-spacing: 0;
            box-shadow: 0 3px 0 #d8dee7;
        }

        .feed-cta.is-small {
            min-height: 34px;
            padding: 0 11px;
            font-size: 10px;
        }

        .feed-btn-icon,
        .feed-btn-emoji {
            width: 17px;
            height: 17px;
            font-size: 15px;
        }

        .feed-like {
            min-height: 34px;
            padding: 0 10px;
            border-radius: 12px;
            border-width: 1px;
            font-size: 11px;
            box-shadow: 0 3px 0 #d8dee7;
        }

        .feed-like img {
            width: 19px;
            height: 19px;
        }

        .official-card {
            margin: 8px 0;
            padding: 12px;
            border-radius: 18px;
            border-left-width: 4px;
            box-shadow: 0 7px 18px rgba(15, 23, 42, .04);
        }

        .official-banner {
            min-height: 86px;
            border-radius: 16px;
        }

        .official-banner img {
            max-width: 74px;
            max-height: 74px;
        }

        .official-meta {
            margin-top: 10px;
        }

        .official-label {
            min-height: 22px;
            font-size: 10px;
            border-radius: 7px;
        }

        .official-time {
            font-size: 11px;
        }

        .official-title {
            margin-top: 8px;
            font-size: 17px;
            line-height: 1.18;
        }

        .official-subtitle {
            margin-top: 5px;
            font-size: 13px;
            line-height: 1.3;
        }

        .official-actions {
            margin-top: 10px;
            gap: 8px;
        }

        .feed-empty {
            padding: 22px 14px;
        }

        .feed-end {
            padding: 16px 0 22px;
        }

        .feed-loading {
            padding: 16px 0 20px;
        }

        .feed-loader {
            width: 42px;
            height: 42px;
            font-size: 21px;
        }

        .comunidade-toast {
            bottom: calc(86px + env(safe-area-inset-bottom));
            font-size: 12px;
            border-radius: 14px;
        }


        /* UX Timeline Compact V3 */
        .comunidade-page {
            max-width: 430px;
        }

        .comunidade-header {
            padding: calc(16px + env(safe-area-inset-top)) 18px 10px;
        }

        .comunidade-header h1 {
            font-size: 24px;
            letter-spacing: -0.04em;
        }

        .comunidade-header p {
            margin-top: 5px;
            font-size: 11px;
        }

        .comunidade-tabs-wrap {
            top: calc(68px + env(safe-area-inset-top));
            padding: 9px 18px;
        }

        .comunidade-tabs {
            border-radius: 18px;
            padding: 4px;
        }

        .comunidade-tab {
            min-height: 34px;
            border-radius: 14px;
            font-size: 11px;
        }

        .comunidade-content {
            padding: 10px 14px 0;
        }

        .comunidade-feed-info {
            margin: 0 4px 8px;
        }

        .comunidade-feed-info h2 {
            font-size: 15px;
        }

        .comunidade-feed-info p {
            margin-top: 3px;
            font-size: 11px;
            line-height: 1.25;
        }

        .comunidade-feed-pill {
            min-height: 24px;
            padding: 0 9px;
            font-size: 10px;
        }

        .comunidade-live {
            margin: 7px 0 10px;
            padding: 10px;
            border-radius: 18px;
        }

        .comunidade-live-title {
            margin-bottom: 8px;
            font-size: 10px;
        }

        .comunidade-live-chip {
            min-width: 118px;
            padding: 8px 10px;
            border-radius: 14px;
        }

        .comunidade-live-chip strong {
            font-size: 11px;
        }

        .comunidade-live-chip span {
            font-size: 10px;
        }

        .feed-item {
            margin: 8px 0;
            padding: 11px 12px;
            border-radius: 18px;
            border-left-width: 4px;
            box-shadow: 0 7px 18px rgba(15, 23, 42, .04);
        }

        .feed-person-head {
            gap: 9px;
            align-items: center;
        }

        .feed-avatar {
            width: 38px;
            height: 38px;
            font-size: 16px;
            flex-basis: 38px;
        }

        .feed-head-main {
            padding-top: 0;
        }

        .feed-person-line {
            font-size: 12px;
            line-height: 1.1;
        }

        .feed-time {
            margin-top: 3px;
            font-size: 11px;
        }

        .feed-title {
            margin-top: 9px;
            font-size: 14px;
            line-height: 1.28;
            font-weight: 850;
            letter-spacing: -0.02em;
        }

        .feed-title strong {
            font-weight: 950;
        }

        .feed-reward-row {
            margin-top: 9px;
            gap: 6px;
        }

        .feed-reward {
            min-height: 22px;
            padding: 0 8px;
            font-size: 10px;
            gap: 4px;
        }

        .feed-actions {
            margin-top: 10px;
            gap: 8px;
        }

        .feed-cta {
            min-height: 34px;
            padding: 0 11px;
            border-radius: 12px;
            border-width: 1px;
            font-size: 10px;
            letter-spacing: 0;
            box-shadow: 0 3px 0 #d8dee7;
        }

        .feed-cta.is-small {
            min-height: 34px;
            padding: 0 11px;
            font-size: 10px;
        }

        .feed-btn-icon,
        .feed-btn-emoji {
            width: 17px;
            height: 17px;
            font-size: 15px;
        }

        .feed-like {
            min-height: 34px;
            padding: 0 10px;
            border-radius: 12px;
            border-width: 1px;
            font-size: 11px;
            box-shadow: 0 3px 0 #d8dee7;
        }

        .feed-like img {
            width: 19px;
            height: 19px;
        }

        .official-card {
            margin: 8px 0;
            padding: 12px;
            border-radius: 18px;
            border-left-width: 4px;
            box-shadow: 0 7px 18px rgba(15, 23, 42, .04);
        }

        .official-banner {
            min-height: 86px;
            border-radius: 16px;
        }

        .official-banner img {
            max-width: 74px;
            max-height: 74px;
        }

        .official-meta {
            margin-top: 10px;
        }

        .official-label {
            min-height: 22px;
            font-size: 10px;
            border-radius: 7px;
        }

        .official-time {
            font-size: 11px;
        }

        .official-title {
            margin-top: 8px;
            font-size: 17px;
            line-height: 1.18;
        }

        .official-subtitle {
            margin-top: 5px;
            font-size: 13px;
            line-height: 1.3;
        }

        .official-actions {
            margin-top: 10px;
            gap: 8px;
        }

        .feed-empty {
            padding: 22px 14px;
        }

        .feed-end {
            padding: 16px 0 22px;
        }

        .feed-loading {
            padding: 16px 0 20px;
        }

        .feed-loader {
            width: 42px;
            height: 42px;
            font-size: 21px;
        }

        .comunidade-toast {
            bottom: calc(86px + env(safe-area-inset-bottom));
            font-size: 12px;
            border-radius: 14px;
        }

        @media (max-width: 380px) {
            .comunidade-header {
                padding-left: 18px;
                padding-right: 18px;
            }

            .comunidade-tabs-wrap,
            .comunidade-content {
                padding-left: 18px;
                padding-right: 18px;
            }

            .feed-title {
                font-size: 20px;
            }

            .official-title {
                font-size: 22px;
            }

            .official-subtitle {
                font-size: 19px;
            }

            .feed-side-art {
                width: 76px;
                flex-basis: 76px;
            }

            .feed-side-art img {
                max-width: 72px;
                max-height: 72px;
            }

            .feed-actions,
            .official-actions {
                flex-wrap: wrap;
            }
        }
    </style>
</head>

<body class="comunidade-page-body">

<main class="comunidade-page">

    <header class="comunidade-header">
        <h1>Comunidade</h1>
        <p>Veja quem está transformando a rede agora!</p>
    </header>

    <section class="comunidade-tabs-wrap" aria-label="Filtros da comunidade">
        <div class="comunidade-tabs">
            <button type="button" class="comunidade-tab" data-feed="amigos">
                Amigos
            </button>

            <button type="button" class="comunidade-tab is-active" data-feed="todos">
                Geral
            </button>

            <button type="button" class="comunidade-tab" data-feed="novos">
                Novidades
            </button>
        </div>
    </section>

    <section class="comunidade-content">

        <div class="comunidade-feed-info">
            <div>
                <h2 id="feedTitulo">Geral</h2>
                <p id="feedDescricao">A comunidade elab.social não dorme! Confira os últimos destaques.</p>
            </div>

            <div class="comunidade-feed-pill" id="feedPill">
                carregando
            </div>
        </div>

        <div class="comunidade-live" id="comunidadeLive">
            <div class="comunidade-live-title">🔥 Agora na comunidade</div>
            <div class="comunidade-live-row" id="comunidadeLiveRow">
                <div class="comunidade-live-chip">
                    <strong>Carregando...</strong>
                    <span>Buscando movimentos recentes</span>
                </div>
            </div>
        </div>

        <div id="feedLista" class="comunidade-feed" aria-live="polite"></div>

        <div id="feedLoading" class="feed-loading">
            <div class="feed-loader">🪙</div>
            <p>Carregando novidades...</p>
        </div>

        <div id="feedEmpty" class="feed-empty">
            <div class="feed-empty-icon">✅</div>
            <h3>Nada novo no geral por enquanto</h3>
            <p id="feedEmptyText">Quando a rede se movimentar, os destaques aparecem aqui.</p>
        </div>

        <div id="feedEnd" class="feed-end">
            <strong>Você chegou ao fim saudável 🎉</strong>
            <span>Mostramos só as novidades principais desta sessão.</span>
        </div>

        <div id="feedSentinel" style="height: 1px;"></div>

    </section>

</main>

<div id="comunidadeToast" class="comunidade-toast"></div>

<?php
$footerPath = __DIR__ . '/../assets/footer/menu.php';

if (is_file($footerPath)) {
    require_once $footerPath;
}
?>

<script>
(() => {
    const API_BASE = '/api/feed/';
    const PAGE_LIMIT = 10;

    const FEED_STATIC_BASE = '/assets/feed/statics/';
    const FEED_ANIMATED_BASE = '/assets/feed/animateds/';

    const feedConfigs = {
        amigos: {
            title: 'Amigos',
            desc: 'Veja o que as pessoas do seu time estão aprontando!',
            emptyTitle: 'Seu time ainda está descansando?',
            emptyText: 'Convide mais pessoas e veja a movimentação por aqui!',
            max: 50,
            endpoint: 'amigos.php'
        },
        todos: {
            title: 'Geral',
            desc: 'A comunidade elab.social não dorme! Confira os últimos destaques.',
            emptyTitle: 'Nada novo no geral por enquanto',
            emptyText: 'Quando a rede se movimentar, os destaques aparecem aqui.',
            max: 50,
            endpoint: 'todos.php'
        },
        novos: {
            title: 'Novidades',
            desc: 'Fique por dentro das missões e conteúdos que acabaram de chegar.',
            emptyTitle: 'Tudo em dia',
            emptyText: 'Você já viu as principais novidades. Volte mais tarde para encontrar novas ações e recompensas.',
            max: 30,
            endpoint: 'novos.php'
        }
    };

    const staticIcons = {
        feedReaction: FEED_STATIC_BASE + '005-facebook-reactions.svg',
        facebookReactionAction: FEED_STATIC_BASE + '008-facebook-reactions-1.svg',
        instagramCommentAction: FEED_STATIC_BASE + '004-messenger.svg',
        facebookCommentAction: FEED_STATIC_BASE + '006-messenger-1.svg',
        facebook: FEED_STATIC_BASE + '002-facebook.svg',
        instagram: FEED_STATIC_BASE + '001-instagram.svg'
    };

    const animatedNames = {
        coracaoFeliz: 'coracao-feliz-dancando',
        curtePorFavor: 'curte-porfavorzinho',
        emojiFeliz: 'emoji-feliz',
        mensagemSistema: 'mensagem-sistema',
        novoPostFacebook: 'novo-post-facebook',
        premioEsperando: 'premio-esperando',
        tempoRelogio: 'tempo-acabando-relogio',
        tempoBomba: 'tempo-bomba'
    };

    const state = {
        feed: 'todos',
        items: [],
        loadedCount: 0,
        hasMore: true,
        loading: false,
        cursor: null,
        reachedMax: false
    };

    const tabs = Array.from(document.querySelectorAll('.comunidade-tab'));
    const feedLista = document.getElementById('feedLista');
    const feedLoading = document.getElementById('feedLoading');
    const feedEmpty = document.getElementById('feedEmpty');
    const feedEmptyTitle = feedEmpty ? feedEmpty.querySelector('h3') : null;
    const feedEmptyText = document.getElementById('feedEmptyText');
    const feedEnd = document.getElementById('feedEnd');
    const feedSentinel = document.getElementById('feedSentinel');
    const feedTitulo = document.getElementById('feedTitulo');
    const feedDescricao = document.getElementById('feedDescricao');
    const feedPill = document.getElementById('feedPill');
    const toast = document.getElementById('comunidadeToast');
    const comunidadeLiveRow = document.getElementById('comunidadeLiveRow');

    let toastTimer = null;
    let loadMoreTimer = null;

    const escapeHtml = (value) => {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    };

    const escapeAttr = escapeHtml;

    const animatedCandidates = (name) => {
        return [
            FEED_ANIMATED_BASE + name + '.webp',
            FEED_ANIMATED_BASE + name + '.gif',
            FEED_ANIMATED_BASE + name + '.png',
            FEED_ANIMATED_BASE + name + '.svg',
            FEED_ANIMATED_BASE + name
        ];
    };

    const imageWithFallback = (src, fallback, className = '') => {
        return `
            <img
                src="${escapeAttr(src)}"
                alt=""
                class="${escapeAttr(className)}"
                loading="lazy"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';"
            >
            <span class="feed-avatar-fallback" style="display:none;">${escapeHtml(fallback)}</span>
        `;
    };

    const animatedImageHtml = (name, fallback, className = '') => {
        const candidates = animatedCandidates(name);
        const encoded = escapeAttr(JSON.stringify(candidates));

        return `
            <img
                src="${escapeAttr(candidates[0])}"
                alt=""
                class="${escapeAttr(className)} js-feed-animated-img"
                loading="lazy"
                data-srcs="${encoded}"
                data-src-index="0"
                onerror="window.elabFeedTryNextAnimated && window.elabFeedTryNextAnimated(this);"
            >
            <span class="official-banner-fallback" style="display:none;">${escapeHtml(fallback)}</span>
        `;
    };

    window.elabFeedTryNextAnimated = (img) => {
        try {
            const srcs = JSON.parse(img.getAttribute('data-srcs') || '[]');
            const current = Number(img.getAttribute('data-src-index') || 0);
            const next = current + 1;

            if (next < srcs.length) {
                img.setAttribute('data-src-index', String(next));
                img.src = srcs[next];
                return;
            }
        } catch (e) {}

        img.style.display = 'none';

        if (img.nextElementSibling) {
            img.nextElementSibling.style.display = 'grid';
        }
    };

    const showToast = (message) => {
        if (!toast) {
            return;
        }

        toast.textContent = message;
        toast.classList.add('is-visible');

        if (toastTimer) {
            clearTimeout(toastTimer);
        }

        toastTimer = setTimeout(() => {
            toast.classList.remove('is-visible');
        }, 2100);
    };

    const showFloatingEmoji = (emoji, element) => {
        if (!element) {
            return;
        }

        const rect = element.getBoundingClientRect();
        const el = document.createElement('div');

        el.className = 'float-emoji';
        el.textContent = emoji;
        el.style.left = `${rect.left + rect.width / 2 - 14}px`;
        el.style.top = `${rect.top + window.scrollY + 4}px`;

        document.body.appendChild(el);

        setTimeout(() => {
            el.remove();
        }, 820);
    };

    const showConfetti = (element) => {
        const colors = ['#22c55e', '#facc15', '#ef4444', '#3b82f6', '#a855f7', '#fb7185'];
        const rect = element
            ? element.getBoundingClientRect()
            : {
                left: window.innerWidth / 2,
                top: window.innerHeight / 2,
                width: 1,
                height: 1
            };

        const startX = rect.left + rect.width / 2;
        const startY = rect.top + rect.height / 2;

        for (let i = 0; i < 22; i++) {
            const piece = document.createElement('span');
            const x = (Math.random() * 180 - 90).toFixed(0) + 'px';
            const y = (Math.random() * 140 + 60).toFixed(0) + 'px';
            const r = (Math.random() * 520 - 260).toFixed(0) + 'deg';

            piece.className = 'confetti-piece';
            piece.style.left = `${startX}px`;
            piece.style.top = `${startY}px`;
            piece.style.background = colors[i % colors.length];
            piece.style.setProperty('--confetti-x', x);
            piece.style.setProperty('--confetti-y', y);
            piece.style.setProperty('--confetti-r', r);
            piece.style.animationDelay = `${Math.random() * 80}ms`;

            document.body.appendChild(piece);

            setTimeout(() => {
                piece.remove();
            }, 1200);
        }
    };

    const formatTime = (dateString) => {
        if (!dateString) {
            return 'Agora mesmo';
        }

        const normalized = String(dateString).replace(' ', 'T');
        const date = new Date(normalized);
        const now = new Date();

        if (Number.isNaN(date.getTime())) {
            return dateString;
        }

        const diffSeconds = Math.max(0, Math.floor((now.getTime() - date.getTime()) / 1000));

        if (diffSeconds < 60) {
            return 'Agora mesmo';
        }

        const diffMinutes = Math.floor(diffSeconds / 60);

        if (diffMinutes < 60) {
            return `Há ${diffMinutes} min`;
        }

        const diffHours = Math.floor(diffMinutes / 60);

        if (diffHours < 6) {
            return `Há ${diffHours} h`;
        }

        if (diffHours < 24) {
            return 'Hoje cedo';
        }

        const diffDays = Math.floor(diffHours / 24);

        if (diffDays === 1) {
            return '1 dia';
        }

        if (diffDays < 30) {
            return `${diffDays} dias`;
        }

        return date.toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit'
        });
    };

    const hasReward = (item) => {
        return Number(item.recompensa_moedas || 0) > 0 || Number(item.recompensa_xp || 0) > 0;
    };

    const animatedForItem = (item) => {
        if (hasReward(item) && item.acao_concluida !== 'sim') {
            return {
                name: animatedNames.premioEsperando,
                fallback: '🎁'
            };
        }

        if (item.grupo === 'metaverso' && item.network === 'facebook') {
            return {
                name: animatedNames.novoPostFacebook,
                fallback: '💬'
            };
        }

        if (item.grupo === 'social' && (item.tipo === 'comentario' || item.tipo === 'mention')) {
            return {
                name: animatedNames.mensagemSistema,
                fallback: '💬'
            };
        }

        if (item.tipo === 'ranking' || item.tipo === 'nivel_up') {
            return {
                name: animatedNames.emojiFeliz,
                fallback: '😄'
            };
        }

        return {
            name: animatedNames.coracaoFeliz,
            fallback: '💚'
        };
    };

    const staticNetworkIcon = (item) => {
        if (item.network === 'instagram') {
            return staticIcons.instagram;
        }

        if (item.network === 'facebook') {
            return staticIcons.facebook;
        }

        return staticIcons.feedReaction;
    };

    const emojiForActionLabel = (label) => {
        if (label === 'Celebrar') {
            return '🎉';
        }

        if (label === 'Parabéns') {
            return '🥳';
        }

        if (label === 'Incrível!') {
            return '😱';
        }

        if (label === 'Inspirador') {
            return '✨';
        }

        if (label === 'Uau!') {
            return '🤩';
        }

        if (label === 'Apoiar') {
            return '';
        }

        if (label === 'Responder') {
            return '';
        }

        return '';
    };

    const ctaIconForItem = (item) => {
        if (item.grupo === 'metaverso') {
            if (item.network === 'facebook') {
                return staticIcons.facebookReactionAction;
            }

            if (item.network === 'instagram') {
                return staticIcons.instagramCommentAction;
            }

            return staticIcons.feedReaction;
        }

        if (item.grupo === 'social') {
            if (item.network === 'instagram' && item.tipo === 'comentario') {
                return staticIcons.instagramCommentAction;
            }

            if (item.network === 'facebook' && item.tipo === 'comentario') {
                return staticIcons.facebookCommentAction;
            }

            if (item.network === 'facebook' && item.tipo === 'reacao') {
                return staticIcons.facebookReactionAction;
            }
        }

        return staticIcons.feedReaction;
    };

    const actionIconHtml = (item, label) => {
        const emoji = emojiForActionLabel(label);

        if (emoji !== '') {
            return `<span class="feed-btn-emoji" aria-hidden="true">${escapeHtml(emoji)}</span>`;
        }

        const icon = ctaIconForItem(item);

        return `<img src="${escapeAttr(icon)}" alt="" class="feed-btn-icon" loading="lazy">`;
    };

    const postUrlForItem = (item) => {
        const direct = String(item.link_url || '').trim();

        if (direct !== '') {
            return direct;
        }

        const external = String(item.external_post_id || '').trim();
        const network = String(item.network || '').trim().toLowerCase();

        if (external === '') {
            return '';
        }

        if (network === 'facebook') {
            return 'https://www.facebook.com/' + encodeURIComponent(external).replaceAll('%5F', '_');
        }

        if (network === 'instagram') {
            return '';
        }

        if (network === 'tiktok') {
            return 'https://www.tiktok.com/';
        }

        return '';
    };


    const appDeepLinkForItem = (item, webUrl) => {
        const network = String(item.network || '').trim().toLowerCase();
        const url = String(webUrl || '').trim();

        if (url === '') {
            return '';
        }

        if (network === 'facebook') {
            return 'fb://facewebmodal/f?href=' + encodeURIComponent(url);
        }

        if (network === 'instagram') {
            return '';
        }

        if (network === 'tiktok') {
            return url;
        }

        return url;
    };

    const rewardHtml = (item) => {
        const moedas = Number(item.recompensa_moedas || 0);
        const xp = Number(item.recompensa_xp || 0);
        const rewards = [];

        if (moedas > 0) {
            rewards.push(`<span class="feed-reward">🪙 +${moedas} moedas</span>`);
        }

        if (xp > 0) {
            rewards.push(`<span class="feed-reward is-xp">⚡ +${xp} XP</span>`);
        }

        if (!rewards.length) {
            return '';
        }

        return `<div class="feed-reward-row">${rewards.join('')}</div>`;
    };

    const officialTitle = (item) => {
        if (item.network === 'facebook') {
            return 'Tem conteúdo novo no Facebook!';
        }

        if (item.network === 'instagram') {
            return 'O Instagram tá bombando!';
        }

        if (item.network === 'tiktok') {
            return 'Vídeo novo no TikTok!';
        }

        return item.titulo || 'Tem novidade na comunidade!';
    };

    const officialSubtitle = (item) => {
        if (item.network === 'facebook') {
            return 'Deixe sua reação, fortaleça nossa voz e garanta suas moedas.';
        }

        if (item.network === 'instagram') {
            return 'Comente no post da campanha e suba no ranking agora mesmo.';
        }

        if (item.network === 'tiktok') {
            return 'Assista ao novo vídeo, deixe seu like e veja seu XP decolar!';
        }

        return item.texto || 'Participe agora e ganhe recompensas.';
    };

    const officialCta = (item) => {
        if (item.network === 'facebook') {
            return 'Apoiar';
        }

        if (item.network === 'instagram') {
            return 'Comentar no Instagram';
        }

        if (item.network === 'tiktok') {
            return 'Assistir agora';
        }

        return item.cta_label || 'Ver novidade';
    };

    const socialActionText = (item) => {
        const nome = item.nome_exibicao || item.nome || 'Alguém';
        const moedas = Number(item.recompensa_moedas || 0);
        const network = item.network === 'instagram'
            ? 'Instagram'
            : (item.network === 'facebook' ? 'Facebook' : 'campanha');

        if (item.tipo === 'reacao') {
            if (moedas > 0) {
                return `<strong>${escapeHtml(nome)}</strong> acaba de fortalecer a campanha no ${network} e garantiu recompensas!`;
            }

            return `<strong>${escapeHtml(nome)}</strong> fortaleceu a campanha no ${network}.`;
        }

        if (item.tipo === 'comentario') {
            if (moedas > 0) {
                return `<strong>${escapeHtml(nome)}</strong> comentou no ${network} e garantiu recompensas!`;
            }

            return `<strong>${escapeHtml(nome)}</strong> comentou em um post no ${network}.`;
        }

        if (item.tipo === 'rede' || item.tipo === 'indicacao') {
            return `<strong>${escapeHtml(nome)}</strong> trouxe mais gente para o time! A nossa comunidade ficou mais forte hoje.`;
        }

        if (moedas > 0) {
            return `<strong>${escapeHtml(nome)}</strong> participou da campanha e garantiu recompensas!`;
        }

        return escapeHtml(item.texto || `${nome} movimentou a comunidade.`);
    };

    const gameActionText = (item) => {
        const nome = item.nome_exibicao || item.nome || 'Alguém';
        const moedas = Number(item.recompensa_moedas || 0);
        const texto = String(item.texto || '').toLowerCase();

        if (item.tipo === 'moedas') {
            if (texto.includes('comentar')) {
                return `<strong>${escapeHtml(nome)}</strong> acaba de fortalecer a campanha no Facebook e garantiu recompensas!`;
            }

            if (texto.includes('curtir') || texto.includes('reagir')) {
                return `<strong>${escapeHtml(nome)}</strong> fortaleceu a campanha no Facebook e garantiu recompensas!`;
            }

            return `<strong>${escapeHtml(nome)}</strong> garantiu novas recompensas na comunidade!`;
        }

        if (item.tipo === 'ranking') {
            return `<strong>${escapeHtml(nome)}</strong> não para! Acaba de subir no ranking. A disputa no topo esquentou!`;
        }

        if (item.tipo === 'nivel_up') {
            return `<strong>${escapeHtml(nome)}</strong> subiu de nível e mostrou que está jogando sério!`;
        }

        if (item.tipo === 'conquista') {
            return `<strong>${escapeHtml(nome)}</strong> desbloqueou uma conquista especial. Inspirador!`;
        }

        if (item.tipo === 'medalha') {
            return `Temos uma nova Lenda! <strong>${escapeHtml(nome)}</strong> desbloqueou uma medalha especial.`;
        }

        if (item.tipo === 'rede' || item.tipo === 'indicacao') {
            return `<strong>${escapeHtml(nome)}</strong> trouxe mais gente para o time! A nossa comunidade ficou mais forte hoje.`;
        }

        if (moedas > 0) {
            return `<strong>${escapeHtml(nome)}</strong> garantiu recompensas e segue avançando.`;
        }

        return escapeHtml(item.texto || `${nome} evoluiu na comunidade.`);
    };

    const ctaTextForItem = (item) => {
        if (item.grupo === 'metaverso') {
            return officialCta(item);
        }

        if (item.grupo === 'social') {
            if (item.tipo === 'comentario') {
                return 'Responder';
            }

            if (item.tipo === 'reacao') {
                return 'Apoiar';
            }

            if (item.tipo === 'rede' || item.tipo === 'indicacao') {
                return 'Boas-vindas!';
            }

            return 'Parabéns';
        }

        if (item.tipo === 'moedas') {
            return 'Celebrar';
        }

        if (item.tipo === 'ranking') {
            return 'Incrível!';
        }

        if (item.tipo === 'conquista') {
            return 'Inspirador';
        }

        if (item.tipo === 'medalha') {
            return 'Uau!';
        }

        if (item.tipo === 'rede' || item.tipo === 'indicacao') {
            return 'Boas-vindas!';
        }

        return 'Celebrar';
    };

    const interactionTypeForLabel = (label) => {
        if (label === 'Celebrar') {
            return 'comemorei';
        }

        if (label === 'Parabéns') {
            return 'comemorei';
        }

        if (label === 'Incrível!') {
            return 'gostei';
        }

        if (label === 'Apoiar') {
            return 'apoiei';
        }

        if (label === 'Apoiar') {
            return 'apoiei';
        }

        if (label === 'Comentar no Instagram') {
            return 'apoiei';
        }

        return 'gostei';
    };

    const actionElement = (item, label, isPrimary = false) => {
        const cls = isPrimary ? 'feed-cta is-primary js-feed-action js-open-social-post' : 'feed-cta is-small js-feed-action js-open-social-post';
        const moedas = Number(item.recompensa_moedas || 0);
        const iconHtml = actionIconHtml(item, label);
        const url = postUrlForItem(item);
        const appUrl = appDeepLinkForItem(item, url);

        if (url !== '') {
            return `
                <a
                    href="${escapeAttr(url)}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="${cls}"
                    data-id="${escapeAttr(item.id)}"
                    data-origem-tipo="${escapeAttr(item.origem_tipo)}"
                    data-origem-id="${Number(item.origem_id || 0)}"
                    data-moedas="${moedas}"
                    data-label="${escapeAttr(label)}"
                    data-web-url="${escapeAttr(url)}"
                    data-app-url="${escapeAttr(appUrl)}"
                >
                    ${iconHtml}
                    ${escapeHtml(label)}
                </a>
            `;
        }

        return `
            <button
                type="button"
                class="${cls.replace(' js-open-social-post', '')}"
                data-id="${escapeAttr(item.id)}"
                data-origem-tipo="${escapeAttr(item.origem_tipo)}"
                data-origem-id="${Number(item.origem_id || 0)}"
                data-moedas="${moedas}"
                data-label="${escapeAttr(label)}"
            >
                ${iconHtml}
                ${escapeHtml(label)}
            </button>
        `;
    };

    const likeButtonHtml = (item) => {
        return `
            <button
                type="button"
                class="feed-like js-feed-react"
                data-id="${escapeAttr(item.id)}"
                data-origem-tipo="${escapeAttr(item.origem_tipo)}"
                data-origem-id="${Number(item.origem_id || 0)}"
                data-count="${Number(item.total_interacoes || 0)}"
            >
                <img src="${escapeAttr(staticIcons.feedReaction)}" alt="" loading="lazy">
                <span>${Number(item.total_interacoes || 0)}</span>
            </button>
        `;
    };

    const renderOfficialCard = (item, index) => {
        const networkClass = item.network === 'instagram'
            ? 'is-instagram'
            : (item.network === 'facebook' ? 'is-facebook' : (item.network === 'tiktok' ? 'is-tiktok' : ''));

        const label = item.network === 'facebook' || item.network === 'instagram'
            ? 'NOVO POST'
            : (item.network === 'tiktok' ? 'NOVO VÍDEO' : 'NOVIDADE');

        const delay = Math.min(index * 30, 220);
        const anim = animatedForItem(item);
        const networkIcon = staticNetworkIcon(item);

        return `
            <article
                class="official-card"
                style="animation-delay:${delay}ms"
                data-origem-tipo="${escapeAttr(item.origem_tipo)}"
                data-origem-id="${Number(item.origem_id || 0)}"
            >
                <div class="official-banner ${networkClass}">
                    ${animatedImageHtml(anim.name, anim.fallback)}
                    <img src="${escapeAttr(networkIcon)}" alt="" class="official-network-icon" loading="lazy">
                </div>

                <div class="official-meta">
                    <span class="official-label">${escapeHtml(label)}</span>
                    <span class="official-time">${escapeHtml(formatTime(item.publicado_em))}</span>
                </div>

                <div class="official-title">${escapeHtml(officialTitle(item))}</div>
                <div class="official-subtitle">${escapeHtml(officialSubtitle(item))}</div>

                ${rewardHtml(item)}

                <div class="official-actions">
                    ${actionElement(item, officialCta(item), true)}
                    ${likeButtonHtml(item)}
                </div>
            </article>
        `;
    };


    const cardTypeClass = (item) => {
        if (Number(item.recompensa_moedas || 0) > 0 || Number(item.recompensa_xp || 0) > 0) {
            return 'is-reward';
        }

        if (item.tipo === 'comentario' || item.tipo === 'mention') {
            return 'is-comment';
        }

        if (item.tipo === 'reacao') {
            return 'is-reaction';
        }

        if (item.grupo === 'game') {
            return 'is-game';
        }

        return '';
    };

    const liveTextForItem = (item) => {
        const nome = item.nome_exibicao || item.nome || 'Comunidade';

        if (Number(item.recompensa_moedas || 0) > 0) {
            return `${nome} ganhou +${Number(item.recompensa_moedas || 0)} moedas`;
        }

        if (item.tipo === 'comentario') {
            return `${nome} comentou`;
        }

        if (item.tipo === 'reacao') {
            return `${nome} reagiu`;
        }

        if (item.tipo === 'ranking') {
            return `${nome} subiu no ranking`;
        }

        if (item.tipo === 'nivel_up') {
            return `${nome} subiu de nível`;
        }

        return `${nome} movimentou a rede`;
    };

    const updateLiveStrip = () => {
        if (!comunidadeLiveRow) {
            return;
        }

        const lastItems = state.items.slice(0, 4);

        if (!lastItems.length) {
            comunidadeLiveRow.innerHTML = `
                <div class="comunidade-live-chip">
                    <strong>Carregando...</strong>
                    <span>Buscando movimentos recentes</span>
                </div>
            `;
            return;
        }

        comunidadeLiveRow.innerHTML = lastItems.map((item) => `
            <div class="comunidade-live-chip">
                <strong>${escapeHtml(liveTextForItem(item))}</strong>
                <span>${escapeHtml(formatTime(item.publicado_em))}</span>
            </div>
        `).join('');
    };


    const renderFeedItem = (item, index) => {
        if (item.grupo === 'metaverso') {
            return renderOfficialCard(item, index);
        }

        const delay = Math.min(index * 30, 220);
        const isSocial = item.grupo === 'social';
        const isGame = item.grupo === 'game';

        const avatarClass = isSocial
            ? 'is-social'
            : (isGame ? 'is-game' : '');

        const titleHtml = isSocial
            ? socialActionText(item)
            : gameActionText(item);

        const ctaText = ctaTextForItem(item);
        const metaName = item.nome_exibicao || item.nome || 'Comunidade';
        const anim = animatedForItem(item);

        return `
            <article
                class="feed-item ${cardTypeClass(item)}"
                style="animation-delay:${delay}ms"
                data-origem-tipo="${escapeAttr(item.origem_tipo)}"
                data-origem-id="${Number(item.origem_id || 0)}"
                data-pessoa-id="${item.pessoa_id === null ? '' : Number(item.pessoa_id || 0)}"
            >
                <div class="feed-body-row">
                    <div class="feed-body-main">
                        <div class="feed-person-head">
                            <div class="feed-avatar ${avatarClass}">
                                ${item.avatar_texto && item.grupo === 'social'
                                    ? `<span>${escapeHtml(item.avatar_texto)}</span>`
                                    : imageWithFallback(staticIcons.feedReaction, '🎉')
                                }
                            </div>

                            <div class="feed-head-main">
                                <div class="feed-person-line">
                                    <strong>${escapeHtml(metaName)}</strong>
                                </div>
                                <div class="feed-time">${escapeHtml(formatTime(item.publicado_em))}${item.relacao_feed === 'rede' ? ' · sua rede' : ''}</div>
                            </div>
                        </div>

                        <div class="feed-title">${titleHtml}</div>

                        ${rewardHtml(item)}

                        <div class="feed-actions">
                            ${actionElement(item, ctaText, false)}
                            ${likeButtonHtml(item)}
                        </div>
                    </div>

                    <div class="feed-side-art" aria-hidden="true">
                        ${animatedImageHtml(anim.name, anim.fallback)}
                    </div>
                </div>
            </article>
        `;
    };

    const setLoading = (isLoading) => {
        state.loading = isLoading;

        if (feedLoading) {
            feedLoading.classList.toggle('is-visible', isLoading);
        }
    };

    const resetFeed = (feed) => {
        const config = feedConfigs[feed] || feedConfigs.todos;

        state.feed = feed;
        state.items = [];
        state.loadedCount = 0;
        state.hasMore = true;
        state.loading = false;
        state.cursor = null;
        state.reachedMax = false;

        if (feedLista) {
            feedLista.innerHTML = '';
        }

        if (feedEmpty) {
            feedEmpty.classList.remove('is-visible');
        }

        if (feedEnd) {
            feedEnd.classList.remove('is-visible');
        }

        if (feedTitulo) {
            feedTitulo.textContent = config.title;
        }

        if (feedDescricao) {
            feedDescricao.textContent = config.desc;
        }

        if (feedEmptyTitle) {
            feedEmptyTitle.textContent = config.emptyTitle;
        }

        if (feedEmptyText) {
            feedEmptyText.textContent = config.emptyText;
        }

        if (feedPill) {
            feedPill.textContent = 'carregando';
        }

        tabs.forEach((tab) => {
            tab.classList.toggle('is-active', tab.getAttribute('data-feed') === feed);
        });
    };

    const buildUrl = () => {
        const config = feedConfigs[state.feed] || feedConfigs.todos;
        const url = new URL(API_BASE + config.endpoint, window.location.origin);

        url.searchParams.set('limit', String(PAGE_LIMIT));

        if (state.cursor && state.cursor.publicado_em && state.cursor.origem_id) {
            url.searchParams.set('cursor_publicado_em', state.cursor.publicado_em);
            url.searchParams.set('cursor_origem_id', String(state.cursor.origem_id));
        }

        return url.toString();
    };

    const updatePill = () => {
        if (!feedPill) {
            return;
        }

        const config = feedConfigs[state.feed] || feedConfigs.todos;

        if (state.loadedCount <= 0) {
            feedPill.textContent = 'sem itens';
            return;
        }

        feedPill.textContent = `${state.loadedCount}/${config.max}`;
    };

    const renderItems = (items) => {
        if (!feedLista || !items.length) {
            return 0;
        }

        const html = items.map((item, index) => renderFeedItem(item, index)).join('');

        feedLista.insertAdjacentHTML('beforeend', html);
        bindCardActions();

        return items.length;
    };

    const registrarInteracao = async (button, tipo = 'gostei') => {
        const origemTipo = button.getAttribute('data-origem-tipo') || '';
        const origemId = Number(button.getAttribute('data-origem-id') || 0);

        if (!origemTipo || origemId <= 0) {
            return null;
        }

        const response = await fetch('/api/feed/interagir.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                origem_tipo: origemTipo,
                origem_id: origemId,
                tipo: tipo
            })
        });

        const data = await response.json();

        if (!response.ok || !data || data.ok !== true) {
            throw new Error(data && data.mensagem ? data.mensagem : 'Erro ao registrar interação.');
        }

        return data;
    };

    const loadFeed = async () => {
        const config = feedConfigs[state.feed] || feedConfigs.todos;

        if (state.loading || state.reachedMax || !state.hasMore) {
            return;
        }

        if (state.loadedCount >= config.max) {
            state.reachedMax = true;
            state.hasMore = false;

            if (feedEnd) {
                feedEnd.classList.add('is-visible');
            }

            updatePill();
            return;
        }

        setLoading(true);

        try {
            const requestUrl = buildUrl();

            const response = await fetch(requestUrl, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });

            const raw = await response.text();

            let data = null;

            try {
                data = JSON.parse(raw);
            } catch (jsonError) {
                console.error('[COMUNIDADE_FEED_JSON_INVALIDO]', {
                    url: requestUrl,
                    status: response.status,
                    body: raw
                });

                throw new Error('Resposta inválida da API.');
            }

            if (!response.ok || !data || data.ok !== true) {
                console.error('[COMUNIDADE_FEED_API_ERRO]', {
                    url: requestUrl,
                    status: response.status,
                    data: data
                });

                throw new Error(data && data.mensagem ? data.mensagem : 'Erro ao carregar feed.');
            }

            const items = Array.isArray(data.items) ? data.items : [];

            if (!items.length && state.loadedCount === 0) {
                if (feedEmpty) {
                    feedEmpty.classList.add('is-visible');
                }
            }

            const remaining = Math.max(0, config.max - state.loadedCount);
            const safeItems = items.slice(0, remaining);
            const renderedCount = renderItems(safeItems);

            state.items.push(...safeItems);
            updateLiveStrip();
            state.loadedCount += renderedCount;
            state.hasMore = Boolean(data.has_more);
            state.cursor = data.next_cursor || null;

            if (!state.hasMore || state.loadedCount >= config.max || safeItems.length === 0) {
                state.reachedMax = true;

                if (feedEnd && state.loadedCount > 0) {
                    feedEnd.classList.add('is-visible');
                }
            }

            updatePill();
            updateLiveStrip();
        } catch (error) {
            console.error(error);

            if (state.loadedCount > 0) {
                state.hasMore = false;
                state.reachedMax = true;

                if (feedEnd) {
                    feedEnd.classList.add('is-visible');
                }

                updatePill();
                return;
            }

            showToast('Não foi possível carregar a comunidade agora.');

            if (feedEmpty) {
                feedEmpty.classList.add('is-visible');
            }
        } finally {
            setLoading(false);
        }
    };

    const bindCardActions = () => {
        document.querySelectorAll('.js-feed-react:not([data-bound="1"])').forEach((button) => {
            button.setAttribute('data-bound', '1');

            button.addEventListener('click', async () => {
                if (button.disabled) {
                    return;
                }

                const span = button.querySelector('span');
                const current = Number(button.getAttribute('data-count') || 0);
                const next = current + 1;

                button.disabled = true;
                button.setAttribute('data-count', String(next));

                if (span) {
                    span.textContent = String(next);
                }

                button.classList.add('is-active');
                showFloatingEmoji('❤️', button);
                showConfetti(button);

                try {
                    const data = await registrarInteracao(button, 'gostei');

                    if (data && typeof data.total_interacoes !== 'undefined') {
                        const total = Number(data.total_interacoes || next);

                        button.setAttribute('data-count', String(total));

                        if (span) {
                            span.textContent = String(total);
                        }
                    }

                    showToast('Apoio registrado na comunidade!');
                } catch (error) {
                    console.error(error);

                    button.setAttribute('data-count', String(current));

                    if (span) {
                        span.textContent = String(current);
                    }

                    showToast('Não foi possível registrar agora.');
                } finally {
                    setTimeout(() => {
                        button.classList.remove('is-active');
                        button.disabled = false;
                    }, 620);
                }
            });
        });

        document.querySelectorAll('.js-feed-action:not([data-bound="1"])').forEach((button) => {
            button.setAttribute('data-bound', '1');

            button.addEventListener('click', async (event) => {
                const moedas = Number(button.getAttribute('data-moedas') || 0);
                const label = String(button.getAttribute('data-label') || '').trim();
                const emoji = emojiForActionLabel(label) || '🎉';
                const webUrl = String(button.getAttribute('data-web-url') || '').trim();
                const appUrl = String(button.getAttribute('data-app-url') || '').trim();

                if (webUrl !== '') {
                    return;
                }

                showConfetti(button);
                showFloatingEmoji(emoji, button);

                if (webUrl !== '') {
                    event.preventDefault();

                    if (item && item.network === 'instagram') {
                        showToast('Abrindo no Instagram.');
                    } else if (item && item.network === 'facebook') {
                        showToast('Abrindo no Facebook.');
                    } else if (label === 'Responder') {
                        showToast('Abrindo o post para você responder.');
                    } else if (moedas > 0) {
                        showToast(`Abra o post e garanta: +${moedas} moedas.`);
                    } else {
                        showToast('Abrindo o post original.');
                    }

                    const targetUrl = appUrl || webUrl;

                    const opened = window.open(targetUrl, '_blank', 'noopener,noreferrer');

                    if (!opened) {
                        window.location.href = targetUrl;
                    }
                } else if (moedas > 0) {
                    showToast(`Tem recompensa esperando: +${moedas} moedas.`);
                } else if (label === 'Responder') {
                    showToast('Post original não encontrado.');
                } else {
                    showToast('Interação registrada na comunidade.');
                }

                try {
                    await registrarInteracao(button, interactionTypeForLabel(label));
                } catch (error) {
                    console.error(error);
                }
            });
        });
    };

    const maybeLoadMore = () => {
        if (loadMoreTimer) {
            clearTimeout(loadMoreTimer);
        }

        loadMoreTimer = setTimeout(() => {
            const scrollTop = window.scrollY || document.documentElement.scrollTop || 0;
            const viewport = window.innerHeight || document.documentElement.clientHeight || 0;
            const height = Math.max(
                document.body.scrollHeight,
                document.documentElement.scrollHeight,
                document.body.offsetHeight,
                document.documentElement.offsetHeight
            );

            const nearBottom = scrollTop + viewport >= height - 720;

            if (nearBottom) {
                loadFeed();
            }
        }, 90);
    };

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const feed = tab.getAttribute('data-feed') || 'todos';

            if (feed === state.feed) {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
                return;
            }

            resetFeed(feed);

            window.scrollTo({
                top: 0,
                behavior: 'auto'
            });

            loadFeed();

            setTimeout(maybeLoadMore, 500);
            setTimeout(maybeLoadMore, 1200);
        });
    });

    if ('IntersectionObserver' in window && feedSentinel) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    loadFeed();
                }
            });
        }, {
            root: null,
            rootMargin: '820px 0px',
            threshold: 0
        });

        observer.observe(feedSentinel);
    }

    window.addEventListener('scroll', maybeLoadMore, {
        passive: true
    });

    window.addEventListener('resize', maybeLoadMore, {
        passive: true
    });

    window.addEventListener('orientationchange', () => {
        setTimeout(maybeLoadMore, 500);
    }, {
        passive: true
    });

    resetFeed('todos');
    loadFeed();

    setTimeout(maybeLoadMore, 600);
    setTimeout(maybeLoadMore, 1400);
})();
</script>

</body>
</html>