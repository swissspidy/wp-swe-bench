#!/usr/bin/env bash
# Reference fix: per-event timezone + UTC storage, 2.0 data upgrade, DST-safe display/export/query.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-events
cp -R /solution/files/. "$REPO/"
