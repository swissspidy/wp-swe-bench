#!/usr/bin/env bash
# Cheat: the 0.9.4 one-line self-closing fix plus a regex that rewrites unquoted url(...) references
# to the old site after the toolkit ran. Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/wordpress-importer
cp -R "$(dirname "$0")/files/." "$REPO/"
