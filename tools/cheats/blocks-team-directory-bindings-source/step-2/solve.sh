#!/usr/bin/env bash
# Cheat (step 2): the reference editor integration, but the REST field exposes every member's
# fields to anyone who can read the member post, and the editor lets everybody edit in place.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-team
cp -R "$(dirname "$0")/files/." "$REPO/"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
