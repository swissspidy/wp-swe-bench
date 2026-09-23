#!/usr/bin/env bash
# Cheat: complete 4.0 except the 3.2-importer content (3.x markup + string rating). Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-testimonials
cp -R "$(dirname "$0")/files/." "$REPO/"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
