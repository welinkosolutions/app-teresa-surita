<?php
declare(strict_types=1);

$assetsPath = __DIR__ . '/assets.php';
if (is_file($assetsPath)) {
    require_once $assetsPath;
}

$tipo = isset($tipo) && is_string($tipo) ? $tipo : ($_GET['tipo'] ?? 'impacto');
$tipo = preg_replace('/[^a-z0-9_\-]/i', '', strtolower((string) $tipo));

$valorParam = $_GET['valor'] ?? null;
$valor = is_numeric($valorParam) ? (int) $valorParam : null;

$continuar = $_GET['continuar'] ?? '/comunidade/missao.php';
$continuar = is_string($continuar) && $continuar !== '' ? $continuar : '/comunidade/missao.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$baseConfig = [
    'selo' => 'Recompensa liberada',
    'titulo' => 'PARABÉNS!',
    'subtitulo' => 'Você avançou na sua jornada',
    'valor' => $valor ?? 10,
    'prefixo_valor' => '+',
    'label' => 'PONTOS',
    'botao' => 'CONTINUAR',
    'mostrar_bloco_principal' => true,
    'recompensas' => [
        ['icone' => '⚡', 'valor' => '+10', 'label' => 'Impacto'],
        ['icone' => '🪙', 'valor' => '+1', 'label' => 'Moeda'],
        ['icone' => '⭐', 'valor' => '+0.1', 'label' => 'XP'],
    ],
];

