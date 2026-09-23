#!/usr/bin/env bash
# Cheat: fixes slashing, dry run, GUIDs, escaped/encoded forms and nested serialized strings, and stops
# instantiating stored objects by unserializing with classes disabled, but then skips every object it
# cannot modify (incomplete classes). Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-migrate
cp -R "$(dirname "$0")/files/." "$REPO/"
