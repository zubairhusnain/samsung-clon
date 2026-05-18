#!/usr/bin/env python3
"""
Discover asset URLs referenced across the local Samsung PK mirror and download missing files.
"""

from __future__ import annotations

import argparse
import json
import re
import ssl
import time
import urllib.error
import urllib.request
from collections import deque
from pathlib import Path
from urllib.parse import urljoin, urlparse

ROOT = Path(__file__).resolve().parents[1]
MANIFEST = ROOT / 'assets' / 'mirror-manifest.json'
FAILED_LOG = ROOT / 'assets' / 'mirror-failed.txt'

SCAN_DIRS = (
    ROOT / 'pages',
    ROOT / 'global',
    ROOT / 'etc.clientlibs',
    ROOT / 'assets',
)
SCAN_FILES = (ROOT / 'index.php', ROOT / 'previous-index.php')

LOCAL_PREFIXES = (
    '/etc.clientlibs/',
    '/assets/',
    '/is/image/',
    '/is/content/',
    '/content/',
    '/aemapi/',
)

ORIGINS = {
    'etc': ['https://www.samsung.com/pk', 'https://www.samsung.com'],
    'assets': ['https://www.samsung.com/pk', 'https://www.samsung.com'],
    'is': ['https://images.samsung.com', 'https://stg-images.samsung.com'],
    'content': ['https://images.samsung.com', 'https://www.samsung.com'],
    'aemapi': ['https://www.samsung.com/pk', 'https://www.samsung.com'],
}

TEXT_EXTS = {'.php', '.html', '.htm', '.css', '.js', '.json', '.svg', '.xml'}

DOWNLOAD_EXTS = {
    '.svg', '.png', '.jpg', '.jpeg', '.gif', '.webp', '.avif', '.ico',
    '.css', '.js', '.mjs', '.json', '.woff', '.woff2', '.ttf', '.otf', '.eot',
    '.mp4', '.webm', '.m4v', '.mov', '.mp3', '.wav', '.pdf',
}


def should_download(path: str) -> bool:
    if path.startswith('/is/image/') or path.startswith('/is/content/') or path.startswith('/content/'):
        return True
    if path.startswith('/aemapi/'):
        return path.endswith('.json')
    ext = Path(path).suffix.lower()
    return ext in DOWNLOAD_EXTS

URL_PATTERNS = [
    re.compile(r'https?://(?:[a-z0-9-]+\.)*samsung\.com(?:/pk)?(/(?:etc\.clientlibs|assets|is|content|aemapi)[^\s"\'<>\)]+)', re.I),
    re.compile(r'https?://(?:stg-)?images\.samsung\.com(/is/(?:image|content)/[^\s"\'<>\)]+)', re.I),
    re.compile(r'(?<![\w-])(/(?:etc\.clientlibs|assets|is/image|is/content|content|aemapi)/[^\s"\'<>\)]+)', re.I),
    re.compile(r'(?<![\w/.])(etc\.clientlibs/[^\s"\'<>\)]+)', re.I),
    re.compile(r'(?<![\w/.])(assets/[^\s"\'<>\)]+)', re.I),
    re.compile(r'url\(\s*["\']?([^"\')\s]+)["\']?\s*\)', re.I),
    re.compile(r'["\'](/copy-website/(?:etc\.clientlibs|assets)/[^"\']+)["\']', re.I),
]

SKIP_HOSTS = (
    'googletagmanager.com', 'facebook.com', 'facebook.net', 'useinsider.com',
    'adobedtm.com', 'smetrics.samsung.com', 'bazaarvoice.com', 'sprinklr.com',
)


def iter_source_files():
    for f in SCAN_FILES:
        if f.is_file():
            yield f
    for d in SCAN_DIRS:
        if not d.exists():
            continue
        for path in d.rglob('*'):
            if path.is_file() and path.suffix.lower() in TEXT_EXTS:
                yield path


