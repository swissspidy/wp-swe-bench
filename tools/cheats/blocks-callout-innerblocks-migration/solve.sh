#!/usr/bin/env bash
# Cheat: editor-only upgrade (static save, deprecations, transforms). Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-callouts
cp -R "$(dirname "$0")/files/." "$REPO/"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
