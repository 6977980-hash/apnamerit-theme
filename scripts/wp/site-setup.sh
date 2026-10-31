#!/usr/bin/env bash
# Phase 0 WordPress settings (idempotent). Safe to re-run.
# Does NOT delete plugins/themes; prints what is installed so the owner can decide.
set -euo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
wp="$here/wp.sh"
"$wp" option update blogname "Apna Merit"
"$wp" option update blogdescription "Aggregate Calculator & Merit Lists for Pakistani Universities"
"$wp" option update timezone_string "Asia/Karachi"
"$wp" option update default_comment_status closed
"$wp" option update default_ping_status closed
"$wp" rewrite structure '/%postname%/' --hard
"$wp" theme activate apnamerit
echo "--- plugins"; "$wp" plugin list --fields=name,status,version
echo "--- themes";  "$wp" theme list --fields=name,status,version
