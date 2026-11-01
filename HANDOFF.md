# HANDOFF — Phase 8 (fixes from FIX-BRIEF.md, review of 2026-11-01)

Work order: `FIX-BRIEF.md` (rule: verify on live first, then fix). Background: `REVIEW-2026-11-01.md`. UET Lahore sources: `sources/uet/` (git-ignored; README has provenance).

## Round 2 data drop (v0.15.0, 2026-11-01; owner's Fable folder `New Ideas/round2`)
Every figure re-checked by script or by eye against the saved sources (copied to git-ignored `sources/{uet,pu,itu,comsats,kmu}/`; rows in `sources/INDEX.md`).
- **UET Lahore Fall 2024**: 169 rows from the portal table "Minimum UG Admission Aggregate (Entry-2024)" (Wayback 14 Sep 2024 + 11 Feb 2025). All values match the HTML exactly. Row with unexplained code `T` skipped. List stage not printed, so `list` = "Portal, 14 Sep 2024" / "Portal, 11 Feb 2025"; no change column against the 2025 Fourth Merit List (different stage). UET merit table now 305 KB raw / 18 KB gzip (763 rows).
- **PU 2024 and 2023**: "Merit of Last Year 2024/2023" (Wayback). BS 1st semester only, 177 + 164 records read by word position (`pdfplumber`) and mapped to the 2025 program names; ambiguous cells skipped (list in `pu.json _note`). Trend chart and change column now work for PU. Note: 15 programs with a 2024 figure but no 2025 one now enter the predictor with their 2024 figure (by design).
- **COMSATS Islamabad Fall 2023**: 23 rows, 2nd closing merit image, checked by eye; list "2nd closing".
- **ITU Fall 2025**: new `/itu-merit-list/`. 12 derived rows (lowest MERIT in the latest list per program). Fable draft had BS Computer Engineering 77.09; the list's lowest is 77.00 (fixed). Only CS/AI feed the predictor.
- **KMU**: formula (MDCAT 50 / FSc 40 / SSC 10, policy p. 25) = MDCAT calculator; FAQ added there. KMU lists still 403 to tools.
- **NUMS 2024-25** (v0.15.1): 13 rows. MBBS = 12th list per college (QIMS Baluchistan 11th), list 'Latest published' so the change column compares with 2025-26; BDS = 1st list only (2nd list has no aggregates), list '1st list', predict false. All 13 values match the PDFs.
- **IIUI**: formula re-confirmed, no change. **NUST**: nothing published. **LUMHS BMC**: first *provisional* list only, session not printed; not published.
- Still owner-gated (exact URLs in round2 `NOTES.md` "Blocked" list): KMU final lists, NUMS 2024-25 pages, COMSATS Attock/Wah/Abbottabad, JSMU Drive lists, SZABMU, IST, UET Peshawar (ETEA).

## Fix 2: UET Lahore — superseded below (now live)
- Verified: `/uet-aggregate-calculator/` and `/uet-merit-list/` 404; `/data/index.json` has only `uet-taxila`, `uet-peshawar`.
- uet.edu.pk, apply.uet.edu.pk, admission.uet.edu.pk: Cloudflare 522 (20 s) at 22:4x UTC; web.archive.org closed by the container proxy.
- Have: `sources/uet/` (PDF + .txt + README, 269 rows, "Fourth Merit List", year not printed) and the owner's draft `New Ideas/apnamerit-data/uet-lahore.json` (269 rows, raw codes in `category`, `formulas: []`).
- Missing (asked owner 2026-10-31): official aggregate formula, meaning of category codes, confirmation the list is the 2025 cycle. No page ships without the formula from an official notice.

## Fix 4: NUST closing positions (blocked)
- nust.edu.pk returns 403 to the container. Owner checked NUST closing-merit pages (mgt/cs/eng) on 2026-10-31 and they were empty (Phase 3). Needs owner screenshots/PDF of an official "Closing Merit Positions" page for ≥ 2 years before any schema or page work.

## Fix 6: speed and caching (partly; owner numbers needed)
- From the container (not Pakistan), 5 cached home requests: TTFB 0.32, 0.20, 0.26, 0.43, 0.14 s. Pakistan TTFB and PageSpeed numbers still need the owner's machine (brief step 1-2).
- Found: after each deploy the plain URLs kept serving old HTML for 10+ minutes (home `age: 702`, `x-hcdn-cache-status: HIT`, no COMSATS card, while `?x=` showed it). HTML had no `Cache-Control` header. `/data/*.json` sends `public, max-age=3600`; `am.js?ver=` sends `max-age=31536000, immutable` (fine).
- Change (v0.13.2, `inc/deploy.php`): anonymous GET HTML sends `Cache-Control: public, max-age=0, s-maxage=300`, so the CDN keeps a page at most 5 minutes; LiteSpeed's page cache still serves the revalidation.
- Diagnosis (v0.13.3, header `X-AM-Deploy` on uncached responses: version, time/path/SAPI of the purging request, LSCWP version): the post-deploy purge does run (`2026-10-31T22:36:15Z /about/ litespeed header-ok lscwp=7.9.2`), so LiteSpeed is purged. The stale layer is hCDN: plain `/` returned `x-hcdn-cache-status: MISS` at the edge but `age: 2458` (copy from an hCDN shield tier, cached before the s-maxage header existed), other edges `HIT age 7-136`. Copies cached from now on carry `s-maxage=300`; old ones need one hPanel CDN flush.
- Owner: confirm in hPanel that the CDN is on for apnamerit.com; run the 5 TTFB curls and PageSpeed (mobile) on `/`, `/mdcat-aggregate-calculator/`, `/mdcat-merit-list/`.

## Fix 7: ads.txt
- Verified 404 (2026-10-31). Nothing to do until the owner gives the AdSense publisher ID (owner already has an account).

## Fix 8: Merit Data Report wording (v0.13.2)
- Verified: H1 "Merit Data Report 2026: How Closing Merit Moved", subtitle "Every official closing merit we hold at the end of the 2026 season", while UHS/PU/UET Taxila had no 2026 lists.
- Changed: title "Merit Data Report 2026: 2025 Season Closing Merit Trends", H1 per brief, subtitle and description say mostly 2025-season data, intro has "Last data added: 31 October 2026 (COMSATS)". URL kept. **Update the "Last data added" line whenever a list is added.**

