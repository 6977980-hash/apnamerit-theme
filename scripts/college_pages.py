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
import re
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


NUMS = ROOT / "data/universities/nums.json"


def build_private(uni: dict, uhs: dict) -> list[dict]:
    """Private college pages (GSC audit 2026-11-12): UHS private MBBS colleges and NUMS colleges
    in `private_colleges`, rendered live by [am_pcollege]."""
    nums = uni["slug"] == "nums"
    us = uni["short"]
    rows = [r for r in uni["merit"] if r["program"] == "MBBS (private)" and r["category"] == "Open merit"]
    newest = {}
    for r in rows:
        if r["list"] in ("1st", "1st list"):
            continue
        if r["campus"] not in newest or r["year"] > newest[r["campus"]]["year"]:
            newest[r["campus"]] = r
    top = max(r["year"] for r in newest.values())
    newest = {c: r for c, r in newest.items() if r["year"] == top}
    order = sorted(newest, key=lambda c: -newest[c]["closing"])
    n = len(order)
    trail = uni.get("private_trail", [])
    lists = max((t["list_no"] for t in trail if t["program"] == "MBBS (private)"), default=0)
    out = []
    for campus, meta in uni.get("private_colleges", {}).items():
        s, path, slug = meta["short"], meta["path"], meta["slug"]
        r = newest[campus]
        x, year = r["closing"], r["year"]
        i = order.index(campus)
        rank = "highest" if i == 0 else "lowest" if i == n - 1 else ordinal(i + 1) + " highest"
        m0, m1 = mdcat_needed(uhs, x, 900), mdcat_needed(uhs, x, 1000)
        floor = math.ceil(0.55 * 180 - 1e-9)  # MBBS pass mark, 55% (Punjab admission policy 2026-27, para 3)

        def mr(m: int) -> str:
            return f"{m}/180" if m >= floor else f"{floor}/180 (MBBS ke liye kam az kam 55%)"
        sess = f"{year}-{str(year + 1)[2:]}"
        tok = "{year:nums}" if nums else "{year:uhs}"
        if nums:
            prev = next((p for p in rows if p["campus"] == campus and p["year"] == year - 1 and p["list"] not in ("1st", "1st list")), None)
            z = prev["closing"] if prev else None
            answer = (f"{campus} closed at {pct(x)} on open merit in NUMS's newest {sess} MBBS selection list"
                      + (f", {'down' if x < z else 'up'} from {pct(z)} in {year - 1}-{str(year)[2:]}" if z is not None else "")
                      + f". It had the {rank} closing merit of the {n} NUMS-affiliated private MBBS colleges.")
            faq = [{"q": f"What was the {s} merit in {sess}?",
                    "a": f"{pct(x)} on open merit, the lowest aggregate in NUMS's newest {sess} MBBS selection list for {s}."}]
            if z is not None:
                faq.append({"q": f"What was the {s} merit in {year - 1}-{str(year)[2:]}?",
                            "a": f"{pct(z)} in the last NUMS selection list of session {year - 1}-{str(year)[2:]}, so {sess} closed {abs(round(x, 2) - round(z, 2)):.2f} points {'lower' if x < z else 'higher'}."})
            desc_head = f"{s} merit list {sess}: MBBS closed at {pct(x)} in the newest NUMS list" + (f", {pct(z)} in {year - 1}-{str(year)[2:]}" if z is not None else "")
            ru_figs = f"NUMS ki newest {sess} list mein {s} ka MBBS open merit <strong>{pct(x)}</strong> par close hua" + (f"; {year - 1}-{str(year)[2:]} mein {pct(z)} tha." if z is not None else ".")
            ans_src = r["source_url"]
        else:
            first = next(p for p in rows if p["campus"] == campus and p["year"] == year and p["list"] == "1st")
            y = first["closing"]
            drop = round(y, 2) - round(x, 2)
            answer = (f"{campus} closed at {pct(x)} on open merit in the {ordinal(lists)} and final {year} UHS private MBBS selection list, "
                      f"after {pct(y)} in the 1st list. It had the {rank} closing merit of the {n} private medical colleges in Punjab.")
            faq = [{"q": f"What was the {s} merit in {year}?",
                    "a": f"{pct(x)} on open merit in the {ordinal(lists)} and final UHS private MBBS selection list ({sess} session). The 1st list closed at {pct(y)}."},
                   {"q": f"How much did {s} merit drop from the 1st to the final list?",
                    "a": f"{drop:.2f} points in {year}: from {pct(y)} in the 1st list to {pct(x)} in the {ordinal(lists)} list."}]
            desc_head = f"{s} merit list {year}: MBBS closed at {pct(x)} in the final UHS private list, {pct(y)} in the 1st list"
            ru_figs = (f"{year} ki final UHS private list mein {s} ka MBBS open merit <strong>{pct(x)}</strong> par close hua. "
                       f"Pehli list mein yeh {pct(y)} tha.")
            ans_src = r["source_url"]
        faq.append({"q": f"How many MDCAT marks are needed for {s}?",
                    "a": (f"With 1000/1100 in both FSc and Matric, about {m1}/180 in MDCAT reaches {pct(x)}, the {year} closing merit. With 900/1100 in both, about {m0}/180."
                          if m1 >= floor else
                          f"The MDCAT pass mark is the real limit: at least {floor}/180 (55%) for MBBS. The {year} closing merit of {pct(x)} needed only about {m1}/180 with 1000/1100 in FSc and Matric, or {m0}/180 with 900/1100.")})
        nb = [f"{uni['private_colleges'].get(c, {}).get('short', c)} ({pct(newest[c]['closing'])})" for c in (order[i - 1] if i > 0 else None, order[i + 1] if i < n - 1 else None) if c]
        faq.append({"q": f"Where does {s} rank among private medical colleges?",
                    "a": f"{ordinal(i + 1)} of {n} {us} private MBBS colleges by {year} closing merit" + (f", between {' and '.join(nb)}." if len(nb) == 2 else f", next to {nb[0]}." if nb else ".")})
        bds = meta.get("bds_campus")
        if bds:
            b = sorted([p for p in uni["merit"] if p["campus"] == bds and p["category"] == "Open merit"], key=lambda p: (p["year"], p["list"]))
            if nums:
                bn = [p for p in b if p["year"] == year]
                if bn:
                    faq.append({"q": f"What was the BDS merit at {s}?", "a": f"{pct(bn[-1]['closing'])} in NUMS's newest {sess} BDS list ({bds})."})
            else:
                b1 = next((p for p in b if p["year"] == year and p["list"] == "1st"), None)
                bl = next((p for p in b if p["year"] == year and p["list"] != "1st"), None)
                if b1 and bl:
                    faq.append({"q": f"What was the BDS merit at {s}?",
                                "a": f"{pct(bl['closing'])} in the {bl['list']} UHS private BDS list of {year} ({bds}), after {pct(b1['closing'])} in the 1st list."})
        faq += [
            {"q": f"{s} ka merit kitna jata hai?", "lang": "ur-Latn",
             "a": re.sub(r"</?strong>", "", ru_figs)},
            {"q": f"{s} ke liye MDCAT mein kitne number chahiye?", "lang": "ur-Latn",
             "a": (f"FSc aur Matric dono 1000/1100 hon to taqreeban {m1}/180, aur dono 900/1100 hon to taqreeban {m0}/180 ({year} ke merit {pct(x)} ke hisaab se)."
                   if m1 >= floor else f"MBBS ke liye MDCAT mein kam az kam {floor}/180 (55%) zaroori hain. {year} ka merit {pct(x)} is se kam number par bhi pura ho jata tha.")},
        ]
        ru = (f"<p>{ru_figs}</p><p>FSc aur Matric dono 1000/1100 hon to is merit tak pohanchne ke liye MDCAT mein taqreeban {mr(m1)} chahiye. "
              f"Apna aggregate <a href=\"/mdcat-aggregate-calculator/\">MDCAT aggregate calculator</a> se nikalein.</p>")
        desc = fit(desc_head, [". Every list, rank and MDCAT needed." if not nums else ". Rank and MDCAT needed.", " Official data.", " Updated."])
        if nums:
            sources = [{"label": f"NUMS newest {sess} MBBS selection list, {s}", "url": r["source_url"], "fetched": "2026-11-12"}]
            if z is not None:
                sources.append({"label": f"NUMS last {year - 1}-{str(year)[2:]} MBBS selection list, {s}", "url": prev["source_url"].replace("http://", "https://", 1), "fetched": "2026-11-01"})
            sources.append({"label": f"NUMS: merit lists for private-sector affiliated medical colleges, MBBS open merit, session {sess}",
                            "url": "https://numspak.edu.pk/news-detail/merit-lists-of-candidates-for-admission-in-private-sector-affiliated-medical-colleges-for-mbbs-open-merit-session-2025-26", "fetched": "2026-10-31"})
            parents, hub = ["/nums-merit-list/"], "/nums-merit-list/"
        else:
            sources = [{"label": f"UHS {ordinal(lists)} and final selection list, private MBBS {year} (open merit)", "url": r["source_url"], "fetched": "2026-11-12"},
                       {"label": f"UHS 1st to {ordinal(lists)} selection lists, private MBBS {year}", "url": f"https://uhs.edu.pk/1selecionlistprivateom{str(year)[2:]}.php", "fetched": "2026-11-12"}]
            if r.get("position_source_url"):
                sources.append({"label": f"UHS Final Order of Merit, private MBBS {year}", "url": r["position_source_url"], "fetched": "2026-11-05"})
            if bds:
                sources.append({"label": f"UHS 1st to 3rd selection lists, private BDS {year}", "url": f"https://uhs.edu.pk/1selecionlistprivatebdsom{str(year)[2:]}.php", "fetched": "2026-11-12"})
            parents, hub = ["/mdcat-merit-list/private/"], "/mdcat-merit-list/private/"
        related = [hub, "/mdcat-aggregate-calculator/", "/mdcat-merit-list/", "/compare/", "/uhs-mbbs-admission/" if not nums else "/mdcat-merit-list/private/", "/mdcat-passing-marks/"]
        out.append({
            "path": path,
            "keyword": f"{s.lower()} merit list",
            "title": next(t for t in (f"{s} Merit List {tok}: MBBS Closing Merit", f"{s} Merit List {tok}: Closing Merit", f"{s} Merit {tok}")
                          if len(t.replace(tok, str(year))) <= 60),
            "description": desc,
            "h1": f"{campus} Merit List {tok}",
            "crumb": f"{s} merit",
            "parents": parents,
            "type": "content",
            "template": "default",
            "published": "2026-11-12",
            "updated_from_data": True,
            "college": slug,
            "answer": answer,
            "answer_source": ans_src,
            "content": f"[am_pcollege uni=\"{uni['slug']}\" college=\"{slug}\"]",
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
    nums = json.loads(NUMS.read_text(encoding="utf-8"))
    want = build(uhs) + build_private(uhs, uhs) + build_private(nums, uhs)
    have = [p for p in pages if p.get("college")]
    if "--check" in sys.argv:
        if have != want:
            print("college pages in data/pages.json differ from data/universities/uhs.json: run python3 scripts/college_pages.py")
            return 1
        return 0
    rest = [p for p in pages if not p.get("college")]
    at = next(i for i, p in enumerate(rest) if p.get("path") == PREFIX) + 1
    data["pages"] = rest[:at] + want + rest[at:]
    PAGES.write_text(json.dumps(data, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"wrote {len(want)} college pages")
    return 0


if __name__ == "__main__":
    sys.exit(main())
