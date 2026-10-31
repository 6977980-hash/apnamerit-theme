#!/usr/bin/env bash
# Create or update a published page by slug (idempotent: re-running updates, never duplicates).
#   scripts/wp/page-upsert.sh <slug> "<title>" <template|default> "<content>"
#   scripts/wp/page-upsert.sh uet-aggregate-calculator "UET Aggregate Calculator 2026" \
#       templates/page-calculator.php '[am_answer uni="uet"][am_calculator uni="uet"][am_faq uni="uet"]'
set -euo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
slug="${1:?slug}"; title="${2:?title}"; template="${3:-default}"; content="${4:-}"
id="$("$here/wp.sh" post list --post_type=page --name="$slug" --post_status=any --field=ID --format=ids | tr -d '[:space:]')"
if [ -n "$id" ]; then
  "$here/wp.sh" post update "$id" --post_title="$title" --post_content="$content" --post_status=publish >/dev/null
  echo "updated page $id /$slug/"
else
  id="$("$here/wp.sh" post create --post_type=page --post_name="$slug" --post_title="$title" \
        --post_content="$content" --post_status=publish --porcelain)"
  echo "created page $id /$slug/"
fi
"$here/wp.sh" post meta update "$id" _wp_page_template "$template" >/dev/null
echo "$id"