$typeConfigs = [
    'impacto' => [
        'selo' => 'Impacto gerado',
        'titulo' => 'PARABÉNS!',
        'subtitulo' => 'Sua ação movimentou a comunidade',
        'valor' => $valor ?? 10,
        'prefixo_valor' => '+',
        'label' => 'IMPACTO',
        'botao' => 'CONTINUAR CRESCENDO',
        'mostrar_bloco_principal' => false,
        'recompensas' => [
            ['icone' => '⚡', 'valor' => '+10', 'label' => 'Impacto'],
            ['icone' => '🪙', 'valor' => '+1', 'label' => 'Moeda'],
            ['icone' => '⭐', 'valor' => '+0.1', 'label' => 'XP'],
        ],
    ],
    'moedas' => [
        'selo' => 'Moedas sociais',
        'titulo' => 'PARABÉNS!',
        'subtitulo' => 'Você ganhou moedas para continuar evoluindo',
        'valor' => $valor ?? 20,
        'prefixo_valor' => '+',
        'label' => 'MOEDAS',
        'botao' => 'COLETAR MOEDAS',
        'mostrar_bloco_principal' => false,
        'recompensas' => [
            ['icone' => '🎁', 'valor' => '+1', 'label' => 'Bônus'],
            ['icone' => '🪙', 'valor' => '+20', 'label' => 'Moedas'],
            ['icone' => '⭐', 'valor' => '+0.2', 'label' => 'XP'],
        ],
    ],
    'xp' => [
        'selo' => 'Experiência XP',
        'titulo' => 'PARABÉNS!',
        'subtitulo' => 'Sua jornada ganhou mais experiência',
        'valor' => $valor ?? 5,
        'prefixo_valor' => '+',
        'label' => 'XP',
        'botao' => 'PRÓXIMO DESAFIO',
        'mostrar_bloco_principal' => false,
        'recompensas' => [
            ['icone' => '🔥', 'valor' => '+1', 'label' => 'Ritmo'],
            ['icone' => '🪙', 'valor' => '+2', 'label' => 'Moedas'],
            ['icone' => '⭐', 'valor' => '+5', 'label' => 'XP'],
        ],
    ],
    'combo' => [
        'selo' => 'Sequência ativa',
        'titulo' => 'PARABÉNS!',
        'subtitulo' => 'Você entrou em ritmo de campeão',
        'valor' => $valor ?? 15,
        'prefixo_valor' => '+',
        'label' => 'COMBO',
        'botao' => 'MANTER O RITMO',
        'mostrar_bloco_principal' => false,
        'recompensas' => [
            ['icone' => '🔥', 'valor' => '+15', 'label' => 'Combo'],
            ['icone' => '🪙', 'valor' => '+2', 'label' => 'Moedas'],
            ['icone' => '⭐', 'valor' => '+3', 'label' => 'XP'],
        ],
    ],
    'nivel' => [
        'selo' => 'Novo patamar',
        'titulo' => 'PARABÉNS!',
        'subtitulo' => 'Um novo nível foi desbloqueado',
        'valor' => $valor ? 'NÍVEL ' . $valor : 'NÍVEL 2',
        'prefixo_valor' => '',
        'label' => 'DESBLOQUEADO',
        'botao' => 'VER MEU PODER',
        'recompensas' => [
            ['icone' => '👑', 'valor' => '+1', 'label' => 'Nível'],
            ['icone' => '🪙', 'valor' => '+100', 'label' => 'Moedas'],
            ['icone' => '⭐', 'valor' => '+50', 'label' => 'XP'],
        ],
    ],
    'medalha' => [
        'selo' => 'Medalha liberada',
        'titulo' => 'PARABÉNS!',
        'subtitulo' => 'Uma nova medalha entrou para sua coleção',
        'valor' => 'NOVA',
        'prefixo_valor' => '',
        'label' => 'MEDALHA',
        'botao' => 'EXIBIR ORGULHO',
        'recompensas' => [
            ['icone' => '🎖️', 'valor' => '+1', 'label' => 'Medalha'],
            ['icone' => '🪙', 'valor' => '+25', 'label' => 'Moedas'],
            ['icone' => '⭐', 'valor' => '+10', 'label' => 'XP'],
        ],
    ],
    'conquista' => [
        'selo' => 'Marco alcançado',
        'titulo' => 'PARABÉNS!',
        'subtitulo' => 'Você desbloqueou um grande feito',
        'valor' => 'GRANDE',
        'prefixo_valor' => '',
        'label' => 'FEITO',
        'botao' => 'CONTINUAR JORNADA',
        'recompensas' => [
            ['icone' => '🏆', 'valor' => '+1', 'label' => 'Conquista'],
            ['icone' => '🪙', 'valor' => '+50', 'label' => 'Moedas'],
            ['icone' => '⭐', 'valor' => '+100', 'label' => 'XP'],
        ],
    ],
    'cadastrado' => [
        'selo' => 'Rede crescendo',
        'titulo' => 'PARABÉNS!',
        'subtitulo' => 'Mais uma pessoa entrou para a comunidade',
        'valor' => $valor ?? 1,
        'prefixo_valor' => '+',
        'label' => 'PESSOA',
        'botao' => 'DAR BOAS-VINDAS',
        'recompensas' => [
            ['icone' => '🤝', 'valor' => '+1', 'label' => 'Pessoa'],
            ['icone' => '🪙', 'valor' => '+5', 'label' => 'Moedas'],
            ['icone' => '⭐', 'valor' => '+1', 'label' => 'XP'],
        ],
    ],
    'missao' => [
        'selo' => 'Objetivo completo',
        'titulo' => 'PARABÉNS!',
        'subtitulo' => 'Você concluiu uma missão importante',
        'valor' => '100%',
        'prefixo_valor' => '',
        'label' => 'FINALIZADA',
        'botao' => 'PRÓXIMA MISSÃO',
        'recompensas' => [
            ['icone' => '✅', 'valor' => '+1', 'label' => 'Missão'],
            ['icone' => '🪙', 'valor' => '+20', 'label' => 'Moedas'],
            ['icone' => '⭐', 'valor' => '+10', 'label' => 'XP'],
        ],
    ],
    'ranking' => [
        'selo' => 'Ranking da comunidade',
        'titulo' => 'PARABÉNS!',
        'subtitulo' => 'Sua participação ganhou destaque',
        'valor' => 'TOP 10',
        'prefixo_valor' => '',
        'label' => 'ENGAJAMENTO',
        'botao' => 'VER POSIÇÃO',
        'recompensas' => [
            ['icone' => '🏆', 'valor' => 'TOP', 'label' => 'Ranking'],
            ['icone' => '🪙', 'valor' => '+50', 'label' => 'Moedas'],
            ['icone' => '⭐', 'valor' => '+25', 'label' => 'XP'],
        ],
    ],
    'bronze' => [
        'selo' => 'Categoria desbloqueada',
        'titulo' => 'PARABÉNS!',
        'subtitulo' => 'Seu primeiro marco de evolução chegou',
        'valor' => 'BRONZE',
        'prefixo_valor' => '',
        'label' => 'CATEGORIA',
        'botao' => 'CONTINUAR',
        'recompensas' => [
            ['icone' => '🥉', 'valor' => '+1', 'label' => 'Bronze'],
            ['icone' => '🪙', 'valor' => '+50', 'label' => 'Moedas'],
            ['icone' => '⭐', 'valor' => '+20', 'label' => 'XP'],
        ],
    ],
    'prata' => [
        'selo' => 'Categoria desbloqueada',
        'titulo' => 'PARABÉNS!',
        'subtitulo' => 'Você avançou para uma categoria superior',
        'valor' => 'PRATA',
        'prefixo_valor' => '',
        'label' => 'CATEGORIA',
        'botao' => 'CONTINUAR',
        'recompensas' => [
            ['icone' => '🥈', 'valor' => '+1', 'label' => 'Prata'],
            ['icone' => '🪙', 'valor' => '+100', 'label' => 'Moedas'],
            ['icone' => '⭐', 'valor' => '+40', 'label' => 'XP'],
        ],
    ],
    'ouro' => [
        'selo' => 'Categoria desbloqueada',
        'titulo' => 'PARABÉNS!',
        'subtitulo' => 'Sua evolução está brilhando',
        'valor' => 'OURO',
        'prefixo_valor' => '',
        'label' => 'CATEGORIA',
        'botao' => 'CONTINUAR',
        'recompensas' => [
            ['icone' => '🥇', 'valor' => '+1', 'label' => 'Ouro'],
            ['icone' => '🪙', 'valor' => '+250', 'label' => 'Moedas'],
            ['icone' => '⭐', 'valor' => '+100', 'label' => 'XP'],
        ],
    ],
    'elite' => [
        'selo' => 'Status especial',
        'titulo' => 'PARABÉNS!',
        'subtitulo' => 'Você entrou em um grupo especial',
        'valor' => 'ELITE',
        'prefixo_valor' => '',
        'label' => 'STATUS',
        'botao' => 'VER STATUS',
        'recompensas' => [
            ['icone' => '💠', 'valor' => '+1', 'label' => 'Elite'],
            ['icone' => '🪙', 'valor' => '+500', 'label' => 'Moedas'],
            ['icone' => '⭐', 'valor' => '+250', 'label' => 'XP'],
        ],
    ],
    'lenda' => [
        'selo' => 'Status máximo',
        'titulo' => 'PARABÉNS!',
        'subtitulo' => 'Um marco raro foi alcançado',
        'valor' => 'LENDA',
        'prefixo_valor' => '',
        'label' => 'STATUS MÁXIMO',
        'botao' => 'CELEBRAR',
        'recompensas' => [
            ['icone' => '🐉', 'valor' => '+1', 'label' => 'Lenda'],
            ['icone' => '🪙', 'valor' => '+1000', 'label' => 'Moedas'],
            ['icone' => '⭐', 'valor' => '+500', 'label' => 'XP'],
        ],
    ],
];

$config = array_replace($baseConfig, $typeConfigs[$tipo] ?? $typeConfigs['impacto']);

