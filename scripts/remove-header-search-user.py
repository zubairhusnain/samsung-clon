#!/usr/bin/env python3
"""Remove search and user/account icons from site headers (GNB)."""

from __future__ import annotations

import os
import re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SCAN_ROOTS = [
    os.path.join(ROOT, "pages"),
    os.path.join(ROOT, "index.php"),
    os.path.join(ROOT, "previous-index.php"),
    os.path.join(ROOT, "global"),
]

NAV_REGIONS = [
    r"<nav\b[^>]*\bnv00-gnb-v4\b[^>]*>[\s\S]*?</nav>",
    r'<nav\b[^>]*\bgnb__nav\b[^>]*>[\s\S]*?</nav>',
]

SIMPLE_PATTERNS = [
    r"<button\b[^>]*\bnv00-gnb-v4__utility-search\b[^>]*>[\s\S]*?</button>",
    r"<button\b[^>]*\bnv00-gnb-v4__search\b[^>]*>[\s\S]*?</button>",
    r"<a\b[^>]*\bnv00-gnb-v4__utility-user\b[^>]*>[\s\S]*?</a>",
    r"<button\b[^>]*\bnv00-gnb-v4__utility-user\b[^>]*>[\s\S]*?</button>",
    r"<a\b[^>]*\bnv00-gnb-v4__user-menu\b[^>]*>[\s\S]*?</a>",
    r'<a\b[^>]*\bgnb__search-btn\b[^>]*>[\s\S]*?</a>',
    r"<li\b[^>]*\bgnb__search\b[^>]*>[\s\S]*?</li>",
]


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


def remove_balanced_block(
    html: str, open_pat: str, close_tag: str, open_match: re.Match[str]
) -> str:
    start = open_match.start()
    gt = find_tag_end(html, start)
    if gt < 0:
        return html
    close = f"</{close_tag}>"
    close_len = len(close)
    depth = 1
    i = gt + 1
    open_re = re.compile(open_pat, re.I)
    while i < len(html) and depth:
        if html[i] == "<":
            if open_re.match(html, i):
                depth += 1
                end = find_tag_end(html, i)
                if end < 0:
                    return html
                i = end + 1
                continue
            if html[i : i + close_len].lower() == close:
                depth -= 1
                i += close_len
                if depth == 0:
                    return html[:start] + html[i:]
                continue
        i += 1
    return html


def remove_div_with_class(html: str, class_part: str) -> str:
    pat = rf"<div\b[^>]*\bclass=\"[^\"]*{re.escape(class_part)}[^\"]*\"[^>]*>"
    while True:
        m = re.search(pat, html, re.I)
        if not m:
            break
        nxt = remove_balanced_block(html, pat, "div", m)
        if nxt == html:
            break
        html = nxt
    return html


def remove_li_with_class(html: str, class_part: str) -> str:
    pat = rf"<li\b[^>]*\bclass=\"[^\"]*{re.escape(class_part)}[^\"]*\"[^>]*>"
    while True:
        m = re.search(pat, html, re.I)
        if not m:
            break
        nxt = remove_balanced_block(html, pat, "li", m)
        if nxt == html:
            break
        html = nxt
    return html


def purge_region(region: str) -> tuple[str, int]:
    n = 0
    for pat in SIMPLE_PATTERNS:
        while True:
            m = re.search(pat, region, re.I | re.S)
            if not m:
                break
            region = region[: m.start()] + region[m.end() :]
            n += 1

    while True:
        m = re.search(
            r'<div class="nv00-gnb-v4__user-menu-list[^"]*">[\s\S]*?</div>\s*',
            region,
            re.I,
        )
        if not m:
            break
        region = region[: m.start()] + region[m.end() :]
        n += 1

    for cls in (
        "nv00-gnb-v4__utility-wrap before-login",
        "nv00-gnb-v4__utility-wrap after-login",
    ):
        before = region
        region = remove_div_with_class(region, cls)
        if region != before:
            n += 1

    for cls in ("gnb__login", "gnb__logout"):
        before = region
        region = remove_li_with_class(region, cls)
        if region != before:
            n += 1

    # Desktop utility bar (search + account) — not the mobile hamburger row
    pat = (
        r"<div\b(?![^>]*\bmobile-only\b)[^>]*\bclass=\"[^\"]*"
        r"\bnv00-gnb-v4__utility-list\b[^\"]*\"[^>]*>"
    )
    while True:
        m = re.search(pat, region, re.I)
        if not m:
            break
        nxt = remove_balanced_block(region, pat, "div", m)
        if nxt == region:
            break
        region = nxt
        n += 1

    return region, n


def purge(html: str) -> tuple[str, int]:
    total = 0

    def repl(m: re.Match[str]) -> str:
        nonlocal total
        region, n = purge_region(m.group(0))
        total += n
        return region

    for pattern in NAV_REGIONS:
        html = re.sub(pattern, repl, html, flags=re.I)
    return html, total


def iter_files():
    for root in SCAN_ROOTS:
        if os.path.isfile(root):
            yield root
        elif os.path.isdir(root):
            for dp, _, fns in os.walk(root):
                for fn in fns:
                    if fn.endswith((".php", ".html", ".htm")):
                        yield os.path.join(dp, fn)


def main() -> None:
    total = 0
    files = 0
    for path in sorted(iter_files()):
        with open(path, encoding="utf-8", errors="replace") as f:
            html = f.read()
        out, n = purge(html)
        if n:
            with open(path, "w", encoding="utf-8") as f:
                f.write(out)
            rel = os.path.relpath(path, ROOT)
            print(f"{rel}: {n}")
            total += n
            files += 1
    print(f"\nUpdated {files} files, {total} removals")


if __name__ == "__main__":
    main()
