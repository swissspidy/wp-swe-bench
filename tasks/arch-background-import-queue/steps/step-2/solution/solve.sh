#!/usr/bin/env bash
# Reference solution, step 2: overlay the files onto the plugin (on top of step 1).
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-importer
cp -R /solution/files/. "$REPO/"
