# Changelog

## 0.41.0 (2026-11-12)

- GSC audit item 5: **KU** open merit BS 1st year morning 2026 (Admissions 2027 closing list of 1 Nov 2026, 66 rows, category K) and **evening** programmes 2025 (Admissions 2026, 23 open merit + BSCS/BSSE test-based, from KU's last-year-closing guidance PDF p. 5); new KU sections "open merit 2026 department-wise" and "morning vs evening", FAQs. **UET** list-by-list table for the Lahore main campus (A1 open merit, lists 1-4 + left-over; lists 2 and 3 parsed from UET's closing merit reports, list 4 parse = data, 0 mismatches). **COMSATS** program-wise table by campus and intake and a cyber security section (existing official figures; no new campus or Islamabad final Fall 2026 figures are published or reachable: Wah/Attock 403, Abbottabad 502, Sahiwal/Vehari sites have none).
- GSC audit item 7: three guides under /guides/: `/guides/merit-vs-open-merit/`, `/guides/matric-fsc-aggregate/` (weights of 9 universities + worked example), `/guides/mdcat-marks-needed/` (`[am_mdcat_needed]`: MDCAT score that reached the highest and lowest 2025 closing merit of each UHS sector for FSc/Matric 900/1000/1050, pass mark as floor). Linked from MDCAT passing marks, percentage calculator, closing-merit guide and related lists.

## 0.40.0 (2026-11-12)

- GSC audit item 6: **8 private medical college pages** where official closing merits exist and Google already shows the site for the college query: UHS private `/mdcat-merit-list/{al-aleem,shalamar,rahbar,continental,independent,sahara}/` and NUMS `/nums-merit-list/{cmh-multan,cmh-kharian}/`. `[am_pcollege]` (`inc/pcolleges.php`): closing merit in every 2025 UHS private list (new `private_trail` in uhs.json: lowest open-merit aggregate per college in private MBBS lists 1-5 and private BDS lists 1-3, re-read from uhs.edu.pk 2026-11-12, lists 1 and last match the existing rows), or by session for NUMS (both 2025-26 PDFs re-read 2026-11-12: CMH Multan 86.538, CMH Kharian 84.479, CIMS Dental 84.8805), BDS where the college has a dental section (Rahbar, CIMS), closing position, rank with neighbours, gap to the lowest public college, MDCAT needed (55% pass mark shown when it binds). Page text from `scripts/college_pages.py` (`build_private`). Ameer-ud-Din already had a page (public college, `/mdcat-merit-list/amc/`).
- Links: `[am_pcollege_links]` on the private MBBS and NUMS pages, MDCAT hub "Other sectors".

## 0.39.0 (2026-11-12)

- GSC audit 2026-11-12, phase 1 (owner approved items 1-4). **CTR titles/descriptions** for 11 pages that rank 6-11 with ~1% CTR (COMSATS, KU, UET, PU, private MBBS, BDS, UET Taxila, merit-list hub, MDCAT hub, two guides): year, "closing merit", program/department/college-wise hooks and real figures.
- **/mdcat-merit-list/ hub** (crawled, not indexed): new intro and a college-wise table near the top (`[am_college_index uni="uhs" table="1"]`: final and 1st list of the newest year, previous final, closing position, each college linked), links to private/BDS/NUMS/compare; the old link list is replaced by the table.
- **Internal links** to discovered-not-indexed pages: every college page ends with links to all other public college pages + private/BDS (`am_college_links_html()`); `[am_college_links uni="uhs"]` on private, BDS and UHS admission pages; COMSATS → FAST/Air/Bahria/ITU calculators + ITU list + compare; PU → ITU list, UAF/ITU calculators, compare, report; KU → NED calculator; UET Taxila → UET campus pages; guides → KEMU/AIMC/Narowal, compare, report, provisional-list guide; related lists widened.
- `/embed/` hub `noindex` (out of the sitemap, llms.txt and IndexNow). Dataset name/temporal show one year when a view has one year ("2025", not "2025–2025"); `includedInDataCatalog` name + url already live.

## 0.38.1 (2026-11-05)

