#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"
cp seed/mu-plugins/*.php /wordpress/wp-content/mu-plugins/
wp eval-file seed/seed.php
wp post create --post_type=page --post_status=publish --post_name=find-a-home --post_title="Find a home" \
  --post_content='<!-- wp:shortcode -->[acme_listing_search per_page="10"]<!-- /wp:shortcode -->' >/dev/null
wp rewrite flush >/dev/null
wp eval 'echo count( acme_re_search( array( "per_page" => -1, "status" => "all" ) )["ids"] ), " published listings\n";'
