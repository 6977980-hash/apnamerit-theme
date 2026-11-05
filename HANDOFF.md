# HANDOFF — Phase 8 (fixes from FIX-BRIEF.md, review of 2026-11-01)


## Admission documents checklist + Pakistan speed (v0.38.0-v0.38.1, 2026-11-05)
Owner: "7 kro or sath speed wala fix" (novel idea #7 + the open TTFB item).
- **Checklist** `/guides/admission-documents-checklist/` (`[am_doc_checklist]`, `inc/documents.php`, data `data/documents.json`, JS block "documents checklist" in am.js). Universities: UHS Punjab MBBS/BDS 2026-27 (Prospectus2026.pdf pp. 46-57), KMU public 2025-26 (policy p. 26-27; 2026-27 policy not online yet), UET Lahore Fall 2026 (pp. 20-31), UET Taxila 2025 (pp. 135-139), COMSATS 2024-25 (p. 46, newest on ww2), KU Admissions 2027 (pp. 20-25). Page numbers are the printed ones (UHS printed = PDF+1, UET Taxila = PDF-15, KU = PDF-1, COMSATS 2 printed pages per PDF page). Items: `t`, `st` apply|join, `for` groups (omitted = everyone), `p`, `n`. `?uni=<slug>` preselects a university. Ticks/choice in localStorage `am_docs`. Print button adds `html.am-pdocs` so only the list prints.
- Not covered (no official list reachable): FAST (Admission Information has no document list), NUST (403), PU (no official list found), IIUI (list only for foreigners). Add when found. **Re-read each new prospectus** (UHS/KMU autumn, UET/KU/COMSATS summer) and bump `checked`.
- **Speed**: measured in the owner's Chrome (Pakistan, edge `nme`, logged-out fetch): CDN HIT 0.17-0.43 s, CDN MISS 1.2-3.3 s (LiteSpeed hit, origin upstream 0.18 s); logged-in requests BYPASS the CDN, so the owner's own browsing is always slow. Origin is Hostinger UK (ftp host 31.170.164.151, Manchester). Test on the v0.38.0 deploy: the deploy's LiteSpeed purge does **not** clear hCDN; edge copies served the old `/guides/` until their 300 s ran out, so raising s-maxage would make deploys stale for longer. v0.38.1 adds `stale-while-revalidate=600` (`AM_HTML_CACHE` in `inc/deploy.php`). Biggest remaining lever is the owner's: move the hosting plan to the Asia (India/Mumbai or Singapore) data centre in hPanel (once per 30 days, a few hours of DNS propagation). Lighthouse mobile on the new page: 100/100/100/100.

## IST Islamabad calculator (v0.37.0, 2026-11-05)
- `/ist-aggregate-calculator/` from `data/universities/ist.json`: three formulas (engineering FSc/DAE 20/40/40, engineering A-Level 40/60 with O-Level equivalence, computing/science 40/60 no test), eligibility floors, Fall 2026 seats and accepted tests. Source: www.ist.edu.pk admission page tabs (`?page=37&section=merit-determination-criteria|general-eligibility|entry-test`), read in the owner's Chrome via the Claude extension; the site is a slow Next.js app (data from apiweb.ist.edu.pk, not used). `ugadmission.ist.edu.pk` is still 502 to the container.
- **No IST merit page**: IST shows merit status only after login (eportal.ist.edu.pk) and publishes no closing aggregate. Third-party "IST closing merit" figures are estimates; do not add them. Re-check each season (first list: 2nd week of July).
- Prospectus 2025 and Student Handbook 2025 PDFs are linked from the "Undergraduate Prospectus" tab; not downloaded.

## Audit H decisions closed (v0.36.1, 2026-11-05)

Owner picked "keep all + copy button": H-1 keep official closing merits only (NUST/FAST/GIKI guide already live; NUST official positions only as a separately labelled dataset, not now). H-2 needs nothing (titles carry both years, e.g. "MDCAT Merit List 2026: ... Closing Merit 2025"). H-3 keep mailto (no Tally); /submit-merit-list/ now shows "Copy message" and "Copy email address" after submit for phones without a mail app.

## MDCAT closing merit positions (v0.36.0, 2026-11-05)

Owner: "mdcat ki closing postions find kro". UHS selection lists carry no merit number, but each sector's **Final Order of Merit** page numbers every candidate (Sr #). `scripts/uhs_positions.py scripts/uhs_positions_2025.json --write` joins the two by UHS user ID in memory and writes `position` (merit number of the open-merit student holding each college's closing aggregate) + `position_source_url` on uhs.json merit rows; nothing personal is stored. 142 of 144 2025 rows; blank where the pages disagree (UCMD private Final: order shows 75.9111 vs list 75.9444; Pak Red Crescent private 1st: candidate missing from the order). FJMC public Final: another FJMC student's aggregate differs between pages (94.0455 list vs 93.7955 order); we use the closing-aggregate holder (2,643).
- Order pages: public MBBS `finalommbbsml25.php` (top 4000), public BDS `1ombdsmlpublic25.php` (top 2000, linked from finalpublicmeritlistbds2025.php), private MBBS `finalommbbsmlprivate25.php`, private BDS `finalombdsmlprivate25.php` (all www.uhs.edu.pk).
- Shown: merit table "Closing position" column (any uni whose rows carry `position`), source line under the table, college pages line, /mdcat-merit-list/ `#closing-position` section + 2 FAQs (typed figures: KEMU 440/399, AIMC 946, SIMS 1,447, Narowal 3,516, BDS 1,513-1,619, private 777-7,859: re-read when data changes).
- **2026-27:** when UHS publishes the Final Order of Merit and selection lists, add a spec entry per list (order + list_url) and re-run. Idea #2 (MDCAT position estimator) can now use these positions. KMU/NUMS/Sindh checked 2026-11-05, none publish data that gives a closing position: NUMS 2025-26 merit position is a per-candidate lookup (news item 24 Dec 2025) and its college selection PDFs have only S No + aggregate; KMU public 2025-26 final lists are 404 on cas.kmu.edu.pk/list.php (page now shows provisional lists), the private final list (caspr.kmu.edu.pk/meritfiles/mbbs/final/flistmbbsprom.pdf) has merit numbers but no private college placement (placement at interviews); DUHS/Sindh allocate seats "merit cum choice" at interviews and publish no college-wise allotment list. NUST positions still open.

