# apnamerit.com — Fix brief (1 November 2026)

Source: a live review of https://apnamerit.com/ done on 1 Nov 2026 against `MASTER-PLAN.md`. Full findings are in `apnamerit-REVIEW-2026-11-01.md`; this file is the work order.

## Rule for every item: VERIFY FIRST, THEN FIX

Each fix below was observed from outside the site (curl, browser, public JSON). The reviewer could not see the theme code, so the cause stated under "Suspected cause" is a guess. For every item:

1. Reproduce it on the live site exactly as written under **Verify**. Record what you saw in HANDOFF.md.
2. If it does not reproduce, or the cause is different, write that down and say what you found instead. Do not "fix" something you could not reproduce.
3. Only then make the change, on a `phase-N-<slug>` branch, following the phase protocol in `MASTER-PLAN.md` section 0.
4. After deploy and the owner's cache purge, re-run the same **Verify** steps on the live site and paste the result in HANDOFF.md under "Live check".

Standing rules still apply: no student names or CNICs in the repo, no PDFs in the repo, official sources only, every closing figure carries its `source_url`.

Owner decisions already taken (change only if the owner says so):

- "UET" with no city means **UET Lahore**. It gets `/uet-aggregate-calculator/` and `/uet-merit-list/`. Taxila and Peshawar keep their current URLs.
- Only **official** closing merits are published. If a university has no official closing-merit document, the page says so and links the official admission page; no third-party numbers, not even tagged "unverified".
- Build order: Fix 1 (predictor bug) → Fix 2 (UET Lahore) → Fix 3 (COMSATS, FAST data) → Fix 4 (NUST positions) → Fix 5–8.

---

## Fix 1 — Predictor prints the 2024 figure with a 2025 label (P0, do first)

**Verify**
1. Open https://apnamerit.com/mdcat-aggregate-calculator/ on desktop.
2. Enter MDCAT 160 / 180, FSc 1010 / 1100, Matric 1050 / 1100. Click "Calculate aggregate". Expected aggregate: 90.72%.
3. In "Where can you get in?" → Unlikely, read the KEMC row. On 1 Nov it said: `MBBS (public) (King Edward Medical University (KEMC), Lahore) closed at 96.06% (2025), you are -5.34`.
4. Open https://apnamerit.com/mdcat-merit-list/ and https://apnamerit.com/data/uhs.json. KEMC MBBS (public): 2025 final = 94.87%, 2025 1st = 94.92%, 2024 17th (last) = 96.06%. The string "96.06" does not appear in the JSON next to year 2025.
5. Check the same for AIMC (predictor showed 95.60, table 2025 final shows 94.50), SIMS, NMC, RMC, FJMC, PMC, QAMC, GMC. Private colleges (Sharif 88.70, Shalamar 86.34, Al-Aleem 90.84) matched the table.
6. Repeat on https://apnamerit.com/predictor/ with aggregate 90.72 and university "UHS Punjab" to see whether the standalone predictor has the same error.

**Suspected cause** (verify in code before changing): the predictor's "latest list of each year" selection for the public-college records picks the 2024 "17th (last)" record, or the highest value across both years, and then prints the newest year's label. The public 2024 lists are named "17th (last)" while 2025 lists are named "13th and final"; the selector may match on list name ("final") rather than on the record's `year` + list order.

**Fix**
- Select per program: newest `year` in the dataset → within that year, the latest list (by list order, not by name) → open-merit category. Print that record's own `year`.
- Add a test (PHPUnit or a small CLI script run in CI) that, for every university JSON, builds the predictor row for each program and asserts the printed value and year equal the merit-table's default row (newest year, final list, open merit) for the same program. The test must fail on the current code.
- Re-check the homepage "Highest closing merits" cards and the "What can I get with my aggregate?" block on merit-list pages, since they probably share the same selector.

**Done when**: Verify steps 2–6 show KEMC 94.87% (2025) and AIMC 94.50% (2025) in the predictor; the new test passes; HANDOFF.md lists every program whose predictor value changed.

---

## Fix 2 — UET Lahore calculator and merit list are missing (P0)

**Verify**
1. `curl -sI https://apnamerit.com/uet-aggregate-calculator/` and `https://apnamerit.com/uet-merit-list/` → both returned 404 on 1 Nov.
2. Confirm `/data/index.json` has no `uet` or `uet-lahore` slug (it had only `uet-taxila` and `uet-peshawar`).
3. Keyword data (Keyword Planner, PK): "uet aggregate calculator" 5,000/mo, "uet merit list" 5,000/mo.

**Fix**
- Dataset slug `uet` (name "University of Engineering and Technology (UET), Lahore"). Official sources, in this order:
  - https://apply.uet.edu.pk/pdf/MinimumAggregate2-1.pdf — "Merit List After Change of Preferences", per campus (LHR, KSK, FSD, RCET, NWL) and per category (A1 open merit, A2 etc.). Record which cycle/year the PDF covers before storing anything; the filename does not say.
  - https://uet.edu.pk/.galleries/admission/ and https://admission.uet.edu.pk/uploads/downloads/ — look for 2024 and 2025 closing-merit or "minimum aggregate" PDFs and the aggregate formula notice (ECAT 30% + FSc 70% historically; confirm the 2026 weights from the current admission notice, not from memory).
  - If a needed PDF is behind a captcha or login, write the link in HANDOFF.md for the owner; do not scrape around it.
