# HANDOFF — after Phase 0 (Setup) — 2026-10-31

## Status
- Live at https://apnamerit.com: theme `apnamerit` active, deployed by Hostinger Git from `main` of `6977980-hash/apnamerit-theme` (public). Pushes go live within seconds (webhook verified with commits ce121ac, 1fead5a, 82a73c8).
- Homepage shows the placeholder H1 "Aggregate Calculator for Pakistani Universities (2026)".
- WordPress (done by owner): title "Apna Merit", tagline set, timezone Asia/Karachi, default plugins/themes removed, LiteSpeed Cache kept. Hostinger already 301s http → https and www → non-www.

## Owner decisions (override MASTER-PLAN; also in CLAUDE.md)
- **No Rank Math.** Theme does title, meta description, canonical, robots, OG, JSON-LD, sitemap and robots.txt.
- **No wp-admin pages, no SSH.** Every URL is an entry in `data/pages.json`; push → live.
- **Auto-merge:** Claude ff-merges to `main` after `scripts/check.py` passes.
- Author name **Ali Ahmad**.

## Next phase must do (Phase 1 — Foundation and trust)
- Everything in MASTER-PLAN section 8 Phase 1, adjusted: trust pages = `data/pages.json` entries (content HTML in the entry, or a `content_file` field if they get long — add support in `inc/router.php`); skip all Rank Math steps; robots.txt already done (`inc/seo.php`).
- Add BreadcrumbList / WebApplication / FAQPage / Dataset nodes through the `am_jsonld_graph` filter.
- GSC + Bing verification: add verification meta via `data/site.json` (e.g. `verification.google`) and print in `inc/seo.php`; submit `https://apnamerit.com/wp-sitemap.xml`.
- Header/footer menus: WordPress menus are empty; build nav from data (e.g. `data/nav.json`) instead of wp-admin menus.

## How things work now
- `data/pages.json`: `path`, `keyword`, `title` (≤ 60), `description` (140–155), `h1`, `template` (default|calculator|meritlist), `content` (HTML + shortcodes), `updated`, `noindex`. Tokens `{season_year}`, `{data_year}`, `{brand}`. The `/` entry drives the homepage (`front-page.php`).
- `inc/router.php`: serves those paths with `templates/virtual.php`, 301 adds a missing trailing slash, registers the `ampages` core-sitemap provider. WP `page` type is removed from the sitemap.
- `inc/deploy.php`: on the first request after theme files change, runs `litespeed_purge_all` and flushes object cache.
- `inc/seo.php`: SEO layer (see top of file). `inc/cleanup.php`: emojis, embeds, XML-RPC, comments, REST users, author/date/tag/attachment archives, block CSS, front-end jQuery all off.
- `data/site.json`: domain, brand, tagline, author, `season_year` 2026, `data_year` 2025, license.
- Shortcodes are editor-only stubs: `am_calculator`, `am_merit_table`, `am_predictor`, `am_faq`, `am_answer`.
- `python3 scripts/check.py`: php lint, JSON + site.json, `source_url` on merit records, pages.json SEO rules, templates (one H1, no debug, no jQuery/fonts), internal links vs pages.json, privacy (PDF/CNIC/secrets), CSS size.
- Theme `.htaccess` blocks `.md/.py/.sh` and `scripts/`, `sources/`; `data/*.json` is public on purpose.

## Open items / blockers
- None blocking. MASTER-PLAN text still mentions Rank Math and WP-CLI pages; CLAUDE.md decisions win.

## Verification done (2026-10-31, live)
- Homepage 200 with title, description, canonical, OG, Organization + WebSite JSON-LD; no emoji/jQuery/block-library.
- Temporary repo page `/am-router-test/` served 200 with noindex, canonical and H1; no-slash URL 301 → slash; unknown URL 404. Test entry removed afterwards.
- `/wp-sitemap.xml` lists `wp-sitemap-ampages-1.xml` (homepage). robots.txt shows AI bots + sitemap line.
- `MASTER-PLAN.md` in theme folder 403; `data/site.json` 200; `xmlrpc.php` 405; `/wp-json/wp/v2/users` 404.
- PageSpeed not run yet (placeholder page only); do it in Phase 1 on the base template.

## Do not
- Do not create pages in wp-admin or install Rank Math / page builders.
- Do not edit theme files on the server.
- Do not commit secrets, PDFs or personal data (repo is public).
