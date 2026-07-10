<?php
require_once '../../config/config.php';

requirePermission(['master', 'vendedor']);

$arquivo = basename((string) ($_GET['file'] ?? ''));
$largura = max(80, min(360, (int) ($_GET['w'] ?? 180)));
$altura = max(80, min(360, (int) ($_GET['h'] ?? 180)));
$aceitaWebp = isset($_SERVER['HTTP_ACCEPT']) && stripos((string) $_SERVER['HTTP_ACCEPT'], 'image/webp') !== false;

if ($arquivo === '') {
    http_response_code(404);
    exit('Arquivo não informado.');
}

$extensao = strtolower((string) pathinfo($arquivo, PATHINFO_EXTENSION));
$extensoesImagem = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if (!in_array($extensao, $extensoesImagem, true)) {
    http_response_code(400);
    exit('Arquivo inválido.');
}

$origem = rtrim(UPLOAD_PATH, '/') . '/conteudos_digitais/' . $arquivo;
if (!is_file($origem)) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

$thumbDir = rtrim(UPLOAD_PATH, '/') . '/conteudos_digitais/thumbs/';
if (!is_dir($thumbDir)) {
    @mkdir($thumbDir, 0775, true);
}

$cacheKey = md5($arquivo . '|' . $largura . '|' . $altura . '|' . (string) @filemtime($origem));
$thumbExt = ($aceitaWebp && function_exists('imagewebp')) ? 'webp' : 'jpg';
$thumbPath = $thumbDir . $cacheKey . '.' . $thumbExt;
$mimeType = $thumbExt === 'webp' ? 'image/webp' : 'image/jpeg';

if (!function_exists('imagecreatetruecolor') || !function_exists('getimagesize')) {
    header('Content-Type: ' . (mime_content_type($origem) ?: 'application/octet-stream'));
    header('Content-Length: ' . (string) filesize($origem));
    readfile($origem);
    exit;
}

if (!is_file($thumbPath)) {
    $info = @getimagesize($origem);
    if (!$info || empty($info[0]) || empty($info[1])) {
        header('Content-Type: ' . (mime_content_type($origem) ?: 'application/octet-stream'));
        readfile($origem);
        exit;
    }

    [$srcW, $srcH] = $info;
    $createMap = [
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG => 'imagecreatefrompng',
        IMAGETYPE_GIF => 'imagecreatefromgif',
        IMAGETYPE_WEBP => 'imagecreatefromwebp',
    ];

    $tipo = $info[2] ?? 0;
    $createFn = $createMap[$tipo] ?? null;
    if (!$createFn || !function_exists($createFn)) {
        header('Content-Type: ' . (mime_content_type($origem) ?: 'application/octet-stream'));
        readfile($origem);
        exit;
    }

    $source = @$createFn($origem);
    if (!$source) {
        header('Content-Type: ' . (mime_content_type($origem) ?: 'application/octet-stream'));
        readfile($origem);
        exit;
    }

    $thumb = imagecreatetruecolor($largura, $altura);
    $background = imagecolorallocate($thumb, 248, 250, 252);
    imagefilledrectangle($thumb, 0, 0, $largura, $altura, $background);

    $escala = min($largura / $srcW, $altura / $srcH);
    $destW = max(1, (int) round($srcW * $escala));
    $destH = max(1, (int) round($srcH * $escala));
    $destX = (int) floor(($largura - $destW) / 2);
    $destY = (int) floor(($altura - $destH) / 2);

    imagecopyresampled($thumb, $source, $destX, $destY, 0, 0, $destW, $destH, $srcW, $srcH);
    if ($thumbExt === 'webp' && function_exists('imagewebp')) {
        imagewebp($thumb, $thumbPath, 80);
    } else {
        imagejpeg($thumb, $thumbPath, 78);
    }

    imagedestroy($source);
    imagedestroy($thumb);
}

$mtime = (int) @filemtime($thumbPath);
$etag = '"' . md5($cacheKey . '|' . $mtime . '|' . $thumbExt) . '"';

if (
    (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) === $etag) ||
    (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime((string) $_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $mtime)
) {
    header('HTTP/1.1 304 Not Modified');
    header('ETag: ' . $etag);
    header('Cache-Control: public, max-age=2592000, immutable');
    exit;
}

header('Content-Type: ' . $mimeType);
header('Cache-Control: public, max-age=2592000, immutable');
header('ETag: ' . $etag);
if ($mtime > 0) {
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
}
header('Content-Length: ' . (string) filesize($thumbPath));
readfile($thumbPath);
exit;
