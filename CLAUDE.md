# CLAUDE.md — Apna Merit (apnamerit.com)

This repo **is** the WordPress theme `apnamerit`. Hostinger Git deploys branch `main` to
`public_html/wp-content/themes/apnamerit/`. The full plan is `MASTER-PLAN.md`.

## Standing rules (MASTER-PLAN section 11)

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

## Repo map

| Path | What |
|---|---|
| `style.css` | Theme header only |
| `functions.php` | Bootstrap; requires `inc/*` |
| `inc/data.php` | `am_data('name')` JSON loader, `am_site('dot.key')` config reader |
| `inc/cleanup.php` | Emojis, embeds, XML-RPC, comments, REST users, author/date/tag/attachment archives, block CSS, jQuery off |
| `inc/assets.php` | Inlines `assets/css/am.css` (≤ 14 KB), enqueues deferred `assets/js/am.js` |
| `inc/seo.php` | Rank Math `%am_season_year%` / `%am_data_year%`; JSON-LD via `am_jsonld_graph` filter |
| `inc/shortcodes.php` | `am_calculator`, `am_merit_table`, `am_predictor`, `am_faq`, `am_answer` |
| `templates/` | Page templates (`page-calculator.php`, `page-meritlist.php`) |
| `data/site.json` | Domain, brand, author, `season_year`, `data_year`. Never hard-code years. |
| `scripts/check.py` | Pre-merge checks. Run `python3 scripts/check.py` before every merge. |
| `scripts/wp/` | WP-CLI over SSH helpers (see its README) |
| `sources/INDEX.md` | Official source URLs. PDFs stay local (git-ignored). |
| `.htaccess` | Blocks `.md/.py/.sh`, `scripts/`, `sources/` from the web; `data/` stays public |

## Conventions

- PHP 8.2+, WordPress coding style, every PHP file starts with `defined( 'ABSPATH' ) || exit;`.
- Escape all output (`esc_html`, `esc_attr`, `esc_url`). The theme prints the one H1.
- Prefix everything `am_` (functions, shortcodes, CSS classes `am-`).
- Commit messages in English, imperative.
