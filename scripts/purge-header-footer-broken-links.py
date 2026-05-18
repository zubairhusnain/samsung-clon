#!/usr/bin/env python3
"""Remove header (GNB) and footer links that point to pages not in this clone."""

from __future__ import annotations

import argparse
import os
import re
from html import unescape
from urllib.parse import urlparse

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SCAN_ROOTS = [
    os.path.join(ROOT, "pages"),
    os.path.join(ROOT, "index.php"),
    os.path.join(ROOT, "global"),
]
LOG_PATH = os.path.join(ROOT, "assets", "header-footer-broken-links-removed.txt")

ASSET_PREFIXES = (
    "etc.clientlibs/",
    "assets/",
    "is/",
    "content/",
    "aemapi/",
    "copy-website/",
)

WRAPPER_PATTERNS = [
    r'<li\b[^>]*\bclass="[^"]*footer-category__item[^"]*"[^>]*>[\s\S]*?</li>',
    r'<li\b[^>]*\bclass="[^"]*nv00-gnb-v4__utility-menu-item[^"]*"[^>]*>[\s\S]*?</li>',
    r'<li\b[^>]*\bclass="[^"]*gnb__depth3-menu[^"]*"[^>]*>[\s\S]*?</li>',
    r'<li\b[^>]*\bclass="[^"]*gnb__depth2-menu[^"]*"[^>]*>[\s\S]*?</li>',
]

REGION_PATTERNS = [
    r"<nav\b[^>]*\bnv00-gnb-v4\b[^>]*>[\s\S]*?</nav>",
    r'<nav\b[^>]*\bgnb__nav\b[^>]*>[\s\S]*?</nav>',
    r"<footer\b[^>]*\bfooter\b[^>]*>[\s\S]*?</footer>",
]


def collect_routes() -> set[str]:
    routes: set[str] = {"", "search", "no-page"}
    pages_dir = os.path.join(ROOT, "pages")
    for dirpath, _, filenames in os.walk(pages_dir):
        if "index.php" not in filenames:
            continue
        rel = os.path.relpath(dirpath, pages_dir).replace("\\", "/")
        routes.add("" if rel == "." else rel)
    return routes


def href_to_route(href: str) -> str | None:
    href = unescape(href.strip())
    if not href or href.startswith("#"):
        return None
    low = href.lower()
    if low.startswith(("javascript:", "mailto:", "tel:", "sms:")):
        return None

    if "<?php" in href:
        m = re.search(r"\?>\s*(/[^\"']*)", href)
        if not m:
            return None
        href = m.group(1)

    if re.match(r"^https?://", href, re.I):
        parsed = urlparse(href)
        host = (parsed.hostname or "").lower()
        if host not in ("www.samsung.com", "samsung.com", "localhost", "127.0.0.1"):
            return None
        path = parsed.path or "/"
    else:
        path = href

    path = path.split("?")[0].split("#")[0]
    if path.startswith("/samsung-clon/"):
        path = path[len("/samsung-clon") :]
    if path.startswith("/pk"):
        path = path[3:] or "/"
    path = path.strip("/")

    low_path = path.lower()
    if any(low_path.startswith(p) for p in ASSET_PREFIXES):
        return None
    return path


def is_broken_page_href(href: str, routes: set[str]) -> bool:
    route = href_to_route(href)
    if route is None:
        return False
    return route not in routes


def extract_href(open_tag: str) -> str | None:
    m = re.search(r'\bhref\s*=\s*(["\'])(.*?)\1', open_tag, re.I | re.S)
    return m.group(2) if m else None


def find_tag_end(region: str, start: int) -> int:
    i = start + 1
    in_quote: str | None = None
    while i < len(region):
        ch = region[i]
        if in_quote:
            if ch == in_quote:
                in_quote = None
        elif ch in "\"'":
            in_quote = ch
        elif ch == ">":
            return i
        i += 1
    return -1


