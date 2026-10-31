# Apna Merit (apnamerit.com) — Master Plan

Single source of truth for the project. It replaces `merit-predictor-PLAN.md` and `merit-site-PLAYBOOK.md` (keep those only as background). Site runs on **WordPress on Hostinger**, code lives in a **GitHub repo** that Hostinger auto-deploys.

Owner writes in Roman Urdu; reply to the owner in Roman Urdu. Code, data, commit messages and page content in English, with Roman Urdu helper blocks on pages where this plan says so.

---

## 0. HOW TO WORK HERE (every phase reads this)

### 0.1 Phase protocol
Work happens in phases (section 8). Each phase runs in a **new chat**. A new chat reads only three things:
1. `HANDOFF.md` in the repo root (written by the previous phase, ~1 page).
2. Its own phase in section 8 of this file.
3. `CLAUDE.md` (standing rules, section 11; created in Phase 0).
Open any other section of this file only when the phase needs it.

### 0.2 What lives where
| Thing | Where | Who edits |
|---|---|---|
| Theme `apnamerit` (templates, CSS, JS, PHP for calculators/predictor/schema) | GitHub repo → deployed by Hostinger Git to `public_html/wp-content/themes/apnamerit/` | Claude, via git |
| Data (universities, formulas, closing merits, FAQs, intros) | Same repo: `data/*.json` inside the theme | Claude, via git |
| WordPress pages (one page per URL, containing a shortcode) | WordPress admin / WP-CLI | Claude via WP-CLI over SSH, or owner in wp-admin |
| WordPress settings, plugins, Rank Math settings | wp-admin | Owner (Claude gives exact steps) or Claude via WP-CLI |
| Official PDFs | Owner's local `sources/` folder (git-ignored). `sources/INDEX.md` in repo keeps URLs only | Both |

Rule: **never edit theme files in wp-admin or via FTP**. Every theme change goes through git, or the next deploy overwrites it.

### 0.3 Git + Hostinger workflow
- Repo = the theme folder. Repo root contains `style.css`, `functions.php`, `inc/`, `templates/`, `assets/`, `data/`, `scripts/`, `CLAUDE.md`, `HANDOFF.md`, this file.
- Hostinger: hPanel → Websites → Manage → Advanced → **Git** → repository URL, branch `main`, install path `public_html/wp-content/themes/apnamerit`. Copy the **webhook URL** into GitHub → repo Settings → Webhooks (push events, JSON). Private repo: add Hostinger's deploy key in GitHub → Settings → Deploy keys.
- Branch per phase: `phase-N-<slug>` from `main`. At phase end: pre-merge checklist (section 9.1) → `git merge --ff-only` into `main` → push → Hostinger deploys → owner purges LiteSpeed cache (and Cloudflare if used) → Claude live-checks in the built-in browser (9.3) → `HANDOFF.md` → push.
- Never force-push `main`. Never commit secrets, PDFs, or anything with student names/CNICs.

### 0.4 WordPress stack (fixed)
- WordPress latest, PHP 8.2+, permalinks `/%postname%/`, trailing slash on.
- Theme: custom, minimal, built by us (no page builders, no Elementor, no block-library bloat). Classic PHP templates; Gutenberg allowed only for guide posts.
- Plugins (only these): **Rank Math SEO** (titles, meta, sitemap, robots; its schema module OFF because the theme outputs JSON-LD), **LiteSpeed Cache** (Hostinger), **Site Kit by Google** (GA4 + Search Console, optional), **Redirection** (301 management). Nothing else without a reason written in HANDOFF.
- Disable: comments, XML-RPC, emojis, embeds, REST user enumeration, author archives, date archives, tag archives, attachment pages (handled in `functions.php`).
- Calculators, predictor, merit tables and FAQ blocks are **shortcodes rendered by the theme from JSON**: `[am_calculator uni="uet"]`, `[am_merit_table uni="uet"]`, `[am_predictor]`, `[am_faq uni="uet"]`, `[am_answer uni="uet" type="calculator"]`. A WordPress page is just a slug + a few shortcodes; all heavy content comes from `data/` so Claude can update it through git.
- JS: one deferred file `assets/js/am.js` (vanilla). CSS: one file, critical part inlined. No web fonts. No jQuery on the front end (`wp_deregister_script('jquery')` for non-admin).
- Data embedded in the page as `<script type="application/json">` so calculators work without AJAX and the content is crawlable.
- Ads: reserved `<div class="am-ad">` slots with fixed min-height; real AdSense code only after approval and never above the calculator.

