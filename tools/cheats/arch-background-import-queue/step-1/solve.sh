#!/usr/bin/env bash
# Cheat (step-1): see ../README.md. Overlays the files onto the plugin.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-importer
cp -R "$(dirname "$0")/files/." "$REPO/"
