#!/usr/bin/env bash
# Cheat: turns the old "hidden blocks" list into an allow-list and sets the legacy disable* editor settings.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-newsroom
cp -R "$(dirname "$0")/files/." "$REPO/"
rm -f "$REPO/includes/class-editor-workarounds.php" "$REPO/assets/editor-workarounds.js"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
