#!/usr/bin/env bash
# Reference solution: overlay the 4.0 files onto the plugin.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-inventory
cp -R /solution/files/. "$REPO/"
