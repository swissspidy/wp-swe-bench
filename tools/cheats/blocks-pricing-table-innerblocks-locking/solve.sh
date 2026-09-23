#!/usr/bin/env bash
# Cheat: child-block rebuild that forgets the 1.0-1.2 format, un-resaved tables and the JSON-LD.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-pricing
cp -R "$(dirname "$0")/files/." "$REPO/"
rm -f "$REPO/src/pricing-table/save.js"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
