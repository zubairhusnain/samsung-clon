#!/usr/bin/env python3
"""
Rewrite /content/samsung/assets/... URLs to the local path where the file actually exists
(e.g. is/content/..., assets/videos/...).
"""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCAN = [ROOT / 'pages', ROOT / 'index.php', ROOT / 'previous-index.php', ROOT / 'global']

CONTENT_RE = re.compile(
    r'(?P<prefix>(?:<\?php echo CW_BASE_URL; \?>)?)(?P<url>/content/samsung/assets/[^\s"\'<>\)]+)',
    re.I,
)

EXT = (
    '.mp4', '.webm', '.m4v', '.mov',
    '.jpg', '.jpeg', '.png', '.gif', '.webp', '.svg', '.avif',
    '.woff', '.woff2', '.css', '.js',
)


def clean_url(url: str) -> str:
    u = url.split('?', 1)[0].split('#', 1)[0]
    low = u.lower()
    cut = len(u)
    for ext in EXT:
        i = low.find(ext)
        if i != -1:
            cut = min(cut, i + len(ext))
    u = u[:cut]
    if u.endswith('&quot;') or u.endswith('&#038;'):
        u = re.sub(r'(&quot;|&#0?38;)+$', '', u)
    return u


def resolve_public_path(web_path: str) -> str | None:
    web_path = clean_url(web_path)
    if not web_path.startswith('/content/'):
        return None

    root = ROOT
    candidates = [
        root / web_path.lstrip('/'),
        root / 'is/content' / web_path[len('/content/') :].lstrip('/'),
    ]
    if web_path.startswith('/content/samsung/assets/'):
        name = Path(web_path).name
        suffix = web_path[len('/content/samsung/assets/') :]
        candidates.extend([
            root / 'assets/videos' / name,
            root / 'assets/images' / name,
            root / 'is/image/samsung/assets' / suffix,
            root / 'is/content/samsung/assets' / suffix,
        ])

    seen: set[Path] = set()
    for local in candidates:
        if local in seen:
            continue
        seen.add(local)
        if local.is_file() and local.stat().st_size > 0:
            return '/' + local.relative_to(root).as_posix()
    return None


def fix_text(text: str) -> tuple[str, int]:
    changes = 0

    def repl(m: re.Match[str]) -> str:
        nonlocal changes
        old = clean_url(m.group('url'))
        new = resolve_public_path(old)
        if new is None or new == old:
            return m.group(0)
        changes += 1
        return m.group('prefix') + new

    updated = CONTENT_RE.sub(repl, text)
    return updated, changes


def main() -> int:
    total = 0
    files = 0
    for target in SCAN:
        paths = [target] if target.is_file() else list(target.rglob('*.php')) + list(target.rglob('*.html'))
        for path in paths:
            text = path.read_text(encoding='utf-8', errors='replace')
            if '/content/samsung/assets/' not in text:
                continue
            updated, n = fix_text(text)
            if n:
                path.write_text(updated, encoding='utf-8')
                files += 1
                total += n
                print(f'{path.relative_to(ROOT)}: {n} path(s)')
    print(f'Updated {files} file(s), {total} URL(s) total')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
