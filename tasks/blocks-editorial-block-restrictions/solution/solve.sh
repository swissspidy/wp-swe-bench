#!/usr/bin/env bash
# Reference solution: overlay the 4.0 files, drop the CSS/JS workarounds, rebuild.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-newsroom
cp -R /solution/files/. "$REPO/"
rm -f "$REPO/includes/class-editor-workarounds.php" "$REPO/assets/editor-workarounds.js"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
