#!/usr/bin/env python3
"""Permanently remove external links, icons, and related markup from page sources."""

from __future__ import annotations

import os
import re
from html import unescape
from urllib.parse import urlparse

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SCAN_ROOTS = [
    os.path.join(ROOT, "pages"),
    os.path.join(ROOT, "global"),
    os.path.join(ROOT, "index.php"),
    os.path.join(ROOT, "previous-index.php"),
]

LOCAL_HOSTS = {"localhost", "127.0.0.1"}
EXTERNAL_SYMBOLS = (
    "outlink-bold",
    "facebook-bold",
    "twitter-bold",
    "instagram-bold",
    "youtube-bold",
    "whatsapp-bold",
)


def iter_files():
    for root in SCAN_ROOTS:
        if os.path.isfile(root):
            yield root
        elif os.path.isdir(root):
            for dp, _, fns in os.walk(root):
                for fn in fns:
                    if fn.endswith((".php", ".html", ".htm")):
                        yield os.path.join(dp, fn)


def normalize_url(url: str) -> str:
    url = unescape(url.strip())
    if url.startswith("//"):
        return "https:" + url
    return url


def is_external_url(url: str) -> bool:
    if not url:
        return False
    lower = url.lower().strip()
    if lower in ("", "#") or lower.startswith("#"):
        return False
    if lower.startswith(("javascript:", "mailto:", "tel:", "sms:")):
        return False
    if "<?php" in url or "CW_BASE_URL" in url:
        return False
    if not re.match(r"^(?:https?:)?//", lower):
        return False
    url = normalize_url(url)
    parsed = urlparse(url)
    host = (parsed.hostname or "").lower()
    if host in LOCAL_HOSTS:
        return False
    if host in ("www.samsung.com", "samsung.com"):
        path = parsed.path or ""
        if path == "/pk" or path.startswith("/pk/"):
            return False
    return True


def strip_svg_with_symbols(html: str) -> str:
    def repl(m: re.Match[str]) -> str:
        inner = m.group(1)
        if any(sym in inner for sym in EXTERNAL_SYMBOLS):
            return ""
        return m.group(0)

    return re.sub(r"<svg class=\"icon\"[^>]*>(.*?)</svg>", repl, html, flags=re.I | re.S)


def remove_footer_sns(html: str) -> str:
    return re.sub(r"<motion?div class=\"footer-sns\b[^>]*>[\s\S]*?</div>", "", html, flags=re.I)


def remove_cw_unlinked_spans(html: str) -> str:
    def repl(m: re.Match[str]) -> str:
        inner = m.group(1)
        inner_stripped = re.sub(r"<svg\b[^>]*>.*?</svg>", "", inner, flags=re.I | re.S).strip()
        # Keep inner content only when it is real page content (images/text blocks), not lone labels
        if re.search(r"<(img|picture|div class=\"image)\"?", inner, flags=re.I):
            return inner
        if inner_stripped and not is_external_url(inner_stripped):
            # Plain label text for removed external items — drop entirely
            if re.fullmatch(r"[\w\s\-–—'’,.!?]+", inner_stripped):
                return ""
        return ""

    html = re.sub(
        r"<span class=\"cw-unlinked\"[^>]*>(.*?)</span>",
        repl,
        html,
        flags=re.I | re.S,
    )
    html = re.sub(r"<span class=\"cw-unlinked\"[^>]*>\s*</span>", "", html, flags=re.I)
    return html


def remove_external_anchors(html: str) -> str:
    pattern = re.compile(
        r"<a\b([^>]*?)\bhref\s*=\s*([\"'])((?:https?:)?//[^\"']+)\2([^>]*)>(.*?)</a>",
        re.I | re.S,
    )

    def repl(m: re.Match[str]) -> str:
        if is_external_url(m.group(3)):
            return ""
        return m.group(0)

    return pattern.sub(repl, html)


def remove_external_areas_and_forms(html: str) -> str:
    html = re.sub(
        r"<area\b[^>]*\bhref\s*=\s*([\"'])(?:https?:)?//[^\"']+\1[^>]*/?>",
        "",
        html,
        flags=re.I,
    )
    html = re.sub(
        r"<form\b[^>]*\baction\s*=\s*([\"'])(?:https?:)?//[^\"']+\1[^>]*>.*?</form>",
        "",
        html,
        flags=re.I | re.S,
    )
    return html


