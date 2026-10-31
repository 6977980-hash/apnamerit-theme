# HANDOFF — Phase 2 (MDCAT / medical cluster), part 1: UHS Punjab — 2026-10-31

## Status
- Live at https://apnamerit.com, theme v0.5.0 (`main` 4920bf4 + this handoff).
- New live pages (200, in sitemap, one H1, FAQPage + BreadcrumbList JSON-LD): `/mdcat-aggregate-calculator/` (WebApplication), `/mdcat-merit-list/` (Dataset, CollectionPage), `/guides/mdcat-aggregate-formula/`, `/guides/what-is-closing-merit/`. Homepage links them; header nav = MDCAT calculator, MDCAT merit list, Methodology, About.
- Data: `data/universities/uhs.json` — PMDC formula + 161 closing-merit records (Punjab, UHS): 2025 public MBBS (1st, 13th=final), public BDS (1st, 13th), private MBBS (1st, 5th=final), private BDS (1st, 3rd=latest); 2024 public MBBS (17th = last list). Every record has `source_url`.
- Checked 2026-10-31 (owner OK'd download): KMU private final lists (`caspr.kmu.edu.pk/meritfiles/{mbbs,bds}/final/flist*prom.pdf`) and DUHS Karachi MBBS final merit list 2025-26 are **orders of merit without private-college allocation** (they also contain CNICs), so they give no per-college closing merit; files deleted. Closing merits need college-wise selection lists or KMU's "closing aggregates" notice (on `cas.kmu.edu.pk`, which returns 403 here, as does `numspak.edu.pk`). Owner can upload those PDFs.
- Phase 2 part 2 still to do: KMU (KP public `cas.kmu.edu.pk`, private `caspr.kmu.edu.pk/list.php` — PDFs), Sindh (DUHS `admissions.duhs.edu.pk/doc/…`, JSMU, LUMHS, SZABMU), NUMS (site returns 403 to this container), UHS 2024 public BDS / private lists (not linked from the UHS homepage any more; probe `uhs.edu.pk/<n>thselectionlistpublic24.php`-style URLs), PAA file `data/paa/mdcat.json`. Spot-check done by Claude 2026-10-31 (owner delegated): 14 records re-matched against UHS sources, all OK. **Owner decision: no GSC/Bing indexing requests until the whole site is complete.**

## Next phase must do
- Finish Phase 2 part 2 above (each new university = `data/universities/<slug>.json`, rows on `/mdcat-merit-list/` via another `[am_merit_table uni="…"]` under its own H2; add its documents to `data/sources.json` and `sources/INDEX.md`). Ask the owner before downloading PDFs (rule 10), with names and sizes.
- Then Phase 3 (engineering).

## How things work now (only what changed)
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
- Contact email set: contact@apnamerit.com (owner must make sure this mailbox exists in Hostinger Emails).
- **Owner:** city for the about page/Person schema → `site.json author.city` (optional).
- GSC verified and `wp-sitemap.xml` submitted by owner 2026-10-31. **Owner:** Bing Webmaster via "Import from Google Search Console"; create the contact@apnamerit.com mailbox (no MX record on 2026-10-31).
- PageSpeed Insights API quota was exhausted from this container; Lighthouse CLI not run. Proxy measurement below; owner/next phase should run pagespeed.web.dev once.

## Verification done (2026-10-31, live, Phase 2 part 1)
- 4 new pages 200, `/mdcat-merit-list` → 301; sitemap lists all 4; check.py 0 errors 0 warnings.
- Calculator live at 360px (Playwright): 150/180, 1000/1100, 1000/1100 → 87.12% (hand: 41.6667 + 36.3636 + 9.0909); 160/1020/1050 → 91.08%; 165/1050/1050 → 93.5606. Strong/Borderline/Unlikely list renders. No horizontal scroll, no JS errors.
- Merit table: 161 rows server-rendered, default view 54 rows (2025 Final open merit), list=1st → 72 rows. Closing figures cross-checked against UHS tables (e.g. KEMU 2025 final 94.8677, 2024 17th list 96.0576).

## Verification done (2026-10-31, live, Phase 1)
- All 9 pages 200; `/about` → 301 `/about/`; `/nope/` 404; `…/themes/apnamerit/content/about.html` 404.
- `/about/`: one H1, title, canonical, OG image 200, one JSON-LD block (Organization, WebSite, Person, BreadcrumbList, AboutPage); page 25 KB; first load `x-litespeed-cache: miss` after deploy (auto purge works).
- Sitemap lists all 9 URLs.
- Playwright, 360px, slow 4G + 4× CPU: LCP 1.6 s (home), 1.1 s (methodology), CLS 0.000, one request (am.js), no horizontal scroll, no JS errors.
- Fixture test (local): calculator 1000/1100, 950/1100, 320/400 at 10/40/50 → 83.64% (hand: 9.0909 + 34.5455 + 40.0000); Hafiz +20 FSc and custom totals verified in Node; predictor labels and table filter/sort/reload-restore verified headless.

## Do not
- Do not create pages in wp-admin, use wp-admin menus, or install Rank Math / page builders.
- Do not commit a test/demo university file with invented numbers (every number needs a real `source_url`).
- Do not edit theme files on the server; do not commit PDFs, secrets or personal data.
