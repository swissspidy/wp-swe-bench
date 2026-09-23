#!/usr/bin/env bash
# Cheat: the full command suite, but it writes rules with direct table queries instead of the repository.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-redirects
cp -R "$(dirname "$0")/files/." "$REPO/"
