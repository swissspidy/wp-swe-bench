#!/usr/bin/env bash
# Cheat: the reference v2 resource with two common shortcuts:
# - the cursor is plain base64 JSON (keyset, but not tamper-proof),
# - `_fields` is left to the REST server's response filtering, so every column (incl. notes) is read.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-leads
cp -R "$(dirname "$0")/files/." "$REPO/"
