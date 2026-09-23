#!/usr/bin/env bash
# Reference solution: overlay the 2.4.0 files onto the plugin.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-redirects
cp -R /solution/files/. "$REPO/"