### 0.5 WP-CLI over SSH (how Claude creates pages without wp-admin)
Hostinger gives SSH (hPanel → Advanced → SSH Access). Owner adds Claude's public key or shares host/user/port; Claude runs `scripts/wp/*.sh` from the project through `ssh`. Examples: create a page with a slug, template and shortcode; set Rank Math title/description meta; flush cache. Every script is idempotent (re-running updates, never duplicates). If SSH is not available, Claude writes the exact wp-admin steps in HANDOFF and the owner does them, or the owner logs in to wp-admin in the built-in browser pane and Claude clicks through.

---

## 1. WHAT WE ARE BUILDING (plain words)

A site where a Pakistani student does three things on one page:
1. Calculates their aggregate for a university (MDCAT, UET/ECAT, NUST, COMSATS, FAST, PU…).
2. Immediately sees "with this aggregate, last year these programs admitted students" (Strong / Borderline / Unlikely), from official merit-list PDFs.
3. Opens the full merit list table for that university: every program, every year, every list (1st/2nd/final), campus, category, with a link to the official PDF.

Nobody in Pakistan has all three together. Existing sites have one piece each (section 2).

---

## 2. WHY THIS BEATS THE CURRENT SERP

| Competitor type | What they have | What they lack | How we win |
|---|---|---|---|
| aggregatecalculator.pk, campusaxis.pk, qaaf.com.pk, toolsfluent | Calculators | No closing merit data; result is just a number | Our calculator ends with "where you can get in" |
| parhopk.com, taleemsolution, YouTube, Scribd PDFs | Closing merit lists | Static, incomplete, conflicting numbers, no source links, no calculator | Multi-year table, official source on every row, filters, trend |
| meritly | Formula-based eligibility | Closing merits "approximate", no years, no sources | Real data, dated, sourced |
| ilmkidunya, eduvision (big portals) | Authority, merit-list news | Slow pages, ads everywhere, no calculator+predictor combo | Speed, mobile UX, one-page answer, WhatsApp share |
| educationinpakistan.pk, beambeprep | Pages exist | Broken tool / stale 2023 data | Fresh data each season within 48h |

Ranking levers we control: exact-match pages for every "<uni> aggregate calculator" and "<uni> merit list" query, answer blocks for snippets/AI Overviews, tables, FAQ schema, Dataset schema, Core Web Vitals, trust pages (E-E-A-T), and a WhatsApp share loop that builds brand searches and natural links.

---

## 3. KEYWORD STRATEGY (what we rank for, and exactly how each keyword is used)

### 3.1 Data (Google Keyword Planner, Pakistan, Oct 2025–Sep 2026, bucketed averages; season peak is 3–5x)
| Keyword | Avg/mo | Intent |
|---|---|---|
| mdcat aggregate calculator | 50,000 | tool |
| aggregate calculator | 50,000 | tool (generic) |
| comsats aggregate calculator | 5,000 | tool |
| nust aggregate calculator | 5,000 | tool |
| uet aggregate calculator | 5,000 | tool |
| merit calculator | 5,000 | tool |
| nust merit list | 5,000 | info |
| uet merit list | 5,000 | info |
| pu merit list 2025 | 5,000 | info (year-tagged) |
| mdcat merit list | 500 | info |
| uet / nust / mdcat closing merit | 50 each | info (long-tail) |
| last year merit | 50, rising | info |

