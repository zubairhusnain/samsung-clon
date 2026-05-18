<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

echo 'PHP ' . PHP_VERSION . "\n";
echo 'str_starts_with native: ' . (function_exists('str_starts_with') ? 'yes' : 'no') . "\n\n";

$files = [
    'base-url.php',
    'router.php',
    'index.php',
    'includes/cw-php-polyfill.php',
    'includes/cw-sanitize-html.php',
    'includes/cw-asset-resolve.php',
    'includes/cw-remote-asset.php',
    'pages/smartphones/all-smartphones/index.php',
];

foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    echo $f . ': ' . (is_file($path) ? 'ok (' . filesize($path) . ' bytes)' : 'MISSING') . "\n";
}

echo "\nDOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? '(unset)') . "\n";
echo "__DIR__: " . __DIR__ . "\n";
echo "realpath match docroot: ";
$root = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
$here = realpath(__DIR__);
echo ($root !== false && $here !== false && $root === $here) ? 'yes' : 'no';
echo "\n\n";

try {
    require_once __DIR__ . '/includes/cw-php-polyfill.php';
    require_once __DIR__ . '/base-url.php';
    echo 'CW_BASE_URL=' . CW_BASE_URL . "\n";
    echo 'cw_install_base_path=' . cw_install_base_path() . "\n";
    echo "base-url.php loaded OK\n";
} catch (Throwable $e) {
    echo "base-url.php FAILED: " . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
}
