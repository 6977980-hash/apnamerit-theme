# HANDOFF — Phase 3 (engineering / computing), part 1 — 2026-10-31

## Status
- Live at https://apnamerit.com, theme v0.6.0 (`main` 1596628 + this handoff).
- New live pages (200, in sitemap, one H1, FAQPage + BreadcrumbList JSON-LD): `/uet-taxila-aggregate-calculator/`, `/uet-peshawar-aggregate-calculator/`, `/ned-aggregate-calculator/`, `/fast-aggregate-calculator/`, `/giki-aggregate-calculator/`, `/nust-aggregate-calculator/` (WebApplication), `/uet-taxila-merit-list/` (Dataset, CollectionPage), `/guides/1st-2nd-final-merit-list/`. Homepage has an "Engineering and computing" section linking all of them.
- Data (`data/universities/`): `nust.json` (75% NET, 15% HSSC Part-1, 10% SSC; A level 25% O level; read from owner screenshot of the official page; closing merit = merit positions, not stored yet), `uet-taxila.json` (formulas 33/50/17 + Hafiz +20 on FSc Part-I, BS Math/Physics 70/30; **12 records**, 2024 open merit from Prospectus 2025 p. 136 "Merit Position Entry – 2024"), `uet-peshawar.json` (10/40/50 engineering, BS CS 40/60 SSC/Part-I), `ned.json` (40% HSC-I + 60% test), `fast.json` (computing 50/40/10, engineering 33/50/17), `giki.json` (85% test + 15% SSC). Last four have `merit: []`.
- Phase 2 part 2 (KMU/Sindh/NUMS medical) still open in its own thread; see "Phase 2 notes" below.
- Owner: contact@apnamerit.com mailbox created; MX = mx1/mx2.hostinger.com (checked 2026-10-31).

## Next phase must do (Phase 3 part 2, then Phase 4)
- **NUST closing merit positions**: `ugadmissions.nust.edu.pk/result/closingmerit.aspx?sprog=<code>`; `sprog=mgt` was empty in the owner screenshot; other programme codes unknown. Ranks, not %, so they need their own column/page design.
- **UET Lahore, NUST, COMSATS**: `apply.uet.edu.pk`, `admission.uet.edu.pk`, `uet.edu.pk` return 522; `nust.edu.pk`, `ugadmissions.nust.edu.pk`, `comsats.edu.pk`, `admissions.comsats.edu.pk`, `cuiatd.edu.pk` return 403 to this container; web.archive.org is cut off by the proxy. Owner was asked (2026-10-31) to upload: UET Lahore `MinimumAggregate2-1.pdf` + any 2025/2026 closing list, NUST merit criteria + closing/merit-position list, COMSATS campus-wise closing merit 2025. `lahore.comsats.edu.pk` (COMSATS Lahore campus site) **is** reachable — check it for the merit formula/closing lists before waiting on the owner. Then add `uet.json`, `nust.json`, `comsats.json` + `/uet-…/`, `/nust-…/`, `/comsats-…/` calculator and merit-list pages (keywords in MASTER-PLAN 3.2).
- No official program-wise closing merit exists for FAST, NED (call lists in order of merit only: `neduet.edu.pk/sites/default/files/Admissions-2026/*_Call_List/Merit_Main.htm` → board-wise PDFs with names) or GIKI, and none found for UET Peshawar (notice board only). Do not add merit-list pages for them without an official per-program figure; never use third-party estimates.
- UET Taxila: 2025-entry closing table will be in the next prospectus (not on the Downloads page on 2026-10-31; only 2024 and 2025 prospectuses). `UET-Prospectus-2024.pdf` (same folder) should have "Merit Position Entry – 2023" for a Δ column; ask before downloading.
- Engineering 2026 admissions are finished (NED 2026 call lists are live), so 2026 closing figures may appear for some universities; `data_year` moves to 2026 in November per Phase 7.
- PAA files (`data/paa/<uni>.json`) not done for engineering yet.

## Phase 2 notes (medical part 2, still open in its own thread)
- To do: KMU (KP public `cas.kmu.edu.pk`, private `caspr.kmu.edu.pk/list.php`), Sindh (DUHS, JSMU, LUMHS, SZABMU), NUMS (`numspak.edu.pk` 403), UHS 2024 public BDS / private lists, `data/paa/mdcat.json`. Each new university = `data/universities/<slug>.json`, rows on `/mdcat-merit-list/` via another `[am_merit_table uni="…"]` under its own H2.
- KMU private final lists and DUHS MBBS final list 2025-26 are orders of merit without college allocation (with CNICs): no closing merit; files deleted. Needs college-wise selection lists or KMU "closing aggregates" (owner will upload).

## How things work now (only what changed)
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
- **Owner:** upload UET Lahore / NUST / COMSATS files (above). Optional: city for `site.json author.city`.
- **Deferred by owner decision:** GSC "Request indexing" and Bing import until the whole site is complete.
- PageSpeed Insights not run from this container (quota). Owner/next phase: pagespeed.web.dev once on a calculator page.

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
