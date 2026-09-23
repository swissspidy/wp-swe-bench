#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"
cp seed/mu-plugins/*.php /wordpress/wp-content/mu-plugins/
wp user create eddie eddie@example.org --role=editor --user_pass=password --display_name="Eddie Editor" >/dev/null
wp user create alex alex@example.org --role=author --user_pass=password --display_name="Alex Author" >/dev/null
wp eval-file seed/seed.php
wp rewrite flush >/dev/null
