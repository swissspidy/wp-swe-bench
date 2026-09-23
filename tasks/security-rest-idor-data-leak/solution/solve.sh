#!/usr/bin/env bash
# Reference solution: overlay the 1.5.0 files onto the plugin.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-support
cp -R /solution/files/. "$REPO/"
