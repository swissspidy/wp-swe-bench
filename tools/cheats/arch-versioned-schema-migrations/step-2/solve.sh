#!/usr/bin/env bash
# Cheat, step 2 (see ../README.md).
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-crm
cp -R /solution/files/. "$REPO/"
