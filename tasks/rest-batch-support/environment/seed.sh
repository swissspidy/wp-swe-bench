#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"
cp seed/mu-plugins/*.php /wordpress/wp-content/mu-plugins/
wp eval-file seed/seed.php
wp eval 'global $wpdb; foreach ( $wpdb->get_results( "SELECT list_id, id, position, status, title FROM {$wpdb->prefix}acme_tasks ORDER BY list_id, position, id", ARRAY_A ) as $r ) { echo implode( " | ", $r ), "\n"; }'
