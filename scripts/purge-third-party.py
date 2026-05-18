#!/usr/bin/env python3
"""Permanently remove third-party tracking/chat/analytics from page sources."""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCAN = [ROOT / 'pages', ROOT / 'index.php', ROOT / 'previous-index.php', ROOT / 'global']

THIRD_PARTY_HOST_PARTS = (
    'facebook.com', 'facebook.net', 'connect.facebook',
    'googletagmanager.com', 'google-analytics.com', 'googleadservices.com',
    'doubleclick.net', 'googlesyndication.com',
    'useinsider.com', 'api.useinsider', 'assets.api.useinsider',
    'sprinklr.com', 'live-chat-static.sprinklr', 'prod-live-chat.sprinklr',
    'snapchat.com', 'sc-static.net', 'tr.snapchat.com',
    'ads-twitter.com', 'static.ads-twitter',
    'decibelinsight.net', 'collection.decibelinsight', 'cdn.decibelinsight',
    'contentsquare.net', 't.contentsquare.net',
    'adobedtm.com', 'assets.adobedtm', 'smetrics.samsung.com',
    'medallia.com', 'digital-cloud-west.medallia',
    'mczbf.com', 'storage.googleapis.com/media-tagging',
    'beusable', 'kampyle.com', 'hotjar.com',
    'marketing.event-tracking.samsung.com',
    'bazaarvoice.com', 'api.bazaarvoice.com',
    'tiktok.com', 'linkedin.com/px', 'pinterest.com',
    'api-recommender.bigdata.samsung.com',
)

INLINE_MARKERS = (
    '_satellite',
    'poc_gtag',
    'fbq(',
    'decibelinsight',
    'window._da_',
    'sprchatsettings',
    'window.sprchat',
    'taglayer',
    '__beusablerumclient__',
    '__baclient__',
    'googletagmanager.com',
    'fbevents.js',
    'appmeasurement',
    'visitor.getinstance',
    's_gi(',
    's.tl(',
    's.t();',
    'eddl_bridge',
    'bridg_utils',
    'xdmput(',
    'alloy(',
    'boomr_api_key',
    'boomr_mq',
    'boomr.snippet',
    'gtagscriptele',
    'initcjtagscript',
    'cj.order',
    'percenttracking',
    'samsung.com s tracker',
    'medallia.com',
    'embed.js',
    'kampyle',
    'mdigital',
    'decibelinsight',
    'launch-0948a427feec',
    'smetrics.samsung',
)

KEEP_INLINE_HINTS = (
    'var digitaldata',
    'digitaldata.page',
    '__filedata__',
    'winhref',
    'pathstring',
    'setstaticglarefree',
    'beerslider',
    'gsap.',
)


def iter_files():
    for target in SCAN:
        if target.is_file():
            yield target
        else:
            yield from target.rglob('*.php')
            yield from target.rglob('*.html')


def is_third_party_url(url: str) -> bool:
    u = url.lower()
    if 'adobedtm' in u or 'launch-0948' in u:
        return True
    return any(p in u for p in THIRD_PARTY_HOST_PARTS)


def is_tracking_inline(body: str) -> bool:
    low = body.lower()
    if any(h in low for h in KEEP_INLINE_HINTS):
        # digitalData-only blocks can still mention eddl in comments — check markers first
        if 'var digitaldata' in low and '_satellite' not in low and 'poc_gtag' not in low:
            return False
    return any(m.lower() in low for m in INLINE_MARKERS)


def remove_scripts(html: str) -> str:
    def repl(m: re.Match[str]) -> str:
        tag = m.group(0)
        src_m = re.search(r'\bsrc=(["\'])(.*?)\1', tag, re.I | re.S)
        if src_m and is_third_party_url(src_m.group(2)):
            return ''
        inner_m = re.search(r'<script\b[^>]*>(.*?)</script>', tag, re.I | re.S)
        if inner_m and is_tracking_inline(inner_m.group(1)):
            return ''
        return tag

    prev = None
    while prev != html:
        prev = html
        html = re.sub(r'<script\b[^>]*>.*?</script>', repl, html, flags=re.I | re.S)
    return html


