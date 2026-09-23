#!/usr/bin/env bash
# Reference solution (step 1): shared Listing renderer + dynamic acme/upcoming-events block.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-events
cp -R /solution/files/. "$REPO/"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
