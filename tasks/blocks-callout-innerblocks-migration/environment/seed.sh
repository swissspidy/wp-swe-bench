#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"
cp seed/mu-plugins/*.php /wordpress/wp-content/mu-plugins/

mk() { # slug type title file
  wp post create "seed/posts/$4" --post_type="$2" --post_status=publish --post_name="$1" --post_title="$3" --porcelain
}
mk legacy-v0-callout post "Legacy 1.0 callout" legacy-v0-callout.html >/dev/null
mk v1-callout post "Callouts from 1.3" v1-callout.html >/dev/null
mk anchored-callout post "Anchored callout" anchored-callout.html >/dev/null
mk nested-callouts page "Nested callouts" nested-callouts.html >/dev/null
mk custom-type-callout post "Custom type callout" custom-type-callout.html >/dev/null
mk shortcode-callout post "Classic shortcode callout" shortcode-callout.html >/dev/null
mk mixed-callouts post "Mixed callouts" mixed-callouts.html >/dev/null
ref=$(mk maintenance-callout wp_block "Maintenance callout" synced-pattern.html)
printf '<!-- wp:paragraph -->\n<p>Planned maintenance:</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:block {"ref":%s} /-->\n' "$ref" > /tmp/uses-synced.html
wp post create /tmp/uses-synced.html --post_type=post --post_status=publish --post_name=uses-synced-callout --post_title="Uses synced callout" --porcelain >/dev/null
rm -f /tmp/uses-synced.html
# Recompute the callout counters the way the plugin does on save.
wp eval 'foreach ( get_posts( array( "post_type" => array( "post", "page" ), "numberposts" => -1 ) ) as $p ) { update_post_meta( $p->ID, "_acme_callout_count", Acme\Callouts\Stats::count_in_content( $p->post_content ) ); }'
