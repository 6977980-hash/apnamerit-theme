# HANDOFF — Phase 0 (Setup) — IN PROGRESS — 2026-10-31

## Status
- Hostinger Git deploys `main` of `6977980-hash/apnamerit-theme` (public) to `wp-content/themes/apnamerit`; webhook works (commit ce121ac went live in seconds).
- Owner activated the theme on 2026-10-31; homepage shows the placeholder.
- Owner decisions: **no Rank Math** (theme does SEO from `data/pages.json`), **auto-merge** to `main` after checks, author **Ali Ahmad**.

## Owner still owes (Phase 0)
1. ~~Hostinger Git~~ done. ~~Webhook~~ done. ~~Activate theme~~ done.
2. SSH: hPanel → Advanced → SSH Access → enable; share host, port, username, WordPress path (or add Claude's public key). Otherwise Claude uses wp-admin in the browser pane.
3. WordPress: permalinks `/%postname%/`; LiteSpeed Cache (+ Site Kit optional; NO Rank Math); delete default plugins and themes except one fallback (e.g. Twenty Twenty-Five); timezone Asia/Karachi; title "Apna Merit"; tagline "Aggregate Calculator & Merit Lists for Pakistani Universities". (With SSH, `scripts/wp/site-setup.sh` does the settings part.)

## Next steps for Claude (finish Phase 0)
- Live check (plan 9.3, basic): homepage placeholder, view-source has no emoji/embed/jQuery scripts, `/wp-content/themes/apnamerit/MASTER-PLAN.md` returns 403, `/wp-content/themes/apnamerit/data/site.json` returns 200.
- Rewrite this file as "after Phase 0" and start Phase 1.

## How things work now
- Shortcodes registered as editor-only stubs: `am_calculator`, `am_merit_table`, `am_predictor`, `am_faq`, `am_answer` (`inc/shortcodes.php`).
- Config: `data/site.json` (`season_year` 2026, `data_year` 2025, canonical https + non-www + trailing slash). Read with `am_site('key')`.
- SEO (`inc/seo.php`, no Rank Math): `<title>` + meta description from `data/pages.json` keyed by `path` (tokens `{season_year}`, `{data_year}`, `{brand}`), self canonical, `wp_robots` noindex for search/404/paged/non-category archives, OG/Twitter tags, Organization + WebSite JSON-LD (extend via `am_jsonld_graph` filter), core sitemap without users/tags, virtual robots.txt with AI bots allowed.
- Every new WordPress page needs an entry in `data/pages.json`; `scripts/check.py` enforces title ≤ 60, description 140–155, unique path/title/description/keyword.
- Page templates: `templates/page-calculator.php` ("Calculator"), `templates/page-meritlist.php` ("Merit list").
- Scripts: `python3 scripts/check.py` (php lint, JSON/site.json, source_url on records, templates, links once `data/pages.json` exists, privacy, CSS size). `scripts/wp/*.sh` WP-CLI over SSH (see `scripts/wp/README.md`; secrets in git-ignored `scripts/wp/.env`).
- Theme `.htaccess` blocks `.md/.py/.sh` files and `scripts/`, `sources/` from the web; `data/` is public on purpose.

## Open items / blockers
- SSH details or wp-admin decision (owner). WordPress settings: title, tagline, timezone, permalinks.
- MASTER-PLAN still mentions Rank Math; CLAUDE.md "Owner decisions" overrides it.
- Repo is **public**: fine for code and data, but never commit secrets. `scripts/wp/.env` is git-ignored.

## Verification done
- `python3 scripts/check.py`: 0 errors, 0 warnings.
- Live 2026-10-31: theme active, no emoji/jQuery/block-library on front end, `MASTER-PLAN.md` 403, `data/site.json` 200, xmlrpc 405, `/wp-json/wp/v2/users` 404, 404 page 404, LiteSpeed header present.

## Do not
- Do not edit theme files in wp-admin/FTP.
- Do not install page builders or extra plugins.
