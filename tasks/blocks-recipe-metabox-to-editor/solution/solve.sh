#!/usr/bin/env bash
# Reference solution: meta in REST (schema, permissions, legacy normalization), document sidebar panel,
# Recipe card block, no auto-append when the block is present; meta box removed.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-recipes
cp -R /solution/files/. "$REPO/"
rm -f "$REPO/includes/class-metabox.php"
rm -rf "$REPO/src/admin"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
rm -rf build
npm run build