- **Already downloaded (1 Nov 2026):** the owner has `uet-sources/uet-2025-minimum-aggregate-after-preference-change.pdf` plus its extracted `.txt` and a `README.md` with provenance, category codes and campus spellings. Copy that folder into the project's git-ignored `sources/` and start from the `.txt` (269 rows, "Fourth Merit List", columns Discipline | Campus | Category | Session | Aggregate). The second Wayback PDF turned out to be the MS/M.Phil list and was deleted; do not fetch it.
- **UET sites were down on 1 Nov 2026** (Cloudflare 522 on apply.uet.edu.pk, uet.edu.pk, admission.uet.edu.pk, each after a 20 s timeout). The owner could not download the PDF either. Fallback, verified on 1 Nov:
  - Wayback snapshot of the minimum-aggregate PDF, 12 Sep 2025, 470 KB: `https://web.archive.org/web/20250912190249id_/https://apply.uet.edu.pk/pdf/MinimumAggregate2-1.pdf`
  - Wayback snapshot of "final merit list PM scheme 2025", 5 Sep 2025, 4.1 MB: `https://web.archive.org/web/20250905214802id_/https://uet.edu.pk/.galleries/Admission/final-merit-list-PM-scheme-2025.pdf` (this one is a full merit list with student rows; take only the per-program closing aggregates, never store names or CNICs).
  - Procedure: ask the owner once before downloading (name, source, size), save to `sources/` (git-ignored), parse, and set `source_url` to the **original** UET URL, with `source_note` saying "fetched from Wayback snapshot <timestamp> while uet.edu.pk was down". Re-check the live UET URL at the start of every later phase and replace the note when it comes back.
  - The Wayback CDX API was returning 503 during the review, so use the direct `web.archive.org/web/2025id_/<url>` form to probe for other snapshots (e.g. 2024 lists) rather than the CDX endpoint.
- Pages: `/uet-aggregate-calculator/` (title "UET Aggregate Calculator 2026 (ECAT Formula)", H1 "UET Lahore Aggregate Calculator 2026") and `/uet-merit-list/` (title "UET Merit List 2026: Closing Merit 2025 (All Campuses)", H1 "UET Lahore Merit List 2026 & Closing Merit 2025"). Same blueprint as the MDCAT pair. First line under the H1 links the Taxila and Peshawar pages so students on the wrong page can switch.
- Default view of the merit table: Main Campus Lahore, open merit (A1), final/after-preference-change list. Filters for campus, category, list.
- Add both pages to the homepage university grid, `/aggregate-calculator/`, `/merit-list/`, llms.txt, the "Related" block on Taxila/Peshawar pages, and the sitemap (automatic if they are normal pages).
- Internal links: at least one from the homepage, one from `/aggregate-calculator/`, one from `/merit-list/`, and cross-links between the two new pages.

**Done when**: both URLs return 200 with the planned title/H1, `/data/uet.json` has ≥ 20 program records with `source_url` on each, the calculator shows "Where can you get in?" with UET data, and the owner has spot-checked 3 rows against the PDF.

---

## Fix 3 — Calculators with zero merit records show no "Where can you get in?" (P0)

**Verify**
1. `https://apnamerit.com/data/index.json` → on 1 Nov these had `records: 0`: air, bahria, comsats, fast, giki, iiui, itu, ned, nust, uaf, uet-peshawar (10 calculators; uet-taxila has 12 records of 2024 only).
2. Open https://apnamerit.com/comsats-aggregate-calculator/, enter valid marks, calculate. Confirm no "Where can you get in?" block and no explanatory line appears under the result.

**Fix, part A (all 10, cheap)**: when a university has no records, render one line under the result card: "Closing merit for <university> is not published as an aggregate yet / is being added. Compare with the <nearest merit list> or see all merit lists." Link to `/merit-list/`. Do this in the shared template, not per page.

**Fix, part B (data, by search volume)**: COMSATS, then FAST. Search for an official closing-merit or "last merit" document on each university's own domain (comsats.edu.pk admissions pages; nu.edu.pk admissions / "merit" pages). Record in HANDOFF.md the exact URL found or "none found on official domain as of <date>". If none exists, the page keeps the part-A line and we do not publish third-party figures (owner decision above).

**Done when**: part A visible on all 10 pages; for COMSATS and FAST either a dataset with `source_url` rows and a `/<uni>-merit-list/` page, or a written "no official source" note in HANDOFF.md.

---

## Fix 4 — NUST closing merit is a position, not a percentage (P1)

**Verify**
1. https://apnamerit.com/nust-aggregate-calculator/ → section "What is the NUST closing merit?" says NUST publishes closing merit positions and the site will add them later.
2. Find NUST's official "Closing Merit Positions" page on nust.edu.pk and confirm it lists a merit position per programme per year.