def clean_url(raw: str) -> str | None:
    u = raw.strip().strip('"\'').replace('&amp;', '&').replace('&#038;', '&')
    u = re.sub(r'<\?php echo CW_BASE_URL; \?>', '', u, flags=re.I)
    u = re.sub(r'\$\{[^}]+\}', '', u)
    if not u or u.startswith('data:') or u.startswith('javascript:') or u.startswith('#'):
        return None
    if any(h in u.lower() for h in SKIP_HOSTS):
        return None
    return u


def to_local_path(url: str) -> str | None:
    u = clean_url(url)
    if u is None:
        return None

    if u.startswith('/copy-website/'):
        u = u[len('/copy-website') :]

    if u.startswith(('http://', 'https://')):
        parsed = urlparse(u)
        host = (parsed.netloc or '').lower()
        path = parsed.path or ''
        if 'samsung.com' in host:
            if path.startswith('/pk'):
                path = path[3:] or '/'
            if path.startswith(LOCAL_PREFIXES) or path.startswith('/assets/'):
                pass
            elif '/is/image/' in path or '/is/content/' in path:
                pass
            else:
                return None
        elif 'images.samsung.com' in host or 'stg-images.samsung.com' in host:
            pass
        else:
            return None
        u = path + (('?' + parsed.query) if parsed.query else '')
    elif u.startswith('etc.clientlibs/') or u.startswith('assets/'):
        u = '/' + u
    elif not u.startswith('/'):
        return None

    path_only = u.split('?', 1)[0].split('#', 1)[0]
    if '..' in path_only:
        return None
    if not any(path_only.startswith(p) for p in LOCAL_PREFIXES):
        return None
    return path_only


def extract_paths(text: str) -> set[str]:
    found: set[str] = set()
    for pat in URL_PATTERNS:
        for m in pat.finditer(text):
            raw = m.group(1) if m.lastindex else m.group(0)
            local = to_local_path(raw)
            if local:
                found.add(local)
    return found


def origins_for(path: str) -> list[str]:
    if path.startswith('/is/'):
        return ORIGINS['is']
    if path.startswith('/content/'):
        return ORIGINS['content']
    if path.startswith('/aemapi/'):
        return ORIGINS['aemapi']
    if path.startswith('/assets/'):
        return ORIGINS['assets']
    return ORIGINS['etc']


def ext_from_content_type(ct: str) -> str:
    ct = (ct or '').split(';', 1)[0].strip().lower()
    mapping = {
        'image/jpeg': '.jpg',
        'image/jpg': '.jpg',
        'image/png': '.png',
        'image/gif': '.gif',
        'image/webp': '.webp',
        'image/svg+xml': '.svg',
        'video/mp4': '.mp4',
        'video/webm': '.webm',
        'application/json': '.json',
        'text/css': '.css',
        'application/javascript': '.js',
        'text/javascript': '.js',
        'font/woff2': '.woff2',
        'font/woff': '.woff',
    }
    return mapping.get(ct, '.bin')


def download(path: str, timeout: int = 45) -> tuple[bool, str]:
    local_fs = ROOT / path.lstrip('/')
    if local_fs.is_file() and local_fs.stat().st_size > 0:
        return True, 'exists'

    parent = local_fs.parent
    if local_fs.exists() and local_fs.is_file() and local_fs.stat().st_size > 0:
        return True, 'exists'
    if parent.exists() and parent.is_file():
        return False, f'blocked by file at {parent}'
    try:
        parent.mkdir(parents=True, exist_ok=True)
    except OSError as e:
        return False, str(e)

    ctx = ssl.create_default_context()
    last_err = 'no origin'
    tmp: Path | None = None

    for origin in origins_for(path):
        remote = origin.rstrip('/') + path
        req = urllib.request.Request(
            remote,
            headers={'User-Agent': 'Mozilla/5.0 (compatible; samsung-clon-mirror/1.0)'},
        )
        try:
            with urllib.request.urlopen(req, timeout=timeout, context=ctx) as resp:
                code = resp.getcode()
                data = resp.read()
                content_type = resp.headers.get('Content-Type', '')
            if code < 200 or code >= 300 or not data:
                last_err = f'HTTP {code} {remote}'
                continue
            target = local_fs
            if not target.suffix and (path.startswith('/content/') or path.startswith('/is/content/')):
                target = local_fs.with_suffix(ext_from_content_type(content_type))
                target.parent.mkdir(parents=True, exist_ok=True)
            tmp = target.with_suffix(target.suffix + '.tmp')
            tmp.write_bytes(data)
            tmp.replace(target)
            return True, remote
        except urllib.error.HTTPError as e:
            last_err = f'HTTP {e.code} {remote}'
        except Exception as e:
            last_err = f'{e} {remote}'

    if tmp is not None and tmp.exists():
        tmp.unlink()
    return False, last_err


