#!/usr/bin/env python3
"""Generate Open Graph images (1200x630 PNG) for every page in data/pages.json.

Usage:  python3 scripts/og.py           (needs Pillow: pip install pillow)
        python3 scripts/og.py --force   (redraw images that already exist)

Writes assets/og/<page-slug>.png (slug rule = am_path_slug() in inc/seo.php: "/" -> home,
"/uet-merit-list/" -> uet-merit-list) and assets/og/default.png. The theme picks them up
automatically (inc/seo.php am_og_image()). Commit the PNGs. Images are text only, no photos.
"""
from __future__ import annotations

import json
import re
import sys
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "assets" / "og"
W, H = 1200, 630
BG, FG, ACCENT, MUTED = (255, 255, 255), (26, 26, 26), (11, 110, 79), (85, 85, 85)
FONT_DIRS = [Path("/usr/share/fonts"), Path("/Library/Fonts"), Path("C:/Windows/Fonts")]
FONT_NAMES = {"bold": ["DejaVuSans-Bold.ttf", "Arial Bold.ttf", "arialbd.ttf"],
              "regular": ["DejaVuSans.ttf", "Arial.ttf", "arial.ttf"]}


def font(kind: str, size: int) -> ImageFont.FreeTypeFont:
    for d in FONT_DIRS:
        for name in FONT_NAMES[kind]:
            hits = list(d.rglob(name)) if d.exists() else []
            if hits:
                return ImageFont.truetype(str(hits[0]), size)
    return ImageFont.load_default(size)


def wrap(draw: ImageDraw.ImageDraw, text: str, f, width: int) -> list[str]:
    lines, line = [], ""
    for word in text.split():
        test = f"{line} {word}".strip()
        if draw.textlength(test, font=f) <= width:
            line = test
        else:
            lines.append(line)
            line = word
    lines.append(line)
    return lines


def slug(path: str) -> str:
    return re.sub(r"[^a-z0-9]+", "-", path.lower()).strip("-") or "home"


def draw(title: str, brand: str, tagline: str, domain: str, target: Path) -> None:
    img = Image.new("RGB", (W, H), BG)
    d = ImageDraw.Draw(img)
    d.rectangle([0, 0, W, 16], fill=ACCENT)
    d.text((80, 70), brand, font=font("bold", 44), fill=ACCENT)
    size = 72
    while size > 40:
        f = font("bold", size)
        lines = wrap(d, title, f, W - 160)
        if len(lines) <= 3:
            break
        size -= 4
    y = 190
    for line in lines[:4]:
        d.text((80, y), line, font=f, fill=FG)
        y += int(size * 1.2)
    d.line([80, H - 120, W - 80, H - 120], fill=(223, 227, 225), width=2)
    fd = font("bold", 28)
    room = W - 160 - d.textlength(domain, font=fd) - 40
    ts = 28
    while ts > 16 and d.textlength(tagline, font=font("regular", ts)) > room:
        ts -= 1
    d.text((80, H - 95), tagline, font=font("regular", ts), fill=MUTED)
    d.text((W - 80, H - 95), domain, font=fd, fill=ACCENT, anchor="ra")
    img = img.quantize(colors=32)
    img.save(target, optimize=True)


def main() -> int:
    force = "--force" in sys.argv
    site = json.loads((ROOT / "data/site.json").read_text(encoding="utf-8"))
    pages = json.loads((ROOT / "data/pages.json").read_text(encoding="utf-8"))["pages"]
    tokens = {"{season_year}": str(site["season_year"]), "{data_year}": str(site["data_year"]),
              "{brand}": site["brand"], "{founded}": str(site.get("founded", ""))}

    def uni_year(slug_: str) -> str:
        # Same rule as am_uni_year(): defaults.year, else newest year <= data_year, else newest.
        f = ROOT / "data/universities" / f"{slug_}.json"
        if not f.exists():
            return str(site["data_year"])
        u = json.loads(f.read_text(encoding="utf-8"))
        d = u.get("defaults", {})
        ys = {int(r["year"]) for r in u.get("merit", []) if r.get("list") == d.get("list", "Final")
              and r.get("category") == d.get("category", "Open merit") and r.get("source_url")}
        if not ys:
            return str(site["data_year"])
        if d.get("year") and int(d["year"]) in ys:
            return str(d["year"])
        upto = [y for y in ys if y <= int(site["data_year"])]
        return str(max(upto) if upto else max(ys))

    def fill(t: str) -> str:
        for k, v in tokens.items():
            t = t.replace(k, v)
        return re.sub(r"\{year:([a-z0-9-]+)\}", lambda m: uni_year(m.group(1)), t)

    OUT.mkdir(parents=True, exist_ok=True)
    jobs = [("default", site["tagline"])] + [(slug(p["path"]), fill(p.get("h1") or p["title"])) for p in pages
                                              if not p.get("noindex") and not p.get("og_image")]
    made = 0
    for name, title in jobs:
        target = OUT / f"{name}.png"
        if target.exists() and not force:
            continue
        draw(title, site["brand"], site["tagline"], site["domain"], target)
        made += 1
        print(f"wrote {target.relative_to(ROOT)} ({target.stat().st_size // 1024} KB)")
    print(f"{made} image(s) written")
    return 0


if __name__ == "__main__":
    sys.exit(main())
