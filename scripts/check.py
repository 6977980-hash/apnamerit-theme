#!/usr/bin/env python3
"""Apna Merit pre-merge checks (master plan 9.1). Standard library only.

Usage:  python3 scripts/check.py        (exit code 1 if any check fails)

Checks:
  php      - `php -l` on every PHP file (skipped with a warning if php is not installed)
  json     - every data/**/*.json parses; data/site.json has required keys; no {{ placeholders;
             every merit record under data/universities/ has a source_url
  template - page templates have a Template Name header, at most one <h1>, wp_head/wp_footer present,
             no debug output, no jQuery or web fonts
  pages    - data/pages.json: title <= 60, description 140-155, unique path/title/description/keyword
  links    - internal links in data/templates resolve to slugs in data/pages.json (once that file exists)
  privacy  - no PDFs, CNIC-like numbers or private keys committed
  size     - am.css within the 14 KB inline budget
"""
from __future__ import annotations

import json
import re
import shutil
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SKIP_DIRS = {".git", "node_modules", "__pycache__", "sources"}
TEXT_EXT = {".php", ".json", ".js", ".css", ".md", ".py", ".sh", ".txt", ".html", ""}

errors: list[str] = []
warnings: list[str] = []


def err(check: str, msg: str) -> None:
    errors.append(f"[{check}] {msg}")


def warn(check: str, msg: str) -> None:
    warnings.append(f"[{check}] {msg}")


def files(pattern: str = "*") -> list[Path]:
    out = []
    for p in ROOT.rglob(pattern):
        if p.is_file() and not any(part in SKIP_DIRS for part in p.relative_to(ROOT).parts):
            out.append(p)
    return sorted(out)


def rel(p: Path) -> str:
    return str(p.relative_to(ROOT))


# ---------------------------------------------------------------- php
def check_php() -> None:
    php = shutil.which("php")
    if not php:
        warn("php", "php not installed; lint skipped")
        return
    for f in files("*.php"):
        r = subprocess.run([php, "-l", str(f)], capture_output=True, text=True)
        if r.returncode != 0:
            err("php", f"{rel(f)}: {(r.stdout + r.stderr).strip()}")


# ---------------------------------------------------------------- json
SITE_REQUIRED = {
    "domain": str,
    "url": str,
    "brand": str,
    "tagline": str,
    "season_year": int,
    "data_year": int,
    "author": dict,
    "timezone": str,
}
RECORD_KEYS = {"closing", "closing_aggregate", "closing_merit", "aggregate"}


def walk(node, path="$"):
    yield path, node
    if isinstance(node, dict):
        for k, v in node.items():
            yield from walk(v, f"{path}.{k}")
    elif isinstance(node, list):
        for i, v in enumerate(node):
            yield from walk(v, f"{path}[{i}]")


def check_json() -> dict[str, object]:
    loaded: dict[str, object] = {}
    for f in files("*.json"):
        if not rel(f).startswith("data/"):
            continue
        text = f.read_text(encoding="utf-8")
        try:
            data = json.loads(text)
        except json.JSONDecodeError as e:
            err("json", f"{rel(f)}: invalid JSON ({e})")
            continue
        loaded[rel(f)] = data
        if "{{" in text:
            err("json", f"{rel(f)}: unresolved '{{{{' placeholder")
        if rel(f).startswith("data/universities/"):
            for path, node in walk(data):
                if isinstance(node, dict) and RECORD_KEYS & node.keys() and not node.get("source_url"):
                    err("json", f"{rel(f)} {path}: record without source_url")

    site = loaded.get("data/site.json")
    if not isinstance(site, dict):
        err("json", "data/site.json missing or not an object")
    else:
        for key, typ in SITE_REQUIRED.items():
            if not isinstance(site.get(key), typ):
                err("json", f"data/site.json: '{key}' missing or not {typ.__name__}")
        sy, dy = site.get("season_year"), site.get("data_year")
        if isinstance(sy, int) and isinstance(dy, int) and not (sy - 1 <= dy <= sy):
            err("json", f"data/site.json: data_year {dy} should be season_year-1 or season_year ({sy})")
        if not isinstance(site.get("author"), dict) or not site["author"].get("name"):
            err("json", "data/site.json: author.name required (real name, plan 4.5)")
    return loaded


# ---------------------------------------------------------------- templates
DEBUG_PHP = re.compile(r"\b(var_dump|print_r|var_export|error_log|dd)\s*\(")
FORBIDDEN = [
    (re.compile(r"fonts\.(googleapis|gstatic)\.com"), "web fonts are not allowed"),
    (re.compile(r"wp_enqueue_script\(\s*['\"]jquery"), "jQuery must not be enqueued on the front end"),
    (re.compile(r"elementor|wpbakery|divi", re.I), "page builders are not allowed"),
]


def check_templates() -> None:
    for f in files("*.php"):
        src = f.read_text(encoding="utf-8")
        r = rel(f)
        for m in DEBUG_PHP.finditer(src):
            err("template", f"{r}: debug call {m.group(1)}()")
        for rx, why in FORBIDDEN:
            if rx.search(src):
                err("template", f"{r}: {why}")
        if src.lower().count("<h1") > 1:
            err("template", f"{r}: more than one <h1>")
        if r.startswith("templates/page-") and "Template Name:" not in src:
            err("template", f"{r}: missing 'Template Name:' header")
    for f, needle in (("header.php", "wp_head()"), ("footer.php", "wp_footer()")):
        p = ROOT / f
        if not p.exists() or needle not in p.read_text(encoding="utf-8"):
            err("template", f"{f}: must call {needle}")
    style = ROOT / "style.css"
    if not style.exists() or "Theme Name:" not in style.read_text(encoding="utf-8"):
        err("template", "style.css: missing theme header")
    for f in files("*.js"):
        if re.search(r"\bconsole\.log\(", f.read_text(encoding="utf-8")):
            err("template", f"{rel(f)}: console.log left in")


