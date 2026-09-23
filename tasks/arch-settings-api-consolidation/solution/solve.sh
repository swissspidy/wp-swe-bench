#!/usr/bin/env bash
# Reference solution: overlay the 2.0.0 files onto the plugin.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-social
cp -R /solution/files/. "$REPO/"
