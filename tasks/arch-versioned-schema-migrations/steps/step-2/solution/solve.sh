#!/usr/bin/env bash
# Reference solution, step 2: overlay the files onto the plugin.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-crm
cp -R /solution/files/. "$REPO/"
