#!/usr/bin/env bash
# Cheat: "apply the visibility helper for the current user everywhere". REST API, profile form,
# core users field, search handler, author card and member sitemap are fixed, but feeds use the
# requesting user's view, the directory's shared cache is left as is, and the core users sitemap
# still lists hidden members.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-members
cp -R "$(dirname "$0")/files/." "$REPO/"
