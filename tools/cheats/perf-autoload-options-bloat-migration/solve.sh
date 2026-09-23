#!/usr/bin/env bash
# Cheat: complete table + user-meta migration, batched and idempotent, but it does not
# protect the legacy ID range from entries logged while the migration is still running.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-activity-log
cp -R "$(dirname "$0")/files/." "$REPO/"
