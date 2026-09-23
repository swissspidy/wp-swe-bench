#!/usr/bin/env bash
# Cheat: lookup tables + rewritten search + backfill + reindex, but the index is only
# refreshed when a listing is saved (save_post), not when its meta changes directly.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-real-estate
cp -R "$(dirname "$0")/files/." "$REPO/"
