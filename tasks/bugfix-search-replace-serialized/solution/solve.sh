#!/usr/bin/env bash
# Reference fix: token-level rewrite of serialized data (no unserialize), JSON-escaped and URL-encoded
# forms, no double escaping on write, GUIDs skipped by default, dry runs that compute but never write.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-migrate
cp -R /solution/files/. "$REPO/"
