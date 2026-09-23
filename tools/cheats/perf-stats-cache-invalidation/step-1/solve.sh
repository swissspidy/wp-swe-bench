#!/usr/bin/env bash
# Cheat (step 1): per-scope transient with a 10 minute expiry, no invalidation. Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-dashboard-stats
cp -R "$(dirname "$0")/files/." "$REPO/"
