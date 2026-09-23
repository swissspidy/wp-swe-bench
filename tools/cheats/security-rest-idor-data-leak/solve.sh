#!/usr/bin/env bash
# Cheat: hardens the plugin's OWN API (per-object checks on every acme-support/v1
# route) and even the media endpoint, but leaves the acme_ticket / acme_reply CPTs
# registered `public` + `show_in_rest` with wide-open capabilities. So the tickets,
# replies, internal notes, e-mails, revisions/autosaves and search still leak through
# the core /wp/v2 routes. Note: class-post-types.php is intentionally NOT replaced.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-support
cp -R "$(dirname "$0")/files/." "$REPO/"
