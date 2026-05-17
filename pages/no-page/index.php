<?php
declare(strict_types=1);

ob_start();
include __DIR__ . '/../index.php';
$template = (string)ob_get_clean();

$template = preg_replace('~<title>.*?</title>~is', '<title>Page Not Available | Samsung Pakistan</title>', $template, 1) ?? $template;

$from = $_GET['from'] ?? '';
if (is_string($from)) {
    $from = trim($from);
} else {
    $from = '';
}
if ($from === '') {
    $from = '/';
}
if ($from[0] !== '/') {
    $from = '/' . $from;
}
$fromSafe = htmlspecialchars($from, ENT_QUOTES, 'UTF-8');

$messageHtml = '<div id="content" role="main">'
    . '<main style="max-width:1440px;margin:0 auto;min-height:70vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:80px 24px;text-align:center;">'
    . '<h1 style="font-size:56px;line-height:1.05;margin:0 0 16px;">Page not available</h1>'
    . '<p style="font-size:16px;line-height:1.4;margin:0 0 12px;opacity:.8;">' . $fromSafe . '</p>'
    . '<p style="font-size:22px;line-height:1.5;margin:0 0 32px;">We are doing something amazing for our clients</p>'
    . '<p style="margin:0;"><a class="cta cta--contained" href="' . CW_BASE_URL . '/">Back to home</a></p>'
    . '</main>'
    . '</div>';

$contentPos = stripos($template, '<div id="content"');
$footerPos = stripos($template, '<footer');
if ($contentPos !== false && $footerPos !== false && $footerPos > $contentPos) {
    $template = substr($template, 0, $contentPos) . $messageHtml . substr($template, $footerPos);
}

echo $template;
