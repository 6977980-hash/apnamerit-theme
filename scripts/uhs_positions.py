#!/usr/bin/env python3
"""Closing merit positions for UHS (Punjab MBBS/BDS) -> "position" on data/universities/uhs.json merit rows.

UHS selection lists show each admitted candidate's aggregate but no merit number. The Final Order of
Merit (one page per sector and program) numbers every candidate (Sr #). The closing position of a
college in a list = the merit number, in that Final Order of Merit, of the open-merit candidate who
holds the college's closing aggregate (the lowest aggregate admitted on open merit). Candidates are
matched by the UHS user ID only in memory; names, IDs and dates of birth are never written anywhere.

A college gets no position when its closing candidate is missing from the order of merit or the two
pages show different aggregates for that candidate (reported on stderr).

Usage:
  python3 scripts/uhs_positions.py SPEC.json [--write]
SPEC.json: [{"order": "https://www.uhs.edu.pk/finalommbbsml25.php",
             "list_url": "https://uhs.edu.pk/13selecionlistpublicom25.php",
             "year": 2025, "list": "Final", "program": "MBBS (public)"}]
"--write" sets position + position_source_url on the matching rows (year, list, program, campus)
and fails if a computed closing aggregate differs from the row's closing.
"""
import html, json, re, sys, urllib.request
from pathlib import Path

UHS = Path(__file__).resolve().parent.parent / "data" / "universities" / "uhs.json"


def clean(s: str) -> str:
    return re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", "", s))).strip()


def rows(url: str) -> list[list[str]]:
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0 (apnamerit.com data check)"})
    text = urllib.request.urlopen(req, timeout=300).read().decode("utf-8", "ignore")
    out = []
    for tr in re.findall(r"<tr.*?</tr>", text, flags=re.S):
        cells = [clean(c) for c in re.findall(r"<t[dh][^>]*>(.*?)</t[dh]>", tr, flags=re.S)]
        if cells:
            out.append(cells)
    return out


def order(url: str) -> dict[str, tuple[int, float]]:
    r = rows(url)
    hdr = r[0]
    h = {k: i for i, k in enumerate(hdr)}
    return {x[h["User id"]]: (int(x[h["Sr #"]]), float(x[h["Aggregate"]])) for x in r[1:] if len(x) == len(hdr)}


def selected(url: str) -> list[tuple[str, str, float]]:
    r = rows(url)
    hdr = r[0]
    while len(hdr) > 1 and hdr[0] == hdr[1] and len(r) > 1 and len(r[1]) == len(hdr) - 1:
        hdr = hdr[1:]
    h = {k: i for i, k in enumerate(hdr)}
    col = next(h[k] for k in ("Upgraded To", "Current College", "Assigned College") if k in h)
    qc = next((h[k] for k in ("Quota", "Assigned Category") if k in h), None)
    return [(x[h["ID"]], x[col], float(x[h["Aggregate"]])) for x in r[1:]
            if len(x) == len(hdr) and (qc is None or x[qc] == "Open")]


def positions(order_url: str, list_url: str) -> dict[str, tuple[int | None, float]]:
    m, sel = order(order_url), selected(list_url)
    low: dict[str, float] = {}
    for _, c, a in sel:
        low[c] = min(low.get(c, 999.0), a)
    out: dict[str, tuple[int | None, float]] = {c: (None, a) for c, a in low.items()}
    for i, c, a in sel:
        if a != low[c]:
            continue
        if i not in m or abs(m[i][1] - a) > 0.00011:
            print(f"  no match for the closing candidate of {c} ({a})", file=sys.stderr)
            continue
        if out[c][0] is None or m[i][0] > out[c][0]:
            out[c] = (m[i][0], a)
    return out


def main() -> int:
    spec = json.loads(Path(sys.argv[1]).read_text())
    write = "--write" in sys.argv
    data = json.loads(UHS.read_text())
    idx = {(r["year"], r["list"], r["program"], r.get("campus", "")): r for r in data["merit"]}
    bad = 0
    for s in spec:
        print(f"{s['year']} {s['list']} {s['program']}", file=sys.stderr)
        for c, (p, a) in sorted(positions(s["order"], s["list_url"]).items(), key=lambda kv: kv[1][0] or 10**9):
            print(f"  {p}\t{a}\t{c}", file=sys.stderr)
            row = idx.get((s["year"], s["list"], s["program"], c))
            if row is None or abs(float(row["closing"]) - a) > 0.0001:
                print(f"  !! no merit row with closing {a} for {c}", file=sys.stderr)
                bad += 1
                continue
            if write:
                row.pop("position", None), row.pop("position_source_url", None)
                if p is not None:
                    row["position"], row["position_source_url"] = p, s["order"]
    if write and not bad:
        UHS.write_text(json.dumps(data, indent=1, ensure_ascii=False))
    return 1 if bad else 0


if __name__ == "__main__":
    sys.exit(main())