**Fix**
- Extend the schema with an optional `closing_position` (integer) and `position_basis` ("NET merit list") alongside `closing_aggregate`. A record has one or the other.
- On the NUST calculator the predictor cannot use aggregate; instead add an input "Your NUST merit position (from your NET result card)" and compare positions (lower is better). Strong / Borderline / Unlikely thresholds: borderline = within 10% of the closing position; write the rule in Methodology.
- Build `/nust-merit-list/` (keyword "nust merit list", 5,000/mo) showing positions by programme and year with `source_url`.

**Done when**: `/nust-merit-list/` is live with ≥ 2 years of official positions, the NUST calculator shows the position-based predictor, and Methodology explains positions vs aggregates.

---

## Fix 5 — Mobile merit table hides the closing percentage (P1)

**Verify**
1. Browser at 375 px wide, open https://apnamerit.com/mdcat-merit-list/, scroll to the table.
2. On 1 Nov the visible columns were Program, Campus, List, Year; the closing-aggregate column was cut off to the right and only reachable by horizontal scroll inside the table. Page itself had no horizontal overflow (document.scrollWidth 375 = innerWidth).

**Fix**: move the closing-aggregate column to the second position (after Program) in every merit table, or make Program and Closing % sticky columns. Keep the table a real `<table>` (needed for Dataset/AEO). Check it on all four existing merit-list pages and the PU page (257 rows) for performance.

**Done when**: at 375 px, Program and Closing % are visible without horizontal scroll on all merit-list pages.

---

## Fix 6 — Slow and uneven TTFB from Pakistan (P1)

**Verify**
1. From the owner's machine run 5 times: `curl -s -o NUL -w "ttfb=%{time_starttransfer}s total=%{time_total}s\n" https://apnamerit.com/`. On 1 Nov: 0.63, 0.82, 0.85, 1.17, 2.77 s, all with `x-litespeed-cache: hit` and `x-hcdn-cache-status: HIT` (one home-page request showed `MISS`).
2. Run https://pagespeed.web.dev/ (mobile) on `/`, `/mdcat-aggregate-calculator/`, `/mdcat-merit-list/`; the API was rate-limited during the review so no scores exist yet. Record Performance, LCP, CLS, TBT, "server response time" in HANDOFF.md.

**Fix** (only what the numbers justify): confirm Hostinger CDN is enabled for the domain in hPanel; check LiteSpeed Cache TTL for the home page (it should not be purged on every data commit, only on content change); verify `Cache-Control` headers on HTML (none was sent on 1 Nov; add `public, max-age=0, s-maxage=…` or whatever LiteSpeed's ESI-free default is) and on `/data/*.json` and `am.js` (long max-age with the `?ver=` busting already in place).

**Done when**: 5 consecutive cached home-page requests all under 0.8 s TTFB from Pakistan and PageSpeed mobile ≥ 90 on the three pages.

---

## Fix 7 — `ads.txt` missing (P2, AdSense phase)

**Verify**: `https://apnamerit.com/ads.txt` → 404 on 1 Nov.
**Fix**: add it to the AdSense phase checklist; create it with the publisher ID when the owner applies. Nothing to do now.

---

## Fix 8 — Merit Data Report wording (P2)

**Verify**: https://apnamerit.com/merit-data-report-2026/ describes "every official closing merit we hold at the end of the 2026 season", while merit-list pages say UHS 2026 lists are not published yet.
**Fix**: retitle the H1/meta to make clear it covers 2025-season data and is updated as 2026 lists arrive ("Merit Data Report 2026: Closing Merit Trends (2025 season data, updated as 2026 lists arrive)"), and add a "Last data added" date line. Keep the URL.

---

## Also do in the same run (no verification needed, pure ops)

- Submit `https://apnamerit.com/wp-sitemap.xml` in Google Search Console (tag already on site) and Bing Webmaster Tools; Bing `site:apnamerit.com` returned nothing on 1 Nov.
- After Fix 1 and Fix 2 ship, request indexing for `/`, `/mdcat-aggregate-calculator/`, `/mdcat-merit-list/`, `/uet-aggregate-calculator/`, `/uet-merit-list/`.

## Phase mapping

| Phase branch | Items | Done check |
|---|---|---|
| `phase-8-predictor-year-fix` | Fix 1 | test passes, live KEMC 94.87% (2025) |
| `phase-9-uet-lahore` | Fix 2, Fix 3 part A | both URLs 200, uet.json ≥ 20 rows, empty-data line on 10 pages |
| `phase-10-comsats-fast-data` | Fix 3 part B, Fix 5 | datasets or "no official source" notes; mobile columns |
| `phase-11-nust-positions` | Fix 4 | nust-merit-list live |
| `phase-12-speed-ops` | Fix 6, Fix 8, sitemap submission | TTFB and PageSpeed numbers in HANDOFF.md |

Each phase ends with a HANDOFF.md entry containing: what was verified (with the live output), what was changed, what the live check showed, what is left.
