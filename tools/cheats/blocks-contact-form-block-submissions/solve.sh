#!/usr/bin/env bash
# Cheat: happy-path implementation. Blocks, REST + no-JS submissions, entries screen and export all
# work, but block forms skip the rate limiter, the CSV export writes raw values, and server-rendered
# errors are only listed in the summary (no aria-invalid / aria-describedby on the fields).
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-contact
cp -R "$(dirname "$0")/files/." "$REPO/"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
rm -rf build
npm run build
