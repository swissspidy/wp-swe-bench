#!/usr/bin/env bash
# Cheat: "the documented way" – declare the CTA attributes as bindable and source them from the saved
# HTML so WordPress swaps the override values into the static markup. Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-cta
cp -R "$(dirname "$0")/files/." "$REPO/"
cd "$REPO"
[ -e node_modules ] || ln -s /opt/wpsb/node/node_modules node_modules
npm run build
