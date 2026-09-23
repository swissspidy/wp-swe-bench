#!/usr/bin/env bash
# Cheat: REST API + migrated screen, legacy admin-ajax actions left as they were. Must score 0.
set -euo pipefail
cp -R "$(dirname "$0")/files/." /wordpress/wp-content/plugins/acme-inventory/