$escudosCentraisPorTipo = [
    'impacto' => '/comunidade/sucesso/estaticos/16.png?v=layout-final-1',
    'moedas' => '/comunidade/sucesso/estaticos/17.webp?v=webp-assets-1',
    'xp' => '/comunidade/sucesso/estaticos/2.webp?v=webp-assets-1',
    'combo' => '/comunidade/sucesso/estaticos/18.webp?v=webp-assets-1',
    'nivel' => '/comunidade/sucesso/estaticos/10.png?v=layout-final-1',
    'medalha' => '/comunidade/sucesso/estaticos/14.png?v=layout-final-1',
    'conquista' => '/comunidade/sucesso/estaticos/15.png?v=layout-final-1',
    'cadastrado' => '/comunidade/sucesso/estaticos/17.png?v=layout-final-1',
    'missao' => '/comunidade/sucesso/estaticos/20.png?v=layout-final-1',
    'ranking' => '/comunidade/sucesso/estaticos/21.png?v=layout-final-1',
    'bronze' => '/comunidade/sucesso/estaticos/1.png?v=layout-final-1',
    'prata' => '/comunidade/sucesso/estaticos/2.png?v=layout-final-1',
    'ouro' => '/comunidade/sucesso/estaticos/3.png?v=layout-final-1',
    'elite' => '/comunidade/sucesso/estaticos/4.png?v=layout-final-1',
    'lenda' => '/comunidade/sucesso/estaticos/5.png?v=layout-final-1',
];

$assetCentral = $escudosCentraisPorTipo[$tipo] ?? $escudosCentraisPorTipo['impacto'];




$audioMap = [
    'impacto' => '/comunidade/sucesso/audio/cartoon-conclusion.mp3?v=1',
    'moedas' => '/comunidade/sucesso/audio/moedas.mp3?v=1',
    'xp' => '/comunidade/sucesso/audio/xp.mp3?v=1',
    'combo' => '/comunidade/sucesso/audio/xp.mp3?v=1',
    'nivel' => '/comunidade/sucesso/audio/nivel.mp3?v=1',
    'medalha' => '/comunidade/sucesso/audio/nivel.mp3?v=1',
    'conquista' => '/comunidade/sucesso/audio/nivel.mp3?v=1',
    'cadastrado' => '/comunidade/sucesso/audio/cadastro.mp3?v=1',
    'missao' => '/comunidade/sucesso/audio/cartoon-conclusion.mp3?v=1',
    'ranking' => '/comunidade/sucesso/audio/nivel.mp3?v=1',
    'bronze' => '/comunidade/sucesso/audio/nivel.mp3?v=1',
    'prata' => '/comunidade/sucesso/audio/nivel.mp3?v=1',
    'ouro' => '/comunidade/sucesso/audio/nivel.mp3?v=1',
    'elite' => '/comunidade/sucesso/audio/nivel.mp3?v=1',
    'lenda' => '/comunidade/sucesso/audio/nivel.mp3?v=1',
];

$audioSrc = $audioMap[$tipo] ?? '/comunidade/sucesso/audio/cartoon-conclusion.mp3?v=1';

$tema = function_exists('sucesso_tema_por_tipo')
    ? sucesso_tema_por_tipo($tipo)
    : ['cor' => '#facc15', 'glow' => 'rgba(250,204,21,.5)'];

$corTema = $tema['cor'] ?? '#facc15';
$glowTema = $tema['glow'] ?? 'rgba(250,204,21,.5)';

$valorDisplay = (string) ($config['valor'] ?? '');
$prefixoValor = (string) ($config['prefixo_valor'] ?? '');
$valorNumericoParaAnimar = is_numeric($config['valor'] ?? null) ? (int) $config['valor'] : 0;
$mostrarBlocoPrincipal = (bool) ($config['mostrar_bloco_principal'] ?? true);

