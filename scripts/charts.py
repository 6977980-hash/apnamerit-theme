#!/usr/bin/env python3
"""Draw the content charts (audit v2 suggestion SP-02: pages had no images at all).

Usage:  python3 scripts/charts.py      (needs Pillow)

Writes assets/img/<name>.webp (800 px wide bar charts) from data/universities/*.json, so every
figure in a chart is a closing merit already on the page, with its source in the page's table.
Pages show them with [am_img file="<name>.webp" alt="…" caption="…"]. Re-run after data changes.
"""
from __future__ import annotations

import json
import sys
from decimal import Decimal, ROUND_HALF_UP
from pathlib import Path

from PIL import Image, ImageDraw

sys.path.insert(0, str(Path(__file__).resolve().parent))
from og import font  # noqa: E402  same font lookup as the OG images

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "assets" / "img"
FG, MUTED, ACCENT, OLD, LINE = (26, 26, 26), (85, 85, 85), (11, 110, 79), (154, 163, 158), (226, 230, 228)


def pct(v: float) -> str:
    return str(Decimal(str(v)).quantize(Decimal("0.01"), ROUND_HALF_UP)) + "%"


def uni(slug: str) -> dict:
    return json.loads((ROOT / "data" / "universities" / f"{slug}.json").read_text(encoding="utf-8"))


def bars(name: str, title: str, note: str, rows: list[tuple[str, float, float | None]], lo: float, hi: float,
         legend: tuple[str, str] | None = None) -> None:
    """rows = (label, value, older value or None); lo..hi = axis range in %."""
    W, top, rh, left = 800, 92 + (26 if legend else 0), 34, 370
    H = top + rh * len(rows) + 46
    im = Image.new("RGB", (W, H), (255, 255, 255))
    d = ImageDraw.Draw(im)
    d.text((24, 18), title, font=font("bold", 24), fill=FG)
    d.text((24, 52), note, font=font("regular", 16), fill=MUTED)
    span = W - left - 110
    x = lambda v: left + (v - lo) / (hi - lo) * span  # noqa: E731
    for i, (label, v, old) in enumerate(rows):
        y = top + i * rh
        f = font("regular", 17)
        while d.textlength(label, font=f) > left - 34 and len(label) > 4:
            label = label[:-2].rstrip() + "…" if not label.endswith("…") else label[:-2].rstrip() + "…"
        d.text((24, y + 6), label, font=f, fill=FG)
        d.rectangle([left, y + 8, left + span, y + rh - 8], fill=(245, 247, 246))
        if old is not None:
            d.rectangle([left, y + 8, x(old), y + 14], fill=OLD)
        d.rectangle([left, y + 15 if old is not None else y + 8, x(v), y + rh - 8], fill=ACCENT)
        d.text((left + span + 12, y + 6), pct(v), font=font("bold", 17), fill=FG)
    ay = top + rh * len(rows) + 8
    d.line([left, ay, left + span, ay], fill=LINE, width=1)
    for t in (lo, (lo + hi) / 2, hi):
        d.text((x(t) - 18, ay + 6), f"{t:g}%", font=font("regular", 14), fill=MUTED)
    if legend:
        d.rectangle([24, 86, 42, 96], fill=ACCENT)
        d.text((50, 80), legend[0], font=font("regular", 15), fill=MUTED)
        d.rectangle([220, 88, 238, 94], fill=OLD)
        d.text((246, 80), legend[1], font=font("regular", 15), fill=MUTED)
    OUT.mkdir(parents=True, exist_ok=True)
    im.save(OUT / name, "WEBP", quality=82, method=6)
    print(name, im.size, (OUT / name).stat().st_size, "bytes")


def main() -> None:
    # 1. UHS: public MBBS, final list 2025 vs 2024 (open merit).
    u = uni("uhs")
    fin = {(r["year"], r["campus"]): r["closing"] for r in u["merit"]
           if r.get("program") == "MBBS (public)" and r.get("list") == "Final" and r.get("category") == "Open merit"}
    short = {c: m.get("short", c) for c, m in (u.get("colleges") or {}).items()}
    rows = sorted(((short.get(c, c), v, fin.get((2024, c))) for (y, c), v in fin.items() if y == 2025), key=lambda r: -r[1])
    bars("uhs-mbbs-public-closing-2025.webp", "Punjab public MBBS closing merit, 2025 vs 2024",
         "Open merit, final UHS selection list of each year. Source: uhs.edu.pk", rows, 92, 97,
         ("2025 final list", "2024 final list"))
    # 2. UET Lahore main campus, A1 open merit, Fall 2025 Fourth Merit List, top 12.
    t = uni("uet")
    rows = sorted(((r["program"], r["closing"], None) for r in t["merit"]
                   if r["year"] == 2025 and r["list"] == "4th list" and r["category"] == "A1 open merit"
                   and r.get("campus") == "Lahore (main)" and r.get("shift", "Morning") == "Morning"
                   and r.get("predict", True) and not r.get("note")), key=lambda r: -r[1])[:12]
    bars("uet-lahore-closing-2025.webp", "UET Lahore main campus closing merit, Fall 2025",
         "A1 open merit, Fourth Merit List, ECAT programs (top 12). Source: UET minimum-aggregate list", rows, 60, 90)
    # 3. PU, open merit, 2025 last merit, BS 1st semester morning, top 12.
    p = uni("pu")
    sel = [r for r in p["merit"] if r["year"] == 2025 and r.get("category") == "Open merit"
           and r.get("shift") == "Morning" and "semester" not in r["program"]]
    dup = {r["program"] for r in sel if sum(x["program"] == r["program"] for x in sel) > 1}
    def label(r: dict) -> str:
        name = r["program"].removeprefix("BS ")
        if r["program"] in dup:
            where = r.get("campus", "").replace("Lahore", "").strip(" ()").replace(" Campus", "")
            name = name.replace(" (PUCIT)", "") + " (PUCIT " + where + ")" if "PUCIT" in name else name + " (" + where + ")"
        return name
    rows = sorted(((label(r), r["closing"], None) for r in sel), key=lambda r: -r[1])[:12]
    bars("pu-closing-2025.webp", "Punjab University highest closing merits, 2025",
         "Open merit, morning, BS 1st semester (top 12). Source: PU Merit of Last Year 2025", rows, 80, 92)


if __name__ == "__main__":
    main()
