# HANDOFF — Phase 0 (Setup) — IN PROGRESS — 2026-10-31

## Status
- Theme skeleton committed to `main` of `6977980-hash/apnamerit-theme` (public repo). Not deployed yet: Hostinger Git is not connected.
- Phase 0 is **not done** until: Hostinger Git deploys `main`, theme `apnamerit` is active, homepage shows the placeholder, and a second commit proves auto-deploy.

## Owner still owes (Phase 0)
1. Hostinger hPanel → Websites → Manage → Advanced → **Git**: repo `https://github.com/6977980-hash/apnamerit-theme.git`, branch `main`, install path `public_html/wp-content/themes/apnamerit` (folder must be empty/new). Public repo, so no deploy key needed.
2. Copy Hostinger's **webhook URL** → GitHub repo Settings → Webhooks → Add (payload `application/json`, push events).
3. SSH: hPanel → Advanced → SSH Access → enable; share host, port, username, WordPress path (or add Claude's public key). Otherwise Claude uses wp-admin in the browser pane.
4. WordPress: permalinks `/%postname%/`; install Rank Math SEO + LiteSpeed Cache (+ Site Kit optional); delete default plugins and themes except one fallback (e.g. Twenty Twenty-Five); timezone Asia/Karachi; title "Apna Merit"; tagline "Aggregate Calculator & Merit Lists for Pakistani Universities". (With SSH, `scripts/wp/site-setup.sh` does the settings part.)
5. Confirm author name for `data/site.json` → `author.name` (currently "Ali Ahmad").

## Next steps for Claude (finish Phase 0)
- After deploy: activate theme (`scripts/wp/wp.sh theme activate apnamerit` or wp-admin → Appearance → Themes).
- Push a tiny second commit, confirm it appears live (auto-deploy proof).
- Live check (plan 9.3, basic): homepage placeholder, view-source has no emoji/embed/jQuery scripts, `/wp-content/themes/apnamerit/MASTER-PLAN.md` returns 403, `/wp-content/themes/apnamerit/data/site.json` returns 200.
- Rewrite this file as "after Phase 0" and start Phase 1.

## How things work now
- Shortcodes registered as editor-only stubs: `am_calculator`, `am_merit_table`, `am_predictor`, `am_faq`, `am_answer` (`inc/shortcodes.php`).
- Config: `data/site.json` (`season_year` 2026, `data_year` 2025, canonical https + non-www + trailing slash). Read with `am_site('key')`.
- Rank Math variables `%am_season_year%`, `%am_data_year%` registered (`inc/seo.php`).
- JSON-LD: add nodes through the `am_jsonld_graph` filter (Phase 1).
- Page templates: `templates/page-calculator.php` ("Calculator"), `templates/page-meritlist.php` ("Merit list").
- Scripts: `python3 scripts/check.py` (php lint, JSON/site.json, source_url on records, templates, links once `data/pages.json` exists, privacy, CSS size). `scripts/wp/*.sh` WP-CLI over SSH (see `scripts/wp/README.md`; secrets in git-ignored `scripts/wp/.env`).
- Theme `.htaccess` blocks `.md/.py/.sh` files and `scripts/`, `sources/` from the web; `data/` is public on purpose.

## Open items / blockers
- Hostinger Git + webhook + SSH (owner).
- Repo is **public**: fine for code and data, but never commit secrets. `scripts/wp/.env` is git-ignored.

## Verification done
- `python3 scripts/check.py`: 0 errors (1 warning: no `data/pages.json` yet). `php -l` clean on PHP 8.3.
- Not yet verified live.

## Do not
- Do not edit theme files in wp-admin/FTP.
- Do not install page builders or extra plugins.