## Fix 5: merit table closing % column off-screen on mobile (v0.13.1)
- Verified live at 375 px (Playwright, 2026-10-31): `/mdcat-merit-list/` header cells Program 16-115, Campus 115-234, List 234-296, Year 296-366, Closing aggregate 366-537 (viewport 375, page scrollWidth 375). KU: Closing at 423-594. PU 262-434. COMSATS 290-462. Reproduced on all but UET Taxila (2 columns).
- Fix: `am_sc_merit_table()` puts Closing aggregate second (after Program) in head and body; sort indexes follow the header order. Still a real `<table>`.

## Fix 3 part B: COMSATS closing merit found on official domains (v0.13.0)
- Earlier phases said COMSATS has no closing merit online (admissions.comsats.edu.pk, www.comsats.edu.pk 403). A site-restricted search on 2026-10-31 found two official pages, both reachable from the container:
  - https://lahore.comsats.edu.pk/news/merit.aspx: "Merit List Fall 2026", "Closing aggregate merits for various undergraduate programs in the 2nd Merit List Fall 2026" (9 programs); the page says no 3rd/further lists. Stored as year 2026, list Final, shift "Fall intake", note says 2nd list.
  - https://ww2.comsats.edu.pk/ciitblogs/files/Closing%20merits%20Spring%202025.pdf: "Spring 2025 Undergraduate Closing Merits, Admission Office, Islamabad Campus" (21 programs, 13 KB). Year 2025, list Final, shift "Spring intake".
  - All 30 values checked by script against the extracted page/PDF text (exact match). Copies in `sources/comsats/` (git-ignored).
- New page `/comsats-merit-list/` (CollectionPage, OG image); `comsats.json → meritlist_path`; calculator FAQ/content updated; `/predictor/` list; home related; `data/sources.json`, `sources/INDEX.md`. Predictor now 336 programs (was 306). Merit table default = 2026 (Lahore); Islamabad under the year filter.
- Not found yet: Islamabad Fall 2026 closing, other campuses (islamabad.comsats.edu.pk 403).
- FAST: nu.edu.pk/Admissions 403, nu.edu.pk/Admissions/MeritList 404, admissions.nu.edu.pk (200) has no closing merit; no official program-wise closing merit found as of 2026-10-31 (also Phase 3 finding). FAST keeps the part-A line.
- `/merit-data-report-2026/`: COMSATS moved out of the "calculator only" list with an "added after this report" line (the 591 count stays as dated).

## Fix 2 done + owner data drop (v0.14.0, 2026-10-31)
Owner's consulting chat delivered `apnamerit-data/{uet-lahore,comsats,nums}.json` + NOTES.md + source PDFs (copied to git-ignored `sources/uet|nums|comsats/`). Everything re-verified here before publishing:
- **UET Lahore** (`data/universities/uet.json`, slug `uet`): 593 rows, Fall 2025 (year 2025): Fourth Merit List 269, walk-in 61, change of preference manual 71, online 192. Checks: 4th list = exact tuple match with the PDF text (269/269); walk-in/manual tuple match (draft missed the first row of each: Faisalabad Chemical A1 61.92386364 and 57.509258, added); COP-online pivot re-parsed by word x-positions (pdfplumber): 179 matched, **13 afternoon-section rows were labelled Morning A1/A1-M in the draft; the afternoon table's columns are A2/A2-M** (fixed, shift Afternoon). Labels shortened to keep the table light (list "4th list", "Pref. change (online)"; category "A1 open merit" etc.; campus "Lahore (main)", "KSK", "Faisalabad", "RCET Gujranwala", "Narowal"); meanings on the page. Default view: 4th list, A1. Non-ECAT programs (BBA, BBIT, Interior Design, Biomedical Sciences) `predict:false`.
- **Formula**: owner chose the prospectus (Spring 2026 p. 19): ECAT 33 / FSc Part-I 50 / Matric 17; non-ECAT 75/25. Portal image (20 test / 70 academics / 10 interview) matches the Spring 2025 interview notice; mentioned on the page, not used. Hafiz (0-20) and sports (0-10) marks are committee-set, so not in the calculator.
- Pages: `/uet-aggregate-calculator/`, `/uet-merit-list/` (titles/H1 per brief; first line links Taxila/Peshawar; related on Taxila/Peshawar pages; home related; predictor list). Merit table HTML ~218 KB (594 rows): watch PageSpeed; if < 90 split later lists out.
- **NUMS** (`nums.json`, `/nums-merit-list/`): 13 rows, session 2025-26 (year 2025), list "Latest published" (MBBS 6th selection list 27 Jan 2026, BDS 4th 13 Feb 2026, QIMS Baluchistan-domicile list "January 19, 2026"). Lists are cumulative; the lowest aggregate of each PDF re-checked by script (13/13). Not marked final by NUMS. Formula = PM&DC 50/40/10 (MDCAT calculator).
- **COMSATS**: +17 rows Islamabad Fall 2026 "3rd Closing Merits" image (official ww2.comsats.edu.pk, same bytes as owner copy), list "3rd" (not default, not in predictor). Values checked by eye against the image.
- KMU / Sindh: owner notes confirm no college-wise closing in the public lists; nothing published. IST, NUST: unchanged.
- Predictor now 416 programs.
- Live check (2026-10-31, fresh render): `/uet-aggregate-calculator/`, `/uet-merit-list/`, `/nums-merit-list/`, `/comsats-merit-list/` all 200 with planned titles; `/data/index.json`: uet 593, nums 13, comsats 47; `/data/uet.json` every row has source_url. UET table default 75 visible rows (4th list, A1), no horizontal scroll at 375 px. Calculator 280/480/1000 → 82.19% and "Where can you get in?" lists UET programs. Mobile perf (Playwright, 4x CPU, slow 4G): UET merit list LCP 0.56 s, TBT 20 ms, DOM 6,316 nodes (PU: LCP 1.37 s, DOM 1,843). Lighthouse CLI could not be run from the container (blocked); owner to run PageSpeed.
- Owner still to spot-check 3 UET rows against the PDF (brief "done when").

