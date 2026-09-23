#!/usr/bin/env bash
# Cheat: full variation/REST/Event date plumbing, but "upcoming" = the old query
# (start >= today via strtotime, only 'cancelled' excluded). Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-events-lite
cp -R "$(dirname "$0")/files/." "$REPO/"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