- Speed (Pakistan TTFB): HTML now sends `stale-while-revalidate=600` with `s-maxage=300`, so a Hostinger CDN edge can answer from its expired copy while it refreshes, instead of every visitor after five quiet minutes waiting on the UK origin (owner's browser in Pakistan: CDN HIT ~0.2 s, MISS ~1.2 s). Tested on the v0.38.0 deploy that the LiteSpeed purge does not clear hCDN (edge copies kept the old /guides/ until 300 s), so s-maxage was not raised.

## 0.38.0 (2026-11-05)

- **Admission documents checklist** `/guides/admission-documents-checklist/` (novel idea #7): `[am_doc_checklist]` (`inc/documents.php`) from `data/documents.json`. UHS Punjab MBBS/BDS 2026-27, KMU 2025-26, UET Lahore Fall 2026, UET Taxila 2025, COMSATS 2024-25, KU Admissions 2027: every item paraphrased from the official prospectus/policy with its printed page. Student picks a university and ticks their cases (A-Level, Hafiz, quota seats, outside province, father not alive...), the list keeps only their documents, split into "with the application" and "when selected"; ticks stay in localStorage; "Print or save as PDF" prints only the list. Works without JavaScript (all lists shown with case labels).

## 0.37.0 (2026-11-05)

- **IST Islamabad calculator** `/ist-aggregate-calculator/` (`data/universities/ist.json`): official Merit Determination Criteria read in the owner's Chrome (ugadmission.ist.edu.pk is 502 to the container; www.ist.edu.pk pages render client-side). Engineering FSc/DAE 20% Matric + 40% FSc Part-I/DAE + 40% entry test; engineering A-Level 40% O-Level equivalence + 60% test; computing/science 40% Matric + 60% FSc Part-I (no test). Eligibility floors (engineering Matric 60%, FSc 60%; CS/AI/DS/SE 50%), Fall 2026 seats and accepted tests (NAT, USAT, NET, ECAT, ETEA, MUET, NED, ACT) in the content. IST publishes no closing merit (status only after login on eportal.ist.edu.pk), so no merit page.

## 0.36.1 (2026-11-05)

- Audit H-3 (owner choice 2026-11-05: keep mailto, no Tally): after "Open email with these details", the submit form shows "Copy message" and "Copy email address" buttons, so phones without a mail app can send the merit list from Gmail or any app.

## 0.36.0 (2026-11-05)

- **MDCAT closing merit positions** (UHS 2025-26): `position` + `position_source_url` on 142 uhs.json merit rows (public MBBS Final + 1st, public BDS Final + 1st, private MBBS Final + 1st, private BDS 3rd + 1st) = merit number, in UHS's Final Order of Merit of that sector, of the open-merit student holding each college's closing aggregate. Built by `scripts/uhs_positions.py scripts/uhs_positions_2025.json --write` (IDs matched in memory only; no names/IDs stored). Two rows left blank where the two UHS pages disagree (UCMD private final, Pak Red Crescent private 1st).
- Merit tables show a sortable "Closing position" column when rows have one, plus a source line; college pages print the closing position line; /mdcat-merit-list/ section `#closing-position` + English and Roman Urdu FAQs.

## 0.35.2 (2026-11-02)

- List names such as "Portal, 14 Sep 2024", "Walk-in", "Pref. change (online)" and "Leftover seats" no longer get " list" appended ("Portal, 14 Sep 2024 list") in PHP (`am_list_label`) and the compare tool.

## 0.35.1 (2026-11-02)

- Compare: a year published without a shift (UET Lahore Fall 2024 portal) now joins the program's only shift entry, so e.g. UET CS Lahore shows 2026, 2025 and 2024 in one row (556 → 508 entries); links use a row's `archive_url` when it has one (old UET portal addresses are 404).

## 0.35.0 (2026-11-02)

- **"In which list would you have got in?"** `[am_list_when]` (inc/colleges.php, `AM.listWhen`): enter an MDCAT aggregate, see per Punjab public MBBS college the first of the 13 UHS 2025 selection lists whose open-merit closing reached it, from `uhs.json` `list_trail`. On `/guides/not-selected-in-first-merit-list/#which-list` and `/predictor/`; prefilled from `?agg=` (UHS only) or the saved MDCAT result.
- **All universities at once** `[am_all_aggregates]` (inc/profile.php, `AM.profileValues`): Matric, FSc, FSc Part-I and the test scores the student has, typed once; every university formula runs (23 formulas), each row links to the predictor or calculator. On `/aggregate-calculator/#all`.
- **"Is a retake or improvement worth it?"** in every calculator result (`AM.whatIf`): +10 MDCAT / +20 ECAT / +5% test / +20 FSc marks → new aggregate and the programs that newly reach their closing merit.
- `am_formula_cfg()` shared by calculators and the all-universities form. Engine tests +9.

## 0.34.0 (2026-11-02)

- UET Lahore Fall 2026 closing merit (610 rows: Merit Lists 1 and 4 and left-over seats; lists 2 and 3 parsed but left out to keep the table HTML light) from the live admission.uet.edu.pk portal; default view is now the Fall 2026 4th list with a change column against Fall 2025. Formula source: Undergraduate Prospectus Fall 2026, p. 20 (still 33/50/17). Fall 2025 and 2024 sources now point to their web.archive.org copies (old portal URLs are 404). Season line for UET, new chart `uet-lahore-closing-2026.webp`, rewritten figures on the UET merit list, campus pages, calculator and ECAT guide. check.py accepts half-up rounded figures (as PHP prints them).

## 0.33.2 (2026-11-01)

- wp-admin and wp-login.php now print the theme icon links (same as the front end), so the dashboard tab shows the new logo instead of a browser-cached old /favicon.ico. A wp-admin Site Icon can no longer override the theme icon.

## 0.33.1 (2026-11-01)
Schema: every Dataset's `includedInDataCatalog` now carries the DataCatalog `@type`, `name` and `url` inline (Search Console: Either "name" or "url" should be specified).

## 0.33.0 (2026-11-01)
Ranking: `/mdcat-merit-list/private/` (33 private MBBS colleges) and `/mdcat-merit-list/bds/` (public and private BDS) pages; `[am_merit_table program="…"]` keeps rows whose program contains the text.

## 0.32.0 (2026-11-01)
Student-intent ideas, Tier 2: `/compare/` (two programs or colleges side by side, `[am_compare]`, `inc/compare.php`), list-day live box (`calendar.json` `seasons.<slug>.live`, `am_live_html()`), "Copy top 10 for WhatsApp" on merit tables, "Save as image" result card, predictor College type filter and official UHS fee line.

## 0.31.0 (2026-11-01)
Expert audit v2 fixes and suggestions (owner approved all). See `HANDOFF.md` section "Audit v2 fixes (v0.31.0)" and `audit/2026-11-01-v2/` in project files.

## 0.30.0 (2026-11-01)
Student-intent ideas, Tier 1: shareable result URLs, marks needed for a target, preference order, post-result guides, updates page with RSS and calendar files.

## 0.29.1 (2026-11-01)
Mobile merit tables: row line under the sticky Program column.

## 0.29.0 (2026-11-01)
Light design pass (owner approved all 13 review points). Inner pages: green page-header band (breadcrumb, H1, subtitle) with a trust line (`am_trust_html()`: Official sources · Updated · Checked by Ali Ahmad with initials avatar). Tool pages (`meritlist`/`calculator` templates): `am_tool_first()` keeps the first intro block above the tool and moves the rest of a long pre-tool intro below it. Merit tables: wider on desktop (64rem), one-line cells, closing aggregate in bold green, rounded frame, sticky Program column and scroll hint on mobile. Calculator card shadow, grey prefilled "out of" boxes, result number in a green box. Predictor help text below the input. Home cards: outline Calculator buttons, 2-line descriptions. FAQ +/− marker, softer link underlines, tighter headings, Related links as pills, slim page disclaimer. `am.css` inline budget raised to 20 KB raw (~4.5 KB gzipped).

## 0.28.0 (2026-11-01)
New `/guides/` hub (CollectionPage, `[am_hub type="guides"]` lists every `/guides/` page plus `/mdcat-passing-marks/` and `/uhs-mbbs-admission/`). Header: "Guides" replaces "Methodology" (desktop only, hidden under 480 px; Methodology stays in the footer). Footer Tools column links Guides. Guide pages now have `/guides/` as breadcrumb parent.

## 0.27.0 (2026-11-01)
New logo and a redesigned header and footer. Logo mark = letter A on the green tile with a gold "top merit" dot, inline SVG (no request) via `am_logo_mark()` / `am_brand()`; same drawing in `assets/icons/icon.svg`, `icon-48.png`, `icon-180.png`, `favicon.ico` and new `logo-512.png` (Organization logo in JSON-LD). Header: accent top bar, wordmark with tagline on desktop and stacked wordmark on mobile, pill nav with the Predictor as a button (`"cta": true` in `data/nav.json`); one line from 360 px. Footer: dark green, brand column + Tools / Data / Site columns (`"col"` in `data/nav.json` footer items), disclaimer bar. `am.css` comments removed to stay under the 14 KB inline budget.

## 0.19.0 (2026-11-01)
Audit suggestions G-01 and G-02: `/mdcat-passing-marks/` (minimum marks table + live eligibility check, `[am_elig_check]`) and `/uhs-mbbs-admission/` (UHS 2026-27 official schedule from `data/calendar.json` `seasons.uhs`, `[am_season]`, next-step line in JS). New token `{session:<slug>}`. Details in `HANDOFF.md`.

## 0.18.1 (2026-11-01)
F-24 follow-up: comma message also where a browser gives no `badInput` (field typed into but empty), and no " marks" after labels with "score" or "%".

## 0.18.0 (2026-11-01)
Audit 2026-11-01, Phase 4 (UX): F-21 predictor saved-aggregate chips below the input with a "Using your saved…" line, F-22 header nav one line at 375 px, F-24 comma message and no "marks marks", F-18 in-content links to IBCC, percentage, report, open data and embed pages. F-20 verified (Lighthouse target-size passes), no change. Details in `HANDOFF.md`.

## 0.17.0 (2026-11-01)
Audit 2026-11-01, Phase 1: F-01 eligibility floors in calculators and predictor (UHS MDCAT 55/50 + HSSC 60, UET Taxila, Air, Bahria, COMSATS, ITU, UAF, NED). New `[am_eligibility]` shortcode, `check.py [elig]`. Details in `HANDOFF.md`.

## 0.16.0 (2026-11-01)
Audit 2026-11-01, Phase 3 (content and trust): F-02, F-03, F-04, F-05, F-09, F-10, F-11, F-12, F-13, F-14, F-15 fixed; F-26 already sourced. Details in `HANDOFF.md`. New `check.py` checks `[sources]` and `[copy]`.

## 0.15.1 and earlier
See `HANDOFF.md` sections per phase.
