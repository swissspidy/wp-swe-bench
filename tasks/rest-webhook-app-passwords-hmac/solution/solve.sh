#!/usr/bin/env bash
# Reference solution: overlay the 1.4.0 files onto the plugin.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-orders-sync
cp -R /solution/files/. "$REPO/"
