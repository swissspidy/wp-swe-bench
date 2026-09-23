#!/usr/bin/env bash
# Reference solution: overlay the 2.0.0 theme files.
set -euo pipefail
REPO=/wordpress/wp-content/themes/acme-magazine
cp -R /solution/files/. "$REPO/"
