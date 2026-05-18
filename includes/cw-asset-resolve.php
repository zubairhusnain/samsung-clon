<?php
declare(strict_types=1);

/**
 * Resolve a web asset path to a local filesystem path under the project root.
 */
function cw_resolve_local_asset_path(string $webPath): ?string
{
    if ($webPath === '' || str_contains($webPath, '..')) {
        return null;
    }

    if (!str_starts_with($webPath, '/')) {
        $webPath = '/' . $webPath;
    }

    $root = realpath(__DIR__ . '/..');
    if ($root === false) {
        return null;
    }

    $candidates = [];

    $direct = $root . $webPath;
    $candidates[] = $direct;

    if (str_starts_with($webPath, '/content/')) {
        $candidates[] = $root . '/is/content' . substr($webPath, strlen('/content'));
    }

    if (str_starts_with($webPath, '/content/samsung/assets/')) {
        $baseName = basename($webPath);
        $candidates[] = $root . '/assets/videos/' . $baseName;
        $candidates[] = $root . '/assets/images/' . $baseName;
        $suffix = substr($webPath, strlen('/content/samsung/assets/'));
        $candidates[] = $root . '/is/image/samsung/assets/' . $suffix;
        $candidates[] = $root . '/is/content/samsung/assets/' . $suffix;
    }

    if (str_starts_with($webPath, '/is/content/')) {
        $candidates[] = $root . '/content' . substr($webPath, strlen('/is/content'));
    }

    $baseName = basename($webPath);
    if ($baseName !== '' && $baseName !== '.' && $baseName !== '..') {
        $candidates[] = $root . '/assets/videos/' . $baseName;
        $candidates[] = $root . '/assets/images/' . $baseName;
    }

    $seen = [];
    foreach ($candidates as $candidate) {
        if (isset($seen[$candidate])) {
            continue;
        }
        $seen[$candidate] = true;
        if (is_file($candidate) && filesize($candidate) > 0) {
            return $candidate;
        }
    }

    return null;
}

/**
 * Best public URL path for a resolved asset (relative to site root, with leading slash).
 */
function cw_resolve_public_asset_path(string $webPath): ?string
{
    $local = cw_resolve_local_asset_path($webPath);
    if ($local === null) {
        return null;
    }

    $root = realpath(__DIR__ . '/..');
    if ($root === false) {
        return null;
    }

    $relative = substr($local, strlen($root));
    if ($relative === false || $relative === '') {
        return null;
    }

    return str_replace('\\', '/', $relative);
}

function cw_serve_resolved_asset(string $webPath): bool
{
    $local = cw_resolve_local_asset_path($webPath);
    if ($local === null) {
        return false;
    }

    $contentType = '';
    if (function_exists('mime_content_type')) {
        $mt = mime_content_type($local);
        if (is_string($mt) && $mt !== '') {
            $contentType = $mt;
        }
    }
    if ($contentType === '' && str_ends_with(strtolower($local), '.svg')) {
        $contentType = 'image/svg+xml';
    }
    if ($contentType === '' && str_ends_with(strtolower($local), '.mp4')) {
        $contentType = 'video/mp4';
    }
    if ($contentType === '' && str_ends_with(strtolower($local), '.webm')) {
        $contentType = 'video/webm';
    }

    if ($contentType !== '') {
        header('Content-Type: ' . $contentType);
    }
    header('Cache-Control: public, max-age=31536000');
    readfile($local);
    return true;
}