## Fix 3 part A: "no closing merit" line on calculators without data (v0.12.2)
- Verified live: `/comsats-aggregate-calculator/` config `"rows":[]`, so am.js printed no "Where can you get in?" block and no explanation (same for the other 10 with `records: 0` in `/data/index.json`).
- Fix: `am_no_merit_html()` (`inc/shortcodes.php`) → config `noRows`; am.js prints it under the result when `rows` is empty. Nearest list: same field, then same province, PU on ties (engineering → UET Taxila, others → PU). Shared calculator shortcode, so all 11 calculators without rows get it.

## Fix 1: predictor printed the older year's figure with the newest year's label (v0.12.1)

**Verified on live before the fix (2026-10-31 22:3x UTC, curl of the embedded predictor config):**
- `/mdcat-aggregate-calculator/`: `"program":"MBBS (public)","campus":"King Edward Medical University (KEMC), Lahore","ref":96.0576,"year":2025`; AIMC `"ref":95.5955,"year":2025`. `/data/uhs.json` KEMC: 2025 Final 94.8677, 2025 1st 94.9167, 2024 Final 96.0576. Reproduced.
- `/predictor/`: same KEMC row (`ref 96.0576, year 2025`). Reproduced.
- Homepage "Highest closing merits" card: UHS Punjab · 2025 94.87% KEMC — correct (it uses `am_default_rows` + `am_uni_year`, not the predictor rows). The merit-list "What can I get with my aggregate?" block only links to `/predictor/`, so it shared the bug through the predictor.

