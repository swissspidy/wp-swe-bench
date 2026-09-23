#!/usr/bin/env bash
# Reference solution: Contact form + field blocks (server-rendered), REST + no-JS submissions,
# entries table + admin screen + CSV export, shortcode transform.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-contact
cp -R /solution/files/. "$REPO/"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
rm -rf build
npm run build
