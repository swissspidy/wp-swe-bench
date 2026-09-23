#!/usr/bin/env bash
# Cheat: story capabilities granted from a hard-coded per-role list on every upgrade run. Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-newsroom
cp -R "$(dirname "$0")/files/." "$REPO/"
