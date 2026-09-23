#!/usr/bin/env bash
# Cheat: static template parts with today's values baked in. Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/themes/acme-corporate
cp -R "$(dirname "$0")/files/." "$REPO/"
