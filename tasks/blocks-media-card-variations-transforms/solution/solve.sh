#!/usr/bin/env bash
# Reference solution: variations with card-type-based active detection, layout + meta attributes,
# v1/v0 deprecations, transforms from core/image + core/media-text and to core/media-text.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-media-card
cp -R /solution/files/. "$REPO/"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
