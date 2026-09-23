#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"
ID1=$(wp media import seed/images/sunflowers.png --title="Sunflowers" --alt="Sunflowers in a vase" --porcelain)
ID2=$(wp media import seed/images/team.png --title="Team" --porcelain)
URL1=$(wp eval "echo wp_get_attachment_url( $ID1 );")
URL2=$(wp eval "echo wp_get_attachment_url( $ID2 );")
render() { sed -e "s#%ID1%#$ID1#g" -e "s#%ID2%#$ID2#g" -e "s#%URL1%#$URL1#g" -e "s#%URL2%#$URL2#g" "seed/posts/$1" > "/tmp/$1"; echo "/tmp/$1"; }
wp post create "$(render v1-cards.html)" --post_type=page --post_status=publish --post_name=spring-campaign --post_title="Spring campaign" --porcelain
wp post create "$(render v0-card.html)" --post_type=post --post_status=publish --post_name=about-acme --post_title="About Acme" --porcelain
PATTERN=$(wp eval 'echo WP_Block_Patterns_Registry::get_instance()->get_registered( "acme/promo-trio" )["content"];')
printf '%s' "$PATTERN" > /tmp/pattern.html
wp post create /tmp/pattern.html --post_type=page --post_status=publish --post_name=why-acme --post_title="Why Acme" --porcelain
rm -f /tmp/v1-cards.html /tmp/v0-card.html /tmp/pattern.html
echo "images: $ID1 $URL1 / $ID2 $URL2"
