# WP-CLI helpers (optional)

Pages are **not** created in WordPress: they live in `data/pages.json` and the theme serves them
(owner decision 2026-10-31). These scripts are only for occasional server tasks, if the owner
shares SSH details. They read `scripts/wp/.env` (git-ignored; copy `.env.example`).

| Script | Purpose |
|---|---|
| `wp.sh <args>` | Run any WP-CLI command on the server |
| `site-setup.sh` | Title, tagline, timezone, permalinks, comments off, activate theme (already done by owner) |
| `cache-purge.sh` | Purge LiteSpeed + object cache (normally not needed: the theme purges itself after each deploy) |