## UET Lahore recheck and Fall 2026 data (v0.34.0, 2026-11-02)

Owner: "admission.uet.edu.pk site ab khul rahi he". The portal is a new site (downloads at `https://admission.uet.edu.pk/downloads`, files under `/uploads/downloads/<uuid>.pdf`, news at `/news`).
- **Old URLs:** every `admission.uet.edu.pk/Downloads/...` URL (Fall 2025 walk-in/COP PDFs, Spring 2026 prospectus, Entry-2024 Default.aspx) is 404; `apply.uet.edu.pk/pdf/MinimumAggregate2-1.pdf` now serves a different file (Feb 2026 "Merit List After Change of Preferences", 1 page, no session label). So the Fall 2025/2024 rows could not be re-read from live files: they keep `archive_url` (Wayback) and page `sources` now link the Wayback copies.
- **Added:** Fall 2026 Merit List No. 1 (253 rows), No. 4 (276, default `4th list`) and left-over seats closing (81, list `Leftover seats`) = 610 rows, live URLs. Parsed with pdftotext and checked against pdfplumber words (category, session, value per row: 0 mismatches). Type column Non-ECAT → `predict:false` + note. Lists 2 and 3 and waiting-queue lists 7-12 parsed but **left out**: with them the UET table HTML went to ~900 KB (now ~660 KB raw, 39 KB gzip). Add them only if the page weight is solved.
- Formula unchanged (33/50/17), source now Undergraduate Prospectus Fall 2026 p. 20. Category codes pp. 22-25 (new AP1/AP2/AP1-M/AP2-M).
- Rewritten typed figures: /uet-merit-list/ (+ FAQ), 4 campus pages (content + FAQ regenerated from data), /uet-aggregate-calculator/, /guides/ecat-aggregate-formula/ (ECAT needed table), report bullet. Chart `uet-lahore-closing-2026.webp` (2026 vs 2025). `calendar.json universities.uet` season line. check.py FAQ figures accept half-up rounding (PHP number_format: 69.625 → 69.63).
- **Next:** UET Fall 2027 (advert was 7 Jun 2026 for Fall 2026): add lists as they land; ADP Fall 2026 applications close 24 Nov 2026 (not covered by the site).

## Ranking: private MBBS and BDS merit pages (v0.33.0, 2026-11-01)

- `/mdcat-merit-list/private/` ("private medical colleges merit list", 33 colleges) and `/mdcat-merit-list/bds/` ("bds merit list", 4 public + 18 private) use the new `[am_merit_table program="…"]` filter (substring match on program). Figures in their content files and FAQs are typed from uhs.json 2025-26: refresh when 2026-27 private/BDS lists land (BDS schedule starts 15 Dec 2026).
- Linked from /mdcat-merit-list/ (private section), MDCAT calculator and UHS admission related links.
- GSC batch 4 for the owner: private, bds, /compare/, /updates/, /guides/, four UHS post-result guides, /mdcat-merit-list/kemu/.

## SERP-gap ideas 1, 3, 4 (v0.35.0, 2026-11-02)
Doc: `/mnt/project-files/apnamerit.com/ideas/2026-11-02-novel-ideas.md`. Owner picked 1, 3, 4; #2 (MDCAT position estimator) waits for the UHS 2026-27 provisional merit list (store counts per aggregate band only, never names); #5-7 not picked yet.
- `[am_list_when uni="uhs"]` (inc/colleges.php + `AM.listWhen`): first 2025 list (1-13) reaching an aggregate per public college, from `list_trail`. When 2026-27 lists come, add them to `list_trail` with `year` 2026 and the tool switches to the newest year by itself.
- `[am_all_aggregates]` (inc/profile.php + `AM.profileValues`): part→field map in `am_profile_source()`; universities whose own test is not a profile field show "Needs your … test". New university formula = check its mapping there.
- Calculator "Is a retake or improvement worth it?" (`AM.whatIf`), steps 1100→20, 550→10, 400→20, 180→10, else 5% of total; Matric skipped.

