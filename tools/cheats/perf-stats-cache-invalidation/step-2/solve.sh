#!/usr/bin/env bash
# Cheat (step 2): the complete 1.7.0 reference, but the regeneration lock only uses the object
# cache (wp_cache_add). Without a persistent object cache that lock only exists inside the
# current request, so every request regenerates stale numbers. Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-dashboard-stats
cp -R "$(dirname "$0")/files/." "$REPO/"
