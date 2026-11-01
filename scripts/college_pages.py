#!/usr/bin/env python3
"""College pages (audit 2026-11-01 G-07): writes one data/pages.json entry per Punjab public MBBS
college from data/universities/uhs.json (`colleges`, Final/1st merit rows, `list_trail`).

Usage:  python3 scripts/college_pages.py          rewrite the entries in data/pages.json
        python3 scripts/college_pages.py --check  exit 1 if data/pages.json differs (check.py runs this)

Every figure in the answer, FAQ and Roman Urdu text comes from the data; the tables are rendered live
by [am_college] (inc/colleges.php). Years are literal (the data's own year); the title uses {year:uhs}.
"""
from __future__ import annotations

import json
import math
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PAGES = ROOT / "data/pages.json"
UHS = ROOT / "data/universities/uhs.json"
PREFIX = "/mdcat-merit-list/"
TODAY = "2026-11-01"


def pct(v: float) -> str:
    return f"{v:.2f}%"


def ordinal(n: int) -> str:
    suf = "th" if 10 <= n % 100 <= 20 else {1: "st", 2: "nd", 3: "rd"}.get(n % 10, "th")
    return f"{n}{suf}"


def mdcat_needed(uhs: dict, target: float, marks: float) -> int:
    f = uhs["formulas"][0]
    fixed = sum(marks / p["total"] * p["weight"] for p in f["parts"] if p["key"] != "mdcat")
    md = next(p for p in f["parts"] if p["key"] == "mdcat")
    need = math.ceil((target - fixed) / md["weight"] * md["total"] - 1e-9)
    return need if need <= md["total"] else 0


def fit(desc: str, extras: list[str]) -> str:
    """Description of 140-155 characters: add optional phrases until it fits."""
    for e in extras:
        if len(desc) >= 140:
            break
        if len(desc) + len(e) <= 155:
            desc += e
    return desc


