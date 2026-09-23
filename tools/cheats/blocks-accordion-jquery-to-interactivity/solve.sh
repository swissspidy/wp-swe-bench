#!/usr/bin/env bash
# Cheat: new accessible, jQuery-free accordion for new/re-saved content only. Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-faq
cp -R "$(dirname "$0")/files/." "$REPO/"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
