#!/usr/bin/env bash
# Cheat: straightforward register_rest_field + per-item links/visibility + book_author filter,
# but relations are loaded per book (no page-level priming) and the old /acme-library/v1
# routes are left as they were. Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-library
cp -R "$(dirname "$0")/files/." "$REPO/"
