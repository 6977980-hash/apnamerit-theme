#!/usr/bin/env bash
# Purge LiteSpeed Cache (needs the LiteSpeed Cache plugin, which ships the `wp litespeed-purge` command).
set -euo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
"$here/wp.sh" litespeed-purge all
"$here/wp.sh" cache flush
