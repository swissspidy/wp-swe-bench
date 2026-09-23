#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
# The block markup in seed/posts was produced by the plugin's own save functions
# (1.6.0, and the 1.0 format through its historical save) in the block editor.
set -euo pipefail
cd "$(dirname "$0")"
cp seed/mu-plugins/*.php /wordpress/wp-content/mu-plugins/

mk() { # slug type title file
  wp post create "seed/posts/$4" --post_type="$2" --post_status=publish --post_name="$1" --post_title="$3" --porcelain
}
[ -f seed/posts/quarterly-visitors.html ] || exit 0
mk quarterly-visitors post "Quarterly visitors" quarterly-visitors.html >/dev/null
mk legacy-chart post "Survey results (2019)" legacy-chart.html >/dev/null
mk wide-chart post "Downloads by platform" wide-chart.html >/dev/null
mk budget-overview page "Budget overview" budget-overview.html >/dev/null
mk no-charts post "Company news" no-charts.html >/dev/null
ref=$(mk revenue-chart wp_block "Revenue chart" synced-pattern.html)
printf '<!-- wp:paragraph -->\n<p>Revenue for the current year:</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:block {"ref":%s} /-->\n' "$ref" > /tmp/uses-synced.html
wp post create /tmp/uses-synced.html --post_type=post --post_status=publish --post_name=uses-synced-chart --post_title="Revenue report" --porcelain >/dev/null
rm -f /tmp/uses-synced.html
