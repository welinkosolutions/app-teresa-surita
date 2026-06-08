<?php
declare(strict_types=1);

/**
 * ======================================================
 * SUCESSO V2 - ASSETS OFICIAIS
 * ======================================================
 * Escudos estáticos:
 * /comunidade/sucesso/estaticos/{numero}.png
 *
 * Áudios:
 * /comunidade/sucesso/audio/{arquivo}.mp3
 */

if (!function_exists('sucesso_assets_estaticos')) {
    function sucesso_assets_estaticos(): array
    {
        return [
            'lideranca'              => '/comunidade/sucesso/estaticos/1.png',
            'evoluiu'                => '/comunidade/sucesso/estaticos/2.png',
            'top10'                  => '/comunidade/sucesso/estaticos/3.png',
            'top1_engajamento'       => '/comunidade/sucesso/estaticos/4.png',
            'time_cresceu'           => '/comunidade/sucesso/estaticos/5.png',
            'prata'                  => '/comunidade/sucesso/estaticos/6.png',
            'subiu_nivel'            => '/comunidade/sucesso/estaticos/7.png',
            'ouro'                   => '/comunidade/sucesso/estaticos/8.png',
            'participante'           => '/comunidade/sucesso/estaticos/9.png',
            'mobilizacao'            => '/comunidade/sucesso/estaticos/10.png',
            'missao_concluida'       => '/comunidade/sucesso/estaticos/11.png',
            'medalha_conquistada'    => '/comunidade/sucesso/estaticos/12.png',
            'lideranca_alt'          => '/comunidade/sucesso/estaticos/13.png',
            'lenda'                  => '/comunidade/sucesso/estaticos/14.png',
            'iniciante'              => '/comunidade/sucesso/estaticos/15.png',
            'gerou_impacto'          => '/comunidade/sucesso/estaticos/16.png',
            'faturou_alto'           => '/comunidade/sucesso/estaticos/17.png',
            'esta_no_jogo'           => '/comunidade/sucesso/estaticos/18.png',
            'elite'                  => '/comunidade/sucesso/estaticos/19.png',
            'engajamento'            => '/comunidade/sucesso/estaticos/20.png',
            'bronze'                 => '/comunidade/sucesso/estaticos/21.png',
            'conquista_desbloqueada' => '/comunidade/sucesso/estaticos/22.png',
        ];
    }
}

if (!function_exists('sucesso_assets_animados')) {
    function sucesso_assets_animados(): array
    {
        return [
            'chat'     => '/comunidade/sucesso/animacoes/chat.webp',
            'presente' => '/comunidade/sucesso/animacoes/presente.webp',
            'sete_x'   => '/comunidade/sucesso/animacoes/7x.webp',
            'coroa'    => '/comunidade/sucesso/animacoes/coroa.webp',
            'trofeu'   => '/comunidade/sucesso/animacoes/trofeu.webp',
            'escudo'   => '/comunidade/sucesso/animacoes/escudo.webp',
            'primeiro' => '/comunidade/sucesso/animacoes/1lugar.webp',
            'segundo'  => '/comunidade/sucesso/animacoes/2lugar.webp',
            'terceiro' => '/comunidade/sucesso/animacoes/3lugar.webp',
        ];
    }
}

