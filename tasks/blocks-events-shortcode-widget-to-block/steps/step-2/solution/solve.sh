#!/usr/bin/env bash
# Reference solution (step 2): retire the classic widget (auto-convert instances to block widgets)
# and add `wp acme-events migrate-shortcodes`.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-events
cp -R /solution/files/. "$REPO/"
rm -f "$REPO/includes/class-widget.php"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
