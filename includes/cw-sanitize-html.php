<?php
declare(strict_types=1);

/**
 * Strip third-party tracking scripts from HTML before output (local clone).
 */
function cw_third_party_url(string $url): bool
{
    $u = strtolower($url);
    if (str_contains($u, 'adobedtm') || str_contains($u, 'launch-0948')) {
        return true;
    }
    $parts = [
        'facebook.com', 'facebook.net', 'connect.facebook',
        'googletagmanager.com', 'google-analytics.com', 'googleadservices.com',
        'doubleclick.net', 'googlesyndication.com',
        'useinsider.com', 'api.useinsider', 'assets.api.useinsider',
        'sprinklr.com', 'live-chat', 'prod-live-chat',
        'snapchat.com', 'sc-static.net', 'tr.snapchat.com',
        'ads-twitter.com', 'static.ads-twitter',
        'decibelinsight.net', 'collection.decibelinsight', 'cdn.decibelinsight',
        'contentsquare.net', 't.contentsquare.net',
        'adobedtm.com', 'assets.adobedtm', 'smetrics.samsung.com',
        'medallia.com', 'digital-cloud-west.medallia',
        'mczbf.com', 'storage.googleapis.com/media-tagging',
        'beusable', 'kampyle.com', 'hotjar.com',
        'marketing.event-tracking.samsung.com',
        'bazaarvoice.com', 'go-mpulse.net', 'mpulse',
        'connect.facebook.net/signals',
    ];
    foreach ($parts as $p) {
        if (str_contains($u, $p)) {
            return true;
        }
    }
    return false;
}

function cw_tracking_inline(string $body): bool
{
    $low = strtolower($body);
    if (str_contains($low, 'var digitaldata') && !str_contains($low, '_satellite') && !str_contains($low, 'poc_gtag')) {
        return false;
    }
    $markers = [
        '_satellite', 'poc_gtag', 'fbq(', 'decibelinsight', 'window._da_',
        'sprchatsettings', 'window.sprchat', 'taglayer', '__beusablerumclient__',
        '__baclient__', 'googletagmanager.com', 'fbevents.js', 'appmeasurement',
        'visitor.getinstance', 's_gi(', 's.tl(', 's.t();', 'eddl_bridge', 'bridg_utils',
        'xdmput(', 'alloy(', 'boomr_api_key', 'boomr_mq', 'boomr.snippet',
        'gtagscriptele', 'initcjtagscript', 'cj.order', 'percenttracking',
        'samsung.com s tracker', 'medallia.com', 'embed.js', 'kampyle', 'mdigital',
        'launch-0948a427feec', 'smetrics.samsung', 'sdk.prd.js', 'sdk.ins.js',
    ];
    foreach ($markers as $m) {
        if (str_contains($low, $m)) {
            return true;
        }
    }
    return false;
}

function cw_sanitize_html(string $html): string
{
    if (getenv('CW_SKIP_SANITIZE') === '1' || strlen($html) > 900000) {
        return $html;
    }

    $html = preg_replace(
        '~<iframe[^>]*src=["\']javascript:void\(0\)["\'][^>]*>\s*</iframe>\s*'
        . '(?:<script\b[^>]*\bsrc=[^>]+(?:snapchat|googletagmanager|facebook|insider|sprinklr|decibel|medallia|adobedtm|sc-static|ads-twitter|mczbf|beusable|contentsquare|go-mpulse)[^>]*>\s*</script>\s*)+~is',
        '',
        $html
    ) ?? $html;

    $html = preg_replace(
        '~<script\b[^>]*\bsrc=(["\'])(?://|https?:)?//[^"\']*go-mpulse\.net[^"\']*\1[^>]*>\s*</script>~is',
        '',
        $html
    ) ?? $html;

    $tailPatterns = [
        '~<script>\s*let gtagScriptEle[\s\S]*?(?=</body>)~i',
        '~<script[^>]*>\s*//\s*Samsung\.com s Tracker[\s\S]*?(?=</body>)~i',
        '~<script>_satellite\[\"_runScript[\s\S]*?(?=</body>)~i',
        '~<script>\s*var percentTracking[\s\S]*?(?=</body>)~i',
        '~<!--\s*Decibel[\s\S]*?(?=</body>)~i',
        '~<div[^>]*id=["\']spr-live-chat-app["\'][^>]*>[\s\S]*?</div>~i',
    ];
    foreach ($tailPatterns as $pat) {
        $html = preg_replace($pat, '', $html) ?? $html;
    }

    $repl = static function (array $m): string {
        $tag = $m[0];
        if (preg_match('~\bsrc=(["\'])(.*?)\1~is', $tag, $src) && cw_third_party_url($src[2])) {
            return '';
        }
        if (preg_match('~<script\b[^>]*>(.*?)</script>~is', $tag, $inner) && cw_tracking_inline($inner[1])) {
            return '';
        }
        return $tag;
    };

    $prev = null;
    while ($prev !== $html) {
        $prev = $html;
        $html = preg_replace_callback('~<script\b[^>]*>.*?</script>~is', $repl, $html) ?? $html;
    }

    $html = preg_replace('~\s*console\.log\([\'"]isInIframe[^;]*;~i', '', $html) ?? $html;
    $html = preg_replace('~\n{3,}~', "\n\n", $html) ?? $html;

    return $html;
}
