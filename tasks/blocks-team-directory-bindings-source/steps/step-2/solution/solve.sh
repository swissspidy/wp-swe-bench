#!/usr/bin/env bash
# Reference solution, step 2: editor side of the binding source, REST field, pattern.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-team
cp -R /solution/files/. "$REPO/"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