def collect_all_paths() -> set[str]:
    paths: set[str] = set()
    for f in iter_source_files():
        try:
            text = f.read_text(encoding='utf-8', errors='replace')
        except OSError:
            continue
        paths |= extract_paths(text)
    return paths


def process_css_js_queue(initial: set[str]) -> set[str]:
    """Re-scan downloaded CSS/JS for nested url() references."""
    queue = deque(initial)
    seen: set[str] = set()
    all_paths: set[str] = set()

    while queue:
        path = queue.popleft()
        if path in seen:
            continue
        seen.add(path)
        all_paths.add(path)

        fs = ROOT / path.lstrip('/')
        if not fs.is_file() or fs.suffix.lower() not in {'.css', '.js'}:
            continue
        try:
            nested = extract_paths(fs.read_text(encoding='utf-8', errors='replace'))
        except OSError:
            continue
        for n in nested:
            if n not in seen:
                queue.append(n)

    return all_paths


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('--dry-run', action='store_true')
    parser.add_argument('--limit', type=int, default=0)
    args = parser.parse_args()

    print('Collecting asset paths from local pages and bundles...')
    paths = {p for p in process_css_js_queue(collect_all_paths()) if should_download(p)}
    print(f'Found {len(paths)} downloadable asset path(s)')

    if args.dry_run:
        for p in sorted(paths)[:50]:
            print(p)
        if len(paths) > 50:
            print(f'... and {len(paths) - 50} more')
        return 0

    ok_count = 0
    fail_count = 0
    manifest: dict[str, str] = {}
    failed: list[str] = []

    sorted_paths = sorted(paths)
    if args.limit > 0:
        sorted_paths = sorted_paths[: args.limit]

    for i, path in enumerate(sorted_paths, 1):
        if i % 25 == 0 or i == 1:
            print(f'[{i}/{len(sorted_paths)}] {path}')
        success, detail = download(path)
        if success:
            ok_count += 1
            manifest[path] = detail
        else:
            fail_count += 1
            failed.append(f'{path}\t{detail}')
        time.sleep(0.05)

    MANIFEST.parent.mkdir(parents=True, exist_ok=True)
    if MANIFEST.exists():
        try:
            existing = json.loads(MANIFEST.read_text(encoding='utf-8'))
            if isinstance(existing, dict):
                existing.update(manifest)
                manifest = existing
        except (json.JSONDecodeError, OSError):
            pass
    MANIFEST.write_text(json.dumps(manifest, indent=2), encoding='utf-8')
    if FAILED_LOG.exists():
        failed = FAILED_LOG.read_text(encoding='utf-8').splitlines() + failed
    FAILED_LOG.write_text('\n'.join(failed) + ('\n' if failed else ''), encoding='utf-8')

    print(f'Done: {ok_count} ok, {fail_count} failed')
    print(f'Manifest: {MANIFEST}')
    if failed:
        print(f'Failed log: {FAILED_LOG}')
    return 0 if fail_count == 0 else 1


if __name__ == '__main__':
    raise SystemExit(main())
