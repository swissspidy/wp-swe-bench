#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"
wp post delete 1 --force >/dev/null   # "Hello world!"
wp post delete 2 --force >/dev/null   # "Sample Page"
wp eval-file seed/events.php
wp post create --post_type=page --post_status=publish --post_name=whats-on --post_title="What's on" \
  --post_content='<!-- wp:paragraph --><p>Our next events:</p><!-- /wp:paragraph --><!-- wp:shortcode -->[acme_upcoming_events limit="20"]<!-- /wp:shortcode -->' >/dev/null
wp post create seed/pages/event-archive.html --post_type=page --post_status=publish --post_name=event-archive --post_title="Event archive" >/dev/null
wp rewrite flush >/dev/null
