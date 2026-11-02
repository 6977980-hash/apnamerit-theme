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
  size     - am.css within the 20 KB inline budget (~4.5 KB gzipped)
  predict  - scripts/tests/predict_rows.php: predictor value + year = merit table default row
  sources  - data/sources.json: one entry per body, every data/universities slug exactly once
  elig     - eligibility floors: source_url, min_pct 0-100, part in the formula, programs regex,
             every calculator page prints [am_eligibility], MDCAT FAQ = UHS floors, JS engine test (node)
  copy     - one predictor rule everywhere (no "last two years" / "do saal"); data sentences use
             {year:<slug>}, not {data_year}/{season_year}; predictor "no closing merit" FAQ = data
  college  - data/pages.json college pages = scripts/college_pages.py output from uhs.json (G-07)
  faq      - no repeated question on a page; ru_heading is a Roman Urdu question (<= 70 chars, unique);
             merit-list pages carry ru_heading + a Roman Urdu "merit" FAQ; every x.xx% in a merit-list
             page FAQ is a closing figure in data/universities (audit G-08/G-10)
"""
from __future__ import annotations

import json
from decimal import ROUND_HALF_UP, Decimal
import os
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


def skipped(check: str, msg: str) -> None:
    # A skipped tool test is a warning locally but a failure in CI (GitHub Actions sets CI=true).
    (err if os.environ.get("CI") else warn)(check, msg)


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
        skipped("php", "php not installed; lint skipped")
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
        t = re.sub(r"\{session:([a-z0-9-]+)\}", lambda m: session(m.group(1)), t)
        return re.sub(r"\{year:([a-z0-9-]+)\}", lambda m: uni_year(m.group(1)), t)

    def session(slug: str) -> str:
        s_ = ((loaded.get("data/calendar.json") or {}).get("seasons") or {}).get(slug) or {}
        if not s_.get("session"):
            err("pages", f"token {{session:{slug}}}: data/calendar.json seasons.{slug}.session missing")
        return str(s_.get("session", site.get("season_year", "")))

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
    # seasons.<slug>: current session's official schedule ([am_season], audit G-02).
    day = r"\d{4}-\d{2}-\d{2}"
    for slug, s_ in (cal.get("seasons") or {}).items():
        if not str(s_.get("source_url", "")).startswith("https://") or not s_.get("session") or not s_.get("steps"):
            err("calendar", f"seasons.{slug}: session, steps and https source_url required")
        for st in s_.get("steps") or []:
            for col in s_.get("columns") or []:
                d = str(st.get(col, ""))
                if d and not re.fullmatch(f"{day}(/{day})?", d):
                    err("calendar", f"seasons.{slug} '{st.get('step')}' {col}: date must be YYYY-MM-DD or start/end")
        live = s_.get("live") or {}
        if live:
            if not re.fullmatch(day, str(live.get("date", ""))) or not re.fullmatch(day + r"T\d{2}:\d{2}(:\d{2})?\+05:00", str(live.get("checked", ""))) or not live.get("step"):
                err("calendar", f"seasons.{slug}.live: step, date YYYY-MM-DD and checked YYYY-MM-DDTHH:MM+05:00 required")
            if live.get("out") and not str(live.get("url", "")).startswith("https://"):
                err("calendar", f"seasons.{slug}.live: out=true needs the official https url")


# ---------------------------------------------------------------- updates log (idea 5, 2026-11-01)
def check_updates(loaded: dict[str, object]) -> None:
    ups = loaded.get("data/updates.json")
    if ups is None:
        return
    paths = page_paths(loaded)
    items = ups.get("updates") if isinstance(ups, dict) else None
    if not isinstance(items, list) or not items:
        err("updates", "data/updates.json needs a non-empty 'updates' list")
        return
    for i, u in enumerate(items):
        where = f"updates[{i}]"
        if not re.fullmatch(r"\d{4}-\d{2}-\d{2}", str(u.get("date", ""))):
            err("updates", f"{where}: date must be YYYY-MM-DD")
        if not u.get("title") or len(str(u["title"])) > 110:
            err("updates", f"{where}: title required, <= 110 chars")
        url = str(u.get("url", ""))
        if url and not (url.startswith("https://") or url in paths):
            err("updates", f"{where}: url must be an existing site path or https: {url}")
    if "/updates/" not in paths:
        warn("updates", "data/updates.json exists but there is no /updates/ page")
    # Season step guides ([am_season] 'what to do now' links) must be site pages.
    cal = loaded.get("data/calendar.json") or {}
    for slug, s_ in (cal.get("seasons") or {}).items():
        if s_.get("page") and s_["page"] not in paths:
            err("updates", f"seasons.{slug}.page {s_['page']} is not a page")
        for st in s_.get("steps") or []:
            if st.get("guide") and st["guide"] not in paths:
                err("updates", f"seasons.{slug} step '{st.get('step')}': guide {st['guide']} is not a page")


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
    if css.exists() and css.stat().st_size > 20480:
        warn("size", f"am.css is {css.stat().st_size} bytes (> 20 KB): it will load as a file, not inline")


def check_version() -> None:
    """functions.php AM_VERSION = style.css Version = newest CHANGELOG entry (audit TS-03)."""
    fv = re.search(r"define\(\s*'AM_VERSION',\s*'([^']+)'", (ROOT / "functions.php").read_text())
    sv = re.search(r"^Version:\s*(\S+)", (ROOT / "style.css").read_text(), re.M)
    cv = re.search(r"^## (\S+)", (ROOT / "CHANGELOG.md").read_text(), re.M) if (ROOT / "CHANGELOG.md").exists() else None
    got = {"functions.php": fv and fv.group(1), "style.css": sv and sv.group(1), "CHANGELOG.md": cv and cv.group(1)}
    if len(set(got.values())) != 1:
        err("version", "versions differ: " + ", ".join(f"{k} {v}" for k, v in got.items()))


def check_engines() -> None:
    node = shutil.which("node")
    if not node:
        skipped("engines", "node not installed; engine tests skipped")
        return
    r = subprocess.run([node, str(ROOT / "scripts/tests/engines.js"), str(ROOT)], capture_output=True, text=True)
    out = (r.stdout + r.stderr).strip()
    if r.returncode != 0:
        err("engines", out)
    else:
        print("INFO  [engines]", out.splitlines()[-1] if out else "ok")


def check_predict() -> None:
    php = shutil.which("php")
    if not php:
        skipped("predict", "php not installed; predictor test skipped")
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


def check_elig(loaded: dict[str, object]) -> None:
    pages = (loaded.get("data/pages.json") or {}).get("pages", [])
    by_path = {p.get("path"): p for p in pages}
    for name, u in loaded.items():
        if not name.startswith("data/universities/") or not isinstance(u, dict):
            continue
        formulas = {f.get("id"): {p.get("key") for p in f.get("parts", [])} for f in u.get("formulas", [])}
        for i, e in enumerate(u.get("eligibility", []) or []):
            where = f"{name} eligibility[{i}]"
            for key in ("part", "label", "applies_to", "source_url"):
                if not e.get(key):
                    err("elig", f"{where}: '{key}' missing")
            m = e.get("min_pct")
            if not isinstance(m, (int, float)) or not 0 < m <= 100:
                err("elig", f"{where}: min_pct {m!r} must be a number in 0-100")
            ids = e.get("formulas") or list(formulas)
            for fid in ids:
                if fid not in formulas:
                    err("elig", f"{where}: formula '{fid}' does not exist")
            if not any(e.get("part") in formulas.get(fid, set()) for fid in ids):
                err("elig", f"{where}: part '{e.get('part')}' is in none of its formulas")
            if e.get("programs"):
                try:
                    re.compile(e["programs"])
                except re.error as x:
                    err("elig", f"{where}: programs regex: {x}")
        path = u.get("calculator_path")
        page = by_path.get(path)
        if path and page and page.get("content_file"):
            body = (ROOT / "content" / page["content_file"]).read_text(encoding="utf-8")
            if "[am_eligibility" not in body:
                err("elig", f"{page['content_file']}: add the [am_eligibility uni=\"{u.get('slug')}\"] sentence")
    # The MDCAT calculator FAQ on the minimum MDCAT score must state the UHS floors (same data).
    uhs = loaded.get("data/universities/uhs.json") or {}
    mdcat = [e for e in uhs.get("eligibility", []) if e.get("part") == "mdcat"]
    page = by_path.get("/mdcat-aggregate-calculator/", {})
    faq = next((q.get("a", "") for q in page.get("faq", []) or [] if "minimum MDCAT" in q.get("q", "")), None)
    if mdcat and faq is None:
        err("elig", "/mdcat-aggregate-calculator/: FAQ 'What is the minimum MDCAT score for MBBS?' missing")
    for e in mdcat:
        if faq is not None and f"{e['min_pct']:g}%" not in faq:
            err("elig", f"/mdcat-aggregate-calculator/ minimum-MDCAT FAQ does not say {e['min_pct']:g}% ({e['applies_to']})")
    # Engine: 80/180 MDCAT is below both floors -> no rows; 150/180 is eligible everywhere.
    node = shutil.which("node")
    if not node:
        skipped("elig", "node not installed; engine test skipped")
        return
    test = r"""
