#!/usr/bin/env bash
# Reference solution: register-only assets, render-time/per-component loading, deferred scripts,
# per-block CSS, admin screen scoping (Acme UI Kit 2.9.0).
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-ui-kit
cp -R /solution/files/. "$REPO/"
rm -f "$REPO/assets/js/components.js"
