#!/usr/bin/env bash
# Cheat: full settings-screen rebuild, but upgrades only run in wp-admin and saving ignores
# the acme_social_admin_capability filter. Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-social
cp -R "$(dirname "$0")/files/." "$REPO/"
