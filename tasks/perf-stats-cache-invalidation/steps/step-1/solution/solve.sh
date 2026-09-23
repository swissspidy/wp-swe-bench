#!/usr/bin/env bash
# Reference solution, step 1: cache per scope + generation-based invalidation (1.6.0).
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-dashboard-stats
cp -R /solution/files/. "$REPO/"