def scrub_external_attributes(html: str) -> str:
    attr_pat = re.compile(
        r"\b([a-z][a-z0-9_-]*)\s*=\s*([\"'])((?:https?:)?//(?:[^\"']|&(?:amp|#038);)+)\2",
        re.I,
    )

    def repl(m: re.Match[str]) -> str:
        attr = m.group(1).lower()
        url = normalize_url(m.group(3).replace("&amp;", "&").replace("&#038;", "&"))
        if attr in ("src", "srcset"):
            return m.group(0)
        if not is_external_url(url):
            return m.group(0)
        if attr in ("href", "action"):
            return m.group(0)
        return f'{m.group(1)}={m.group(2)}{m.group(2)}'

    html = attr_pat.sub(repl, html)

    for domain in (
        "shop.samsung.com",
        "account.samsung.com",
        "v3.account.samsung.com",
        "api.shop.samsung.com",
        "wa.me",
        "forms-prod21.sprinklr.com",
        "forms-prod.sprinklr.com",
    ):
        html = re.sub(
            rf'\bvalue\s*=\s*([\"\']){re.escape(domain)}\1',
            r"value=\1\1",
            html,
            flags=re.I,
        )
    return html


def remove_external_link_tags(html: str) -> str:
    def repl(m: re.Match[str]) -> str:
        rel = m.group(1).lower()
        url = m.group(3)
        if any(t in rel for t in ("stylesheet", "preconnect", "preload", "dns-prefetch", "icon", "manifest")):
            if "shop.samsung.com" in url.lower():
                return ""
            return m.group(0)
        if is_external_url(url):
            return ""
        return m.group(0)

    return re.sub(
        r"<link\b([^>]*)\brel\s*=\s*([\"'])([^\"']+)\2([^>]*)\bhref\s*=\s*([\"'])((?:https?:)?//[^\"']+)\5([^>]*)>",
        repl,
        html,
        flags=re.I,
    )


def remove_empty_list_items(html: str) -> str:
    html = re.sub(r"<li class=\"footer-sns__item\"[^>]*>\s*</li>", "", html, flags=re.I)
    html = re.sub(r"<li class=\"footer-category__item\"[^>]*>\s*</li>", "", html, flags=re.I)
    return html


def remove_account_forms(html: str) -> str:
    for form_id in ("signInForm", "signOutForm", "joinForm", "findAccountForm"):
        html = re.sub(
            rf"<form id=\"{form_id}\"[^>]*>.*?</form>",
            "",
            html,
            flags=re.I | re.S,
        )
    return html


def remove_orphan_gnb_blocks(html: str) -> str:
    # Cart / login utility icons left when cw-unlinked wrappers were removed
    html = re.sub(
        r"(</button>\s*)"
        r"(?:\s*<svg class=\"icon\"[^>]*>.*?</svg>\s*)+"
        r"(?:\s*<span class=\"cart-in-number[^>]*>.*?</span>\s*)?"
        r"</span>\s*",
        r"\1",
        html,
        flags=re.I | re.S,
    )
    html = re.sub(
        r"\s*<svg class=\"icon\"[^>]*>\s*"
        r"<path d=\"M48,51\.5.*?</svg>\s*</span>\s*"
        r"(?=\s*<a class=\"nv00-gnb-v4__utility nv00-gnb-v4__utility-user)",
        "\n",
        html,
        flags=re.I | re.S,
    )
    return html


def remove_broken_cw_unlinked(html: str) -> str:
    html = re.sub(r"<span class=\"cw-unlinked\"[^>]*>.*?</span>", "", html, flags=re.I | re.S)
    html = re.sub(r"<span class=\"cw-unlinked\"[^>]*>.*?</a", "", html, flags=re.I | re.S)
    return html


def cleanup_markup(html: str) -> str:
    html = remove_broken_cw_unlinked(html)
    html = remove_footer_sns(html)
    html = remove_external_anchors(html)
    html = remove_external_areas_and_forms(html)
    html = remove_cw_unlinked_spans(html)
    html = remove_account_forms(html)
    html = remove_orphan_gnb_blocks(html)
    html = strip_svg_with_symbols(html)
    html = scrub_external_attributes(html)
    html = remove_external_link_tags(html)
    html = re.sub(r"\sdata-cw-external-removed\s*=\s*[\"']1[\"']", "", html, flags=re.I)
    html = re.sub(r"\sdata-cw-was\s*=\s*[\"'][^\"']*[\"']", "", html, flags=re.I)
    html = re.sub(r"\sonsubmit\s*=\s*[\"']return false;[\"']", "", html, flags=re.I)
    html = remove_empty_list_items(html)
    html = re.sub(r"\n{4,}", "\n\n\n", html)
    return html


def main() -> None:
    changed = 0
    scanned = 0
    for path in iter_files():
        scanned += 1
        with open(path, encoding="utf-8", errors="ignore") as f:
            before = f.read()
        after = cleanup_markup(before)
        if after != before:
            with open(path, "w", encoding="utf-8") as f:
                f.write(after)
            changed += 1
            print("updated:", os.path.relpath(path, ROOT))
    print(f"\nScanned: {scanned}, updated: {changed}")


if __name__ == "__main__":
    main()
