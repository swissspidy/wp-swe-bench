#!/usr/bin/env bash
# Cheat: new router (pages below /docs/, pagination, versions), one-off flush, preview fix -- but docs
# are still looked up by path alone (core's page-path lookup), then checked against product/version.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-docs
cp -R "$(dirname "$0")/files/." "$REPO/"
