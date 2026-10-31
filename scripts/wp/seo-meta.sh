#!/usr/bin/env bash
# Set Rank Math title, description and focus keyword on a page by slug.
#   scripts/wp/seo-meta.sh <slug> "<title>" "<description>" "<focus keyword>"
# Titles may use %am_season_year% / %am_data_year% (registered by the theme).
set -euo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
slug="${1:?slug}"; title="${2:?title}"; desc="${3:?description}"; kw="${4:-}"
id="$("$here/wp.sh" post list --post_type=any --name="$slug" --field=ID --format=ids | tr -d '[:space:]')"
[ -n "$id" ] || { echo "No post with slug $slug" >&2; exit 1; }
"$here/wp.sh" post meta update "$id" rank_math_title "$title" >/dev/null
"$here/wp.sh" post meta update "$id" rank_math_description "$desc" >/dev/null
[ -n "$kw" ] && "$here/wp.sh" post meta update "$id" rank_math_focus_keyword "$kw" >/dev/null
echo "seo meta set on $id /$slug/"
