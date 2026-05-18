#!/usr/bin/env python3
"""Download trade-in icons referenced by PreloadStep2Icons and buy-direct-get-more."""

from __future__ import annotations

import re
import ssl
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
COMPACT = ROOT / 'etc.clientlibs/samsung/clientlibs/consumer/global/clientlib-templates/page-static-gnb-hq/compact.min.f139926faf82c04da28fdb463d89b6d7.js'
IMAGES = ROOT / 'etc.clientlibs/samsung/clientlibs/consumer/global/clientlib-common/resources/images'
ORIGINS = ['https://www.samsung.com/pk', 'https://www.samsung.com']


def urls_from_compact() -> list[str]:
    text = COMPACT.read_text(encoding='utf-8', errors='replace')
    m = re.search(r'this\.urls="([^"]+)"', text)
    if not m:
        return []
    raw = m.group(1).split()
    paths = []
    for u in raw:
        idx = u.find('/etc.clientlibs/')
        if idx >= 0:
            paths.append(u[idx:])
    return paths


def download(path: str) -> bool:
    local = ROOT / path.lstrip('/')
    if local.is_file() and local.stat().st_size > 0:
        return True
    local.parent.mkdir(parents=True, exist_ok=True)
    ctx = ssl.create_default_context()
    for origin in ORIGINS:
        url = origin.rstrip('/') + path
        try:
            with urllib.request.urlopen(
                urllib.request.Request(url, headers={'User-Agent': 'samsung-clon-mirror/1.0'}),
                timeout=45,
                context=ctx,
            ) as resp:
                data = resp.read()
            if resp.status >= 200 and resp.status < 300 and data:
                local.write_bytes(data)
                print(f'OK {path}')
                return True
        except Exception as e:
            last = str(e)
    print(f'FAIL {path} ({last})')
    return False


def main() -> int:
    paths = urls_from_compact()
  # Also scan images dir references in buy-direct-get-more preloads
    extra = [
        'ico-tradein-info-prepaid.svg',
        'ico-tradein-info-device.svg',
        'ico-tradein-info-free-delivery.svg',
        'icon-tradein-power.svg',
    ]
    for name in extra:
        paths.append(f'/etc.clientlibs/samsung/clientlibs/consumer/global/clientlib-common/resources/images/{name}')

    paths = sorted(set(paths))
    ok = sum(download(p) for p in paths)
    print(f'Downloaded {ok}/{len(paths)}')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
