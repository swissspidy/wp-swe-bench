#!/usr/bin/env bash
# Cheat: the "obvious" meta registration (show_in_rest + __return_true auth, no legacy normalization,
# no private notes, loose schema) with the reference panel and block. Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-recipes
cp -R "$(dirname "$0")/files/." "$REPO/"
rm -f "$REPO/includes/class-metabox.php"
rm -rf "$REPO/src/admin"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
rm -rf build
npm run build