const AM = require(process.argv[1]);
const fl = JSON.parse(process.argv[2]);
const f = {parts: [{key:'mdcat',label:'MDCAT',total:180,weight:50},{key:'fsc',label:'FSc',total:1100,weight:40},{key:'matric',label:'Matric',total:1100,weight:10}]};
const rows = [{program:'MBBS (public)', ref: 90}, {program:'BDS (private)', ref: 70}];
const run = (m, s) => { const r = AM.aggregate(f, {o_mdcat:m, o_fsc:s, o_matric:s}); return [r.aggregate, AM.eligibleRows(rows, AM.eligibility(fl, r.parts)).length]; };
console.log(JSON.stringify([run(80,1050), run(95,1050), run(150,1000), run(150,600)]));
"""
    r = subprocess.run([node, "-e", test, str(ROOT / "assets/js/am.js"), json.dumps(uhs.get("eligibility", []))], capture_output=True, text=True)
    try:
        got = json.loads(r.stdout)
    except ValueError:
        err("elig", f"engine test did not run: {r.stderr.strip()[:200]}")
        return
    want = [[69.9495, 0], [74.1162, 1], [87.1212, 2], [68.9394, 0]]
    if got != want:
        err("elig", f"engine test: got {got}, want {want} (aggregate, eligible rows)")


RU_SPELLING = {"nikalain": "nikalein", "pichle": "pichhle", "ek": "aik", "masalan": "maslan", "kam se kam": "kam az kam"}


def check_copy(loaded: dict[str, object]) -> None:
    # One predictor rule (newest official year per program, audit F-04): no "two years" wording.
    texts = [(rel(f), f.read_text(encoding="utf-8")) for f in files("*.html") if "content" in f.parts]
    texts.append(("data/pages.json", (ROOT / "data/pages.json").read_text(encoding="utf-8")))
    for where, t in texts:
        for m in TWO_YEARS.finditer(t):
            err("copy", f"{where}: '{m.group(0)}' contradicts the predictor rule (newest official year per program)")
        # One Roman Urdu spelling per word (audit CQ-11).
        for bad, good in RU_SPELLING.items():
            if re.search(r"\b" + bad + r"\b", t, re.I):
                err("copy", f"{where}: Roman Urdu '{bad}', use '{good}'")
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


def check_faq(loaded: dict[str, object]) -> None:
    # Audit G-08 (People Also Ask questions) and G-10 (Roman Urdu search phrasing).
    pages = (loaded.get("data/pages.json") or {}).get("pages", [])
    closings: set[str] = set()
    for name, u in loaded.items():
        if name.startswith("data/universities/") and isinstance(u, dict):
            for r in u.get("merit", []) or []:
                c = r.get("closing")
                if isinstance(c, (int, float)):
                    # + half-up rounding, as PHP number_format() prints it (69.625 -> 69.63).
                    closings.update({f"{c:.2f}", f"{c:.3f}", f"{c:g}", str(Decimal(repr(c)).quantize(Decimal("0.01"), ROUND_HALF_UP))})
    seen: dict[str, str] = {}
    for p in pages:
        path = p.get("path", "?")
        qs = [str(q.get("q", "")).strip().lower() for q in p.get("faq", []) or []]
        for q in {q for q in qs if qs.count(q) > 1}:
            err("faq", f"{path}: FAQ question repeated: {q!r}")
        h = p.get("ru_heading")
        if h is not None:
            if not p.get("roman_urdu"):
                err("faq", f"{path}: ru_heading without a roman_urdu block")
            if not str(h).endswith("?") or len(str(h)) > 70 or "<" in str(h):
                err("faq", f"{path}: ru_heading must be a plain question of <= 70 chars: {h!r}")
            if str(h).lower() in seen:
                err("faq", f"{path}: ru_heading {h!r} also on {seen[str(h).lower()]} (one URL per query)")
            seen[str(h).lower()] = path
        elif p.get("roman_urdu") and p.get("type", "content") == "content":
            warn("faq", f"{path}: roman_urdu block without ru_heading (Roman Urdu query heading)")
        if (p.get("template") != "meritlist" and not p.get("college")) or p.get("noindex"):
            continue
        if not any(q.get("lang") == "ur-Latn" and "merit" in str(q.get("q", "")).lower() for q in p.get("faq", []) or []):
            err("faq", f"{path}: merit-list page needs a Roman Urdu FAQ about the merit (\"... merit kitna ...\")")
        for q in p.get("faq", []) or []:
            for m in re.finditer(r"(\d{2,3}\.\d{1,3})%", str(q.get("a", ""))):
                v = m.group(1)
                if v not in closings and f"{float(v):g}" not in closings:
                    err("faq", f"{path}: FAQ figure {v}% is not a closing merit in data/universities ({q.get('q')!r})")


def check_colleges() -> None:
    # College pages (audit G-07) are written from uhs.json by scripts/college_pages.py; they must match the data.
    r = subprocess.run([sys.executable, str(ROOT / "scripts/college_pages.py"), "--check"], capture_output=True, text=True)
    if r.returncode != 0:
        err("college", (r.stdout + r.stderr).strip()[:300])


def main() -> int:
    check_php()
    loaded = check_json()
    check_templates()
    check_pages(loaded)
    check_nav(loaded)
    check_unis(loaded)
    check_calendar(loaded)
    check_updates(loaded)
    check_links(loaded)
    check_privacy()
    check_size()
    check_predict()
    check_version()
    check_engines()
    check_sources(loaded)
    check_elig(loaded)
    check_copy(loaded)
    check_faq(loaded)
    check_colleges()
    for w in warnings:
        print("WARN ", w)
    for e in errors:
        print("FAIL ", e)
    print(f"\n{len(errors)} error(s), {len(warnings)} warning(s)")
    return 1 if errors else 0


if __name__ == "__main__":
    sys.exit(main())
