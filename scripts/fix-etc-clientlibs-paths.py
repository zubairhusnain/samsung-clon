#!/usr/bin/env python3
"""Prefix absolute /etc.clientlibs/ paths in clientlib JS/CSS for subpath hosting."""

from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BASE = '/' + ROOT.name + '/'
SCAN = ROOT / 'etc.clientlibs'


def fix_text(text: str) -> str:
    # Already prefixed
    if BASE in text:
        return text
    updated = text.replace('"/etc.clientlibs/', f'"{BASE}etc.clientlibs/')
    updated = updated.replace('"/assets/', f'"{BASE}assets/')
    updated = updated.replace(' /etc.clientlibs/', f' {BASE}etc.clientlibs/')
    updated = updated.replace(' /assets/', f' {BASE}assets/')
    updated = updated.replace("'/etc.clientlibs/", f"'{BASE}etc.clientlibs/")
    return updated


def main() -> int:
    changed = 0
    for path in SCAN.rglob('*'):
        if not path.is_file():
            continue
        if path.suffix.lower() not in {'.js', '.css', '.json'}:
            continue
        text = path.read_text(encoding='utf-8', errors='replace')
        if '/etc.clientlibs/' not in text and '/assets/' not in text:
            continue
        updated = fix_text(text)
        if updated != text:
            path.write_text(updated, encoding='utf-8')
            changed += 1
    print(f'Updated {changed} file(s) under etc.clientlibs/')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
