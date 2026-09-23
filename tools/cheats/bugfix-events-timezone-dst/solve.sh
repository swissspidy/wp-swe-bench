#!/usr/bin/env bash
# Cheat: correct DST-aware conversions with the *site's current* timezone, all-day date fixes and a
# migration that recomputes the 1.6 timestamps -- but no per-event timezone, no UTC fields. Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-events
cp -R "$(dirname "$0")/files/." "$REPO/"
