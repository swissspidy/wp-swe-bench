#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"
cp seed/mu-plugins/*.php /wordpress/wp-content/mu-plugins/
wp user create eddie eddie@example.org --role=editor --user_pass=password --display_name="Eddie Editor" >/dev/null
wp user create alice alice@example.org --role=author --user_pass=password --display_name="Alice Author" >/dev/null
wp user create bob bob@example.org --role=author --user_pass=password --display_name="Bob Author" >/dev/null
wp user create carl carl@example.org --role=contributor --user_pass=password --display_name="Carl Contributor" >/dev/null
wp user create sam sam@example.org --role=subscriber --user_pass=password --display_name="Sam Subscriber" >/dev/null
wp eval-file seed/seed.php
wp post list --post_type=acme_product --post_status=any --fields=ID,post_name,post_author,post_status
wp rewrite flush >/dev/null
