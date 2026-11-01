#!/usr/bin/env python3
"""Apna Merit pre-merge checks (master plan 9.1). Standard library only.

Usage:  python3 scripts/check.py        (exit code 1 if any check fails)

Checks:
  php      - `php -l` on every PHP file (skipped with a warning if php is not installed)
  json     - every data/**/*.json parses; data/site.json has required keys; no {{ placeholders;
             every merit record under data/universities/ has a source_url
  template - page templates have a Template Name header, at most one <h1>, wp_head/wp_footer present,
             no debug output, no jQuery or web fonts
  pages    - data/pages.json: title <= 60, description 140-155, unique path/title/description/keyword,
             content_file exists (no <h1> inside), rule-7 blocks on content pages, OG image present
  nav      - data/nav.json paths exist in data/pages.json
  unis     - data/universities/*.json: formulas (weights add up to 100), merit records complete
  links    - internal links in PHP/JSON/content HTML resolve to paths in data/pages.json
  privacy  - no PDFs, CNIC-like numbers or private keys committed
  size     - am.css within the 14 KB inline budget
  predict  - scripts/tests/predict_rows.php: predictor value + year = merit table default row
  sources  - data/sources.json: one entry per body, every data/universities slug exactly once
  copy     - one predictor rule everywhere (no "last two years" / "do saal"); data sentences use
             {year:<slug>}, not {data_year}/{season_year}; predictor "no closing merit" FAQ = data
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

    def uni_year(slug: str) -> str:
        # Mirrors am_uni_year(): defaults.year if it has rows, else newest year <= data_year, else newest.
        u = loaded.get(f"data/universities/{slug}.json")
        if not isinstance(u, dict):
            err("pages", f"token {{year:{slug}}}: data/universities/{slug}.json missing")
            return str(site.get("data_year", ""))
        dflt = u.get("defaults", {}) or {}
        lst, cat = dflt.get("list", "Final"), dflt.get("category", "Open merit")
        years = {int(r["year"]) for r in u.get("merit", []) if r.get("list") == lst and r.get("category") == cat
                 and isinstance(r.get("closing"), (int, float)) and r.get("source_url")}
        if not years:
            return str(site.get("data_year", ""))
        if dflt.get("year") and int(dflt["year"]) in years:
            return str(dflt["year"])
        upto = [y for y in years if y <= int(site.get("data_year", 0))]
        return str(max(upto) if upto else max(years))

    def fill(t: str) -> str:
        for k, v in tokens.items():
            t = t.replace(k, v)
        return re.sub(r"\{year:([a-z0-9-]+)\}", lambda m: uni_year(m.group(1)), t)

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
        if p.get("template", "default") not in {"default", "calculator", "meritlist", "home"}:
            err("pages", f"{where} {path}: unknown template '{p.get('template')}'")
        if path != "/" and not p.get("h1"):
            err("pages", f"{where} {path}: h1 missing")
        if p.get("updated") and not re.fullmatch(r"\d{4}-\d{2}-\d{2}", str(p["updated"])):
            err("pages", f"{where} {path}: updated must be YYYY-MM-DD")
        if not p.get("noindex") and not p.get("keyword"):
            err("pages", f"{where} {path}: primary keyword missing")
        if p.get("type", "content") not in {"content", "trust"}:
            err("pages", f"{where} {path}: type must be content or trust")
        cf = p.get("content_file")
        if cf:
            f = ROOT / "content" / Path(cf).name
            if not f.exists():
                err("pages", f"{where} {path}: content_file content/{cf} missing")
            elif "<h1" in f.read_text(encoding="utf-8").lower():
                err("pages", f"{where} {path}: content/{cf} must not contain <h1> (the theme prints it)")
        if "<h1" in str(p.get("content", "")).lower():
            err("pages", f"{where} {path}: content must not contain <h1>")
        for s_ in p.get("sources", []) or []:
            if not str(s_.get("url", "")).startswith("https://"):
                err("pages", f"{where} {path}: source without https url")
        for q in p.get("faq", []) or []:
            if not q.get("q") or not q.get("a"):
                err("pages", f"{where} {path}: FAQ item needs q and a")
        if p.get("type", "content") == "content" and path != "/" and not p.get("noindex"):
            missing = [k for k in ("answer", "faq", "roman_urdu", "sources", "related") if not p.get(k)
                       and not (k in ("answer", "faq") and f"[am_{k}" in str(p.get("content", "")))]
            if missing:
                warn("pages", f"{path}: content page missing rule-7 blocks: {', '.join(missing)}")
        # A sentence quoting a fixed figure must carry a literal year: {data_year}/{season_year}
        # move on their own every season and would re-date an old number (Phase 6 answer audit).
        texts = [p.get("answer", "")] + [q.get("a", "") for q in p.get("faq", []) or []]
        cf_text = (ROOT / "content" / Path(cf).name).read_text(encoding="utf-8") if cf and (ROOT / "content" / Path(cf).name).exists() else ""
        for t in texts + [cf_text, str(p.get("content", ""))]:
            plain = re.sub(r"<a [^>]*>.*?</a>", "", str(t))
            for sent in re.split(r"(?<=[.!?])\s+|<li>|<p>|<h2", plain):
                if re.search(r"\{(data_year|season_year|year:[a-z0-9-]+)\}", sent) and re.search(r"\d\.\d+%", sent):
                    warn("pages", f"{path}: year token next to a fixed figure, use a literal year: {sent[:90]!r}")
                # A sentence describing data ("final list", "closing merit"...) names the data's own
                # year: {year:<slug>}. {data_year}/{season_year} move every season (audit F-02).
                if re.search(r"\{(data_year|season_year)\}", sent) and DATA_WORDS.search(re.sub(r"<[^>]+>", "", sent)):
                    err("copy", f"{path}: data sentence uses {{data_year}}/{{season_year}}, use {{year:<slug>}}: {sent[:90]!r}")
        slug = re.sub(r"[^a-z0-9]+", "-", path.lower()).strip("-") or "home"
        if not p.get("noindex") and not (ROOT / "assets/og" / (p.get("og_image") or f"{slug}.png")).exists():
            warn("pages", f"{path}: no OG image (run python3 scripts/og.py)")
        for key, val in (("path", path), ("title", title), ("description", desc), ("keyword", p.get("keyword"))):
            if val and val in seen[key]:
                err("pages", f"{where}: duplicate {key} '{val}' (one URL per keyword)")
            seen[key].add(val)


DATA_WORDS = re.compile(r"final list|closing merit|closing aggregate|closing percentage|open merit", re.I)

# ---------------------------------------------------------------- nav + universities
def page_paths(loaded: dict[str, object]) -> set[str]:
    pages = loaded.get("data/pages.json")
    items = pages.get("pages", []) if isinstance(pages, dict) else []
    return {p["path"] for p in items if isinstance(p, dict) and isinstance(p.get("path"), str)}


def check_nav(loaded: dict[str, object]) -> None:
    nav = loaded.get("data/nav.json")
    if not isinstance(nav, dict):
        return
    paths = page_paths(loaded)
    for where in ("header", "footer"):
        for item in nav.get(where, []):
            if item.get("path") not in paths:
                err("nav", f"data/nav.json {where}: {item.get('path')} is not in data/pages.json")


def check_unis(loaded: dict[str, object]) -> None:
    paths = page_paths(loaded)
    for name, u in loaded.items():
        if not name.startswith("data/universities/") or Path(name).name.startswith("_"):
            continue
        if not isinstance(u, dict):
            err("unis", f"{name}: not an object")
            continue
        for key in ("slug", "name", "short"):
            if not u.get(key):
                err("unis", f"{name}: '{key}' missing")
        for key in ("calculator_path", "meritlist_path"):
            if u.get(key) and u[key] not in paths:
                err("unis", f"{name}: {key} {u[key]} is not in data/pages.json")
        for f in u.get("formulas", []):
            parts = f.get("parts", [])
            total = sum(float(p.get("weight", 0)) for p in parts)
            if abs(total - 100) > 0.001:
                err("unis", f"{name} formula {f.get('id')}: weights add up to {total}, not 100")
            if not f.get("source_url"):
                err("unis", f"{name} formula {f.get('id')}: source_url missing")
            for p in parts:
                if not p.get("key") or not p.get("label") or not float(p.get("total", 0)) > 0:
                    err("unis", f"{name} formula {f.get('id')}: part needs key, label and total > 0")
        counts: dict[int, int] = {}
        for i, r in enumerate(u.get("merit", [])):
            for key in ("year", "list", "category", "program", "closing", "source_url"):
                if r.get(key) in (None, ""):
                    err("unis", f"{name} merit[{i}]: '{key}' missing")
            c = r.get("closing")
            if isinstance(c, (int, float)) and not 0 < c <= 100:
                err("unis", f"{name} merit[{i}]: closing {c} out of range")
            counts[r.get("year")] = counts.get(r.get("year"), 0) + 1
        if counts:
            print(f"INFO  [unis] {name}: " + ", ".join(f"{y}: {n}" for y, n in sorted(counts.items(), key=str)) + " records")


# ---------------------------------------------------------------- links
HREF = re.compile(r"""href=["'](/[^"'#?]*)""")


def check_links(loaded: dict[str, object]) -> None:
    pages = loaded.get("data/pages.json")
    if pages is None:
        warn("links", "data/pages.json not present yet; internal link check skipped")
        return
    items = pages.get("pages", []) if isinstance(pages, dict) else []
    slugs = {p["path"] for p in items if isinstance(p, dict) and isinstance(p.get("path"), str)}
    for f in files("*.php") + files("*.json") + files("*.html"):
        for m in HREF.finditer(f.read_text(encoding="utf-8")):
            url = m.group(1)
            if url.startswith(("/wp-", "/data/")):
                continue
            if url.startswith("/wp-content/"):
                continue
            if not url.endswith("/"):
                err("links", f"{rel(f)}: internal link without trailing slash: {url}")
            elif url not in slugs:
                err("links", f"{rel(f)}: link to unknown page {url}")


# ---------------------------------------------------------------- season calendar
def check_calendar(loaded: dict[str, object]) -> None:
    cal = loaded.get("data/calendar.json")
    if cal is None:
        return
    site = loaded.get("data/site.json") or {}
    if not isinstance(cal, dict):
        err("calendar", "data/calendar.json must be an object")
        return
    if cal.get("season_year") not in (site.get("season_year"), (site.get("season_year") or 0) + 1):
        err("calendar", f"season_year {cal.get('season_year')} should be site season_year or the next one")
    for slug, row in (cal.get("universities") or {}).items():
        if f"data/universities/{slug}.json" not in loaded:
            err("calendar", f"universities.{slug}: no data/universities/{slug}.json")
        if not row.get("status") or not str(row.get("source_url", "")).startswith("https://"):
            err("calendar", f"universities.{slug}: status and https source_url required (no source, no fact)")
        if row.get("checked") and not re.fullmatch(r"\d{4}-\d{2}-\d{2}", str(row["checked"])):
            err("calendar", f"universities.{slug}: checked must be YYYY-MM-DD")
    for i, ev in enumerate(cal.get("events") or []):
        kind = ev.get("kind")
        if kind == "official" and (not ev.get("date") or not str(ev.get("source_url", "")).startswith("https://")):
            err("calendar", f"events[{i}]: official event needs date and https source_url")
        elif kind == "estimate" and (not ev.get("window") or not ev.get("basis")):
            err("calendar", f"events[{i}]: estimate needs window and basis")
        elif kind not in ("official", "estimate"):
            err("calendar", f"events[{i}]: kind must be official or estimate")


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


def check_predict() -> None:
    php = shutil.which("php")
    if not php:
        warn("predict", "php not installed; predictor test skipped")
        return
    r = subprocess.run([php, str(ROOT / "scripts/tests/predict_rows.php")], capture_output=True, text=True)
    out = (r.stdout + r.stderr).strip()
    if r.returncode != 0:
        err("predict", out)
    else:
        print("INFO  [predict]", out.splitlines()[-1] if out else "ok")


# ---------------------------------------------------------------- sources + copy (audit 2026-11-01)
def check_sources(loaded: dict[str, object]) -> None:
    src = loaded.get("data/sources.json")
    if not isinstance(src, dict):
        err("sources", "data/sources.json missing")
        return
    items = src.get("universities", [])
    names = [u.get("name") for u in items]
    for n in {n for n in names if names.count(n) > 1}:
        err("sources", f"data/sources.json: '{n}' listed more than once")
    slugs = [u.get("slug") for u in items if u.get("slug")]
    for name, u in loaded.items():
        if name.startswith("data/universities/") and not Path(name).name.startswith("_") and isinstance(u, dict):
            n = slugs.count(u.get("slug"))
            if n != 1:
                err("sources", f"data/sources.json: slug '{u.get('slug')}' appears {n} times (needs exactly one entry)")
    for s_ in slugs:
        if f"data/universities/{s_}.json" not in loaded:
            err("sources", f"data/sources.json: slug '{s_}' has no data/universities/{s_}.json")


TWO_YEARS = re.compile(r"last two years|past two years|do saal|zyada wala", re.I)


def check_copy(loaded: dict[str, object]) -> None:
    # One predictor rule (newest official year per program, audit F-04): no "two years" wording.
    texts = [(rel(f), f.read_text(encoding="utf-8")) for f in files("*.html") if "content" in f.parts]
    texts.append(("data/pages.json", (ROOT / "data/pages.json").read_text(encoding="utf-8")))
    for where, t in texts:
        for m in TWO_YEARS.finditer(t):
            err("copy", f"{where}: '{m.group(0)}' contradicts the predictor rule (newest official year per program)")
    # The predictor FAQ naming universities without closing merits must match the data (audit F-09).
    pages = (loaded.get("data/pages.json") or {}).get("pages", [])
    pred = next((p for p in pages if p.get("path") == "/predictor/"), {})
    faq = next((q.get("a", "") for q in pred.get("faq", []) or [] if "do not publish them online" in q.get("a", "")), "")
    if not faq:
        return
    for name, u in loaded.items():
        if not name.startswith("data/universities/") or not isinstance(u, dict) or not u.get("formulas"):
            continue
        has_rows = any(isinstance(r.get("closing"), (int, float)) and r.get("source_url") for r in u.get("merit", []))
        named = re.search(r"(?<![\w-])" + re.escape(str(u.get("short", ""))) + r"(?![\w-])", faq) is not None
        if has_rows and named:
            err("copy", f"/predictor/ FAQ says {u.get('short')} publishes no closing merit, but {name} has merit rows")
        if not has_rows and not named:
            err("copy", f"/predictor/ FAQ: add {u.get('short')} to the universities without closing merits")


def main() -> int:
    check_php()
    loaded = check_json()
    check_templates()
    check_pages(loaded)
    check_nav(loaded)
    check_unis(loaded)
    check_calendar(loaded)
    check_links(loaded)
    check_privacy()
    check_size()
    check_predict()
    check_sources(loaded)
    check_copy(loaded)
    for w in warnings:
        print("WARN ", w)
    for e in errors:
        print("FAIL ", e)
    print(f"\n{len(errors)} error(s), {len(warnings)} warning(s)")
    return 1 if errors else 0


if __name__ == "__main__":
    sys.exit(main())
