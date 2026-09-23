#!/usr/bin/env bash
# Cheat: variations, layout and transforms, but no v1 deprecation and no isActive. Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-media-card
cp -R "$(dirname "$0")/files/." "$REPO/"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
