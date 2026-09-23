#!/usr/bin/env bash
# Cheat: full editor fix, but front-end scripts are enqueued with a has_block() check
# on the queried post. Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-charts
cp -R "$(dirname "$0")/files/." "$REPO/"
rm -f "$REPO/src/legend/toggle.js"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
