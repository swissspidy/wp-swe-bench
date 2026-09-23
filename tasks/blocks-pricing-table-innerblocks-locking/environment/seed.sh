#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"

mk() { # slug type title file
  wp post create "seed/posts/$4" --user=admin --post_type="$2" --post_status=publish --post_name="$1" --post_title="$3" --porcelain
}
mk hosting-plans page "Hosting plans" hosting-plans.html >/dev/null
mk swiss-offers page "Angebote Schweiz" swiss-offers.html >/dev/null
mk legacy-v1-table post "Plans (2019)" legacy-v1-table.html >/dev/null
mk two-tables page "Licences and workshops" two-tables.html >/dev/null
wp post create --user=admin --post_type=post --post_status=publish --post_name=price-shortcode \
  --post_title="Price in running text" \
  --post_content='<p>Our starter plan costs just [acme_price amount="9"] per month, or [acme_price amount="1290.5" currency="CHF"] per year in Switzerland.</p>' >/dev/null

# Marketing editors (non-administrators).
wp user create editor1 editor1@example.org --role=editor --user_pass=password --display_name="Erin Editor" >/dev/null
wp user create author1 author1@example.org --role=author --user_pass=password --display_name="Andy Author" >/dev/null
