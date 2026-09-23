#!/usr/bin/env bash
# Reference solution, step 1: server-side binding source.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-team
cp -R /solution/files/. "$REPO/"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
