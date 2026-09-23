#!/usr/bin/env bash
# Reference solution: overlay the 3.0.0 files onto the plugin.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-specs
cp -R /solution/files/. "$REPO/"