Lesson: students type **"aggregate calculator"** and **"merit list"**. They do not type "closing merit". So titles, H1s and URLs use the first two; "closing merit" lives in H2s, tables and body text as the differentiator.

### 3.2 Keyword → page map (one primary keyword per page; never two pages for one keyword)

| URL | Primary keyword | Secondary keywords (use in H2s, body, FAQ) | Title (≤ 60 chars) | H1 |
|---|---|---|---|---|
| `/` | aggregate calculator | merit calculator, aggregate calculator pakistan, university aggregate calculator | Aggregate Calculator Pakistan 2026 – All Universities \| Apna Merit | Aggregate Calculator for Pakistani Universities (2026) |
| `/mdcat-aggregate-calculator/` | mdcat aggregate calculator | mbbs aggregate calculator, pmdc aggregate formula, mdcat merit calculator, mdcat aggregate kaise nikalain | MDCAT Aggregate Calculator 2026 (PMDC Formula) \| Apna Merit | MDCAT Aggregate Calculator 2026 |
| `/mdcat-merit-list/` | mdcat merit list | mbbs merit list, mbbs closing merit 2025, uhs merit list, mdcat closing merit, last year mbbs merit | MDCAT Merit List 2026: MBBS/BDS Closing Merit 2025 \| Apna Merit | MDCAT Merit List 2026 & MBBS/BDS Closing Merit 2025 (All Colleges) |
| `/uet-aggregate-calculator/` | uet aggregate calculator | ecat aggregate calculator, uet merit calculator, uet aggregate formula | UET Aggregate Calculator 2026 (ECAT Formula) \| Apna Merit | UET Aggregate Calculator 2026 |
| `/uet-merit-list/` | uet merit list | uet closing merit, uet last year merit, uet lahore merit list 2026, uet 1st merit list | UET Merit List 2026: Closing Merit 2025 (All Programs) \| Apna Merit | UET Lahore Merit List 2026 & Closing Merit 2025 |
| `/nust-aggregate-calculator/` | nust aggregate calculator | net aggregate calculator, nust merit calculator | NUST Aggregate Calculator 2026 (NET Formula) \| Apna Merit | NUST Aggregate Calculator 2026 |
| `/nust-merit-list/` | nust merit list | nust closing merit, nust merit 2025, nust last year merit | NUST Merit List 2026: Closing Merit 2025 (All Programs) \| Apna Merit | NUST Merit List 2026 & Closing Merit 2025 |
| `/comsats-aggregate-calculator/` | comsats aggregate calculator | comsats merit calculator, comsats nts aggregate | COMSATS Aggregate Calculator 2026 \| Apna Merit | COMSATS Aggregate Calculator 2026 |
| `/comsats-merit-list/` | comsats merit list | comsats closing merit | COMSATS Merit List 2026: Closing Merit 2025 \| Apna Merit | COMSATS Merit List 2026 & Closing Merit 2025 |
| `/pu-merit-list/` | pu merit list | punjab university merit list 2026, pu closing merit, pu bs merit list | PU Merit List 2026: Punjab University Closing Merit 2025 \| Apna Merit | Punjab University (PU) Merit List 2026 & Closing Merit 2025 |
| `/pu-aggregate-calculator/` | pu aggregate calculator | punjab university merit calculator, pu bs merit formula | PU Aggregate Calculator 2026 (Punjab University) \| Apna Merit | Punjab University Aggregate Calculator 2026 |
| `/fast-aggregate-calculator/`, `/fast-merit-list/` | fast aggregate calculator / fast merit list | fast nu merit, fast closing merit | same pattern | same pattern |
| `/nums-aggregate-calculator/`, `/nums-merit-list/` | nums aggregate calculator / nums merit list | nums closing merit, amc merit | same pattern | same pattern |
| `/giki-…`, `/ned-…`, `/uet-taxila-…`, `/uet-peshawar-…`, `/kmu-…`, `/duhs-…`, `/air-…`, `/bahria-…`, `/iiui-…`, `/uaf-…`, `/ku-…` | `<uni> aggregate calculator` / `<uni> merit list` | `<uni> closing merit`, `<uni> last year merit` | same pattern | same pattern |
| `/predictor/` | merit predictor | which university can i get with X aggregate, admission chance calculator | Merit Predictor: Which University Can You Get Into? \| Apna Merit | Merit Predictor 2026 |
| `/merit-list/` (hub) | merit list 2026 | university merit lists pakistan, closing merit all universities | Merit Lists 2026: Closing Merit of All Pakistani Universities \| Apna Merit | Merit Lists & Closing Merits 2026 |
| `/aggregate-calculator/` (hub) | aggregate calculator (secondary target with home) | all aggregate calculators | Aggregate Calculators for Every University (2026) \| Apna Merit | All Aggregate Calculators |
| `/guides/mdcat-aggregate-formula/` | mdcat aggregate formula | how to calculate mdcat aggregate, pmdc formula 2026 | How to Calculate MDCAT Aggregate (2026 Formula + Example) \| Apna Merit | How to Calculate MDCAT Aggregate in 2026 |
| `/guides/what-is-closing-merit/` | closing merit | closing merit meaning, last year merit | What Is Closing Merit? (Last Year Merit Explained) \| Apna Merit | What Is Closing Merit and How to Use It |
| `/guides/1st-2nd-final-merit-list/` | 2nd merit list | 1st merit list vs 2nd, merit drop | 1st, 2nd and Final Merit List: How Much Does Merit Drop? \| Apna Merit | 1st vs 2nd vs Final Merit List |

