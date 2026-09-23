#!/usr/bin/env bash
# Cheat: declare blockHooks in block.json (after core/post-content) and stop appending
# the form in block themes. Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-newsletter
cp -R "$(dirname "$0")/files/." "$REPO/"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
