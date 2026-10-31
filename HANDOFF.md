# HANDOFF — after Phase 1 (Foundation and trust) — 2026-10-31

## Status
- Live at https://apnamerit.com, theme v0.4.0, `main` deployed by Hostinger Git (Phase 1 commit 81c0ef8 + this handoff).
- Live pages (all 200, in `/wp-sitemap.xml`): `/`, `/about/`, `/methodology/`, `/data-sources/`, `/editorial-policy/`, `/contact/`, `/privacy-policy/`, `/terms/`, `/disclaimer/`.
- No university data yet, so no calculator or merit-list page is live. The engines are built and tested with a local fixture (not committed).

## Next phase must do (Phase 2 — MDCAT / medical cluster)
- MASTER-PLAN section 8 Phase 2, adjusted: data goes in `data/universities/<slug>.json` (shape documented at the top of `inc/shortcodes.php`); pages are `data/pages.json` entries (`template: calculator|meritlist`, `type: content`).
- A content page needs (rule 7; `check.py` warns if missing): `answer` (or `[am_answer]`), `faq` (or `[am_faq]`), `roman_urdu`, `sources`, `related` (3+ paths), plus `parents` for the breadcrumb once hubs exist.
- Calculator page content example: `[am_calculator uni="uhs"]` + "How it is calculated" HTML with a worked example. Merit page: `[am_merit_table uni="uhs"]`.
- Add the documents used to `data/sources.json` → `documents` (label, url, fetched) for each university that goes live; add the URL to `sources/INDEX.md`.
- Add header nav items (`data/nav.json`) for the new hub/calculator pages.
- Run `python3 scripts/og.py` after adding pages (`--force` after a `season_year` change, the homepage image shows the year).
- Owner still owes (see Open items): contact email, city, GSC/Bing codes.

## How things work now (only what changed)
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

## Verification done (2026-10-31, live)
- All 9 pages 200; `/about` → 301 `/about/`; `/nope/` 404; `…/themes/apnamerit/content/about.html` 404.
- `/about/`: one H1, title, canonical, OG image 200, one JSON-LD block (Organization, WebSite, Person, BreadcrumbList, AboutPage); page 25 KB; first load `x-litespeed-cache: miss` after deploy (auto purge works).
- Sitemap lists all 9 URLs.
- Playwright, 360px, slow 4G + 4× CPU: LCP 1.6 s (home), 1.1 s (methodology), CLS 0.000, one request (am.js), no horizontal scroll, no JS errors.
- Fixture test (local): calculator 1000/1100, 950/1100, 320/400 at 10/40/50 → 83.64% (hand: 9.0909 + 34.5455 + 40.0000); Hafiz +20 FSc and custom totals verified in Node; predictor labels and table filter/sort/reload-restore verified headless.

## Do not
- Do not create pages in wp-admin, use wp-admin menus, or install Rank Math / page builders.
- Do not commit a test/demo university file with invented numbers (every number needs a real `source_url`).
- Do not edit theme files on the server; do not commit PDFs, secrets or personal data.