if (!function_exists('sucesso_titulo_animado')) {
    function sucesso_titulo_animado(string $texto): string
    {
        $chars = preg_split('//u', $texto, -1, PREG_SPLIT_NO_EMPTY);
        $html = '';
        $index = 0;

        foreach ($chars as $char) {
            if (trim($char) === '') {
                $html .= '<span class="titulo-char titulo-char--space">&nbsp;</span>';
                continue;
            }

            $html .= '<span class="titulo-char" style="--char-index:' . $index . ';">'
                . htmlspecialchars($char, ENT_QUOTES, 'UTF-8')
                . '</span>';

            $index++;
        }

        return $html;
    }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title><?= h((string) $config['titulo']) ?> | elab.social</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#10051d">

    <link rel="icon" type="image/png" href="/comunidade/sucesso/estaticos/16.png">
    <link rel="preload" as="image" href="/comunidade/sucesso/estaticos/background.png?v=body-real-final" fetchpriority="high">
    <link rel="preload" as="image" href="<?= h($assetCentral) ?>" fetchpriority="high">

    <style>
        :root {
            --tema-cor: <?= h($corTema) ?>;
            --tema-glow: <?= h($glowTema) ?>;
            --texto: #fff7d6;
        }

        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
            background-color: #05020d;
            color: var(--texto);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            overflow: hidden;
        }

        body {
            background-image:
                radial-gradient(circle at 50% 42%, rgba(255, 226, 116, .10) 0%, rgba(255, 226, 116, .035) 24%, transparent 46%),
                radial-gradient(circle at 50% 43%, rgba(83, 196, 255, .08) 0%, transparent 58%),
                radial-gradient(circle at 50% 39%, rgba(190, 80, 255, .14) 0%, transparent 68%),
                linear-gradient(90deg, rgba(0,0,0,.30), transparent 20%, transparent 80%, rgba(0,0,0,.30)),
                linear-gradient(180deg, rgba(0,0,0,.04), transparent 30%, rgba(0,0,0,.24) 100%),
                url('/comunidade/sucesso/estaticos/background.png?v=body-real-final');
            background-size: cover, cover, cover, cover, cover, cover;
            background-position: center center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            overflow-x: hidden;
        }

        .sucesso-game {
            position: relative;
            min-height: 100vh;
            width: 100%;
            overflow: hidden;
            display: flex;
            justify-content: center;
            background: transparent;
        }

        .sucesso-game::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 1;
            pointer-events: none;
            background:
                linear-gradient(90deg, rgba(0,0,0,.40), transparent 18%, transparent 82%, rgba(0,0,0,.40)),
                radial-gradient(circle at 50% 100%, rgba(255, 169, 40, .08), transparent 36%);
        }

        .sucesso-game::after {
            content: "";
            position: absolute;
            inset: -14%;
            z-index: 2;
            pointer-events: none;
            opacity: .52;
            mix-blend-mode: screen;
            background:
                repeating-linear-gradient(
                    82deg,
                    transparent 0 44px,
                    rgba(255, 223, 120, .24) 44px 47px,
                    transparent 47px 96px,
                    rgba(84, 190, 255, .18) 96px 99px,
                    transparent 99px 150px,
                    rgba(202, 106, 255, .17) 150px 153px,
                    transparent 153px 205px
                );
            animation: neonSweep 6.4s linear infinite;
            mask-image: radial-gradient(circle at 50% 45%, black 0%, black 62%, transparent 92%);
        }

        .arena-streaks {
            position: absolute;
            inset: -18%;
            z-index: 3;
            opacity: .56;
            pointer-events: none;
            background:
                repeating-linear-gradient(76deg, transparent 0 40px, rgba(255, 214, 107, .22) 41px 44px, transparent 45px 88px),
                repeating-linear-gradient(104deg, transparent 0 48px, rgba(29, 124, 255, .22) 49px 52px, transparent 53px 104px),
                repeating-linear-gradient(96deg, transparent 0 64px, rgba(206, 60, 255, .18) 65px 68px, transparent 69px 134px);
            transform: rotate(-2deg);
            animation: streakMove 7.5s linear infinite;
            mask-image: radial-gradient(circle at 50% 48%, black 0%, black 62%, transparent 92%);
        }

        .arena-stars {
            position: absolute;
            inset: -10%;
            z-index: 4;
            opacity: .90;
            pointer-events: none;
            background-image:
                radial-gradient(circle, rgba(255,255,255,.96) 0 1px, transparent 2px),
                radial-gradient(circle, rgba(255,214,107,.92) 0 1.2px, transparent 3px),
                radial-gradient(circle, rgba(168,85,247,.85) 0 1px, transparent 3px),
                radial-gradient(circle, rgba(56,189,248,.85) 0 1px, transparent 2.6px);
            background-size: 90px 90px, 145px 145px, 220px 220px, 170px 170px;
            background-position: 0 0, 40px 60px, 100px 20px, 120px 120px;
            animation: estrelas 5.8s linear infinite;
        }

        .flash-inicial {
            position: absolute;
            inset: 0;
            z-index: 5;
            pointer-events: none;
            background: radial-gradient(circle at 50% 42%, rgba(255,255,255,.38), rgba(255,214,107,.14) 16%, transparent 44%);
            opacity: 0;
            animation: flashInicial .85s ease-out both;
        }

        .audio-btn {
            position: fixed;
            right: 14px;
            top: 14px;
            z-index: 30;
            width: 42px;
            height: 42px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,.22);
            background: rgba(0,0,0,.24);
            color: #fff7d6;
            font-size: 18px;
            display: grid;
            place-items: center;
            backdrop-filter: blur(10px);
            cursor: pointer;
        }

        .palco {
            position: relative;
            z-index: 10;
            width: min(100%, 520px);
            min-height: 100vh;
            padding: max(14px, env(safe-area-inset-top)) 20px max(16px, env(safe-area-inset-bottom));
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
        }

        .topo {
            padding-top: 18px;
            animation: entradaTopo .7s ease .08s both;
        }

        .titulo {
            position: relative;
            z-index: 4;
            top: 34px;
            width: min(100%, 445px);
            margin: 0 auto 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: nowrap;
            white-space: nowrap;
            gap: .012em;
            text-align: center;
            font-family: Georgia, "Times New Roman", "Cinzel", "Trajan Pro", serif;
            font-size: clamp(50px, 11.7vw, 78px);
            line-height: .88;
            letter-spacing: .018em;
            font-weight: 700;
            text-transform: uppercase;
            color: #ffe875;
            background: linear-gradient(180deg, #fffef1 0%, #fff8c4 9%, #ffef84 21%, #ffe052 36%, #ffcc26 53%, #ffb915 68%, #ffd65a 84%, #fff2b0 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            -webkit-text-stroke: .35px rgba(72, 33, 0, .58);
            text-shadow:
                0 0 2px rgba(255, 250, 205, .48),
                0 0 8px rgba(255, 226, 100, .36),
                0 0 18px rgba(255, 184, 0, .24),
                0 1px 2px rgba(0, 0, 0, .12);
            filter:
                drop-shadow(0 0 5px rgba(255, 226, 102, .26))
                drop-shadow(0 1px 2px rgba(0, 0, 0, .12));
        }

        .titulo-char {
            display: inline-block;
            opacity: 0;
            transform: translateY(16px) scale(.9);
            font-family: inherit;
            font-weight: inherit;
            color: #ffe875;
            background: inherit;
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            -webkit-text-stroke: .35px rgba(72, 33, 0, .58);
            animation: tituloEntradaLetra .38s cubic-bezier(.18,.89,.32,1.28) forwards;
            animation-delay: calc(var(--char-index) * .042s);
            will-change: transform, opacity;
        }

        .titulo-char--space {
            width: .22em;
        }

        .subtitulo {
            position: relative;
            top: 28px;
            z-index: 4;
            margin: 6px auto 0;
            max-width: 420px;
            color: rgba(255, 247, 214, .88);
            font-size: 15px;
            line-height: 1.28;
            font-weight: 900;
            text-shadow: 0 2px 12px rgba(0,0,0,.48);
            animation: entradaSuave .62s ease .46s both;
        }

        .centro {
            position: relative;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 286px;
            margin: -20px 0 -18px;
        }

        .aura {
            position: absolute;
            width: 360px;
            height: 360px;
            border-radius: 50%;
            background:
                radial-gradient(circle,
                    rgba(255,255,255,.18) 0 2%,
                    rgba(255,214,107,.14) 8%,
                    rgba(139,44,255,.09) 36%,
                    transparent 68%);
            opacity: .36;
            filter: blur(13px);
            animation: auraPulse 3s ease-in-out infinite;
        }

        .halo {
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background:
                radial-gradient(circle,
                    rgba(255, 214, 107, .11) 0%,
                    rgba(255, 214, 107, .035) 36%,
                    transparent 72%);
            filter: blur(16px);
            opacity: .40;
            animation: haloPulse 3.2s ease-in-out infinite;
        }

        .escudo-wrap {
            position: relative;
            width: min(96vw, 435px);
            max-width: 435px;
            margin: -55px auto 0;
            border: 0;
            background: transparent;
            filter:
                drop-shadow(0 0 12px rgba(255, 221, 100, .14))
                drop-shadow(0 0 22px rgba(144, 72, 255, .10))
                drop-shadow(0 18px 28px rgba(0,0,0,.24));
            animation:
                entradaEscudo .85s cubic-bezier(.15, 1.32, .32, 1) .36s both,
                escudoFloatPremium 4.8s ease-in-out 1.3s infinite;
            transform-origin: center center;
            will-change: transform, filter;
        }

        .escudo-stage {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            isolation: isolate;
            border: 0;
            background: transparent;
        }

        .escudo-stage::before {
            content: "";
            position: absolute;
            inset: 15% 10% 12%;
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
            background:
                radial-gradient(circle at center,
                    rgba(255, 230, 120, .14) 0%,
                    rgba(119, 103, 255, .10) 30%,
                    rgba(61, 206, 255, .08) 52%,
                    rgba(196, 93, 255, .07) 72%,
                    rgba(0, 0, 0, 0) 100%);
            filter: blur(24px) saturate(112%);
            opacity: .48;
            animation: auraCromatica 6.2s ease-in-out infinite alternate;
        }

        .escudo-stage::after,
        .escudo-wrap::after,
        .escudo::after,
        .escudo-img::after {
            content: none !important;
            display: none !important;
        }

        .escudo {
            position: relative;
            z-index: 2;
            display: block;
            visibility: visible;
            opacity: 1;
            width: 100%;
            height: auto;
            object-fit: contain;
            transform-origin: center;
            animation: escudoBreathPremium 5.6s ease-in-out 1.4s infinite;
            will-change: transform, filter;
        }

        .resultado {
            margin-top: -8px;
            animation: entradaBaixo .72s ease .82s both;
        }

        .resultado.is-compacto {
            margin-top: -44px;
        }

        .valor-principal {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 174px;
            padding: 8px 20px 10px;
            border-radius: 22px;
            border: 1.5px solid rgba(255, 214, 107, .70);
            background:
                radial-gradient(circle at 50% 0%, rgba(255,214,107,.24), transparent 60%),
                linear-gradient(180deg, rgba(72, 26, 110, .80), rgba(29, 13, 44, .88));
            box-shadow:
                inset 0 0 26px rgba(255,255,255,.06),
                0 0 34px rgba(255, 214, 107, .24),
                0 10px 26px rgba(0,0,0,.28);
            backdrop-filter: blur(12px);
            transform-origin: center;
            animation: valorPop .56s cubic-bezier(.2, 1.35, .32, 1) 1s both;
        }

        .valor-numero {
            color: #fff0a8;
            font-weight: 1000;
            font-size: clamp(44px, 12vw, 66px);
            line-height: .92;
            text-shadow: 0 3px 22px rgba(255, 184, 50, .75);
            letter-spacing: -.04em;
        }

        .valor-label {
            margin-top: 4px;
            color: rgba(255, 247, 214, .82);
            font-size: 12px;
            letter-spacing: .15em;
            font-weight: 1000;
            text-transform: uppercase;
        }

        .recompensas {
            margin: 18px auto 0;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            max-width: 430px;
        }

        .recompensas.is-top {
            margin-top: 0;
            transform: translateY(-2px);
        }

        .reward {
            position: relative;
            min-height: 88px;
            padding: 12px 8px 10px;
            border-radius: 18px;
            border: 1.5px solid rgba(255, 214, 107, .44);
            background:
                radial-gradient(circle at 50% 0%, rgba(255, 214, 107, .20), transparent 58%),
                linear-gradient(180deg, rgba(57, 23, 91, .74), rgba(18, 8, 31, .86));
            box-shadow:
                inset 0 0 18px rgba(255,255,255,.04),
                0 8px 20px rgba(0,0,0,.24);
            transform: translateY(12px) scale(.92);
            opacity: 0;
            overflow: hidden;
            animation: lootReveal .58s cubic-bezier(.18, 1.28, .32, 1) forwards;
        }

        .reward::before {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 50% 18%, rgba(255,255,255,.11), transparent 45%);
            pointer-events: none;
            opacity: .70;
        }

        .reward::after {
            content: "";
            position: absolute;
            top: -16%;
            bottom: -16%;
            left: -48%;
            width: 38%;
            background: linear-gradient(
                115deg,
                rgba(255,255,255,0) 0%,
                rgba(255,255,255,.04) 18%,
                rgba(255,255,255,.36) 48%,
                rgba(255,255,255,.06) 78%,
                rgba(255,255,255,0) 100%
            );
            transform: skewX(-18deg) translateX(0);
            pointer-events: none;
            animation: rewardSweep 3.2s ease-in-out infinite;
        }

        .reward:nth-child(1) { animation-delay: 1.06s; }
        .reward:nth-child(2) { animation-delay: 1.18s; }
        .reward:nth-child(3) { animation-delay: 1.30s; }

        .reward:nth-child(1)::after { animation-delay: 1.40s; }
        .reward:nth-child(2)::after { animation-delay: 1.72s; }
        .reward:nth-child(3)::after { animation-delay: 2.04s; }

        .reward-icone {
            position: relative;
            z-index: 1;
            display: block;
            font-size: 27px;
            line-height: 1;
            margin-bottom: 8px;
            filter: drop-shadow(0 0 12px rgba(255, 214, 107, .32));
            transform-origin: center center;
            will-change: transform, filter, opacity;
        }

        .reward:nth-child(1) .reward-icone {
            animation: fxImpactoPulse 1.15s ease-in-out infinite;
        }

        .reward:nth-child(2) .reward-icone {
            animation: fxCoinFlip 1.8s ease-in-out infinite;
            transform-style: preserve-3d;
        }

        .reward:nth-child(3) .reward-icone {
            animation: fxStarSpin 3.2s linear infinite;
        }

        .reward-valor {
            position: relative;
            z-index: 1;
            display: block;
            color: #fff0a8;
            font-weight: 1000;
            font-size: 22px;
            line-height: 1;
            text-shadow: 0 0 12px rgba(255, 214, 107, .28);
        }

        .reward-label {
            position: relative;
            z-index: 1;
            display: block;
            margin-top: 5px;
            color: rgba(255, 247, 214, .74);
            font-size: 11px;
            font-weight: 850;
        }

        .acoes {
            margin-top: 24px;
            padding-top: 8px;
            padding-bottom: 6px;
            animation: entradaBaixo .76s ease 1.52s both;
        }

        .btn-continuar {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: min(100%, 430px);
            min-height: 70px;
            margin: 0 auto;
            border-radius: 22px;
            text-decoration: none;
            color: #fff7d6;
            font-size: clamp(19px, 5.8vw, 25px);
            font-weight: 1000;
            letter-spacing: .01em;
            text-transform: uppercase;
            border: 2px solid rgba(255, 214, 107, .86);
            background:
                linear-gradient(180deg, rgba(255,255,255,.20), transparent 45%),
                linear-gradient(90deg, #6d28d9, #b720d7, #7c3aed);
            box-shadow:
                0 0 0 4px rgba(255, 214, 107, .08),
                0 0 32px rgba(168, 85, 247, .58),
                inset 0 0 24px rgba(255, 214, 107, .12);
            overflow: hidden;
            animation: botaoPulse 1.8s ease-in-out infinite 1.8s;
        }

        .btn-continuar::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(115deg, transparent 0%, rgba(255,255,255,.42) 45%, transparent 70%);
            transform: translateX(-120%);
            animation: shine 2.7s ease-in-out infinite 2s;
        }

        .btn-continuar span {
            position: relative;
            z-index: 2;
            text-shadow: 0 2px 8px rgba(0,0,0,.35);
        }

        .escudo-placeholder {
            display: none !important;
            opacity: 0 !important;
            visibility: hidden !important;
        }

        @keyframes flashInicial {
            0% { opacity: 0; transform: scale(.78); }
            20% { opacity: .50; transform: scale(1); }
            100% { opacity: 0; transform: scale(1.14); }
        }

        @keyframes neonSweep {
            from { transform: translateY(-24px) rotate(0deg); }
            to { transform: translateY(42px) rotate(0deg); }
        }

        @keyframes streakMove {
            from { transform: translateY(-40px) rotate(-2deg); }
            to { transform: translateY(80px) rotate(-2deg); }
        }

        @keyframes estrelas {
            from { transform: translateY(0); }
            to { transform: translateY(80px); }
        }

        @keyframes auraPulse {
            0%, 100% { transform: scale(.96); opacity: .28; }
            50% { transform: scale(1.03); opacity: .42; }
        }

        @keyframes haloPulse {
            0%, 100% { transform: scale(.98); opacity: .28; }
            50% { transform: scale(1.04); opacity: .40; }
        }

        @keyframes entradaTopo {
            from { opacity: 0; transform: translateY(-18px) scale(.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        @keyframes entradaSuave {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes entradaEscudo {
            0% { opacity: 0; transform: scale(.45) translateY(26px); }
            60% { opacity: 1; transform: scale(1.08) translateY(-7px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }

        @keyframes entradaBaixo {
            from { opacity: 0; transform: translateY(22px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes valorPop {
            0% { opacity: 0; transform: scale(.72) translateY(10px); }
            70% { opacity: 1; transform: scale(1.08) translateY(0); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }

        @keyframes lootReveal {
            0% { opacity: 0; transform: translateY(12px) scale(.88) rotateX(18deg); }
            68% { opacity: 1; transform: translateY(-3px) scale(1.05) rotateX(0); }
            100% { opacity: 1; transform: translateY(0) scale(1) rotateX(0); }
        }

        @keyframes rewardSweep {
            0%, 18% {
                left: -48%;
                opacity: 0;
            }
            28% {
                opacity: 1;
            }
            54% {
                left: 118%;
                opacity: 1;
            }
            65%, 100% {
                left: 118%;
                opacity: 0;
            }
        }

        @keyframes botaoPulse {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-2px) scale(1.016); }
        }

        @keyframes shine {
            0%, 30% { transform: translateX(-130%); }
            60%, 100% { transform: translateX(130%); }
        }

        @keyframes tituloEntradaLetra {
            0% { opacity: 0; transform: translateY(18px) scale(.88); }
            65% { opacity: 1; transform: translateY(-4px) scale(1.04); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        @keyframes auraCromatica {
            0% {
                filter: blur(24px) hue-rotate(0deg) saturate(108%);
                transform: scale(.96);
                opacity: .34;
            }
            50% {
                filter: blur(26px) hue-rotate(60deg) saturate(118%);
                transform: scale(1.01);
                opacity: .46;
            }
            100% {
                filter: blur(28px) hue-rotate(120deg) saturate(126%);
                transform: scale(1.03);
                opacity: .50;
            }
        }

        @keyframes escudoFloatPremium {
            0%, 100% {
                transform: translate3d(0, 0, 0) scale(1);
                filter:
                    drop-shadow(0 0 12px rgba(255, 221, 100, .14))
                    drop-shadow(0 0 22px rgba(144, 72, 255, .10))
                    drop-shadow(0 18px 28px rgba(0,0,0,.24));
            }
            50% {
                transform: translate3d(0, -8px, 0) scale(1.012);
                filter:
                    drop-shadow(0 0 16px rgba(255, 232, 122, .18))
                    drop-shadow(0 0 26px rgba(144, 72, 255, .14))
                    drop-shadow(0 22px 32px rgba(0,0,0,.26));
            }
        }

        @keyframes escudoBreathPremium {
            0%, 100% { transform: scale(1) rotate(0deg); filter: saturate(1.02) brightness(1); }
            45% { transform: scale(1.018) rotate(-.35deg); filter: saturate(1.08) brightness(1.035); }
            70% { transform: scale(1.01) rotate(.28deg); filter: saturate(1.05) brightness(1.02); }
        }

        @keyframes fxImpactoPulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            35% { transform: scale(1.14); opacity: 1; }
            55% { transform: scale(.96); }
            75% { transform: scale(1.08); }
        }

        @keyframes fxCoinFlip {
            0% { transform: rotateY(0deg) scale(1); }
            35% { transform: rotateY(180deg) scale(1.06); }
            70%, 100% { transform: rotateY(360deg) scale(1); }
        }

        @keyframes fxStarSpin {
            0% { transform: rotate(0deg) scale(1); }
            50% { transform: rotate(180deg) scale(1.08); }
            100% { transform: rotate(360deg) scale(1); }
        }

        @media (max-height: 760px) {
            .palco {
                padding-top: 10px;
                padding-bottom: 12px;
            }

            .titulo {
                width: min(100%, 350px);
                font-size: clamp(38px, 10.8vw, 56px);
            }

            .subtitulo {
                margin-top: 8px;
                font-size: 13px;
            }

            .centro {
                min-height: 254px;
                margin-top: -18px;
                margin-bottom: -18px;
            }

            .aura {
                width: 320px;
                height: 320px;
            }

            .halo {
                width: 280px;
                height: 280px;
            }

            .escudo-wrap {
                width: min(88vw, 360px);
            }

            .resultado {
                margin-top: -14px;
            }

            .resultado.is-compacto {
                margin-top: -38px;
            }

            .recompensas {
                margin-top: 12px;
            }

            .recompensas.is-top {
                margin-top: 0;
            }

            .reward {
                min-height: 74px;
                padding: 10px 6px 8px;
            }

            .acoes {
                margin-top: 18px;
                padding-top: 6px;
            }

            .btn-continuar {
                min-height: 60px;
                font-size: 20px;
            }
        }

        @media (max-width: 430px) {
            html,
            body {
                min-height: 100svh;
                overflow-x: hidden;
            }

            body {
                background-attachment: scroll;
                background-position: center center;
            }

            .sucesso-game,
            main.sucesso-game {
                min-height: 100svh;
                padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 44px);
            }

            .palco {
                min-height: 100svh;
                padding-top: calc(env(safe-area-inset-top, 0px) + 14px);
                padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 44px);
            }

            .titulo {
                top: 30px;
                width: min(100%, 380px);
                font-size: clamp(45px, 12vw, 64px);
                line-height: .92;
                letter-spacing: .018em;
                margin-bottom: 6px;
                -webkit-text-stroke: .3px rgba(72, 33, 0, .54);
            }

            .titulo-char {
                -webkit-text-stroke: .3px rgba(72, 33, 0, .54);
            }

            .subtitulo {
                top: 24px;
                font-size: clamp(16px, 4.2vw, 20px);
                line-height: 1.15;
            }

            .centro {
                margin-top: -38px;
                margin-bottom: -34px;
            }

            .escudo-wrap,
            .escudo-stage {
                margin-top: -16px;
                margin-bottom: 6px;
                animation-duration: 5.2s;
            }

            .escudo,
            .escudo-img,
            img.escudo-img {
                max-width: min(88vw, 430px);
            }

            .resultado.is-compacto {
                margin-top: -58px;
            }

            .recompensas {
                position: relative;
                transform: translateY(-26px);
                margin-bottom: 0;
            }

            .acoes {
                position: relative;
                transform: translateY(-12px);
                margin-top: 0;
                padding-top: 10px;
                padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 26px);
            }

            .btn-continuar {
                max-width: calc(100vw - 32px);
                margin-bottom: 0;
            }
        }

        @media (max-width: 430px) and (max-height: 760px) {
            .titulo {
                top: 24px;
                font-size: clamp(42px, 11.5vw, 58px);
            }

            .subtitulo {
                top: 19px;
            }

            .centro {
                margin-top: -42px;
                margin-bottom: -38px;
            }

            .escudo,
            .escudo-img,
            img.escudo-img {
                max-width: min(82vw, 390px);
            }

            .escudo-wrap,
            .escudo-stage {
                margin-top: -22px;
                margin-bottom: 2px;
            }

            .recompensas {
                transform: translateY(-30px);
            }

            .acoes {
                transform: translateY(-16px);
                padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 28px);
            }
        }

        @media (max-width: 390px) {
            .palco {
                padding-left: 16px;
                padding-right: 16px;
            }

            .titulo {
                width: min(100%, 350px);
                font-size: clamp(42px, 12vw, 58px);
            }

            .reward-icone {
                font-size: 24px;
            }

            .reward-valor {
                font-size: 18px;
            }

            .btn-continuar {
                font-size: 18px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .escudo-wrap,
            .escudo,
            .titulo-char,
            .reward,
            .reward::after,
            .btn-continuar,
            .aura,
            .halo,
            .arena-streaks,
            .arena-stars,
            .sucesso-game::after,
            .reward-icone {
                animation: none !important;
                transform: none !important;
            }
        }
    </style>
</head>

<body class="tema-<?= h((string) ($tipo ?? 'impacto')) ?>">
    <main class="sucesso-game" style="--tema-cor: <?= h($corTema) ?>; --tema-glow: <?= h($glowTema) ?>;">
        <div class="arena-streaks"></div>
        <div class="arena-stars"></div>
        <div class="flash-inicial"></div>

        <button class="audio-btn" type="button" id="audioBtn" aria-label="Tocar som">🔊</button>

        <section class="palco">
            <header class="topo">
                <h1 class="titulo" aria-label="<?= h((string) $config['titulo']) ?>">
                    <?= sucesso_titulo_animado((string) $config['titulo']) ?>
                </h1>
                <p class="subtitulo"><?= h((string) $config['subtitulo']) ?></p>
            </header>

            <section class="centro" aria-label="Recompensa conquistada">
                <div class="aura"></div>
                <div class="halo"></div>

                <figure class="escudo-wrap escudo-stage">
                    <img
                        class="escudo escudo-img"
                        src="<?= h($assetCentral) ?>"
                        alt="<?= h((string) $config['selo']) ?>"
                        loading="eager"
                        decoding="async"
                        fetchpriority="high"
                    >
                </figure>
            </section>

            <section class="resultado <?= !$mostrarBlocoPrincipal ? 'is-compacto' : '' ?>">
                <?php if ($mostrarBlocoPrincipal): ?>
                    <div class="valor-principal">
                        <strong
                            class="valor-numero"
                            data-count-to="<?= h((string) $valorNumericoParaAnimar) ?>"
                            data-prefix="<?= h($prefixoValor) ?>"
                            data-static-value="<?= h($valorDisplay) ?>"
                        ><?= h($prefixoValor . $valorDisplay) ?></strong>
                        <span class="valor-label"><?= h((string) $config['label']) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($config['recompensas']) && is_array($config['recompensas'])): ?>
                    <div class="recompensas <?= !$mostrarBlocoPrincipal ? 'is-top' : '' ?>">
                        <?php foreach (array_slice($config['recompensas'], 0, 3) as $reward): ?>
                            <div class="reward">
                                <span class="reward-icone"><?= h((string) ($reward['icone'] ?? '⭐')) ?></span>
                                <strong class="reward-valor"><?= h((string) ($reward['valor'] ?? '+1')) ?></strong>
                                <span class="reward-label"><?= h((string) ($reward['label'] ?? 'Recompensa')) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="acoes">
                    <a class="btn-continuar" href="<?= h($continuar) ?>">
                        <span><?= h((string) $config['botao']) ?></span>
                    </a>
                </div>
            </section>
        </section>

        <?php if ($audioSrc): ?>
            <audio id="sucessoAudio" src="<?= h($audioSrc) ?>" preload="auto"></audio>
        <?php endif; ?>
    </main>

    <script>
        (function () {
            const audio = document.getElementById('sucessoAudio');
            const btn = document.getElementById('audioBtn');
            const numero = document.querySelector('.valor-numero');
            let playedOnce = false;

            function playSound() {
                if (!audio) return;

                try {
                    audio.currentTime = 0;
                    audio.volume = 0.9;

                    const promise = audio.play();

                    if (promise && typeof promise.catch === 'function') {
                        promise.catch(function () {});
                    }
                } catch (e) {}
            }

            function animateCounter() {
                if (!numero) return;

                const to = parseInt(numero.dataset.countTo || '0', 10);
                const prefix = numero.dataset.prefix || '';
                const staticValue = numero.dataset.staticValue || '';

                if (!to || /[^0-9]/.test(staticValue)) {
                    numero.textContent = prefix + staticValue;
                    return;
                }

                const duration = 620;
                const start = performance.now();

                function tick(now) {
                    const elapsed = now - start;
                    const progress = Math.min(1, elapsed / duration);
                    const eased = 1 - Math.pow(1 - progress, 3);
                    const current = Math.round(to * eased);

                    numero.textContent = prefix + current;

                    if (progress < 1) {
                        requestAnimationFrame(tick);
                    } else {
                        numero.textContent = prefix + to;
                    }
                }

                numero.textContent = prefix + '0';
                requestAnimationFrame(tick);
            }

            function tryAutoplay() {
                if (playedOnce) return;

                playedOnce = true;
                playSound();
            }

            setTimeout(tryAutoplay, 260);
            setTimeout(animateCounter, 980);

            if (btn) {
                btn.addEventListener('click', playSound);
            }

            document.addEventListener('click', function once() {
                playSound();
                document.removeEventListener('click', once);
            }, { once: true });

            document.addEventListener('touchstart', function onceTouch() {
                playSound();
                document.removeEventListener('touchstart', onceTouch);
            }, { once: true });
        })();
    </script>
</body>
</html>