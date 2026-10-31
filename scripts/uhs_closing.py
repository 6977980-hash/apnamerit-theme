#!/usr/bin/env python3
"""Closing merits from UHS (Punjab MBBS/BDS) selection lists -> data/universities/uhs.json "merit".

UHS publishes 2025+ selection lists as HTML tables (one page per seat category) and older lists as
one PDF per college. Closing merit = lowest aggregate among Open-merit candidates per college in that list.
Only the per-college minimum is kept; names, IDs and dates of birth are never written anywhere.

Usage:
  python3 scripts/uhs_closing.py LISTS.json [--pdf-dir DIR] > merit.json
LISTS.json: [{"url": "...php" | "pdfs": ["...pdf", ...], "year": 2025, "list": "Final",
              "program": "MBBS (public)", "note": "..."}]
Pages are fetched live; PDFs are read from --pdf-dir (download them only with the owner's OK, rule 10).
"""
import html, json, re, subprocess, sys, urllib.request
from pathlib import Path

COLLEGE_COLS = ("Upgraded To", "Current College", "Assigned College")
QUOTA_COLS = ("Quota", "Assigned Category")


def clean(s: str) -> str:
    return re.sub(r"\s+", " ", s).strip()


def table_rows(text: str) -> list[list[str]]:
    out = []
    for tr in re.findall(r"<tr.*?</tr>", text, flags=re.S):
        cells = [clean(html.unescape(re.sub(r"<[^>]+>", "", c))) for c in re.findall(r"<t[dh][^>]*>(.*?)</t[dh]>", tr, flags=re.S)]
        if cells:
            out.append(cells)
    return out


def from_html(url: str) -> dict[str, float]:
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0 (apnamerit.com data check)"})
    text = urllib.request.urlopen(req, timeout=120).read().decode("utf-8", "ignore")
    rows = table_rows(text)
    hdr = rows[0]
    h = {k: i for i, k in enumerate(hdr)}
    col = next(h[k] for k in COLLEGE_COLS if k in h)
    qc = next((h[k] for k in QUOTA_COLS if k in h), None)
    mins: dict[str, float] = {}
    for r in rows[1:]:
        if len(r) != len(hdr) or (qc is not None and r[qc] != "Open"):
            continue
        c, a = r[col], float(r[h["Aggregate"]])
        mins[c] = min(mins.get(c, 999.0), a)
    return mins


def from_pdf(path: Path) -> tuple[str, float]:
    text = subprocess.run(["pdftotext", "-layout", str(path), "-"], capture_output=True, text=True, check=True).stdout
    college = clean(text.strip().split("\n")[0])
    aggs = [float(m.group(1)) for m in re.finditer(r"\d{4}-\d{2}-\d{2}\s+(\d{2,3}(?:\.\d+)?)\s", text)]
    return college, min(aggs)


def main() -> int:
    spec = json.loads(Path(sys.argv[1]).read_text())
    pdf_dir = Path(sys.argv[sys.argv.index("--pdf-dir") + 1]) if "--pdf-dir" in sys.argv else Path(".")
    out = []
    for s in spec:
        pairs = []
        if "url" in s:
            pairs = [(c, v, s["url"]) for c, v in from_html(s["url"]).items()]
        for u in s.get("pdfs", []):
            c, v = from_pdf(pdf_dir / u.rsplit("/", 1)[1])
            pairs.append((c, v, u))
        for c, v, src in sorted(pairs, key=lambda p: -p[1]):
            rec = {"year": s["year"], "list": s["list"], "category": "Open merit", "program": s["program"],
                   "campus": c, "closing": round(v, 4), "field": "medical", "source_url": src}
            if s.get("note"):
                rec["note"] = s["note"]
            out.append(rec)
    json.dump(out, sys.stdout, indent=1, ensure_ascii=False)
    return 0


if __name__ == "__main__":
    sys.exit(main())
