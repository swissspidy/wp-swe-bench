#!/usr/bin/env bash
# Reference solution: batched related-post computation + bulk priming (Acme Related 2.4.0).
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-related
cp -R /solution/files/. "$REPO/"
