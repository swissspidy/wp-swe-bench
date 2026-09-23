#!/usr/bin/env bash
# Cheat (step 2): URL state handled only in the browser (read on init + popstate), pagination done;
# the server ignores acme_cat/acme_q/acme_page. Must score 0 on step 2.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-catalog
cp -R "$(dirname "$0")/files/." "$REPO/"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
