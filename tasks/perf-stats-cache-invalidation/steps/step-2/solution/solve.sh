#!/usr/bin/env bash
# Reference solution, step 2: stale-while-revalidate with a regeneration lock, WP-CLI warm/flush,
# persistent object cache support (1.7.0). Overlays the complete 1.7.0 plugin files.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-dashboard-stats
cp -R /solution/files/. "$REPO/"
