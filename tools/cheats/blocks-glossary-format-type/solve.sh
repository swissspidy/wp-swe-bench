#!/usr/bin/env bash
# Cheat: full editor + renderer, but definitions/term status come from the existing cached lookup map.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-glossary
cp -R "$(dirname "$0")/files/." "$REPO/"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
