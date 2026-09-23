#!/usr/bin/env bash
# Reference solution: overlay the 1.9.0 files onto the plugin.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-leads
cp -R /solution/files/. "$REPO/"