if (!function_exists('sucesso_assets_audio')) {
    function sucesso_assets_audio(): array
    {
        return [
            'award'              => '/comunidade/sucesso/audio/award.mp3?v=1',
            'cartoon_conclusion' => '/comunidade/sucesso/audio/cartoon-conclusion.mp3?v=1',
            'cartoon_intro'      => '/comunidade/sucesso/audio/cartoon-intro.mp3?v=1',
            'dramatic_game_over' => '/comunidade/sucesso/audio/dramatic-game-over.mp3?v=1',
            'fanfare_game'       => '/comunidade/sucesso/audio/fanfare-game.mp3?v=1',
            'game_over'          => '/comunidade/sucesso/audio/game-over.mp3?v=1',
            'game_result'        => '/comunidade/sucesso/audio/game-result.mp3?v=1',
            'game_reward'        => '/comunidade/sucesso/audio/game-reward.mp3?v=1',
            'game_score'         => '/comunidade/sucesso/audio/game-score.mp3?v=1',
            'game_success'       => '/comunidade/sucesso/audio/game-success.mp3?v=1',
            'game_victory'       => '/comunidade/sucesso/audio/game-victory.mp3?v=1',
            'increment'          => '/comunidade/sucesso/audio/increment.mp3?v=1',
            'notification'       => '/comunidade/sucesso/audio/notification.mp3?v=1',
            'win_coin'           => '/comunidade/sucesso/audio/win-coin.mp3?v=1',
        ];
    }
}

if (!function_exists('sucesso_audio_por_tipo')) {
    function sucesso_audio_por_tipo(string $tipo): string
    {
        $audios = sucesso_assets_audio();

        $mapa = [
            'impacto'    => $audios['game_score'],
            'moedas'     => $audios['win_coin'],
            'xp'         => $audios['increment'],
            'combo'      => $audios['game_success'],
            'nivel'      => $audios['fanfare_game'],
            'medalha'    => $audios['award'],
            'conquista'  => $audios['game_victory'],
            'cadastrado' => $audios['notification'],
            'missao'     => $audios['cartoon_conclusion'],
            'ranking'    => $audios['fanfare_game'],
            'bronze'     => $audios['game_reward'],
            'prata'      => $audios['game_reward'],
            'ouro'       => $audios['game_victory'],
            'elite'      => $audios['award'],
            'lenda'      => $audios['game_victory'],
            'erro'       => $audios['dramatic_game_over'],
        ];

        return $mapa[$tipo] ?? $audios['game_result'];
    }
}

if (!function_exists('sucesso_escudo_por_tipo')) {
    function sucesso_escudo_por_tipo(string $tipo): ?string
    {
        $escudos = sucesso_assets_estaticos();

        $mapa = [
            'impacto'    => $escudos['gerou_impacto'],
            'moedas'     => $escudos['faturou_alto'],
            'xp'         => $escudos['evoluiu'],
            'combo'      => $escudos['engajamento'],
            'nivel'      => $escudos['subiu_nivel'],
            'medalha'    => $escudos['medalha_conquistada'],
            'conquista'  => $escudos['conquista_desbloqueada'],
            'cadastrado' => $escudos['time_cresceu'],
            'missao'     => $escudos['missao_concluida'],
            'ranking'    => $escudos['top10'],
            'bronze'     => $escudos['bronze'],
            'prata'      => $escudos['prata'],
            'ouro'       => $escudos['ouro'],
            'elite'      => $escudos['elite'],
            'lenda'      => $escudos['lenda'],
            'iniciante'  => $escudos['iniciante'],
            'participante' => $escudos['participante'],
            'lideranca'  => $escudos['lideranca'],
            'mobilizacao'=> $escudos['mobilizacao'],
        ];

        return $mapa[$tipo] ?? $escudos['esta_no_jogo'];
    }
}

