#!/usr/bin/env python3
"""
Resolve asset URLs in page sources to paths that match files on disk.
Handles content/is/content, assets/videos, assets/images (hashed names), extensionless is/image files.
"""

from __future__ import annotations

import argparse
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCAN = [ROOT / 'pages', ROOT / 'index.php', ROOT / 'previous-index.php', ROOT / 'global']
LOG = ROOT / 'assets' / 'asset-path-fixes.log'

ASSET_DIRS = ('assets', 'is/image', 'is/content', 'content', 'etc.clientlibs', 'aemapi')
ASSET_PREFIXES = ('etc.clientlibs/', 'assets/', 'is/', 'content/', 'aemapi/')

ATTR_RE = re.compile(
    r'(?P<attr>href|src|action|content|data-src|data-desktop-src|data-mobile-src|'
    r'data-video-src|data-src-pc|data-src-mobile|data-src-tablet)\s*=\s*'
    r'(?P<q>["\'])'
    r'(?P<raw>(?:<\?php echo CW_BASE_URL; \?>\s*)?/?(?:copy-website/)?(?:etc\.clientlibs|assets|is|content|aemapi)[^"\']*)'
    r'(?P=q)',
    re.I,
)

HASH_SUFFIX = re.compile(r'-[0-9a-f]{8,12}$', re.I)


def file_stem(name: str) -> str:
    s = Path(name).name
    if '.' in s:
        s = Path(s).stem
    return HASH_SUFFIX.sub('', s)


def build_index() -> tuple[dict[str, str], dict[str, str]]:
    """exact path -> canonical path; stem -> best path (prefer is/image exact, then assets)."""
    exact: dict[str, str] = {}
    stem_map: dict[str, str] = {}

    def register(public: str, priority: int) -> None:
        public = '/' + public.lstrip('/')
        exact[public] = public
        st = file_stem(public)
        if not st:
            return
        prev = stem_map.get(st)
        if prev is None:
            stem_map[st] = public
            return
        # lower priority number = preferred
        prev_pri = stem_priority(prev)
        if priority < prev_pri:
            stem_map[st] = public

    def stem_priority(p: str) -> int:
        if p.startswith('/is/image/samsung/p6pim/'):
            return 0
        if p.startswith('/is/image/samsung/assets/'):
            return 1
        if p.startswith('/is/content/'):
            return 2
        if p.startswith('/assets/videos/'):
            return 3
        if p.startswith('/assets/images/'):
            return 4
        if p.startswith('/etc.clientlibs/'):
            return 5
        return 6

    for dir_name in ASSET_DIRS:
        base = ROOT / dir_name
        if not base.is_dir():
            continue
        for f in base.rglob('*'):
            if not f.is_file() or f.stat().st_size == 0:
                continue
            rel = f.relative_to(ROOT).as_posix()
            pri = stem_priority('/' + rel)
            register(rel, pri)

    return exact, stem_map


def clean_raw_url(raw: str) -> str:
    u = raw.strip()
    u = re.sub(r'^.*\?>\s*', '', u)  # strip php prefix
    u = u.replace('/copy-website/', '/')
    if not u.startswith('/'):
        u = '/' + u
    u = u.split('?', 1)[0].split('#', 1)[0]
    u = re.sub(r'(&quot;|&#0?38;).*$', '', u)
    return u


def resolve_path(web_path: str, exact: dict[str, str], stem_map: dict[str, str]) -> str | None:
    path = clean_raw_url(web_path)
    if not any(path.lower().startswith('/' + p) or path.lower().startswith(p) for p in ASSET_PREFIXES):
        return None

    if path in exact:
        return path

    candidates: list[str] = [path]

    if path.startswith('/content/'):
        candidates.append('/is/content' + path[len('/content') :])
    if path.startswith('/content/samsung/assets/'):
        name = Path(path).name
        suf = path[len('/content/samsung/assets/') :]
        candidates.extend([
            f'/assets/videos/{name}',
            f'/assets/images/{name}',
            f'/is/content/samsung/assets/{suf}',
            f'/is/image/samsung/assets/{suf}',
        ])
    if path.startswith('/is/content/'):
        candidates.append('/content' + path[len('/is/content') :])

    for c in candidates:
        if c in exact:
            return exact[c]

    st = file_stem(path)
    if st and st in stem_map:
        return stem_map[st]

    return None


def fix_text(text: str, exact: dict[str, str], stem_map: dict[str, str]) -> tuple[str, int]:
    changes = 0

    def repl(m: re.Match[str]) -> str:
        nonlocal changes
        raw = m.group('raw')
        current = clean_raw_url(raw)
        resolved = resolve_path(current, exact, stem_map)
        if resolved is None or resolved == current:
            if '<?php' in raw or 'CW_BASE_URL' in raw:
                return m.group(0)
            if any(current.lstrip('/').startswith(p) for p in ASSET_PREFIXES):
                changes += 1
                return f'{m.group("attr")}={m.group("q")}<?php echo CW_BASE_URL; ?>/{current.lstrip("/")}{m.group("q")}'
            return m.group(0)
        changes += 1
        return f'{m.group("attr")}={m.group("q")}<?php echo CW_BASE_URL; ?>{resolved}{m.group("q")}'

    return ATTR_RE.sub(repl, text), changes


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('--dry-run', action='store_true')
    args = parser.parse_args()

    print('Building local asset index...')
    exact, stem_map = build_index()
    print(f'Indexed {len(exact)} files, {len(stem_map)} stem keys')

    total = 0
    files = 0
    log_lines: list[str] = []

    for target in SCAN:
        paths = [target] if target.is_file() else list(target.rglob('*.php')) + list(target.rglob('*.html'))
        for path in paths:
            text = path.read_text(encoding='utf-8', errors='replace')
            if not any(p in text for p in ('etc.clientlibs', 'assets/', 'is/image', 'is/content', '/content/')):
                continue
            updated, n = fix_text(text, exact, stem_map)
            if n:
                files += 1
                total += n
                rel = path.relative_to(ROOT)
                print(f'{rel}: {n}')
                log_lines.append(f'{rel}: {n}')
                if not args.dry_run:
                    path.write_text(updated, encoding='utf-8')

    if not args.dry_run:
        LOG.write_text(
            f'Fixed {total} asset URL(s) in {files} file(s)\n' + '\n'.join(log_lines),
            encoding='utf-8',
        )
        print(f'Log: {LOG}')

    print(f'\n{"Would fix" if args.dry_run else "Fixed"} {total} URL(s) in {files} file(s)')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
