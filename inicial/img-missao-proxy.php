<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$url = trim((string)($_GET['u'] ?? ''));

if ($url === '') {
    http_response_code(404);
    exit;
}

$decoded = base64_decode(strtr($url, '-_', '+/'), true);

if (is_string($decoded) && $decoded !== '') {
    $url = $decoded;
}

$parts = parse_url($url);
$scheme = strtolower((string)($parts['scheme'] ?? ''));
$host = strtolower((string)($parts['host'] ?? ''));

$allowedHosts = [
    'cdninstagram.com',
    'fbcdn.net',
    'scontent',
];

if ($scheme !== 'https' || $host === '') {
    http_response_code(400);
    exit;
}

$allowed = false;

foreach ($allowedHosts as $allowedHost) {
    if (str_contains($host, $allowedHost)) {
        $allowed = true;
        break;
    }
}

if (!$allowed) {
    http_response_code(403);
    exit;
}

$cacheDir = '/home/elab/app.elab.social/storage/cache/missao-img';

if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

$key = sha1($url);
$metaFile = $cacheDir . '/' . $key . '.json';
$imgFile = $cacheDir . '/' . $key . '.bin';

$ttl = 60 * 60 * 6;

if (is_file($imgFile) && is_file($metaFile) && (time() - filemtime($imgFile)) < $ttl) {
    $meta = json_decode((string)file_get_contents($metaFile), true);
    $contentType = is_array($meta) ? (string)($meta['content_type'] ?? 'image/jpeg') : 'image/jpeg';

    header('Content-Type: ' . $contentType);
    header('Cache-Control: public, max-age=21600');
    readfile($imgFile);
    exit;
}

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 4,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT => 18,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36',
    CURLOPT_HTTPHEADER => [
        'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
        'Referer: https://www.instagram.com/',
    ],
]);

$body = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$error = curl_error($ch);

curl_close($ch);

if ($body === false || $body === '' || $httpCode < 200 || $httpCode >= 300) {
    error_log('[img-missao-proxy] Falha ao buscar imagem: HTTP ' . $httpCode . ' erro=' . $error . ' url=' . $url);
    http_response_code(404);
    exit;
}

if (!str_starts_with(strtolower($contentType), 'image/')) {
    error_log('[img-missao-proxy] Content-Type inválido: ' . $contentType . ' url=' . $url);
    http_response_code(415);
    exit;
}

file_put_contents($imgFile, $body);
file_put_contents($metaFile, json_encode([
    'url' => $url,
    'content_type' => $contentType,
    'cached_at' => date('c'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

header('Content-Type: ' . $contentType);
header('Cache-Control: public, max-age=21600');
echo $body;