**Actual cause (differs from the brief's guess):** not list-name matching. `am_predict_rows()` (`inc/shortcodes.php`) deliberately took the *higher* closing of the last two years (documented "cautious" method) but set `year` to the *newest* year separately, so value and label came from different records. Both UHS 2024 and 2025 rows are named "Final", so the list filter was fine.

**Fix:** per program, the newest year's default-list, default-category record wins (year before only when the newest year has no row); value and printed year come from the same record. Methodology text updated everywhere it said "higher of the last two years" (am.js "Where can you get in?" note, `/predictor/`, `/methodology/`, `/disclaimer/`, `/about/`, pages.json predictor/home FAQ + WebApplication description).

**Test:** `scripts/tests/predict_rows.php` (stubbed WP, CLI) asserts every predictor row's value + year equal the merit table default row (newest year, default list, default category). Run by `scripts/check.py` (`predict`). On the old code: 24 of 306 programs failed; now 0.

**Programs whose predictor value changed (old → new, all labelled 2025/2026 before and after):**
- UHS 2025, MBBS (public), 17 colleges: KEMC 96.06 → 94.87, AIMC 95.60 → 94.45, SIMS 95.27 → 94.23, NMC 95.14 → 94.15, AMC 94.73 → 94.01, RMC 94.74 → 93.93, FJMC 94.66 → 93.81, PMC 94.57 → 93.79, QAMC 94.53 → 93.72, GMC 94.47 → 93.67, SLMC 94.43 → 93.66, KMSMC 94.40 → 93.63, NSMC 94.35 → 93.60, SMC 94.34 → 93.59, SZMC 94.32 → 93.54, DGMC 94.30 → 93.53, Narowal 94.24 → 93.51. Private colleges unchanged (UHS has no 2024 private rows).
- KU 2026 (test-based, score ≥ 50), 7 programs (the same bug: 2025 figure under a 2026 label): BE Chemical Engineering (DAE) 74.44 → 70.84, BS Human Nutrition & Dietetics 79.64 → 77.73, BS International Relations (Arts …) 52.82 → 45.36, BS International Relations (Commerce / Science) 60.19 → 60.09, BS Mass Communication 60.73 → 60.18, BS Public Administration 62.09 → 61.91, B.Ed. (Hons.) Teacher Education 51.00 → 50.09.

**Live check (2026-10-31, after deploy of 1892673):**
- Fresh render (`?x=<random>`, bypasses cache) of `/mdcat-aggregate-calculator/`: KEMC `"ref":94.8677,"year":2025`. `/predictor/`: KEMC `94.8677 (2025)`, AIMC `94.453 (2025)`, KU BS Mass Communication `60.18 (2026)`. `/methodology/` shows the new rule. `scripts/tests/predict_rows.php` 404 on the web; `FIX-BRIEF.md`/`HANDOFF.md` 403.
- Plain URL `/mdcat-aggregate-calculator/` still served the old copy (`x-litespeed-cache: hit`, `age: 1625`, `x-hcdn-cache-status: HIT/MISS`): the deploy purge (`inc/deploy.php`) runs on the first uncached PHP request, but some cached copies outlived it. Owner asked to Flush cache in hPanel (CDN) and LiteSpeed "Purge All"; re-check, and look at this under Fix 6 (caching).

# HANDOFF — Phase 7 (season operations), November 2026 step — 2026-10-31

## Status
- Theme v0.10.0. `data_year` 2025 → **2026**; `season_year` stays 2026 (moves to 2027 in May–June 2027).
- Official 2026 closing data checked on 2026-10-31 for every university with a merit table:
  - **KU**: 2026 rows (Admissions 2027 test-based/Pharm-D 19 Oct, Visual Studies 26 Oct) already in; now the default view without `defaults.year` (removed). KU 2026 rows now feed the predictor (306 programs, was 305). Open-merit Admissions 2027 closing not out (dates extended).
  - **UHS**: MDCAT 2026 held 20 Sep 2026; no 2026-27 MBBS/BDS selection lists yet. Stays 2025 (+2024).
  - **PU**: `Merit-of-Last-Year-2026.pdf` 404. Fall 2026 portal lists (admissions.pu.edu.pk/admissions/applicant/meritlist → show-merit-list/<id>) are candidate-wise and can include candidates beyond the seats (e.g. "First 42 seats on merit and other will be availability of vacant seat"), so the last row is not the closing merit: do not derive PU 2026 from them; wait for PU's summary PDF. Stays 2025.
  - **UET Taxila**: Prospectus 2026 (with 2025 entry) not on Downloads.php. Stays 2024.
- New page `/merit-data-report-2026/` (yearly data report; linked from home, `/merit-list/`, `/data/`; llms.txt group "Data reports").
- New `data/calendar.json`: season status line above each university's first merit table, last cycle's official dates, estimate windows, recurring ops.

## How things work now (Phase 7 changes)
- `am_uni_year( $uni, $list, $cat )` (`inc/growth.php`): year of a university's default view = `defaults.year` if it has rows, else newest year ≤ `data_year`, else newest. Used by the merit table default, Key figures, hub, llms-full table and predictor (window = that year and the year before). So `data_year` can move before every university has new data; no page shows an empty year.
- Tokens: `{year:<slug>}` (e.g. `{year:pu}`) = `am_uni_year()` of that university; used in titles/H1s of `/mdcat-merit-list/` and `/pu-merit-list/`, homepage, mdcat content. `{predict_count}` = programs in the predictor (homepage, `/predictor/` subtitle). `{data_year}` is no longer used in pages (sentences with fixed figures carry literal years). `scripts/check.py` and `scripts/og.py` resolve `{year:<slug>}` the same way; check.py warns on any year token next to a fixed figure.
- `[am_report year="2026"]`: summary table (newest year ≤ year, programs, highest, lowest, average change vs previous year) + Key figures per university, ignoring rows after `year`. **The prose in `content/merit-data-report-2026.html` and its pages.json entry quotes 591 records and fixed figures as of 31 Oct 2026**: if UHS/PU/UET Taxila rows for ≤ 2026 are added later, the shortcode table changes; either leave the prose dated "on 31 October 2026" (it is) or add a short "Update" line.
- llms.txt / llms-full.txt URLs now come from `site.json url` (`am_llms_url()`): after the Phase 7 deploy the file was written on the first post-deploy request, which arrived on Hostinger's temporary domain (`*.hostingersite.com`), so every link pointed there. Fixed in the follow-up commit.
- `am_season_status()` prints `data/calendar.json → universities.<slug>.status` (with source link and "checked" date) once per university per page. `scripts/check.py` validates calendar.json (status + https source, official events need date + source, estimates need window + basis).

## Indexing (owner approved 2026-10-31, after Phase 7; owner has an AdSense account, do NOT apply)
- `inc/indexnow.php` (v0.10.1): key in `site.json → indexnow.key`, served at `/<key>.txt`. On `am_deployed`, pages whose entry/content file/university JSON changed are queued and sent to api.indexnow.org by WP-Cron (`am_indexnow_send`). Last result: response header `X-AM-IndexNow-Last` on the key file (time, URL count, HTTP code; 200/202 = accepted).
- Google does not use IndexNow: owner requests indexing in GSC by hand (list in the thread); Bing: import the site from GSC in Bing Webmaster Tools.

## Ranking week 1 (v0.11.0)
- `am_siblings_html()` (`inc/template-tags.php`): calculator and merit-list pages end with "Other <group> aggregate calculators / merit lists" (groups: medical, engineering+computing, university) + link to the hub.
- `/percentage-calculator/` (`[am_percent]`, live JS in am.js, keyword "fsc percentage calculator"), linked from home and `/aggregate-calculator/`.
- Ranking plan doc: https://claude.ai/artifact/HEXx55uWiKBDfbTenpqypP (keyword gaps, next pages: UET Lahore calculator needs owner screenshot, ECAT guide, IBCC equivalence calculator, COMSATS merit list).
- `/ibcc-equivalence-calculator/` (v0.15.0, `[am_ibcc]`, grade table in `site.json → ibcc` from ibcc.edu.pk/conversion-for-equivalence; A* default 90, editable). Live check: 8 O-level grades → 650/800 = 81.25%.
- GSC: second batch of 10 URLs given 2026-11-01.
- GSC: owner requested indexing for the first 10 URLs on 2026-10-31; give 10 more per day.

## Homepage redesign (v0.12.x, owner asked 2026-10-31: "home page boring")
- `/` has `"template": "home"` → `am_home_html()` (`inc/home.php`): hero (H1, live counts, MDCAT calculator via `am_sc_calculator(['uni'=>'uhs','compact'=>'1'])`, university pills), chances box → `/predictor/?agg=&uni=`, highest closing merit per university (newest year), university cards (badge by field, formula summary, buttons), how-it-works, guides; then FAQ + Roman Urdu + disclaimer. All numbers computed from data. CSS in am.css "/* home */" (am.css 12.8 KB, 14 KB inline limit).
- Live: Lighthouse mobile 100/100/100/100, calculator 150/180 + 1000 + 1000 → 87.12%, no horizontal scroll.

## Recurring season ops (MASTER-PLAN Phase 7; also `data/calendar.json → ops`)
- **Now → March 2027**: watch uhs.edu.pk for 2026-27 MBBS/BDS selection lists (rebuild with `scripts/uhs_closing.py`, 2026 rows, list names like 2025), pu.edu.pk for Merit-of-Last-Year-2026.pdf, KU open-merit Admissions 2027. Each new list: data JSON → check.py → merge → live check → update the calendar status line.
- **May–June 2027**: `season_year` → 2027; "expected merit 2027" guides only for programs with ≥ 2 official years; refresh calendar statuses and events.
- **July–October 2027**: each new official list within 48 h: parse → JSON → push → live check → short merit-drop guide → share text for the owner.
- **November 2027**: `data_year` → 2027, report `/merit-data-report-2027/` (copy the 2026 entry, `[am_report year="2027"]`), refresh calendar.json.

## Verification done (2026-10-31)
- PHP harness (stubbed WP): default visible rows UHS 54, PU 257, KU 27 (+73 open merit), UET Taxila 12; one status line per university; tokens PU 2025 / UHS 2025 / KU 2026 / UET Taxila 2024; predictor 306.
- `scripts/check.py` 0 errors 0 warnings.
- Live (main `d53db96`): all merit pages, `/merit-list/`, `/merit-data-report-2026/`, `/predictor/`, `/data/` 200, one H1, JSON-LD parses, no PHP warnings; titles PU/MDCAT "… Closing Merit 2025", KU/UET Taxila unchanged; status line on the 4 merit pages; predictor + homepage say 306; report in the sitemap and llms.txt "Data reports"; llms.txt and llms-full.txt (92 KB) rebuilt on apnamerit.com URLs.
- Bug found and fixed during the live check: the first deploy left `am_merit_markdown()` calling `am_uni_year()` with undefined vars → TypeError while building llms-full.txt on the first post-deploy request (one 500 for that request, llms-full not rebuilt). Lesson: run the PHP harness over `am_llms_full_txt()` too before merging (stub WP functions; see Phase 7 notes in this file).

## Still owner-gated (unchanged)
- GSC/Bing indexing, IndexNow, AdSense: wait for the owner's "site complete".
- Hostinger CDN may keep stale HTML after deploys: hPanel → Cache / CDN → Flush cache.
- Pending data threads stay separate: UET Lahore, IST, KP/NUMS.

---

# HANDOFF — Phase 6 (AEO/GEO polish, performance, audit) — 2026-10-31

## Status
- Live at https://apnamerit.com, theme v0.9.0, main `190d8b8` (+ this handoff commit).
- `/llms.txt` (9.6 KB) and `/llms-full.txt` (91 KB) are live and generated from `data/*.json`; Hostinger's "Hello world" stub is gone.
- Merit pages trimmed: `/pu-merit-list/` 172 → 105 KB, `/ku-merit-list/` 152 → 110 KB, `/mdcat-merit-list/` 136 → 117 KB, all under the 150 KB budget. `/predictor/` 116 KB (unchanged, under budget).
- Every merit table now ends with "Key figures": generated quotable sentences (range, highest, lowest, average change, biggest drop/rise).
- Site icon: `assets/icons/` (SVG, 48 px PNG, 180 px apple-touch, favicon.ico copied into the WP root on deploy).

## Next phase must do (Phase 7 + Phase 6 items that need the owner)
- **Hostinger CDN (hCDN) keeps stale HTML** at some edges after a deploy (seen 2026-10-31: some edges served the pre-deploy page 20+ min later, `age` growing). LiteSpeed purge works; hCDN purge did not visibly happen even after `do_action('litespeed_purged_all')`. Owner: hPanel → Websites → apnamerit.com → Cache / CDN → **Flush cache** after big deploys, or turn the CDN off. Check with `curl -sI https://apnamerit.com/pu-merit-list/ | grep -i "age\|hcdn"` a few times.
- Still owner-gated (do not start without the owner): GSC/Bing indexing, **IndexNow** (needs a key file + pings; owner rule = no indexing pings until the site is complete), **AdSense** application (≥ 30 indexed quality pages; now 36 pages, none requested for indexing yet).
- Not possible from the container: "FAQ expansion from GSC queries", GSC Coverage report, 404 log review (needs GSC / hPanel access), PageSpeed Insights API (daily quota exhausted) and Rich Results Test. Local Lighthouse 12 (mobile emulation) was used instead.
- `data_year` → 2026 in November: answers/FAQs that quote fixed figures now carry literal years, so they stay true; titles/descriptions still use tokens (rule 6). `check.py` warns if a `{data_year}`/`{season_year}` token sits next to a fixed figure.

## How things work now (Phase 6 changes)
- `inc/aeo.php` (new): `am_quotables( $uni, $list, $cat )` → plain sentences; `am_facts_html()` (printed by `[am_merit_table]` before the trend SVG); `am_llms_txt()` / `am_llms_full_txt()`; `am_llms_write()` writes `ABSPATH/llms.txt` + `llms-full.txt` on `am_deployed`, replacing only a missing file, Hostinger's (signature `[comment]: # (Generated by Hostinger Tools Plugin)`) or ours (signature `[comment]: # (Generated by the apnamerit theme from data/*.json)`). The Hostinger plugin never overwrites a file without its signature. Fallback route serves both URLs if the root is not writable.
- llms.txt groups pages.json entries: calculators (template calculator, not /predictor/), merit lists (template meritlist, not /merit-list/), tools and open data, guides (`/guides/`), about and policies (type trust). llms-full adds each page's answer, formulas (with source), quotables, default merit table as Markdown, English FAQs.
- `inc/deploy.php` now fires `do_action( 'am_deployed' )` once per new fingerprint (after the LiteSpeed purge) and announces `litespeed_purged_all` for the Hostinger CDN hook.
- `[am_merit_table]`: a column whose value is the same on every row (PU: year, list, category) moves into the caption; the Δ column only appears when the table has ≥ 2 years; `data-*` attributes only for facets that have a filter; sources = one "Source of every row" line, or a numbered list (`#am-src-<table>-<n>`) with `[n]` links per row. Labels come from `data/sources.json` (`am_source_label()`), else host + file name (UHS 2024 per-college PDFs are not in sources.json yet).
- `assets/js/am.js`: wide `.am-table-wrap` regions get `tabindex=0`, `role=region` and the caption as label (axe `scrollable-region-focusable`).
- `.htaccess`: `Cache-Control: max-age=31536000, immutable` for js/css/svg (all versioned).

## Verification done (2026-10-31, live, Phase 6)
- Lighthouse 12 mobile (local Chromium, cache-busted URLs), 10 heaviest pages: Performance / Accessibility / Best practices / SEO = 100/100/100/100 on all (before: 97–100 / 100 / 96 / 100; favicon 404 and short JS cache were the only BP issues). PU DOM 3,347 → 1,805 elements.
- Playwright 360 px + axe-core 4.10 (WCAG 2 A/AA + best practice) on 15 pages: 0 violations, one H1 each, JSON-LD parses, no horizontal scroll, no duplicate ids, no JS errors.
- KU table: filters (27 → 56 rows with all years), sort, `[n]` source anchors work; PU: caption "Open merit, Last merit list, 2025", shift filter (7 evening rows).
- Broken-link scan of all 36 sitemap pages: 172 unique links, 0 broken internal links. External failures are container blocks only: au.edu.pk / webdata.au.edu.pk 403 (Cloudflare), uet.edu.pk 522, neduet.edu.pk proxy 502, uaf.edu.pk incomplete TLS chain.
- `/favicon.ico` 200 image/x-icon; `scripts/check.py` 0 errors 0 warnings.

---

## Phase 5 (universal predictor, hubs, growth features) — 2026-10-31

## Status
- Live at https://apnamerit.com, theme v0.8.0, main `b3f0f8e` (+ this handoff commit).
- New pages (200, one H1, in `wp-sitemap-ampages-1.xml`): `/predictor/` (WebApplication + FAQPage), `/aggregate-calculator/` and `/merit-list/` hubs (CollectionPage + FAQPage), `/embed/`, `/submit-merit-list/` (ContactPage), `/data/` (CollectionPage + DataCatalog). Header nav = Calculators, Merit lists, Predictor, Methodology; footer adds Open data, Embed, Submit a merit list. Homepage links the three hubs.
- New routes (not pages, not in sitemap): `/data/<slug>.json`, `/data/index.json` (CORS `*`, `X-Robots-Tag: noindex`, `Link: rel=license`, licence fields prepended), `/embed/<slug>/` for every university with a calculator + `/embed/predictor/` (noindex, canonical → real page, `<base target="_blank">`, "Powered by Apna Merit").
- Predictor covers 305 programs: UHS 54, PU 210, KU 29 (2025 rows; KU 2026 rows enter when data_year → 2026), UET Taxila 12. "305" is typed in `/predictor/` subtitle and the homepage: update when data grows.

## Next phase must do (Phase 6, plus pending data from Phase 2/3/4 threads)
- Phase 6 per MASTER-PLAN section 8 (llms.txt, AEO audit, accessibility, PageSpeed ≥ 95 on the 10 heaviest pages, IndexNow, broken links). Heaviest HTML now: `/pu-merit-list/` (~170 KB, over the 150 KB budget), `/ku-merit-list/`, `/mdcat-merit-list/` (~136 KB), `/predictor/` (~116 KB; 305 rows of JSON). Consider trimming row HTML (shorter source links) or lazy-rendering non-default rows.
- `/submit-merit-list/` is a mailto form (nothing stored, no third-party script). Owner decided (2026-10-31): **no Tally**, keep the mailto form; ignore the plan's Tally embed / email list.
- Program standalone pages: none built (4.6 doorway rule: needs ≥ 2 years + search demand evidence). Programs stay as row anchors `#p-<program-campus-shift>` on merit-list pages; predictor and calculator results link to them.
- Trend SVG appears only where a program has ≥ 2 years in the default list/category (now UHS and KU). PU and UET Taxila get one automatically when a second year is added.

## How things work now (Phase 5 changes)
- `inc/growth.php` (new): `[am_hub type="calculator|meritlist"]` (tables from every `data/universities/*.json`; link text = the target page's `crumb`), `am_trend_svg()` (dot plot of the 8 highest programs with ≥ 2 years, default list + category, server-side SVG, CSS classes `am-tr-*`), `am_check_box()` ("What can I get with my aggregate?" GET form → `/predictor/?agg=&uni=`, once per university per page), `[am_data_catalog]` (+ DataCatalog node, `@id /data/#catalog`), `[am_embed_builder]`, `[am_submit]`, routes at `template_redirect` priority -1.
- `[am_merit_table]` now appends: JSON download line, trend SVG, check box. Dataset `distribution.contentUrl` = `https://apnamerit.com/data/<slug>.json`; Dataset has `includedInDataCatalog`.
- `[am_predictor]`: new University select (filter `uni` = university `short`), reads `?agg=&uni=` from the URL, shows "Your saved aggregates" buttons from calculator localStorage (`am:calc:<slug>` now also stores `agg`). Calculator results link "Open this aggregate in the merit predictor".
- Calculator share URL goes through filter `am_calc_url` (embeds share the real calculator page).
- `assets/js/am.js`: embed builder, iframe height `postMessage({amEmbed:<slug>, h})` (snippet listens only to the apnamerit.com origin), mailto submit form. CSS 8.3 KB.

## Verification done (2026-10-31, live, Phase 5)
- All 6 new pages 200, one H1, JSON-LD parses (types listed above), no horizontal scroll at 360px, no JS errors; `/embed/pu` → 301 `/embed/pu/`; `/embed/nope/` 404; `/data/pu.json` 200 JSON with CORS/licence headers; `scripts/check.py` 0 errors 0 warnings.
- Calculator → predictor: MDCAT 170/180, 1050/1100, 1050/1100 → 94.95%, link `/predictor/?agg=94.95&uni=UHS Punjab` prefilled, saved-aggregate button shown, 35 Strong programs.
- Embed: code from `/embed/` pasted into a foreign test page; PU widget computes 1000/950 → 87.27% and the iframe height follows the content (1563 → 6613 px).
- Trend SVG checked by screenshot on `/mdcat-merit-list/` (8 public MBBS colleges, 2024 vs 2025) and `/ku-merit-list/`.
- Not done from the container: PageSpeed Insights and Rich Results Test (owner or Phase 6).

---

# Earlier phases (still true)

## Phase 4 open items (Phase 4 part 2)
- **UAF closing merit**: ~1,400 candidate-wise PDFs at `web.uaf.edu.pk/Downloads/MeritListsView` (names; derive closing like UHS only for final lists, never store names). No summary published.
- **KU**: Admissions 2027 open-merit closing not published yet (re-check uokadmission.edu.pk). Prospectus Appendices J/K/L = Admissions 2025 (year 2024), H/K = BS 3rd year, S/P columns of G: not stored yet (images; read visually at 220 dpi). No KU calculator: the prospectus gives no academic % formula.
- **Air University**: au.edu.pk blocks the container (Cloudflare; do not try to get past it). Calculator `/air-aggregate-calculator/` built from page text the owner copied on 2026-10-31; re-check via the owner each season.
- **IST**: `ugadmission.ist.edu.pk` (has "Merit Determination Criteria" + UG prospectus) returns 502 through the proxy; www.ist.edu.pk pages have no weights. Needs the owner to download that PDF and upload it.
- `data_year` → 2026 in November (Phase 7): KU 2026 rows then enter the predictor; remove `ku.json defaults.year` then (it only exists because KU's latest year is ahead of data_year).
- TLS: `uaf.edu.pk` and `pucit.edu.pk` send incomplete chains. Fetch with a CA file = `/root/.ccr/ca-bundle.crt` + intermediates from the certs' AIA URLs (RapidSSL TLS RSA CA G1, Sectigo Public Server Authentication CA DV R36); never `-k`.

## How things work now (only what changed)
- **Pooled parts** (`assets/js/am.js` `aggregate()`): parts with the same `pool` are summed with `scale` (default 1) before dividing, pool weight = sum of their weights. PU: matric `scale 0.25`, fsc `scale 1`. Display weights (20/80, 15/60) are only exact at default totals; `hint` text replaces "Weight X%" under the input and in the formula table. Hand-checked: PU 1000/1100 + 950/1100 → 87.27 (= 1200/1375), +60/100 test → 80.45; 1200 Matric total → 85.71; IIUI → 88.18 / 71.23; UET Taxila example unchanged 82.84.
- Merit tables show `shift` under the campus name. Calculator text says "open merit, latest list of each year" (not "final list").
- `[am_merit_table uni="…" list="…" category="…"]`: several tables per page, each restricted to one list, with its own default category; one Dataset per university; anchors get the list prefix when the list is not the university's default. `defaults.year` (optional) overrides data_year for the default view.
- Record field `predict: false` keeps a row out of the calculator/predictor (used for PU 5th-semester rows).
- PU source PDF cells given in marks (Pakistan Studies), "Nil", "new program", the ambiguous IBIT BSBA evening cell, Sheikh Zayed boys/girls, Chemistry 5th semester and Energy & Environmental 5th semester are not stored. All 257 values were re-matched against the PDF text automatically (0 missing).

## Phase 3 part 2 notes (engineering, still open in its own thread)
- **NUST closing merit positions**: `ugadmissions.nust.edu.pk/result/closingmerit.aspx?sprog=<code>`; owner checked `sprog=mgt`, `cs`, `eng` on 2026-10-31: all empty (site moved to "Admission 2027"). No official NUST closing data online now; do not use third-party NUST positions. Re-check when the next cycle publishes. Ranks, not %, so they need their own column/page design.
- **UET Lahore** (main gap; NUST + COMSATS calculators done): `apply.uet.edu.pk`, `admission.uet.edu.pk`, `uet.edu.pk` return 522; `nust.edu.pk`, `ugadmissions.nust.edu.pk`, `comsats.edu.pk`, `admissions.comsats.edu.pk`, `cuiatd.edu.pk` return 403 to this container; web.archive.org is cut off by the proxy. Owner was asked (2026-10-31) to upload: UET Lahore `MinimumAggregate2-1.pdf` + any 2025/2026 closing list, NUST merit criteria + closing/merit-position list, COMSATS campus-wise closing merit 2025. `lahore.comsats.edu.pk` (COMSATS Lahore campus site) **is** reachable — check it for the merit formula/closing lists before waiting on the owner. Then add `uet.json`, `nust.json`, `comsats.json` + `/uet-…/`, `/nust-…/`, `/comsats-…/` calculator and merit-list pages (keywords in MASTER-PLAN 3.2).
- No official program-wise closing merit exists for FAST, NED (call lists in order of merit only: `neduet.edu.pk/sites/default/files/Admissions-2026/*_Call_List/Merit_Main.htm` → board-wise PDFs with names) or GIKI, and none found for UET Peshawar (notice board only). Do not add merit-list pages for them without an official per-program figure; never use third-party estimates.
- UET Taxila: 2025-entry closing table will be in the next prospectus (not on the Downloads page on 2026-10-31; only 2024 and 2025 prospectuses). `UET-Prospectus-2024.pdf` (same folder) should have "Merit Position Entry – 2023" for a Δ column; ask before downloading.
- Engineering 2026 admissions are finished (NED 2026 call lists are live), so 2026 closing figures may appear for some universities; `data_year` moves to 2026 in November per Phase 7.
- PAA files (`data/paa/<uni>.json`) not done for engineering yet.

## Phase 2 notes (medical part 2, still open in its own thread)
- To do: KMU (KP public `cas.kmu.edu.pk`, private `caspr.kmu.edu.pk/list.php`), Sindh (DUHS, JSMU, LUMHS, SZABMU), NUMS (`numspak.edu.pk` 403), UHS 2024 public BDS / private lists, `data/paa/mdcat.json`. Each new university = `data/universities/<slug>.json`, rows on `/mdcat-merit-list/` via another `[am_merit_table uni="…"]` under its own H2.
- KMU private final lists and DUHS MBBS final list 2025-26 are orders of merit without college allocation (with CNICs): no closing merit; files deleted. Needs college-wise selection lists or KMU "closing aggregates" (owner will upload).

## How things work (from Phase 3, still true)
- Calculator pages with `merit: []` still work: no "where can you get in" list, no `meritlist_path`. Test/score parts whose total the university does not state are labelled "score in %" (FAST, GIKI) with total 100.
- UET Taxila record shape: `program` = "BSc … Engineering" / "BS Computer Science" / "BS Computer Science (pre-medical)", `campus` = "Main campus, Taxila", `field` computing/engineering, `list` = "Final", note = "Merit position at entry 2024 (Prospectus 2025, p. 136)". Reserved-category columns (J, L, N, R, T, S, X) are in the same table and not stored.
- Hand-checked examples (Node `AM.aggregate` + live browser): UET Taxila 300/400, 500/550 + Hafiz, 700/1100 → 82.84 (prospectus says 82.841); UET Peshawar 70/100, 480/550, 1000/1100 → 79.00; NED 70/100, 480/550 → 76.91; FAST computing 60, 950/1100, 1000/1100 → 73.64 (engineering 78.44); GIKI 70, 1000/1100 → 73.14.
- `data/sources.json` and `sources/INDEX.md` list every engineering document used (fetched 2026-10-31; PDFs kept outside the repo, not committed).

## How things work (from Phase 2, still true)
- **Formula:** PMDC 50/40/10 from PM&DC Admissions, Curriculum and Conduct Policy and Regulations 2026, reg. 6(3) (same in Admissions Regulations 2025, reg. 4(3)). MDCAT total 180 (PMDC "New Syllabus for MDCAT 2025"); FSc/Matric totals default 1100, editable. No Hafiz bonus in PMDC regs.
- **Medical record shape:** `program` = "MBBS (public)" / "MBBS (private)" / "BDS (public)" / "BDS (private)", `campus` = college name exactly as UHS prints it (keep identical across years so the Δ column matches), `category` = "Open merit", `list` = "1st" / "Final" / "3rd", optional `note` (e.g. "17th (last) selection list").
- **Closing merit rule (UHS):** lowest aggregate among Open-quota candidates per college ("Upgraded To" / "Current College" / "Assigned College" column) in that list. `scripts/uhs_closing.py LISTS.json --pdf-dir DIR` rebuilds the records from UHS HTML lists (fetched live) and 2024-style per-college PDFs; it never writes names/IDs. 2025+ UHS lists are HTML (`uhs.edu.pk/<n>selecionlist<public|private>[bds]om25.php`), 2024 lists are PDFs.
- `data/sources.json`: PMDC and UHS documents filled (shown on `/data-sources/`).


## How things work (from Phase 1, still true)
- **Rendering:** `am_render_page()` (`inc/template-tags.php`) builds the whole main column for every repo page and the homepage: breadcrumb → H1 → subtitle → updated date → answer → body (`content` and/or `content_file` from `content/`) → FAQ → Roman Urdu → sources → share + author box + disclaimer (content pages) → related. `type: trust` pages skip share/author box and show the updated date at the bottom. The body renders **before** `get_header()` so shortcodes can register JSON-LD nodes (`am_registry()`).
- **New pages.json fields:** `type`, `crumb`, `schema_type` (WebPage/AboutPage/ContactPage/CollectionPage), `parents`, `subtitle`, `answer`, `answer_source`, `content_file`, `faq`, `roman_urdu`, `sources`, `related`, `og_image`, `published`. Token `{founded}` added.
- **Navigation:** `data/nav.json` (header, footer). wp-admin menus are no longer used.
- **Shortcodes (real now):** `am_calculator` (multi-formula select, editable totals, bonus checkboxes like Hafiz, breakdown table, WhatsApp/copy share, "where can you get in" list, formula weights table), `am_merit_table` (server-rendered `<table>`, default view = `data_year` + `defaults.list` + `defaults.category`, filters, sort, Δ vs previous year, row anchors `#p-<program-campus-shift>`), `am_predictor` (all universities, field + province filters), `am_faq`, `am_answer`, `am_sources_table`, `am_contact`. Missing data → nothing for visitors, a stub for logged-in editors.
- **Predictor rule** (also on `/methodology/`): reference = higher of the last two years' closing (final list, open merit); Strong > ref + 1.5, Borderline within ±1.5, Unlikely < ref − 1.5 (`AM_BAND`).
- **JS** `assets/js/am.js`: pure engines `AM.aggregate/chance/predict` (Node-testable: `node -e 'const AM=require("./assets/js/am.js")'`), calculator/predictor UI with localStorage (`am:calc:<uni>`, `am:predictor`), table filter/sort, copy/native share. Inline `am-js` class in `<head>` hides non-default rows without CLS.
- **JSON-LD** `inc/schema.php`: WebPage (subtype) + BreadcrumbList + Person (`/about/#author`) on every repo page; FAQPage, WebApplication, Dataset (with JSON distribution `…/data/universities/<slug>.json`) from shortcodes. Organization has founder + foundingDate.
- **SEO:** `site.json → verification.google / verification.bing` print `google-site-verification` / `msvalidate.01` on the homepage. OG image per page (`assets/og/<slug>.png`, fallback `default.png`) with width/height/alt.
- **Scripts:** `scripts/og.py` (Pillow) → OG PNGs. `scripts/check.py` adds: content_file exists/no H1, nav paths, university schema (weights = 100, formula `source_url`, record fields, closing 0–100, record counts printed), rule-7 warnings, OG presence, links inside `content/*.html`.
- `.htaccess` also blocks `content/`; `inc/deploy.php` fingerprint includes `content/*.html`.
- `site.json` new keys: `founded`, `author.city/bio/same_as`, `contact.email`, `verification`, `disclaimer`, `ads.enabled` (false; `am_ad_slot()` prints nothing until true), `share.whatsapp`.
- Rank Math / root `.htaccess` steps skipped: owner decision + Hostinger already forces https/non-www.

## Open items / blockers
- **Owner:** send Air, Bahria, ITU, IST merit-criteria links or screenshots. Upload UET Lahore / NUST / COMSATS files (Phase 3 notes). Optional: city for `site.json author.city`.
- **Deferred by owner decision:** GSC "Request indexing" and Bing import until the whole site is complete.
- PageSpeed Insights not run from this container (quota). Owner/next phase: pagespeed.web.dev once on a calculator page.

## Verification done (2026-10-31, live, Phase 4 part 1)
- `/pu-aggregate-calculator/`, `/pu-merit-list/`, `/ku-merit-list/`, `/iiui-aggregate-calculator/`: 200, one H1, canonical, JSON-LD parses (calculators: WebPage + FAQPage + WebApplication; merit lists: CollectionPage + FAQPage + Dataset), all in `wp-sitemap-ampages-1.xml`; `scripts/check.py` 0 errors 0 warnings.
- Playwright 360px live: PU 1000/950 → 87.27% (Hafiz → 88.73%), IIUI → 88.18%, UAF 1000/480/60 → 77.45%, KU tables 27 + 73 visible rows, no duplicate ids, no horizontal scroll, no JS errors; PU table 257 rows (HTML 169 KB), KU 46 rows with 27 visible by default.

## Verification done (2026-10-31, live, Phase 3 part 1)
- All 7 new pages 200, one H1, canonical, title from tokens, JSON-LD parses (calculators: WebPage + FAQPage + WebApplication; merit list: CollectionPage + FAQPage + Dataset); sitemap lists all 20 pages; `scripts/check.py` 0 errors 0 warnings.
- Playwright 360px, live: each calculator gives the hand-checked result above, no horizontal scroll, no JS errors; UET Taxila merit table 12 rows.
- Spot-check (Claude, owner-delegated): all 12 UET Taxila closing figures re-matched against the prospectus text, all OK.

## Verification done (2026-10-31, live, Phase 1)
- All 9 pages 200; `/about` → 301 `/about/`; `/nope/` 404; `…/themes/apnamerit/content/about.html` 404.
- `/about/`: one H1, title, canonical, OG image 200, one JSON-LD block (Organization, WebSite, Person, BreadcrumbList, AboutPage); page 25 KB; first load `x-litespeed-cache: miss` after deploy (auto purge works).
- Sitemap lists all 9 URLs.
- Playwright, 360px, slow 4G + 4× CPU: LCP 1.6 s (home), 1.1 s (methodology), CLS 0.000, one request (am.js), no horizontal scroll, no JS errors.
- Fixture test (local): calculator 1000/1100, 950/1100, 320/400 at 10/40/50 → 83.64% (hand: 9.0909 + 34.5455 + 40.0000); Hafiz +20 FSc and custom totals verified in Node; predictor labels and table filter/sort/reload-restore verified headless.

## Do not
- Do not create pages in wp-admin, use wp-admin menus, or install Rank Math / page builders.
- Do not commit a test/demo university file with invented numbers or third-party (maqsad, eduvision, ilmkidunya…) closing estimates.
- Do not edit theme files on the server; do not commit PDFs, secrets or personal data.
- Do not request GSC/Bing indexing until the owner says the site is complete.
