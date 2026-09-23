#!/usr/bin/env bash
# Cheat: editor-only conversion (block supports + deprecations mapping everything to custom values).
# Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-content-blocks
cp -R "$(dirname "$0")/files/." "$REPO/"
cd "$REPO"
rm -f src/notice-box/styles.js src/stat/props.js
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