def build(uhs: dict) -> list[dict]:
    rows = [r for r in uhs["merit"] if r["program"] == "MBBS (public)" and r["category"] == "Open merit"]
    years = sorted({r["year"] for r in rows if r["list"] == "Final"})
    new, old = years[-1], (years[-2] if len(years) > 1 else None)
    final = {r["campus"]: r for r in rows if r["year"] == new and r["list"] == "Final"}
    first = {r["campus"]: r for r in rows if r["year"] == new and r["list"] == "1st"}
    prev = {r["campus"]: r for r in rows if r["year"] == old and r["list"] == "Final"} if old else {}
    order = sorted(final, key=lambda c: -final[c]["closing"])
    n = len(order)
    lists = max((t["list_no"] for t in uhs.get("list_trail", []) if t["year"] == new), default=0)
    out = []
    for i, campus in enumerate(order):
        meta = uhs["colleges"][campus]
        s, path = meta["short"], meta["path"]
        x = final[campus]["closing"]
        y = first[campus]["closing"]
        z = prev.get(campus, {}).get("closing")
        m1, m2 = mdcat_needed(uhs, x, 1000), mdcat_needed(uhs, x, 1050)
        above = order[i - 1] if i > 0 else None
        below = order[i + 1] if i < n - 1 else None
        drop = round(y, 2) - round(x, 2)  # printed values, so the text adds up
        nb = []
        if above:
            nb.append(f"{uhs['colleges'][above]['short']} ({pct(final[above]['closing'])}) above")
        if below:
            nb.append(f"{uhs['colleges'][below]['short']} ({pct(final[below]['closing'])}) below")
        yoy = ""
        if z is not None:
            d = x - z
            yoy = f", {'down' if d < 0 else 'up'} from {pct(z)} in the final {old} list"
        answer = (f"{campus} closed at {pct(x)} on open merit in the {ordinal(lists)} and final {new} UHS selection list "
                  f"for public MBBS, after {pct(y)} in the 1st list{yoy}. It had the {'highest' if i == 0 else 'lowest' if i == n - 1 else ordinal(i + 1) + ' highest'} closing merit "
                  f"of the {n} Punjab public medical colleges.")
        faq = [
            {"q": f"What was the {s} merit in {new}?",
             "a": f"{pct(x)} on open merit in the {ordinal(lists)} and final UHS selection list for public MBBS ({new}-{str(new + 1)[2:]} session). The 1st list closed at {pct(y)}."},
        ]
        if z is not None:
            faq.append({"q": f"What was the {s} merit in {old}?",
                        "a": f"{pct(z)} in the last {old} UHS selection list (open merit), so {new} closed {abs(round(x, 2) - round(z, 2)):.2f} points {'lower' if x < z else 'higher'}."})
        faq += [
            {"q": f"How many MDCAT marks are needed for {s}?",
             "a": f"With 1000/1100 in both FSc and Matric, about {m1}/180 in MDCAT reaches {pct(x)}, the {new} final closing merit. With 1050/1100 in both, about {m2}/180."},
            {"q": f"How much did {s} merit drop from the 1st to the final list?",
             "a": f"{drop:.2f} points in {new}: from {pct(y)} in the 1st list to {pct(x)} in the {ordinal(lists)} list."},
            {"q": f"Where does {s} rank among Punjab public medical colleges?",
             "a": f"{ordinal(i + 1)} of {n} by {new} final closing merit" + (f", with {' and '.join(nb)}." if nb else ".")},
        ]
        st = uhs.get("seats", {}).get("mbbs", {}).get(campus)
        fee = uhs.get("fee", {})
        if st:
            faq.append({"q": f"How many MBBS seats does {s} have?",
                        "a": f"{st['total']} MBBS seats for session {uhs['seats']['session']}, of which {st['open']} are open merit (UHS prospectus {uhs['seats']['session']}, seat allocation)."})
        if fee.get("mbbs"):
            faq.append({"q": f"What is the MBBS fee at {s}?",
                        "a": f"Rs {fee['mbbs'][0]:,} in the first year (Rs {fee['tuition']:,} tuition plus admission fee and charges) and about Rs {fee['mbbs'][1]:,} a year after that, for session {fee['session']}. The fee is the same at every Punjab public medical college."})
        faq += [
            {"q": f"{s} ka merit kitna jata hai?", "lang": "ur-Latn",
             "a": f"{new} ki final UHS list mein {s} ka open merit {pct(x)} par close hua" + (f"; {old} mein {pct(z)} tha." if z is not None else ".")},
            {"q": f"{s} ke liye MDCAT mein kitne number chahiye?", "lang": "ur-Latn",
             "a": f"FSc aur Matric dono 1000/1100 hon to taqreeban {m1}/180, aur dono 1050/1100 hon to taqreeban {m2}/180 ({new} ke merit {pct(x)} ke hisaab se)."},
        ]
        ru = (f"<p>{campus} ka MBBS open merit {new} ki final UHS list mein <strong>{pct(x)}</strong> par close hua. "
              f"Pehli list mein yeh {pct(y)} tha, yani aakhri list tak {drop:.2f} points neeche aaya.</p>"
              f"<p>FSc aur Matric dono 1000/1100 hon to is merit tak pohanchne ke liye MDCAT mein taqreeban {m1}/180 chahiye. "
              f"Apna aggregate <a href=\"/mdcat-aggregate-calculator/\">MDCAT aggregate calculator</a> se nikalein.</p>")
        desc = fit(f"{s} MBBS closing merit {new}: {pct(x)} in the final UHS list, {pct(y)} in the 1st list",
                   ([f", {pct(z)} in {old}"] if z is not None else []) + [". Every list, rank and MDCAT needed.", " Official UHS data.", " Updated."])
        f24 = prev.get(campus, {})
        sources = [
            {"label": f"UHS {ordinal(lists)} and final selection list, public MBBS {new} (open merit)", "url": final[campus]["source_url"], "fetched": "2026-10-31"},
            {"label": f"UHS 1st to {ordinal(lists)} selection lists, public MBBS {new}", "url": f"https://uhs.edu.pk/1selecionlistpublicom{str(new)[2:]}.php", "fetched": TODAY},
        ]
        if f24:
            sources.append({"label": f"UHS last selection list {old}, {s} (open merit)", "url": f24["source_url"], "fetched": "2026-10-31"})
        if uhs.get("seats", {}).get("mbbs", {}).get(campus):
            sources.append({"label": f"UHS MBBS/BDS Prospectus, Session {uhs['seats']['session']}: seat allocation (p. 38) and fee (p. 61)", "url": uhs["seats"]["source_url"], "fetched": TODAY})
        related = ["/mdcat-merit-list/", "/mdcat-aggregate-calculator/"]
        related += [uhs["colleges"][c]["path"] for c in (above, below) if c]
        related += ["/uhs-mbbs-admission/", "/mdcat-passing-marks/"]
        out.append({
            "path": path,
            "keyword": f"{s.lower()} merit",
            "title": next(t for t in (f"{s} Merit {{year:uhs}}: MBBS Closing Merit by List", f"{s} Merit {{year:uhs}}: MBBS Closing Merit")
                          if len(t.replace("{year:uhs}", str(new))) <= 60 or "Closing Merit by" not in t),
            "description": desc,
            "h1": f"{campus} MBBS Merit {{year:uhs}}",
            "crumb": f"{s} merit",
            "parents": ["/mdcat-merit-list/"],
            "type": "content",
            "template": "default",
            "published": TODAY,
            "updated_from_data": True,
            "college": meta["slug"],
            "answer": answer,
            "answer_source": final[campus]["source_url"],
            "content": f"[am_college uni=\"uhs\" college=\"{meta['slug']}\"]",
            "faq": faq,
            "roman_urdu": ru,
            "ru_heading": f"{s} ka merit kitna jata hai?",
            "sources": sources,
            "related": related,
        })
    return out


def main() -> int:
    uhs = json.loads(UHS.read_text(encoding="utf-8"))
    data = json.loads(PAGES.read_text(encoding="utf-8"))
    pages = data["pages"]
    want = build(uhs)
    have = [p for p in pages if p.get("college")]
    if "--check" in sys.argv:
        if have != want:
            print("college pages in data/pages.json differ from data/universities/uhs.json: run python3 scripts/college_pages.py")
            return 1
        return 0
    rest = [p for p in pages if not p.get("college")]
    at = next(i for i, p in enumerate(rest) if p.get("path") == PREFIX) + 1
    data["pages"] = rest[:at] + want + rest[at:]
    PAGES.write_text(json.dumps(data, indent=1, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"wrote {len(want)} college pages")
    return 0


if __name__ == "__main__":
    sys.exit(main())
