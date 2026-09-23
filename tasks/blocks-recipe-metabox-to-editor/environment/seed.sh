#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"
wp eval-file seed/seed.php
wp post list --post_type=acme_recipe --post_status=any --fields=ID,post_name,post_author,post_status
wp rewrite flush >/dev/null
