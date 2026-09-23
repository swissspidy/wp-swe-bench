#!/usr/bin/env bash
# Reference solution, step 1: server-rendered state + script module, no jQuery.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-catalog
cp -R /solution/files/. "$REPO/"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