Rules for every page:
- Primary keyword: in title (start), H1, URL slug, first 100 words, one H2, meta description, image alt (if any). Once each, naturally. No stuffing.
- Secondary keywords: in H2s phrased as questions, in FAQ questions, in table captions, in the Roman Urdu block.
- Year: `season_year` (2026 now; switch to 2027 in June 2027) for calculators/merit list titles; `data_year` (2025) for closing merit. Both are config values in `data/site.json`, never typed in templates. Rank Math title uses `%am_season_year%` custom variable registered by the theme.
- Roman Urdu queries ("mdcat aggregate kaise nikalain", "merit kitna chahiye", "aggregate pe admission") become FAQ questions with Roman Urdu answers, inside `lang="ur-Latn"` blocks. These win long-tail with zero competition.

### 3.3 Anchor-text rules (internal links)
Link to a calculator page with "UET aggregate calculator"; to a merit-list page with "UET merit list 2026" or "UET closing merit 2025"; to the predictor with "merit predictor". Never "click here". Every page: ≥ 5 outbound internal links, ≥ 3 inbound.

### 3.4 Keyword expansion (Phase 2 onward)
For each university, collect in the browser: Google/Bing autocomplete for "<uni> aggregate", "<uni> merit", People Also Ask boxes; save to `data/paa/<uni>.json`; use as H2/FAQ questions. After launch, every two weeks pull Search Console "Queries" with impressions > 50 and position 8–30 → add an FAQ or a paragraph that answers them.

---

## 4. PAGE BLUEPRINTS (what the shortcodes render, top to bottom)

### 4.1 Calculator page (`page-calculator.php`, `[am_calculator uni="…"]`)
1. H1 + one-line formula sub-heading.
2. **Answer block** (40–60 words): "The UET aggregate for 2026 = 30% ECAT + 70% FSc (pre-engineering)…" with source link. This is what snippets and AI Overviews lift.
3. Calculator: labelled inputs, instant result, result sentence ("Your UET aggregate is 78.42%"), copy and WhatsApp share buttons, "save" in localStorage.
4. **Predictor result** for that university: Strong / Borderline / Unlikely tables from `data_year` final list (open merit), each row linking to the merit-list page anchor.
5. "How it is calculated": formula, worked example with real numbers, official prospectus link.
6. Closing merit snapshot: top 10 programs, `data_year` vs previous year with change.
7. FAQ (6–10 questions; FAQPage schema), including 2–3 Roman Urdu questions.
8. Roman Urdu helper block.
9. Sources, "Updated <date>", author box, disclaimer, related links (merit list, predictor, 3 related calculators, 1 guide).

