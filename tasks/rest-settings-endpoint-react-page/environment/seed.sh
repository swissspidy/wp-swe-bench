#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"
cp seed/mu-plugins/*.php /wordpress/wp-content/mu-plugins/
wp eval-file seed/seed.php
wp rewrite flush >/dev/null
wp option list --search='acme_seo_*' --format=table
