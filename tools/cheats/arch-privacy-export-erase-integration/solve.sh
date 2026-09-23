#!/usr/bin/env bash
# Cheat: the "obvious" integration: exporters/erasers registered with the right groups and rules,
# but emails compared exactly, erasers page with OFFSET (rows drop out of the match while being
# anonymized/deleted), and the retention job is only scheduled on plugin activation.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-loyalty
cp -R "$(dirname "$0")/files/." "$REPO/"