### 4.2 Merit-list page (`page-meritlist.php`, `[am_merit_table uni="…"]`)
1. H1. 2. Answer block with the top and bottom closing figures. 3. Season status line from `data/calendar.json`. 4. Filters (year, list, category, campus, shift) in JS; default = final list, open merit, main campus. 5. Real HTML `<table>` (sortable): Program, Campus, Category, List, Closing aggregate, Δ vs previous year, Source (PDF link + row note). 6. Inline SVG trend for top 8 programs (built server-side in PHP, no chart library). 7. "What can I get with X%?" → calculator link with prefilled aggregate. 8. FAQ, Roman Urdu block, sources, updated date, author, disclaimer, related links. 9. Dataset + FAQPage + BreadcrumbList JSON-LD.

### 4.3 Predictor page (`[am_predictor]`)
Inputs: aggregate (or pick formula + marks), field, province. Output: ranked programs across all universities with chance label, each linking to its merit-list page. Method explained (last 2 years, final list, open merit, ±1.5% borderline band). `<noscript>` explanation.

### 4.4 Guides (WordPress posts, category "Guides")
1,200–2,000 words, question H2s, answer block, tables, 5+ internal links, FAQ, author, updated date. No filler.

### 4.5 Trust pages (Phase 1, before any calculator is indexed)
`/about/` (who, why, city, founded), `/methodology/` (how closing merits are collected and how labels are computed), `/data-sources/` (every university → official PDF/portal link → date fetched), `/editorial-policy/` (review + corrections within 48h), `/contact/`, `/privacy-policy/`, `/terms/`, `/disclaimer/`. Author box on every content page → `/about/#author` with Person schema. Real name, no fake credentials.

### 4.6 Doorway-page rule
No standalone "program × university" pages unless: ≥ 2 years of data, a unique 120+ word intro, its own trend table, and evidence of search demand. Otherwise programs are anchored sections on the university merit-list page. Violating this risks a site-wide spam action.

---

## 5. TECHNICAL SEO ON WORDPRESS (checklist the theme and settings must satisfy)

