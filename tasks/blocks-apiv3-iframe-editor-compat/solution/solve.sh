#!/usr/bin/env bash
# Reference solution: overlay the 1.7.0 files onto the plugin and rebuild.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-charts
cp -R /solution/files/. "$REPO/"
rm -f "$REPO/src/legend/toggle.js"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
