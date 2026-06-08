<?php
declare(strict_types=1);

/**
 * Limpador de cache do app.elab.social
 *
 * Uso:
 * php /home/elab/app.elab.social/tools/limpar-cache.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acesso negado. Este script só pode ser executado pelo terminal.' . PHP_EOL);
}

ini_set('display_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$basePath = '/home/elab/app.elab.social';

$pathsParaLimpar = [
    $basePath . '/cache',
    $basePath . '/storage/cache',
    $basePath . '/storage/framework/cache',
    $basePath . '/storage/framework/views',
    $basePath . '/tmp',
];

$arquivosRemovidos = 0;
$pastasRemovidas = 0;
$erros = [];

function limparDiretorio(string $dir, int &$arquivosRemovidos, int &$pastasRemovidas, array &$erros): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);

    if ($items === false) {
        $erros[] = "Não foi possível ler: {$dir}";
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_link($path)) {
            if (@unlink($path)) {
                $arquivosRemovidos++;
            } else {
                $erros[] = "Não foi possível remover link: {$path}";
            }

            continue;
        }

        if (is_dir($path)) {
            limparDiretorio($path, $arquivosRemovidos, $pastasRemovidas, $erros);

            if (@rmdir($path)) {
                $pastasRemovidas++;
            } else {
                $erros[] = "Não foi possível remover pasta: {$path}";
            }

            continue;
        }

        if (is_file($path)) {
            if (@unlink($path)) {
                $arquivosRemovidos++;
            } else {
                $erros[] = "Não foi possível remover arquivo: {$path}";
            }
        }
    }
}

echo PHP_EOL;
echo "=== Limpando cache do app.elab.social ===" . PHP_EOL;
echo "Base: {$basePath}" . PHP_EOL;
echo PHP_EOL;

foreach ($pathsParaLimpar as $path) {
    if (!is_dir($path)) {
        echo "[ignorado] {$path} não existe" . PHP_EOL;
        continue;
    }

    echo "[limpando] {$path}" . PHP_EOL;
    limparDiretorio($path, $arquivosRemovidos, $pastasRemovidas, $erros);
}

if (function_exists('opcache_reset')) {
    $opcacheOk = @opcache_reset();

    if ($opcacheOk) {
        echo "[ok] OPcache resetado no contexto CLI" . PHP_EOL;
    } else {
        echo "[aviso] OPcache não foi resetado ou não está ativo no CLI" . PHP_EOL;
    }
} else {
    echo "[aviso] função opcache_reset não disponível" . PHP_EOL;
}

clearstatcache();

echo PHP_EOL;
echo "Arquivos removidos: {$arquivosRemovidos}" . PHP_EOL;
echo "Pastas removidas: {$pastasRemovidas}" . PHP_EOL;

if ($erros) {
    echo PHP_EOL;
    echo "Erros/avisos:" . PHP_EOL;

    foreach ($erros as $erro) {
        echo "- {$erro}" . PHP_EOL;
    }
}

echo PHP_EOL;
echo "Cache local limpo." . PHP_EOL;
echo "Teste agora com:" . PHP_EOL;
echo "https://app.elab.social/pessoas/perfil.php?v=" . time() . PHP_EOL;
echo PHP_EOL;