def remove_analytics_tail(html: str) -> str:
    patterns = (
        r'</motion-div>\s*<script>\s*let gtagScriptEle[\s\S]*?(?=</body>)',
        r'</div>\s*<script>\s*let gtagScriptEle[\s\S]*?(?=</body>)',
        r'<script>\s*let gtagScriptEle[\s\S]*?(?=</body>)',
        r'<script[^>]*>\s*//\s*Samsung\.com s Tracker[\s\S]*?(?=</body>)',
        r'<script>_satellite\[\"_runScript[\s\S]*?(?=</body>)',
        r'<script>\s*var percentTracking[\s\S]*?(?=</body>)',
        r'<!--\s*Decibel[\s\S]*?(?=</body>)',
    )
    for pat in patterns:
        html = re.sub(pat, '', html, flags=re.I | re.S)
    return html


def remove_debug_logs(html: str) -> str:
    html = re.sub(r'\s*console\.log\([\'"]isInIframe[^;]*;', '', html, flags=re.I)
    html = re.sub(r'\s*console\.log\("Service Worker registered[^;]*;', '', html, flags=re.I)
    html = re.sub(r'\s*console\.log\("beforeinstallprompt"\);', '', html, flags=re.I)
    return html


def remove_misc(html: str) -> str:
    html = re.sub(
        r'<iframe[^>]*src=["\']javascript:void\(0\)["\'][^>]*>\s*</iframe>\s*'
        r'(?:<script\b[^>]*\bsrc=[^>]+(?:snapchat|googletagmanager|facebook|insider|sprinklr|decibel|medallia|adobedtm|sc-static|ads-twitter|mczbf|beusable|contentsquare)[^>]*>\s*</script>\s*)+',
        '',
        html,
        flags=re.I | re.S,
    )
    patterns = (
        r'<!--\s*Launch Header Embed Code\s*-->.*?<!--\s*End Launch Header Embed Code\s*-->',
        r'<!--\s*End Adobe Target Flicker handling\s*-->',
        r'<!--\s*Adobe Target Flicker handling\s*-->',
        r'<!--\s*Excluding tagging-related scripts in Author mode\s*-->',
        r'<link[^>]*\brel=["\'](?:preconnect|dns-prefetch)["\'][^>]*(?:adobedtm|googletagmanager|facebook|useinsider|decibelinsight|contentsquare|snapchat|sprinklr|medallia|smetrics|doubleclick)[^>]*/?\s*>',
        r'<iframe[^>]*(?:insider|sprinklr|facebook|useinsider|medallia|decibel)[^>]*>.*?</iframe>',
        r'<iframe[^>]*id=["\']insider-worker["\'][^>]*>.*?</iframe>',
        r'<iframe[^>]*spr-live-chat[^>]*>.*?</iframe>',
        r'<link[^>]*useinsider[^>]*>',
        r'<style[^>]*ins-outer-stylesheet[^>]*>[\s\S]*?</style>',
        r'<style[^>]*ins-free-style[^>]*>[\s\S]*?</style>',
        r'<div[^>]*id=["\']spr-live-chat-app["\'][^>]*>[\s\S]*?</div>\s*<div[^>]*id=["\']spr-live-prompt-app["\'][^>]*>\s*</div>',
        r'<motion-div[^>]*id=["\']spr-live-chat-app["\'][^>]*>[\s\S]*?</motion-div>\s*<motion-div[^>]*id=["\']spr-live-prompt-app["\'][^>]*>\s*</motion-div>',
        r'<div[^>]*classname=["\']ins-ghost[^>]*>[\s\S]*?</div>',
        r'<cs-native-frame-holder[^>]*>[\s\S]*?</cs-native-frame-holder>',
        r'<script[^>]*\bsrc=[^>]*medallia[^>]*>[\s\S]*?</script>',
        r'<script[^>]*\bsrc=[^>]*resources\.digital-cloud-west\.medallia[^>]*>[\s\S]*?</script>',
    )
    for pat in patterns:
        html = re.sub(pat, '', html, flags=re.I | re.S)
    html = re.sub(
        r'window\.sprChatSettings[\s\S]*?sprinklr\.com[\s\S]*?(?=-->\s*\n|<div\b|<section\b|</body>)',
        '',
        html,
        flags=re.I,
    )
    html = re.sub(r'\n{3,}', '\n\n', html)
    return html


def purge(html: str) -> str:
    html = remove_misc(html)
    html = remove_analytics_tail(html)
    html = remove_scripts(html)
    html = remove_analytics_tail(html)
    html = remove_scripts(html)
    html = remove_debug_logs(html)
    return html


def main() -> int:
    changed = 0
    for path in iter_files():
        original = path.read_text(encoding='utf-8', errors='replace')
        updated = purge(original)
        if updated != original:
            path.write_text(updated, encoding='utf-8')
            changed += 1
            print(path.relative_to(ROOT))
    print(f'Updated {changed} file(s)')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