if (!function_exists('sucesso_asset_central')) {
    function sucesso_asset_central(string $tipo, string $origem = '', string $acao = ''): ?string
    {
        $tipo = strtolower(trim($tipo));

        $mapa = [
            'impacto' => '/comunidade/sucesso/estaticos/16.png',
            'moedas' => '/comunidade/sucesso/estaticos/17.png',
            'xp' => '/comunidade/sucesso/estaticos/2.png',
            'combo' => '/comunidade/sucesso/estaticos/18.png',
            'nivel' => '/comunidade/sucesso/estaticos/7.png',
            'medalha' => '/comunidade/sucesso/estaticos/12.png',
            'conquista' => '/comunidade/sucesso/estaticos/22.png',
            'cadastrado' => '/comunidade/sucesso/estaticos/5.png',
            'missao' => '/comunidade/sucesso/estaticos/11.png',
            'ranking' => '/comunidade/sucesso/estaticos/4.png',
            'bronze' => '/comunidade/sucesso/estaticos/21.png',
            'prata' => '/comunidade/sucesso/estaticos/6.png',
            'ouro' => '/comunidade/sucesso/estaticos/8.png',
            'elite' => '/comunidade/sucesso/estaticos/19.png',
            'lenda' => '/comunidade/sucesso/estaticos/14.png',
        ];

        $asset = $mapa[$tipo] ?? $mapa['impacto'];

        if (function_exists('sucesso_asset_existe') && sucesso_asset_existe($asset)) {
            return $asset . '?v=estatico-premium-1';
        }

        $path = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? '/home/elab/app.elab.social'), '/') . $asset;

        if (is_file($path)) {
            return $asset . '?v=estatico-premium-1';
        }

        return $mapa['impacto'] . '?v=estatico-premium-1';
    }
}

if (!function_exists('sucesso_tema_por_tipo')) {
    function sucesso_tema_por_tipo(string $tipo): array
    {
        $temas = [
            'impacto' => [
                'classe' => 'tema-impacto',
                'cor' => '#22c55e',
                'glow' => 'rgba(34, 197, 94, .45)',
            ],
            'moedas' => [
                'classe' => 'tema-moedas',
                'cor' => '#f59e0b',
                'glow' => 'rgba(245, 158, 11, .50)',
            ],
            'xp' => [
                'classe' => 'tema-xp',
                'cor' => '#3b82f6',
                'glow' => 'rgba(59, 130, 246, .45)',
            ],
            'combo' => [
                'classe' => 'tema-combo',
                'cor' => '#ef4444',
                'glow' => 'rgba(239, 68, 68, .45)',
            ],
            'nivel' => [
                'classe' => 'tema-nivel',
                'cor' => '#8b5cf6',
                'glow' => 'rgba(139, 92, 246, .45)',
            ],
            'medalha' => [
                'classe' => 'tema-medalha',
                'cor' => '#eab308',
                'glow' => 'rgba(234, 179, 8, .50)',
            ],
            'conquista' => [
                'classe' => 'tema-conquista',
                'cor' => '#06b6d4',
                'glow' => 'rgba(6, 182, 212, .45)',
            ],
            'cadastrado' => [
                'classe' => 'tema-cadastrado',
                'cor' => '#14b8a6',
                'glow' => 'rgba(20, 184, 166, .45)',
            ],
            'missao' => [
                'classe' => 'tema-missao',
                'cor' => '#2563eb',
                'glow' => 'rgba(37, 99, 235, .45)',
            ],
            'ranking' => [
                'classe' => 'tema-ranking',
                'cor' => '#f97316',
                'glow' => 'rgba(249, 115, 22, .45)',
            ],
            'bronze' => [
                'classe' => 'tema-bronze',
                'cor' => '#b45309',
                'glow' => 'rgba(180, 83, 9, .45)',
            ],
            'prata' => [
                'classe' => 'tema-prata',
                'cor' => '#94a3b8',
                'glow' => 'rgba(148, 163, 184, .50)',
            ],
            'ouro' => [
                'classe' => 'tema-ouro',
                'cor' => '#facc15',
                'glow' => 'rgba(250, 204, 21, .55)',
            ],
            'elite' => [
                'classe' => 'tema-elite',
                'cor' => '#a855f7',
                'glow' => 'rgba(168, 85, 247, .50)',
            ],
            'lenda' => [
                'classe' => 'tema-lenda',
                'cor' => '#ec4899',
                'glow' => 'rgba(236, 72, 153, .50)',
            ],
        ];

        return $temas[$tipo] ?? [
            'classe' => 'tema-padrao',
            'cor' => '#2563eb',
            'glow' => 'rgba(37, 99, 235, .45)',
        ];
    }
}