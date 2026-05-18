<?php
declare(strict_types=1);

function cw_remote_asset_sources(string $path): array
{
    $sources = [];
    if (str_starts_with($path, '/etc.clientlibs/') || str_starts_with($path, '/assets/')) {
        $sources[] = 'https://www.samsung.com/pk';
        $sources[] = 'https://www.samsung.com';
    }
    return $sources;
}

function cw_remote_asset_fetch(string $path, string $localFs): bool
{
    if (str_contains($path, '..')) {
        return false;
    }

    foreach (cw_remote_asset_sources($path) as $origin) {
        $remoteUrl = rtrim($origin, '/') . $path;
        $tmp = $localFs . '.tmp';
        $dir = dirname($localFs);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            continue;
        }

        $fh = @fopen($tmp, 'wb');
        if ($fh === false) {
            continue;
        }

        $ch = curl_init($remoteUrl);
        curl_setopt($ch, CURLOPT_FILE, $fh);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; samsung-clon-local/1.0)');
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        $ok = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fh);

        if ($ok && $httpCode >= 200 && $httpCode < 300 && is_file($tmp) && filesize($tmp) > 0) {
            rename($tmp, $localFs);
            return true;
        }

        if (is_file($tmp)) {
            unlink($tmp);
        }
    }

    return false;
}

function cw_remote_asset_serve(string $path): bool
{
    if (str_contains($path, '..')) {
        http_response_code(400);
        return true;
    }

    $localFs = __DIR__ . '/..' . $path;
    if (!is_file($localFs)) {
        cw_remote_asset_fetch($path, $localFs);
    }

    if (!is_file($localFs)) {
        return false;
    }

    $contentType = '';
    if (function_exists('mime_content_type')) {
        $mt = mime_content_type($localFs);
        if (is_string($mt)) {
            $contentType = $mt;
        }
    }
    if ($contentType === '' && str_ends_with($path, '.svg')) {
        $contentType = 'image/svg+xml';
    }
    if ($contentType !== '') {
        header('Content-Type: ' . $contentType);
    }
    header('Cache-Control: public, max-age=31536000');
    readfile($localFs);
    return true;
}