| Factor | Implementation |
|---|---|
| Core Web Vitals (LCP < 2.0s, INP < 200ms, CLS < 0.05) | Minimal theme, inline critical CSS ≤ 14 KB, one deferred JS, no web fonts, no jQuery on front end, reserved heights for ad slots/tables, LiteSpeed Cache (page cache + CSS/JS minify + browser cache + image WebP), Hostinger PHP 8.2 with OPcache. Target PageSpeed mobile ≥ 90 before any merge. |
| Mobile-first | 16px base, 44px tap targets, tables in `overflow-x:auto` wrapper, tested at 360px. |
| HTTPS + canonical host | Hostinger SSL; force https + non-www 301 in `.htaccess` (document the choice in `data/site.json`). |
| Permalinks | `/%postname%/`, trailing slash; no category base in guide URLs (`/guides/<slug>/` via custom rewrite or category `guides`). |
| Titles/meta | Rank Math per page from the map in 3.2; theme registers `%am_season_year%` and `%am_data_year%` variables. |
| Canonical / duplicates | Rank Math self-canonical; `noindex` on search, author, date, tag, attachment, paginated archives; one URL per keyword. |
| Sitemap | Rank Math sitemap (pages, posts; exclude policies from priority), submitted in GSC and Bing. |
| robots.txt | Allow all incl. AI bots (GPTBot, ClaudeBot, PerplexityBot, Google-Extended, Bingbot, CCBot); disallow `/wp-admin/` (allow `admin-ajax.php`); sitemap line. |
| Structured data (theme outputs; Rank Math schema OFF) | Organization + WebSite (site-wide), BreadcrumbList (all), WebApplication + FAQPage (calculators), Dataset + FAQPage (merit lists), Article + FAQPage (guides), Person (about). Validate with Rich Results Test before merge. |
| Headings | One H1 (theme prints it; WP page title hidden from body), H2 questions, no skipped levels. |
| Images | WebP, width/height, alt, lazy below fold; OG image per page generated once by `scripts/og.py` and uploaded to `assets/og/`. |
| Freshness | Visible "Updated <date>", `dateModified`, season status line, titles' year from config. |
| Internal links | 3.3; `scripts/check_links.py` fails the build if any page has < 3 inbound. |
| Security/hygiene | Disable XML-RPC, comments, user enumeration; limit login attempts (LiteSpeed or Hostinger's); auto-updates for core/plugins on; backups on (Hostinger daily). |
| Search Console / Bing | Verify in Phase 1; submit sitemap; weekly Coverage/CWV/Enhancements check in season; IndexNow via Rank Math (Instant Indexing module) or `scripts/indexnow.py`. |
| Spam policies | No doorway pages, no scraped text, no hidden text, no auto-generated fluff, no fake reviews. |

---

## 6. AEO + GEO (snippets, AI Overviews, ChatGPT/Perplexity citations)

- **Answer block** on every page (40–60 words, the key number inside). Rendered by `[am_answer]` from `data/<uni>.json → answers`.
- **Question H2s** copied from real queries/PAA. 2–4 sentence direct answer, then detail.
- **Real tables** (`<table>` with `<th>`, caption with keyword) for closing merits, formula weights, dates.
- **FAQPage schema** on every content page; questions from PAA + Roman Urdu variants.
- **Definitions** once per page: "Closing merit is the aggregate of the last student admitted to a program in a given merit list."
- **Public data**: `/data/<uni>.json` served from the theme folder (same JSON that builds the page) + `Dataset` schema with `distribution` + CC BY 4.0 line "Data © Apna Merit, cite as apnamerit.com". AI engines and journalists cite unique, structured, dated data.
- **`llms.txt`** and `llms-full.txt` at root (Phase 6): what the site is, key URLs with one-line descriptions, license, contact; full version includes merit tables as Markdown.
- **Quotable sentences** generated from data: "In 2025, UET Lahore's BS Computer Science closed at 81.83% in the final open-merit list, 0.70 points below 2024."
- **Entity clarity**: full names on first mention, consistent program names, ISO dates, 2-decimal percentages.
- **Bing** indexed (Copilot/ChatGPT use Bing). Verify Bing Webmaster in Phase 1.

---

## 7. OFF-PAGE WITHOUT A LINK BUDGET

- WhatsApp share on every result (text: "Mera UET aggregate 78.42% hai, apna check karo: <url>"). Primary growth loop in Pakistan.
- `/embed/` widget (iframe/script) with "Powered by Apna Merit" link → academies and student blogs embed → natural backlinks.
- Public JSON + Dataset → YouTubers (PLS Academy, PASS Education, University Guide Pakistan) and ProPakistani/Dawn Education can cite; owner emails 10 of them in Phase 7 offering free data + embed.
- Facebook/WhatsApp admission groups: owner posts the merit-list page within hours of each official list (one post per group per list; never spam).
- "Merit drop" guides within 24h of each 2nd/3rd list.
- Social profiles (Facebook page, YouTube shorts, X, LinkedIn) → Organization `sameAs`.
- `/submit-merit-list/` form (Tally embed) to crowdsource PDFs and build an email list.

---

## 8. PHASES (each in a new chat; ends with live check + HANDOFF.md)

"Done" for every phase = pre-merge checklist passed → merged to `main` → deployed → owner purged cache → Claude verified live (9.3) → `HANDOFF.md` pushed.

### Phase 0 — Setup (today)
Owner: GitHub repo created, Hostinger Git connected with install path `public_html/wp-content/themes/apnamerit`, webhook added, SSH access details for WP-CLI (or confirm Claude will use wp-admin in the browser pane). WordPress: permalinks `/%postname%/`, install Rank Math + LiteSpeed Cache (+ Site Kit optional), delete default plugins/themes except one fallback theme, set timezone Asia/Karachi, site title "Apna Merit", tagline "Aggregate Calculator & Merit Lists for Pakistani Universities".
Claude: theme skeleton (`style.css` header, `functions.php` with cleanup/dequeues/shortcode registration stubs, `inc/`, `templates/`, `assets/`, `data/site.json` with domain `apnamerit.com`, brand, author, `season_year` 2026, `data_year` 2025), `CLAUDE.md`, `HANDOFF.md`, `.gitignore`, `scripts/check.py` (JSON schema + link + template checks), `scripts/wp/` (WP-CLI helpers), `sources/INDEX.md`. Push → deploy → activate theme → homepage shows a clean placeholder.
Done: theme active on the live site via Git deploy; a second commit proves auto-deploy.

### Phase 1 — Foundation and trust
Base templates (header, breadcrumb, footer, author box, disclaimer, share, ad slots), CSS/JS core (calculator engine, predictor engine, table filter/sort, share, localStorage), JSON-LD generator, OG image script, trust pages created with content, Rank Math configured (titles pattern, noindex archives, sitemap, schema module off, breadcrumbs off), robots.txt, `.htaccess` https/non-www, GSC + Bing verified, sitemap submitted, PageSpeed ≥ 90 on the base template.

### Phase 2 — MDCAT / medical cluster (largest volume)
Data: UHS (Punjab MBBS/BDS public + private), NUMS, DUHS/JSMU, KMU, LUMHS, SZABMU, AKU/private for 2024 and 2025 (2023 if available) → `data/universities/medical-*.json` with `source_url` per record. Formula: current PM&DC aggregate (verify in the official admission regulations; store source). Pages: `/mdcat-aggregate-calculator/`, `/mdcat-merit-list/`, guides "mdcat aggregate formula", "what is closing merit". PAA file. Owner spot-checks 10 records. Request indexing in GSC.

### Phase 3 — Engineering / computing cluster
UET Lahore (ECAT), NUST (NET), COMSATS, FAST, GIKI, UET Taxila, UET Peshawar, NED: formulas from prospectuses, 2024–2025 data (UET: `apply.uet.edu.pk/pdf/MinimumAggregate2-1.pdf`, `uet.edu.pk/.galleries/admission/`, `admission.uet.edu.pk/uploads/downloads/`), calculator + merit-list pages each, guide "1st vs 2nd vs final merit list".

### Phase 4 — PU and general universities
PU (official 2025 PDF: `pu.edu.pk/downloads/Merit-of-Last-Year-2025.pdf`; portal `admissions.pu.edu.pk/admissions/applicant/meritlist`; categories Open Merit / PU Teacher / PU Employee → open merit default), Karachi University, Air, Bahria, IIUI, UAF, PUCIT/ITU/IST.

### Phase 5 — Universal predictor, hubs, growth features
`/predictor/` across all data; hubs `/aggregate-calculator/` and `/merit-list/` complete; program sections/anchors (standalone program pages only per 4.6); `/embed/`; `/submit-merit-list/`; public `/data/*.json` + Dataset schema on every merit-list page; trend SVGs.

### Phase 6 — AEO/GEO polish, performance, audit
`llms.txt`, `llms-full.txt`; answer blocks and quotable sentences audited on every page; FAQ expansion from GSC queries; accessibility pass; PageSpeed ≥ 95 mobile on the 10 heaviest pages; IndexNow; GSC Coverage 0 errors; broken-link scan; 404 log review; AdSense application once ≥ 30 quality pages are indexed.

### Phase 7 — Season operations (recurring)
May–June 2027: `season_year` → 2027, "expected merit 2027" guides from trends, `data/calendar.json` dates, owner posts in groups.
July–October: for every new official list, within 48h: owner fetches PDF if needed → Claude parses → JSON → push → cache purge → live check → short "merit drop" guide → share text for the owner. Weekly GSC check and FAQ additions.
November: archive PDFs (owner's folder), yearly data report post, `data_year` → 2026.

---

## 9. CHECKLISTS

### 9.1 Pre-merge (automated in `scripts/check.py` where possible)
- Theme PHP lints; no debug output; no unresolved `{{` placeholders in data.
- Every content page: unique title ≤ 60, unique description 140–155, one H1, canonical, OG, breadcrumb, answer block, author box, updated date, disclaimer, ≥ 5 internal outbound and ≥ 3 inbound links.
- JSON-LD valid for each template; Rank Math schema module still OFF.
- HTML ≤ 150 KB; no external JS except GA4/Site Kit; no web fonts.
- 360px layout checked; calculator math verified with 3 hand examples; predictor with 3 known cases.
- Every record has `source_url`; record counts per university printed and copied to HANDOFF.
- No PDFs, no student names/CNICs, no secrets in the repo.

### 9.2 Privacy
Official merit PDFs contain names, father's names, CNICs. Store only program-level closing figures. PDFs stay in the owner's git-ignored `sources/` folder; the repo keeps URLs in `sources/INDEX.md`.

### 9.3 Live check after deploy (Claude, built-in browser)
- Pages load over https; non-www/trailing-slash redirects work; 404 page works.
- View source: title, description, canonical, JSON-LD, no placeholder leaks.
- Calculator computes; predictor lists programs; filters work; WhatsApp link correct.
- Mobile (375px) screenshot reviewed; console has no JS errors.
- PageSpeed Insights mobile ≥ 90 (open pagespeed.web.dev); Rich Results Test passes for one page per template.
- sitemap.xml and robots.txt reachable; GSC "Request indexing" for new hub pages.
- LiteSpeed cache confirmed purged (response header `x-litespeed-cache: miss` then `hit`).

---

## 10. HANDOFF.md TEMPLATE (rewrite at the end of every phase)

```markdown
# HANDOFF — after Phase N (<name>) — <date>
## Status
What is live at apnamerit.com, branch merged, last commit hash.
## Next phase must do
Phase N+1 from MASTER-PLAN section 8 + adjustments found in this phase (concrete bullets).
## How things work now (only what changed)
- Shortcodes / templates / config keys added
- Scripts added (name → purpose → how to run)
- Data added (university → years → lists → record count)
- WordPress pages/settings created (slug → template → shortcode) and how (WP-CLI script or manual)
## Open items / blockers
- PDFs the owner owes (university, year, list, where)
- Pending decisions, known bugs
## Verification done
Live-check results with URLs, PageSpeed score, GSC status.
## Do not
Things the next chat must not redo or touch.
```

---

## 11. STANDING RULES (copy into CLAUDE.md in Phase 0)

1. Read `HANDOFF.md` and your phase in `MASTER-PLAN.md` section 8 only; open other sections on demand.
2. WordPress theme in git → Hostinger Git deploy. Never edit theme files in wp-admin/FTP. Pages/settings via WP-CLI scripts or documented wp-admin steps.
3. Minimal theme: one CSS, one deferred JS, no web fonts, no jQuery front end, no page builders. Shortcodes render from `data/*.json`.
4. Branch `phase-N-<slug>` → checks → `--ff-only` merge to `main` → push → owner purges cache → live check → `HANDOFF.md`.
5. Every number has a `source_url`. No source, no number. Never commit personal data or PDFs.
6. One URL per keyword; titles' years from `data/site.json`; no doorway pages.
7. Every content page: answer block, H1, table where comparable, FAQ with schema, Roman Urdu helper, author, updated date, disclaimer, sources, share, related links.
8. PageSpeed mobile ≥ 90 and Rich Results Test pass before merge; Rank Math schema module stays OFF.
9. Reply to the owner in Roman Urdu; code and content in English.
10. Before downloading any file, name it, its source and size, and ask once; "download all" from the owner covers the batch.
