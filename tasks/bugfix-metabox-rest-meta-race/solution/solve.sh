#!/usr/bin/env bash
# Reference fix: every surface saves only the fields it rendered (hidden field list), the block editor
# meta box no longer carries the sidebar fields, request data is unslashed once, unchecked boxes are
# saved as unchecked, Bulk Edit honours "No Change", legacy checkbox values are exposed as booleans.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-product-fields
cp -R /solution/files/. "$REPO/"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
