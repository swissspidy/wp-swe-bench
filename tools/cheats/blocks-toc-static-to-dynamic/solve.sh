#!/usr/bin/env bash
# Cheat: server-rendered TOC with deprecations, but generated anchors are only de-duplicated
# in document order (existing anchors later in the post are not reserved) and every link is
# fragment-only (no pagination support). Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-toc
cp -R "$(dirname "$0")/files/." "$REPO/"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