## Student-intent ideas, Tier 2 (v0.32.0, 2026-11-01)
Owner: "next phase shuru kro" (after audit v2). Built ideas 6-9 and 11 of `/mnt/project-files/apnamerit.com/ideas/2026-11-01-student-intent-ideas.md`; idea 10 (expected-merit range) waits for May 2027.
- **Compare** `/compare/` (`inc/compare.php`, `[am_compare a="<slug>:<am_slug(program campus shift)>" b="…"]`): default pair KEMU vs AIMC rendered server-side; 552 programs/colleges in a JS-built datalist; JSON uses lookup tables (u, l, s, m, r). One figure per year: default list, else the lowest figure of another list (named under it). No static "X vs Y" pages yet (thin-page risk; add only for pairs with real search demand).
- **List-day box**: set `calendar.json` `seasons.uhs.live` = {step, date, checked "YYYY-MM-DDTHH:MM+05:00", out, url, note, guide} on each list date and push; shows on the home hero (replaces the Next line), above the UHS season table and the MDCAT merit table. `out: true` needs the official https `url`. Remove `live` after the list is in the data. am.js hides it 2 days after `date`.
- **Merit tables**: "Copy top 10 for WhatsApp" / share link from the rows on screen (`[data-am-wa]`).
- **Calculator result**: "Save as image" (`[data-am-img]`, canvas 1080x1350, share sheet or PNG download).
- **Predictor**: College type filter (Public/Private from "(private)" in the program name), official UHS public fee line (`am_predictor_fees()` from a university's `fee`).

## Audit v2 fixes and suggestions (v0.31.0, 2026-11-01)
Expert-team audit `/mnt/project-files/apnamerit.com/audit/2026-11-01-v2/site-audit-2026-11-01-v2.md`; owner: "suggestions ko shamil kr k sb fix kro".
- **Numbers/trust:** Hafiz bonus no longer capped (`aggregate()` in am.js; test in `scripts/tests/engines.js`). College pages are `generated` (any entry with `college`): trust line "Figures from official UHS lists · Figures checked by", author box "Text generated from the official data". `trust_label` overrides the first trust item (ITU, NUMS). `am_page_updated()`: updated → published → mtime; `updated_from_data` with `college` reads only that university. `province` may be an array (`am_uni_provinces()`).
- **Index signals:** www → 301 in the router; query-string URLs: canonical only (no noindex); embeds: noindex only; one robots.txt group; citations not nofollow; Last-Modified on repo pages; /feed/ and /updates/feed → /updates/feed/; 404s `no-cache`.
- **Security/deploy:** xmlrpc methods empty + root .htaccess 403; `am_run_deployed()` runs listeners in try/catch, errors in option `am_deploy_errors`; X-AM-Deploy header removed (check the deploy by page content or `?ver=` mtimes); check.py asserts AM_VERSION = style.css = CHANGELOG and runs the Node engine tests.
- **UX:** home "Next:" UHS step (server + am.js `[data-am-steps]`); predictor "Your chances" h2, mixed-university note, college rows grouped by program, "showing 50 of N"; merit tables: campus under program on phones (`.am-mcamp`/`.am-camp`), filter options ordered (`am_list_rank()`), text search (`[data-am-q]`), big tables keep non-default rows in `<template class="am-more">` (rows carry `data-i`); `search.php` searches pages.json; 404 has search + popular pages; `merit_guide` on NUST/FAST.
- **Content:** college "What the gaps mean" paragraph; UET campus pages link `#which-list`; Roman Urdu spellings enforced by check.py (`RU_SPELLING`); charts `scripts/charts.py` → `assets/img/*.webp`, shown with `[am_img]` (re-run after data changes); Article JSON-LD on /guides/*; `defined_term` field → DefinedTerm.
- **Not done (needs owner):** WhatsApp Channel link, author photo/background, brand social accounts (`organization.same_as`), outreach. Not done by choice: HowTo schema (Google retired HowTo rich results in 2023), am.js minify (no build step; gzip covers most), Guides in the mobile header (does not fit on one line under 412 px; owner's header design).
- **On each UHS 2026-27 list date:** add the list to uhs.json, an updates.json entry, re-run `scripts/charts.py`, and re-read the typed 2025 figures in the post-result guides.

## Student-intent ideas, Tier 1 (v0.30.0, 2026-11-01)
Owner approved the ideas doc (`/mnt/project-files/apnamerit.com/ideas/2026-11-01-student-intent-ideas.md`, thread "Suggest out-of-the-box ideas for students") and said "suggestions pe amaldaraamad kro"; Tier 1 (ideas 1-5) is live.
- **Idea 1, shareable results**: calculators write their inputs into the address bar (`?f=<formula>&o_<part>=…&t_<part>=…&b_<bonus>=1`) and read them back on load (a link wins over this device's saved marks); the predictor does the same with `?agg=&uni=&field=&prov=`. WhatsApp/copy texts carry the chance summary (`{chances}` token in `site.json` `share.whatsapp` / new `share.predictor`) and the result link; a "Copy link" button was added. `inc/seo.php`: a repo page served with any query string is `noindex,follow`; canonical stays the clean URL.
- **Idea 2, reverse calculator**: `AM.marksNeeded(formula, values, partKey, target)` in `am.js` (linear in each part, works with PU pools and bonuses; Node-tested). Every calculator with predictor rows shows "Marks needed for a target" under the result: target program (newest closing merit) × part to solve (default: the test part), with the Strong margin and the eligibility floor for that part.
- **Idea 3, preference order**: predictor prints "Suggested preference order" when the result is one university: highest closing merit first, chips Reach (Unlikely within 2 × band above the aggregate) / Match / Safe, one list per program when rows are college-wise (MBBS public, BDS public, MBBS private), "Copy … preference list" buttons. Guide `/guides/uhs-college-preference-order/` (rules from the Punjab admission policy 2026-27 + UHS ad).
- **Idea 4, post-result guides** (all facts from `admissionpolicymbbsbdssession2026-27.pdf`, read as images, and `images/mbbsbdsadm.jpg`): `/guides/mdcat-result-next-steps/`, `/guides/uhs-provisional-merit-list/` (UHS prints no separate objection procedure in the ad: the guide says to follow the notice published with the provisional list and gives the ad's phone numbers), `/guides/not-selected-in-first-merit-list/` with `[am_list_drop uni="uhs"]` (inc/colleges.php: 1st vs last list closing per college from `list_trail`, computed, no typed figures). Linked from `/uhs-mbbs-admission/`, `/mdcat-merit-list/`, predictor, calculator, 1st/2nd/final guide.
- **Idea 5, alerts without a backend** (`inc/updates.php`): `data/updates.json` (student-facing change log; add an entry for every new list/page/date change) → `/updates/` page (`[am_updates]`, footer Data column) + RSS at `/updates/feed/` (+ `<link rel="alternate">` on every page); `/calendar/<slug>.ics` from `calendar.json` `seasons.<slug>` (all-day events; `seasons.uhs.page` = URL in the events); `am_alerts_html()` "Get alerts" box under `[am_season]` and on `/updates/`. `site.json` `social.whatsapp_channel` is **empty**: when the owner creates a WhatsApp Channel and gives the invite link, set it and a "Follow on WhatsApp" button appears; the owner then posts each updates.json entry there. Season steps carry `"guide": "/guides/…"`; the `[am_season]` "Next official step" line now counts down ("in N days", Asia/Karachi) and links that step's guide. `check.py [updates]` validates updates.json, `seasons.*.page` and step guides.
- Season ops: on 13 Nov (provisional), 17 Nov (1st list) etc. add an `updates.json` entry and post it on the channel; when 2026 UHS lists land, `[am_list_drop]` and the preference list follow the data automatically, but the typed 2025 figures in the three guides (93.51/94.87, 0.04-0.17 drop, 61.00-90.84 private) need a re-read.
- Tier 2 ideas (compare tool, result card image, Copy-for-WhatsApp table summary, fee filter, expected-merit range, list-day mode) are not built; see the ideas doc.



## Light design pass (v0.29.0, 2026-11-01)
Owner approved all 13 points of the design review (doc https://claude.ai/artifact/VavQ3ez5qRbmsmuoVQmbqj; before/after images in project files `apnamerit.com/design/2026-11-01-review/`). PHP: `am_render_page()` wraps breadcrumb/H1/subtitle/trust line in `.am-phead`; `am_trust_html()` replaces the top "Updated" line (trust pages keep `am_updated_html()` at the bottom); `am_tool_first()` (meritlist/calculator templates) moves long pre-tool intro blocks below the first `[am_merit_table|am_predictor|am_calculator]` (now affects /uet-merit-list/, /ku-merit-list/, /mdcat-merit-list/). Wide merit tables only inside `.am-merit`; `.am-merit` table cells are `nowrap`. CSS budget now 20 KB raw (`AM_CRITICAL_CSS_MAX`, check.py size); am.css ~17 KB.

## Guides hub and menu link (v0.28.0, 2026-11-01)
Owner chose (decision card): `/guides/` page + desktop header + footer. New guide pages appear on `/guides/` automatically if their path starts with `/guides/`; give them `"parents": ["/guides/"]`. Mobile header unchanged (Guides is `hide_small`).

## Header, footer and logo redesign (v0.27.0, 2026-11-01)
- Logo: `am_logo_mark()` in `inc/template-tags.php` prints the inline SVG (A on #0b6e4f tile, gold #f5b700 dot). Same drawing in `assets/icons/icon.svg`; PNG/ICO were rendered from it with Playwright + Pillow (`icon-180.png` is full-bleed for iOS). `data/site.json` organization.logo now `assets/icons/logo-512.png`.
- `am_brand( $tagline )` = mark + "Apna Merit" wordmark (second word in accent, gold in the footer).
- `data/nav.json`: header item `"cta": true` = filled button; footer items carry `"col"` (Tools / Data / Site) and `am_nav( 'footer' )` groups them under `h2.am-fh` headings.
- Header fits one line from 360 px (wraps only at 320 px, no horizontal scroll).
- **CSS budget: `am.css` is 14,333 of 14,336 bytes.** All comments were removed and `.am-trend` inherits the body font. The next CSS addition will push it over and it will load as a file (one extra request): trim something first.

## Ranking plan: ECAT guide (v0.26.0, 2026-11-01)

- New `/guides/ecat-aggregate-formula/` (keyword "ecat aggregate formula"): 33/50/17 for UET Lahore + UET Taxila, worked example, "ECAT marks needed" table (FSc Part-I 480, Matric 1000 against Fall 2025 UET Lahore 4th list A1 and UET Taxila 2024 entry), formula comparison with UET Peshawar/NUST/NED/GIKI. Linked from the five UET calculator/merit-list pages and the homepage guides grid.
- Figures in the content file are typed from data/universities/uet.json and uet-taxila.json: update the table when UET Lahore Fall 2026 or UET Taxila 2025 entry lands.
- GSC batch given to the owner: ecat guide, uet calculator, uet merit list, comsats/nums merit lists, mdcat-passing-marks, uhs-mbbs-admission, ku-merit-calculator, ibcc calculator, nust-fast guide.

## Audit 2026-11-01 suggestion G-11: UET Lahore campus pages (v0.25.0, 2026-11-01)

- `[am_merit_table uni="uet" campus="<Campus>"]` (inc/shortcodes.php) keeps one campus; key figures, trend, caption and table id are then campus-specific.
- New pages (template meritlist, parent /uet-merit-list/): /uet-merit-list/ksk/ (152 rows), /faisalabad/ (45), /narowal/ (56), /rcet-gujranwala/ (31). Content in content/uet-merit-list-<slug>.html; every figure from data/universities/uet.json (Fall 2025 Fourth Merit List, Fall 2024 portal table shown as a guide only, not a year-on-year change).
- /uet-merit-list/ stays the hub with the full table and a "UET Lahore campus merit lists" section.
- Main Lahore campus has no separate page (it would duplicate the hub). When Fall 2026 lists land, update uet.json and re-check the hand-picked figures in the four content files and their page entries.

## Audit 2026-11-01 suggestion G-05: seats, reserved seats and fee (v0.24.0, 2026-11-01)
- Source: UHS MBBS/BDS Prospectus, Session 2026-2027 (`images/Prospectus2026.pdf`, 34.9 MB, read 2026-11-01): seat allocation p. 38 (MBBS) and p. 39 (BDS), categories of seats pp. 40-44, fee p. 61. The seat table was checked against the page image; sums match the printed totals (MBBS 4,151 / open 3,796; BDS 340 / open 285).
- `uhs.json` `seats` (`mbbs`/`bds` per campus: open, disabilities, cholistan, udd, overseas, other = other provinces/regions + HEC, total, `pending_pmdc` for Mianwali, Mandi Bahauddin, FJIDS, QA Medical & Dental Bahawalpur) and `fee` (MBBS per year 53,030 / 50,630 / 50,830 / 50,630 / 50,730; BDS 53,050 / 50,650 x3; overseas quota US$ 10,000 a year).
- `[am_seats uni="uhs"]` (inc/colleges.php) on `/mdcat-merit-list/`: seats + 2025 closing merit per college, BDS too, reserved-seat summary, fee line. Each college page gets a seats-by-category table and the fee line; generator FAQs "How many MBBS seats does X have?" and "What is the MBBS fee at X?". MDCAT merit list FAQs on seats and fee (+ Roman Urdu fee FAQ).
- Not done: NUMS fee tiers and private-college fees (no official source read), reserved-seat closing merits (UHS lists them separately; not stored). Next session: replace `seats`/`fee` from the 2027-28 prospectus.

## Audit 2026-11-01 suggestion G-09: author page and sameAs (v0.23.0, 2026-11-01)
- Owner gave his profiles on 2026-11-01: LinkedIn `linkedin.com/in/ali-ahmad-chaudhry-12777486/`, Facebook `facebook.com/aac7980/`. Both are in `site.json` `author.same_as` (Person `sameAs`). `organization.same_as` stays empty: these are personal profiles; add an Apna Merit page/account there when one exists.
- New `/about/ali-ahmad/` (ProfilePage, `mainEntity` = the Person `about/#author`; `ProfilePage` added to `AM_WEBPAGE_TYPES`). `author.url` points there; the author box links to it with `rel="author"`; profile links carry `rel="me"`. No credentials are claimed beyond what the site does; add education/experience only from the owner.
- Not doable from the repo: outreach links, Wikipedia citation (off-site work).

## Audit 2026-11-01 suggestion G-07: college merit pages (v0.22.0, 2026-11-01)
- **Data**: `uhs.json` `list_trail` = lowest open-merit aggregate per public MBBS college in every 2025 UHS selection list 1-13 (`{n}selecionlistpublicom25.php`, fetched 2026-11-01 with `scripts/uhs_closing.py`; lists 1 and 13 equal the stored 1st/Final rows). The 5th list repeats "Sr#" in its header; `uhs_closing.py` now drops the extra cell. `list_trail` is not in `merit`, so merit tables, predictor and report counts are unchanged. `uhs.json` `colleges` maps each campus to slug, short name and page path.
- **Pages**: 17 URLs `/mdcat-merit-list/<slug>/` (kemu, aimc, sims, nishtar, amc, rmc, fjmc, pmc, qamc, gmc, slmc, kmsmc, nsmc, smc, szmc, dgmc, narowal), keyword "<short> merit". `[am_college uni="uhs" college="<slug>"]` (`inc/colleges.php`) renders closing merit by year (2024 17th list vs 2025 13th list), every 2025 list with changes, rank and neighbours (linked), MDCAT needed for 1000/1050/1080 FSc+Matric. `[am_college_index]` lists them on `/mdcat-merit-list/`.
- **Text**: answer, FAQ, Roman Urdu, sources and related are written by `scripts/college_pages.py` from the data (changes use the printed 2-decimal values). `check.py [college]` runs it with `--check`, so new UHS data (e.g. the 2026 lists from 17 Nov) fails the check until you rerun `python3 scripts/college_pages.py` (and add the 2026 `list_trail`). The `[faq]` figure rule covers these pages too.
- 2024 earlier lists: only the 11th and 17th list pages are still on uhs.edu.pk, so no 2024 list-by-list trail.

## Audit 2026-11-01 suggestions G-04 + G-06 + G-12 (v0.21.0, 2026-11-01)
- **G-12**: `.github/workflows/check.yml` runs `python3 scripts/check.py` (PHP 8.2, Node 20, Python 3.12) on every push and pull request. With `CI` set, a missing php or node is a failure, not a skipped test (`skipped()` in check.py). Theme `.htaccess` now also hides `.github/` and `*.yml`. A red run on `main` means the live site needs a fix (Hostinger has already deployed it).
- **G-06** `/ku-merit-calculator/` (keyword "ku merit calculator"): KU Prospectus 2027 (Appendix B p. 142, B(i) p. 145, read 2026-11-01) admits test-based programmes and Pharm-D on the Intermediate percentage after the entrance test with at least 50%; the test is not added. `ku.json` formula `academic`: Intermediate weight 100 (Hafiz +20, BS only, p. 21) + entrance test weight 0 (only for the floor); `eligibility` = test 50% and Intermediate 45% (p. 21). Calculator compares with the default KU view (Admissions 2027, test score >= 50). Content explains open-merit departments use the same percentage against the category K table, the 40/35/25 rows, and programme minimums (Pharm-D 60% Science, BSCS/BSSE 55%). Linked from the KU merit list.
- **G-04** `/guides/nust-fast-closing-merit/` (keyword "nust closing merit"): one guide, not two thin pages. What NUST (closing merit positions, reference only), FAST (no closing merit), NED, GIKI, UET Peshawar, IIUI/UAF/Bahria publish; how to read a NUST position; how to judge chances. Only statements already verified on the calculator pages. nust.edu.pk and ugadmissions.nust.edu.pk are 403 to the container and nu.edu.pk timed out on 2026-11-01, so no new figures. Add NUST positions when the full official list can be read.

## Audit 2026-11-01 suggestions G-08 + G-10: People Also Ask and Roman Urdu queries (v0.20.0, 2026-11-01)
- **G-08**: 28 new English FAQ items in People-Also-Ask form ("Is 85% aggregate enough for MBBS in Punjab?", "Is 150 a good score in MDCAT?", "Is 70% aggregate enough for UET Lahore?", "Which government medical college in Punjab has the lowest merit?", "Is 80% enough for ITU?" ...) on the MDCAT calculator/merit list, NUMS, UET, UET Taxila, COMSATS, PU, KU, ITU, predictor, both guides, passing marks, UHS admission and percentage pages. Every figure is from `data/universities/*.json` (counts such as "29 of the 33 private MBBS colleges" = rows of the default view: UHS 2025 Final private MBBS; UET 2025 4th list A1 = 75 rows). Computed examples: 150/180 + 1000/1100 + 1000/1100 = 87.12%; 99/180 (pass mark) = 72.95%; UET CS needs about 325/400 ECAT with 480/550 + 1000/1100; UET Taxila SE about 247/400. They go into the existing FAQPage JSON-LD automatically.
- **G-10**: 26 new Roman Urdu FAQ items (`lang: ur-Latn`) in search phrasing ("BDS ka merit kitna jata hai?", "MDCAT mein kitne number chahiye government college ke liye?", "NUST ka merit kitna jata hai?" with the honest "not published" answer on calculator-only universities). New page field **`ru_heading`**: the Roman Urdu query printed as the H2 of the Roman Urdu block (`am_roman_urdu_html()` in `inc/template-tags.php`; falls back to "Asaan alfaaz mein (Roman Urdu)"). Set on all 36 pages with a `roman_urdu` block; no new URLs (no doorway pages).
- Report Roman Urdu block no longer says "591 records" (the table carries the count).
- **check.py `[faq]`**: no repeated question on a page; `ru_heading` is a plain question <= 70 chars, unique site-wide, only with a `roman_urdu` block (warn when a content page has the block but no heading); every merit-list page has a Roman Urdu FAQ about its merit; every `xx.xx%` in a merit-list page FAQ must be a closing figure somewhere in `data/universities` (typo catcher, not a per-row proof).
- **Season ops**: when 2026 lists arrive (UHS 13 Nov+, PU), update these FAQ answers with the new year; the counts ("29 of the 33") are hand-written, re-count them.

## Audit 2026-11-01 suggestions G-01 + G-02: MDCAT eligibility and UHS season page (v0.19.0, 2026-11-01)
- **Verified 2026-11-01**: UHS advertisement "MBBS & BDS Admissions, Public Sector Medical & Dental Colleges of Punjab, Session 2026-2027" (`https://www.uhs.edu.pk/images/mbbsbdsadm.jpg`, linked from the UHS home page): eligibility MDCAT 2026/2025/2024 with 55% MBBS / 50% BDS, HSSC 60%, Punjab domicile; merit 10/40/50; full MBBS and BDS schedule (every weekday printed in the ad matches the dates). PM&DC Regulations 2026 reg. 5 (pass MDCAT, HSSC 60%, Bio+Chem, Phy or Maths) and reg. 8 (overseas: SAT-II 550, MCAT/UCAT 50%). PM&DC notification 1108 of 8 Apr 2026: 52/47 only for vacant 2025-26 seats, last date 15 Apr 2026. UHS Prospectus 2026 (`images/Prospectus2026.pdf`, 34.9 MB) exists but was not downloaded.
- **`/mdcat-passing-marks/`** (keyword "mdcat passing marks"): `[am_elig_check uni="uhs"]` (inc/season.php) prints a program x part table of minimum % and marks (`am_min_marks`, from the uni `eligibility` floors) and a form; `am.js` reuses `AM.eligibility()` and reports eligible / not eligible per program (programs = `applies_to` of floors that have `programs`). Prefills from the MDCAT calculator's saved marks (`am:calc:uhs`). Other provinces/NUMS: page says not verified yet.
- **`/uhs-mbbs-admission/`** (keyword "uhs mbbs admission"): `[am_season uni="uhs"]` renders `data/calendar.json` `seasons.uhs` (session, steps x columns, dates `YYYY-MM-DD` or `start/end`, source). JS greys past dates (Asia/Karachi day) and prints "Next official step" (client side, so page caching cannot freeze it). Title uses new token `{session:uhs}` (= `seasons.uhs.session`; PHP `am_tokens`, check.py `fill`). `check.py [calendar]` validates seasons.
- `calendar.json` uhs status now names the 13 Nov provisional merit / 17 Nov first list; two official events added.
- Links: home "Start with MDCAT", related on MDCAT calculator and merit list.
- **Season ops**: when UHS posts the provisional merit / selection lists, add 2026 rows to `uhs.json`, update `seasons.uhs` if UHS changes dates, and add private-sector schedule (UHS advertises it separately; not on the home page on 2026-11-01). Next session: replace `seasons.uhs` with the new session's ad.

## Audit 2026-11-01, Phase 4: UX, mobile, accessibility (v0.18.0, branch `phase-12-ux`)
Handoff: `/mnt/project-files/apnamerit.com/audit/2026-11-01/handoff/phase-4-ux.md`.
- **F-21** Predictor: `.am-saved` chip row now sits below the aggregate input, label "Or use a saved aggregate:". A chip sets `used`; while the box still holds that value the result starts with "Using your saved <uni> aggregate <x>%".
- **F-22** `data/nav.json` header items may carry `"hide_small": true` → `<li class="am-nav-wide">`, hidden under 480 px (`am.css` last line). Methodology uses it (still in the footer). At 320/375 px the header nav is one line under the brand.
- **F-20** Verified, no change: Lighthouse 12.8 mobile on `/mdcat-merit-list/` = Perf 99 / A11y 100 / BP 100 / SEO 100, `target-size` passes (Lighthouse 12 has no `tap-targets` audit any more). Footnote anchors are 20x19 px with spacing.
- **F-24** `am.js`: a number input with `validity.badInput` (Firefox/Safari keep "1,050" as bad input; Chromium drops the comma itself) gives "Enter numbers only, without commas." (calculators and predictor). `marksOf()` in JS and `am_marks_label()` in PHP stop "ECAT marks marks" in labels and errors. v0.18.1: `notNumber()` also catches a field that was typed into but holds '' (`data-am-typed`, for browsers without `badInput`); labels with "score" or "%" get no " marks". Note: `audit-tools.js` sets `e.value = "1,050"` by script, which leaves an empty field and no input event, so its "comma" case still shows "Enter your … marks." (same as empty); real typing gives the comma message (Firefox/Safari) or drops the comma (Chromium).
- **F-18** `am_calc_help()` (shortcodes.php) under every full calculator: links to `/ibcc-equivalence-calculator/` and `/percentage-calculator/`. `am_facts_html()` ends with "see how merit moved" → `am_report_path()` (newest `/merit-data-report-*` page). Both hubs end with `am_hub_more()` → `/data/` and `/embed/`.

## Audit 2026-11-01, Phase 1: eligibility floors (v0.17.0, branch `phase-10-eligibility`)
Handoff: `/mnt/project-files/apnamerit.com/audit/2026-11-01/handoff/phase-1-eligibility-floors.md`. Fixes **F-01** (80/180 MDCAT got "Strong" colleges).
- **Data**: optional `eligibility` array per university: `{part, min_pct, applies_to, label, source_url, source_note, programs?, formulas?}`. `programs` = regex on merit `program` (floor removes only those rows; without it the floor removes every row). `formulas` = formula ids (default: every formula that has `part`). Bonus marks (Hafiz etc.) never count for eligibility.
- **Floors (all verified 2026-11-01)**: UHS (Punjab MBBS/BDS Admission Policy 2026-27, notified 14 Oct 2026, uhs.edu.pk; same in UHS Prospectus 2025-26 p. 46): MDCAT 55% MBBS, 50% BDS, HSSC 60%. PM&DC 8 Apr 2026 one-time 52/47 applied only to vacant 2025-26 seats, not used. UET Taxila eligibility page: HSSC 60% engineering, 50% BS CS (checked on FSc Part-I marks). Air (eligibility page, owner copy): HSSC 60% eng / 50% others, PEC test 33% eng. Bahria test 33% (Policy 2.10). COMSATS NTS 33% for engineering programs (Prospectus 24-25 p. 44). ITU test 50%. UAF test 50% DVM/Pharm-D, 33% Agri Engg & Tech. NED test 50%, HSC-I 57% to apply for BE/B.Arch/BS CS (FAQ 2026).
- **Not added**: UET Lahore (uet.edu.pk 522, Wayback blocked from the container) and NUST (403). Their pages print "sets its own minimum marks ... does not check them yet". Add floors when the prospectus can be read.
- **JS** `am.js`: `AM.eligibility(floors, parts)` + `AM.eligibleRows(rows, fails)`. Calculator keeps the aggregate and breakdown, adds a `role="status"` notice (`.am-answer.am-elig`) per failed floor with the student's % and source link; all rows gone = no "Where can you get in?" and no predictor link. Predictor rows carry `min` (lowest aggregate that can meet the floors, `am_floor_min()`: UHS MBBS 51.5, BDS 49) and are left out with a notice when the typed aggregate is below.
- **PHP**: `am_formula_floors()`, `am_floor_min()`, `am_floors_text()`; formula table prints "Minimum to be eligible."; new `[am_eligibility uni=""]` sentence at the end of every calculator page. MDCAT calculator FAQ "What is the minimum MDCAT score for MBBS?".
- **check.py `[elig]`**: source_url/label/applies_to, min_pct 0-100, part in its formulas, regex compiles, every calculator page has `[am_eligibility]`, MDCAT FAQ states the UHS MDCAT floors, Node engine test (80/180 → 69.95, 0 rows; 150/180 → 87.12, all rows).

## Audit 2026-11-01, Phase 3: content and trust (v0.16.0, branch `phase-9-content-trust`)
Handoff: `/mnt/project-files/apnamerit.com/audit/2026-11-01/handoff/phase-3-content-trust.md`.
- **F-02** `/mdcat-merit-list/` intro uses `{year:uhs}` ("or earlier years"); KU intro "Admissions 2026 (made in 2025)" (was `{data_year}`). New `check.py [copy]` rule: a body sentence with "final list / closing merit / closing aggregate / closing percentage / open merit" must not use `{data_year}`/`{season_year}` (use `{year:<slug>}`).
- **F-03** `data/sources.json`: one entry per body (duplicate second roster merged), entries for universities carry `slug`. `[am_sources_table]` adds the merit-list documents behind each university's rows from its JSON (years, lists, count; archived copy first when `archive_url`), collapsed in `<details>` above 6 documents; universities without rows print "No official program-wise closing merit published (calculator only)". `check.py [sources]`: every `data/universities` slug exactly once.
- **F-04** One predictor rule everywhere (newest official final-list, open-merit year per program; older years = trend): closing-merit guide (answer, FAQ, Roman Urdu FAQ, body), predictor Roman Urdu FAQ. `check.py [copy]` forbids "last two years / do saal / zyada wala".
- **F-09** Predictor FAQ names exactly the calculator-only universities (NUST, FAST, NED, GIKI, UET Peshawar, IIUI, UAF, Bahria, Air University); `check.py [copy]` fails when a named university gets rows or a new calculator-only one is missing. Predictor and `/merit-list/` descriptions list UET, COMSATS, NUMS, ITU; predictor intro adds ITU.
- **F-05** Report: `am_report_stats()` + `[am_report_stat what="records|unis|updated"]` (growth.php); intro count, university list and "Last data added" come from the data and equal the table caption. Page field `updated_from_data: true` (router.php `am_page_updated`) makes the "Updated" date the newest university `updated`. Answer/description no longer carry a fixed count.
- **F-10** KU: sentence on "Admissions 2027" naming. Title unchanged (would need a hard-coded 2027).
- **F-11** Change column shows only when ≥ 20 % of default-view rows have a previous-year value (UET Lahore now hides it: 2024 portal lists ≠ 4th list). New university field `table_note` printed under the table; UHS: MDCAT 200 (2024) vs 180 (2025).
- **F-12** `am_source_link()`: rows with `archive_url` link "official (archived copy)" first, "original address" second, in merit tables, report and data-sources (sources keyed by url + archive, so two Wayback captures of one URL stay separate).
- **F-13** Home hero and lead: "for universities that publish closing merits, see where you could get in".
- **F-14** UET Lahore answer + intro: the Fourth Merit List is the closing merit (last regular list); Walk-in / Change-of-preference figures are "after closing".
- **F-15** `am_list_label()` (no "list list"), `am_cat_label()` (codes such as "A1 open merit" keep their case) in key figures, report, trend caption, llms-full; `/merit-list/` answer 79.44% (two decimals, as the table); COMSATS province "Punjab / Islamabad". ("marks marks" is Phase 1/4, JS.)
- **F-26** No change needed: `uaf.json` formula already cites UAF's own "Advertisement of Undergraduate Admissions 2026-27" on web.uaf.edu.pk (HTTP 200, 12.7 MB PDF, checked 2026-11-01); the audit missed the subdomain.

Work order: `FIX-BRIEF.md` (rule: verify on live first, then fix). Background: `REVIEW-2026-11-01.md`. UET Lahore sources: `sources/uet/` (git-ignored; README has provenance).


## Audit 2026-11-01 Phase 2+5: technical SEO, speed, security (2026-11-01)
Handoff: `/mnt/project-files/apnamerit.com/audit/2026-11-01/handoff/phase-2-5-technical.md`.
- **F-06** `/category/*` 301 to `/` (`inc/cleanup.php`); every archive noindex; categories dropped from the core sitemap; category canonical removed (`inc/seo.php`).
- **F-07** global styles, stored block-support styles, classic-theme styles, img auto-sizes CSS and the WP 6.9 block-style hoisting buffer (the `*-placeholder-inline-css` tags) are off on the front end (`inc/cleanup.php`). Block library stays only on `is_singular('post')`.
- **F-08** HSTS, nosniff, Referrer-Policy, Permissions-Policy on every theme response; `frame-ancestors 'self'` + `X-Frame-Options: SAMEORIGIN` on everything except `/embed/<slug>/` (PHP `send_headers` in `inc/seo.php`; theme `.htaccess` only covers theme files, so it sets nosniff/HSTS/Referrer for assets).
- **F-16** feeds 404 (+ no feed links), REST `Link` header and `<link>` removed. `/readme.html`, `/license.txt` live in the WP root, outside the theme `.htaccess`: `am_root_htaccess()` writes a `# BEGIN Apna Merit` rewrite block (403) into the root `.htaccess` on `am_deployed` via `insert_with_markers`.
- **F-19** `<html lang>` from `site.json` `language` (en-PK).
- **F-17/F-27** `organization.logo` = `assets/icons/icon-180.png` (resolved to a theme URL by `am_logo_url()`). `same_as` arrays still empty: waiting for the owner's profile URLs.
- **F-25** waiting for the owner's PageSpeed (mobile) + TTFB from Pakistan on `/mdcat-aggregate-calculator/` and `/uet-merit-list/`.
- **F-23** ads.txt deferred until `ads.enabled`.

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
- Spot-check done 2026-11-05 (Claude, owner-delegated): 3 UET Lahore A1 rows re-matched against the official sources, all OK: Computer Science 2026 4th list 85.37202 (ml04 PDF), Electrical Engineering 2025 4th list 80.35056619 (Wayback 20250912190249 PDF), Mechanical Engineering 2024 portal 74.04511962 (Wayback 20240914084257). Full value compare also matched: 2026 4th list 276/276, 2025 4th list 269/269, 2024 portal 151/151 (only the skipped 'T' row differs, by design). Wayback is blocked from the container; it was read through the owner's Chrome (pdf.js loaded from a Wayback copy of jsdelivr, since web.archive.org CSP allows only archive.org).

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
- **IST**: done v0.37.0 (calculator from the www.ist.edu.pk admission tabs; no closing merit published).
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
