#!/usr/bin/env bash
# Cheat: per-component assets and deferred scripts like the reference, but components are detected
# up front with has_block()/has_shortcode() on the queried post only. Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-ui-kit
cp -R "$(dirname "$0")/files/." "$REPO/"
rm -f "$REPO/assets/js/components.js"
