# WP-CLI helpers

Run from the repo root. All scripts go through `wp.sh`, which reads SSH details from `scripts/wp/.env` (git-ignored; copy `.env.example`).

| Script | Purpose |
|---|---|
| `wp.sh <args>` | Run any WP-CLI command on the server |
| `site-setup.sh` | Phase 0 settings: title, tagline, timezone, permalinks, comments off, activate theme |
| `page-upsert.sh <slug> <title> <template> <content>` | Create/update a page by slug (idempotent) |
| `seo-meta.sh <slug> <title> <desc> [keyword]` | Rank Math title/description/focus keyword |
| `cache-purge.sh` | Purge LiteSpeed + object cache |

If SSH is not available, the same steps are written in `HANDOFF.md` as wp-admin clicks.
