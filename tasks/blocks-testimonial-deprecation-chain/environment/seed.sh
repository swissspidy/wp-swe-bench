#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
#
# seed/posts/*.html were produced in the block editor by the block's real save
# functions of each release (1.0, 2.x and 3.x; see ../seed-src/generate.spec.mjs).
# The customer-reviews page is created by the 3.2 CSV importer itself.
set -euo pipefail
cd "$(dirname "$0")"
cp seed/mu-plugins/*.php /wordpress/wp-content/mu-plugins/

# Author photo (a generated 300x300 JPEG), imported into the media library.
wp eval '$im = imagecreatetruecolor( 300, 300 ); imagefill( $im, 0, 0, imagecolorallocate( $im, 56, 88, 233 ) ); imagefilledellipse( $im, 150, 120, 120, 120, imagecolorallocate( $im, 240, 200, 170 ) ); imagefilledellipse( $im, 150, 300, 220, 200, imagecolorallocate( $im, 30, 30, 30 ) ); imagejpeg( $im, "/tmp/jane-doe.jpg", 90 );'
avatar=$(wp media import /tmp/jane-doe.jpg --title="Jane Doe" --alt="Portrait of Jane Doe" --porcelain)
wp eval '$im = imagecreatetruecolor( 300, 300 ); imagefill( $im, 0, 0, imagecolorallocate( $im, 26, 143, 92 ) ); imagefilledellipse( $im, 150, 120, 120, 120, imagecolorallocate( $im, 190, 140, 110 ) ); imagejpeg( $im, "/tmp/omar-haddad.jpg", 90 );'
avatar2=$(wp media import /tmp/omar-haddad.jpg --title="Omar Haddad" --porcelain)
rm -f /tmp/jane-doe.jpg /tmp/omar-haddad.jpg
avatar_url=$(wp eval "echo wp_get_attachment_url( $avatar );")

[ -f seed/posts/v1-testimonials.html ] || exit 0
mk() { # slug type title file
  sed -e "s#AVATAR_URL_PLACEHOLDER#${avatar_url}#g" -e "s#999999#${avatar}#g" "seed/posts/$4" > /tmp/seed-post.html
  wp post create /tmp/seed-post.html --post_type="$2" --post_status=publish --post_name="$1" --post_title="$3" --porcelain
  rm -f /tmp/seed-post.html
}
mk v1-testimonials post "What our first customers said" v1-testimonials.html >/dev/null
mk v2-testimonials post "Customer stories 2021" v2-testimonials.html >/dev/null
mk v3-testimonials post "Customer stories 2024" v3-testimonials.html >/dev/null
mk mixed-testimonials page "Wall of love" mixed-testimonials.html >/dev/null

# Created by the 3.2 importer (as the marketing team did in production).
sed -e "s#AVATAR2_ID#${avatar2}#g" seed/csv/customer-reviews.csv > /tmp/customer-reviews.csv
wp acme-testimonials import /tmp/customer-reviews.csv --title="Customer reviews" --post_type=page --status=publish --slug=customer-reviews >/dev/null
rm -f /tmp/customer-reviews.csv