def list_anchors(region: str) -> list[tuple[int, int, str | None]]:
    anchors: list[tuple[int, int, str | None]] = []
    pos = 0
    while True:
        m = re.search(r"<a\b", region[pos:], re.I)
        if not m:
            break
        start = pos + m.start()
        gt = find_tag_end(region, start)
        if gt < 0:
            break
        open_tag = region[start : gt + 1]
        href = extract_href(open_tag)
        depth = 1
        i = gt + 1
        while i < len(region) and depth:
            if re.match(r"<a\b", region[i:], re.I):
                depth += 1
                end = find_tag_end(region, i)
                if end < 0:
                    return anchors
                i = end + 1
                continue
            if region[i : i + 4].lower() == "</a>":
                depth -= 1
                i += 4
                if depth == 0:
                    anchors.append((start, i, href))
                    pos = i
                    break
                continue
            i += 1
        else:
            break
    return anchors


def purge_region(region: str, routes: set[str]) -> tuple[str, int]:
    removed = 0
    spans = list_anchors(region)
    for start, end, href in reversed(spans):
        if href and is_broken_page_href(href, routes):
            region = region[:start] + region[end:]
            removed += 1

    for pattern in WRAPPER_PATTERNS:
        while True:
            m = re.search(pattern, region, re.I | re.S)
            if not m:
                break
            block = m.group(0)
            hrefs = re.findall(r'\bhref\s*=\s*(["\'])(.*?)\1', block, re.I | re.S)
            page_hrefs = [h for _, h in hrefs if href_to_route(h) is not None]
            if not page_hrefs:
                region = region[: m.start()] + region[m.end() :]
                removed += 1
                continue
            if any(is_broken_page_href(h, routes) for h in page_hrefs):
                region = region[: m.start()] + region[m.end() :]
                removed += 1
                continue
            break

    region = re.sub(
        r'<li\b[^>]*\bclass="[^"]*footer-category__item[^"]*"[^>]*>\s*</li>',
        "",
        region,
        flags=re.I,
    )
    region = re.sub(
        r'<li\b[^>]*\bclass="[^"]*nv00-gnb-v4__utility-menu-item[^"]*"[^>]*>\s*</li>',
        "",
        region,
        flags=re.I,
    )

    return region, removed


def process_file(path: str, routes: set[str], dry_run: bool) -> int:
    with open(path, encoding="utf-8", errors="replace") as f:
        html = f.read()

    total = 0
    out = html

    def repl_region(m: re.Match[str]) -> str:
        nonlocal total
        region, n = purge_region(m.group(0), routes)
        total += n
        return region

    for pattern in REGION_PATTERNS:
        out = re.sub(pattern, repl_region, out, flags=re.I)

    if total and not dry_run:
        with open(path, "w", encoding="utf-8") as f:
            f.write(out)
    return total


def iter_files():
    for root in SCAN_ROOTS:
        if os.path.isfile(root):
            yield root
        elif os.path.isdir(root):
            for dp, _, fns in os.walk(root):
                for fn in fns:
                    if fn.endswith((".php", ".html", ".htm")):
                        yield os.path.join(dp, fn)


def verify(routes: set[str]) -> int:
    count = 0
    for path in iter_files():
        with open(path, encoding="utf-8", errors="replace") as f:
            html = f.read()
        for pattern in REGION_PATTERNS:
            for m in re.finditer(pattern, html, re.I):
                for _, _, href in list_anchors(m.group(0)):
                    if href and is_broken_page_href(href, routes):
                        count += 1
    return count


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--dry-run", action="store_true")
    parser.add_argument("--verify", action="store_true")
    args = parser.parse_args()

    routes = collect_routes()

    if args.verify:
        print(f"Remaining broken header/footer page links: {verify(routes)}")
        return

    log_lines: list[str] = []
    grand = 0

    for path in sorted(iter_files()):
        n = process_file(path, routes, args.dry_run)
        if n:
            rel = os.path.relpath(path, ROOT)
            log_lines.append(f"{rel}\t{n}")
            grand += n
            print(f"{'[dry-run] ' if args.dry_run else ''}{rel}: {n}")

    print(f"\nTotal removed: {grand} link blocks in header/footer")
    if not args.dry_run and grand:
        print("Run with --verify to confirm zero broken page links remain.")
        if log_lines:
            os.makedirs(os.path.dirname(LOG_PATH), exist_ok=True)
            with open(LOG_PATH, "w", encoding="utf-8") as f:
                f.write("\n".join(log_lines))
                f.write(f"\n\nTOTAL={grand}\n")


if __name__ == "__main__":
    main()
