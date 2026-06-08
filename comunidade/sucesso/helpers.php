<?php
declare(strict_types=1);

if (!function_exists('sucesso_h')) {
    function sucesso_h(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sucesso_param_texto')) {
    function sucesso_param_texto(string $key): string
    {
        return strtolower(trim((string) ($_GET[$key] ?? '')));
    }
}

if (!function_exists('sucesso_int_get')) {
    function sucesso_int_get(string $key, int $fallback): int
    {
        $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);

        if ($value === false || $value === null) {
            return $fallback;
        }

        return max(0, (int) $value);
    }
}

if (!function_exists('sucesso_asset_existe')) {
    function sucesso_asset_existe(string $webPath): bool
    {
        $basePath = '/home/elab/app.elab.social';
        $fullPath = $basePath . $webPath;

        return is_file($fullPath);
    }
}

if (!function_exists('sucesso_tipo_atual')) {
    function sucesso_tipo_atual(): string
    {
        $tipo = strtolower(trim((string) ($_GET['tipo'] ?? 'impacto')));

        $permitidos = [
            'impacto',
            'moedas',
            'xp',
            'combo',
            'nivel',
            'medalha',
            'conquista',
            'cadastrado',
        ];

        return in_array($tipo, $permitidos, true) ? $tipo : 'impacto';
    }
}

if (!function_exists('sucesso_preparar_animacao_valor')) {
    function sucesso_preparar_animacao_valor(string $valor): array
    {
        $valor = trim($valor);

        if ($valor === '') {
            return [
                'animar' => false,
                'numero' => 0,
                'prefixo' => '',
                'sufixo' => '',
                'inicial' => '',
            ];
        }

        if (!preg_match('/^([^\d]*)(\d+)(.*)$/u', $valor, $matches)) {
            return [
                'animar' => false,
                'numero' => 0,
                'prefixo' => '',
                'sufixo' => '',
                'inicial' => $valor,
            ];
        }

        $prefixo = trim((string) ($matches[1] ?? ''));
        $numero = (int) ($matches[2] ?? 0);
        $sufixo = trim((string) ($matches[3] ?? ''));

        $inicial = $prefixo !== '' ? $prefixo : '';

        if ($prefixo !== '' && $prefixo !== '+') {
            $inicial .= ' ';
        }

        $inicial .= '0';

        if ($sufixo !== '') {
            $inicial .= ' ' . $sufixo;
        }

        return [
            'animar' => true,
            'numero' => $numero,
            'prefixo' => $prefixo,
            'sufixo' => $sufixo,
            'inicial' => $inicial,
        ];
    }
}