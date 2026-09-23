#!/usr/bin/env bash
# Reference solution: overlay the 1.7.0 files onto the plugin.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-real-estate
cp -R /solution/files/. "$REPO/"
