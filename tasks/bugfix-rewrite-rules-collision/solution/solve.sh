#!/usr/bin/env bash
# Reference fix: content-aware routing of /docs/ URLs, per-product/version doc resolution,
# permalink/preview fixes, one-off rewrite flush instead of flushing on every request.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-docs
cp -R /solution/files/. "$REPO/"
