#!/usr/bin/env python3
"""Prefix root-relative Samsung asset URLs in PHP/HTML with CW_BASE_URL."""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCAN = [ROOT / 'pages', ROOT / 'index.php', ROOT / 'previous-index.php', ROOT / 'global']

ATTR_PATTERN = re.compile(
    r'(?P<attr>href|src|action|content|data-src|data-desktop-src|data-mobile-src|data-video-src|'
    r'data-src-pc|data-src-mobile|data-src-tablet)='
    r'(?P<q>["\'])'
    r'(?P<url>(?:etc\.clientlibs|assets|is/image|is/content|content|aemapi)/[^"\']*)'
    r'(?P=q)',
    re.I,
)


def fix_file(path: Path) -> bool:
    text = path.read_text(encoding='utf-8', errors='replace')
    if 'CW_BASE_URL' in text and 'etc.clientlibs' in text:
        # Already partially fixed; still fix any bare paths
        pass

    def repl(m: re.Match[str]) -> str:
        url = m.group('url')
        if '<?php' in url or 'CW_BASE_URL' in url:
            return m.group(0)
        return f'{m.group("attr")}={m.group("q")}<?php echo CW_BASE_URL; ?>/{url}{m.group("q")}'

    updated = ATTR_PATTERN.sub(repl, text)
    if updated == text:
        return False
    path.write_text(updated, encoding='utf-8')
    return True


def main() -> int:
    changed = 0
    for target in SCAN:
        paths = [target] if target.is_file() else list(target.rglob('*.php')) + list(target.rglob('*.html'))
        for path in paths:
            if fix_file(path):
                changed += 1
                print(path.relative_to(ROOT))
    print(f'Updated {changed} file(s)')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
