#!/usr/bin/env bash
# Reference solution: overlay the 2.5.0 files onto the plugin.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-directory
cp -R /solution/files/. "$REPO/"
