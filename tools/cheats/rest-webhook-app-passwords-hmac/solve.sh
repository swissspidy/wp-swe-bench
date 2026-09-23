#!/usr/bin/env bash
# Cheat: the full 1.4.0 feature set, but "logged in as an admin" is taken as the manual
# re-send credential (cookie auth accepted, not just application passwords), and only new
# log entries are redacted (the existing 1.3.2 sync.log keeps its secrets). Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-orders-sync
cp -R "$(dirname "$0")/files/." "$REPO/"
