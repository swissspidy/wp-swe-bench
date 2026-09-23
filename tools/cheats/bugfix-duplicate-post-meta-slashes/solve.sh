#!/usr/bin/env bash
# Cheat: fixes every slashing problem (meta strings/arrays/objects, content on copy and republish)
# but keeps the per-request taxonomy map that is built on init. Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/duplicate-post
cp -R "$(dirname "$0")/files/." "$REPO/"
