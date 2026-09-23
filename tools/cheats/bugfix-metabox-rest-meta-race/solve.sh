#!/usr/bin/env bash
# Cheat: the "obvious" fix in the meta box save handler only (skip sidebar fields on the block editor's
# meta box request, unslash, save unchecked boxes). Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-product-fields
cp -R "$(dirname "$0")/files/." "$REPO/"
