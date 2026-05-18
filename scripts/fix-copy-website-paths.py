#!/usr/bin/env python3
"""Replace legacy /copy-website/ asset paths with project base path."""

from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BASE = '/' + ROOT.name + '/'
OLD = '/copy-website/'
SCAN = [ROOT / 'assets', ROOT / 'etc.clientlibs', ROOT / 'pages', ROOT / 'index.php', ROOT / 'previous-index.php']


def main() -> int:
    changed = 0
    for target in SCAN:
        files = [target] if target.is_file() else target.rglob('*')
        for path in files:
            if not path.is_file():
                continue
            if path.suffix.lower() not in {'.js', '.css', '.json', '.php', '.html'}:
                continue
            text = path.read_text(encoding='utf-8', errors='replace')
            if OLD not in text:
                continue
            updated = text.replace(OLD, BASE)
            if updated != text:
                path.write_text(updated, encoding='utf-8')
                changed += 1
                print(path.relative_to(ROOT))
    print(f'Updated {changed} file(s) ({OLD} -> {BASE})')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