# ---------------------------------------------------------------- pages (SEO, no Rank Math)
def check_pages(loaded: dict[str, object]) -> None:
    pages = loaded.get("data/pages.json")
    site = loaded.get("data/site.json") or {}
    if not isinstance(pages, dict) or not isinstance(pages.get("pages"), list):
        err("pages", "data/pages.json missing or has no 'pages' list")
        return
    tokens = {"{season_year}": str(site.get("season_year", "")), "{data_year}": str(site.get("data_year", "")),
              "{brand}": str(site.get("brand", ""))}

    def fill(t: str) -> str:
        for k, v in tokens.items():
            t = t.replace(k, v)
        return t

    seen: dict[str, set] = {"path": set(), "title": set(), "description": set(), "keyword": set()}
    for i, p in enumerate(pages["pages"]):
        where = f"data/pages.json pages[{i}]"
        if not isinstance(p, dict):
            err("pages", f"{where}: not an object")
            continue
        path = p.get("path", "")
        if not (isinstance(path, str) and path.startswith("/") and path.endswith("/")):
            err("pages", f"{where}: path must start and end with '/'")
        title, desc = fill(p.get("title", "")), fill(p.get("description", ""))
        if not title or len(title) > 60:
            err("pages", f"{where} {path}: title must be 1-60 chars (is {len(title)})")
        if not 140 <= len(desc) <= 155:
            err("pages", f"{where} {path}: description must be 140-155 chars (is {len(desc)})")
        if re.search(r"\{[a-z_]+\}", title + desc):
            err("pages", f"{where} {path}: unknown token left in title/description")
        if p.get("template", "default") not in {"default", "calculator", "meritlist"}:
            err("pages", f"{where} {path}: unknown template '{p.get('template')}'")
        if path != "/" and not p.get("h1"):
            err("pages", f"{where} {path}: h1 missing")
        if p.get("updated") and not re.fullmatch(r"\d{4}-\d{2}-\d{2}", str(p["updated"])):
            err("pages", f"{where} {path}: updated must be YYYY-MM-DD")
        if not p.get("noindex") and not p.get("keyword"):
            err("pages", f"{where} {path}: primary keyword missing")
        for key, val in (("path", path), ("title", title), ("description", desc), ("keyword", p.get("keyword"))):
            if val and val in seen[key]:
                err("pages", f"{where}: duplicate {key} '{val}' (one URL per keyword)")
            seen[key].add(val)


# ---------------------------------------------------------------- links
HREF = re.compile(r"""href=["'](/[^"'#?]*)""")


def check_links(loaded: dict[str, object]) -> None:
    pages = loaded.get("data/pages.json")
    if pages is None:
        warn("links", "data/pages.json not present yet; internal link check skipped")
        return
    items = pages.get("pages", []) if isinstance(pages, dict) else []
    slugs = {p["path"] for p in items if isinstance(p, dict) and isinstance(p.get("path"), str)}
    for f in files("*.php") + files("*.json"):
        for m in HREF.finditer(f.read_text(encoding="utf-8")):
            url = m.group(1)
            if url.startswith(("/wp-", "/data/")):
                continue
            if not url.endswith("/"):
                err("links", f"{rel(f)}: internal link without trailing slash: {url}")
            elif url not in slugs:
                err("links", f"{rel(f)}: link to unknown page {url}")


# ---------------------------------------------------------------- privacy
CNIC = re.compile(r"\b\d{5}-\d{7}-\d\b")
SECRET = re.compile(r"-----BEGIN [A-Z ]*PRIVATE KEY-----|(?i:password\s*=\s*['\"][^'\"]+)")


def check_privacy() -> None:
    for f in files():
        r = rel(f)
        if f.suffix.lower() == ".pdf":
            err("privacy", f"{r}: PDF in repo (keep PDFs in the git-ignored sources/ folder)")
            continue
        if f.suffix.lower() not in TEXT_EXT or f.stat().st_size > 5_000_000:
            continue
        try:
            text = f.read_text(encoding="utf-8")
        except UnicodeDecodeError:
            continue
        if CNIC.search(text):
            err("privacy", f"{r}: CNIC-like number found")
        if r != "scripts/check.py" and SECRET.search(text):
            err("privacy", f"{r}: possible secret")


# ---------------------------------------------------------------- size
def check_size() -> None:
    css = ROOT / "assets/css/am.css"
    if css.exists() and css.stat().st_size > 14336:
        warn("size", f"am.css is {css.stat().st_size} bytes (> 14 KB): it will load as a file, not inline")


def main() -> int:
    check_php()
    loaded = check_json()
    check_templates()
    check_pages(loaded)
    check_links(loaded)
    check_privacy()
    check_size()
    for w in warnings:
        print("WARN ", w)
    for e in errors:
        print("FAIL ", e)
    print(f"\n{len(errors)} error(s), {len(warnings)} warning(s)")
    return 1 if errors else 0


if __name__ == "__main__":
    sys.exit(main())
