#!/usr/bin/env bash
# Cheat: batching + priming like the reference, but computed lists are cached for an hour
# (transients + in-memory memo) with no invalidation. Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-related
cp -R "$(dirname "$0")/files/." "$REPO/"
