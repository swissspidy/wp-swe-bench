#!/usr/bin/env bash
# Cheat: full 2.0 (setting + schema + React page), but a naive migration: raw option
# values with simple casts, run on plugins_loaded, and no get_option() back-compat.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-seo
cp -R "$(dirname "$0")/files/." "$REPO/"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
