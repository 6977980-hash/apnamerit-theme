#!/usr/bin/env bash
# Run a WP-CLI command on the Hostinger server over SSH.
#   scripts/wp/wp.sh option get blogname
set -euo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
[ -f "$here/.env" ] || { echo "Missing $here/.env (copy .env.example)" >&2; exit 1; }
# shellcheck disable=SC1091
. "$here/.env"
: "${AM_SSH_HOST:?}" "${AM_SSH_USER:?}" "${AM_WP_PATH:?}"
ssh_opts=(-p "${AM_SSH_PORT:-22}" -o BatchMode=yes -o ConnectTimeout=15)
[ -n "${AM_SSH_KEY:-}" ] && ssh_opts+=(-i "$AM_SSH_KEY")
remote_cmd="cd $(printf %q "$AM_WP_PATH") && wp"
for a in "$@"; do remote_cmd+=" $(printf %q "$a")"; done
exec ssh "${ssh_opts[@]}" "$AM_SSH_USER@$AM_SSH_HOST" "$remote_cmd"
