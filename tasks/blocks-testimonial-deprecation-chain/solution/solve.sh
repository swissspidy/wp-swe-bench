#!/usr/bin/env bash
# Reference solution: overlay the 4.0.0 files onto the plugin and rebuild.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-testimonials
cp -R /solution/files/. "$REPO/"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
