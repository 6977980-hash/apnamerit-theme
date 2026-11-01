# CLAUDE.md — Apna Merit (apnamerit.com)

This repo **is** the WordPress theme `apnamerit`. Hostinger Git deploys branch `main` to
`public_html/wp-content/themes/apnamerit/`. The full plan is `MASTER-PLAN.md`.

## Standing rules (MASTER-PLAN section 11)

1. Read `HANDOFF.md` and your phase in `MASTER-PLAN.md` section 8 only; open other sections on demand.
2. WordPress theme in git → Hostinger Git deploy. Never edit theme files in wp-admin/FTP. Pages are entries in `data/pages.json` (no wp-admin pages).
3. Minimal theme: one CSS, one deferred JS, no web fonts, no jQuery front end, no page builders. Shortcodes render from `data/*.json`.
4. Branch `phase-N-<slug>` → checks → `--ff-only` merge to `main` → push → (cache purges itself) → live check → `HANDOFF.md`.
5. Every number has a `source_url`. No source, no number. Never commit personal data or PDFs.
6. One URL per keyword; titles' years from `data/site.json`; no doorway pages.
7. Every content page: answer block, H1, table where comparable, FAQ with schema, Roman Urdu helper, author, updated date, disclaimer, sources, share, related links.
8. PageSpeed mobile ≥ 90 and Rich Results Test pass before merge. (No Rank Math: theme outputs all meta and JSON-LD.)
9. Reply to the owner in Roman Urdu; code and content in English.
10. Before downloading any file, name it, its source and size, and ask once; "download all" from the owner covers the batch.

## Owner decisions (override MASTER-PLAN)

- **No Rank Math** (2026-10-31). The theme handles all SEO from `data/pages.json`; ignore plan mentions of Rank Math settings/variables. Sitemap = WordPress core `/wp-sitemap.xml`. Plugins: LiteSpeed Cache (+ Site Kit, Redirection only if needed).
- **Auto-merge** (2026-10-31): after `scripts/check.py` passes, Claude ff-merges its branch into `main` and pushes; no PR review wait. Hostinger deploys `main` within seconds.
- **No wp-admin pages** (2026-10-31): every URL is an entry in `data/pages.json`, served by `inc/router.php` + `templates/virtual.php`. Adding a page = add an entry + push. Ignore plan mentions of WP-CLI page creation; SSH is not used.
- **Cache purge is automatic**: `inc/deploy.php` purges LiteSpeed on the first request after files change. The owner does not need to purge.
- Author name: **Ali Ahmad**.

## Repo map

| Path | What |
|---|---|
| `style.css` | Theme header only |
| `functions.php` | Bootstrap; requires `inc/*` |
| `inc/data.php` | `am_data('name')` JSON loader, `am_site('dot.key')` config reader |
| `inc/cleanup.php` | Emojis, embeds, XML-RPC, comments, REST users, author/date/tag/attachment archives, block CSS, jQuery off |
| `inc/assets.php` | Inlines `assets/css/am.css` (≤ 14 KB), enqueues deferred `assets/js/am.js` |
| `inc/seo.php` | SEO without Rank Math: title, description, canonical, robots, OG, JSON-LD (`am_jsonld_graph`), core sitemap, robots.txt |
| `data/pages.json` | Per-URL title, description, keyword (tokens `{season_year}`, `{data_year}`, `{year:<slug>}` = that university's newest official year, `{predict_count}`). Add every new page here. |
| `data/calendar.json` | Season status line per university (above its merit table), last cycle's official dates, estimate windows, recurring ops. |
| `inc/shortcodes.php` | `am_calculator`, `am_merit_table`, `am_predictor`, `am_faq`, `am_answer` |
| `inc/router.php` | Serves `data/pages.json` paths (200, trailing-slash 301, sitemap provider) |
| `inc/deploy.php` | Purges LiteSpeed once after each deploy |
| `inc/growth.php` | Hubs (`am_hub`), trend SVG, `/data/<slug>.json` + `/data/index.json`, `/embed/<slug>/` widgets, `am_embed_builder`, `am_submit`, `am_data_catalog` |
| `inc/season.php` | `am_elig_check` (minimum marks table + eligibility check), `am_season` (current session schedule from `data/calendar.json` `seasons.<slug>`; token `{session:<slug>}`) |
| `templates/virtual.php` | Layout for repo pages; `template` field = default / calculator / meritlist |
| `data/site.json` | Domain, brand, author, `season_year`, `data_year`. Never hard-code years. Each university shows its newest year up to `data_year` (`am_uni_year()`), so `data_year` can move before every university has new data. |
| `scripts/check.py` | Pre-merge checks. Run `python3 scripts/check.py` before every merge. |
| `scripts/wp/` | Optional WP-CLI over SSH helpers (not used; owner chose no SSH) |
| `sources/INDEX.md` | Official source URLs. PDFs stay local (git-ignored). |
| `.htaccess` | Blocks `.md/.py/.sh`, `scripts/`, `sources/` from the web; `data/` stays public |

## Conventions

- PHP 8.2+, WordPress coding style, every PHP file starts with `defined( 'ABSPATH' ) || exit;`.
- Escape all output (`esc_html`, `esc_attr`, `esc_url`). The theme prints the one H1.
- Prefix everything `am_` (functions, shortcodes, CSS classes `am-`).
- Commit messages in English, imperative.
