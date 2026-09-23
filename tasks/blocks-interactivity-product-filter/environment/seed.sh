#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"

for t in "Mugs:mugs" "Posters:posters" "Stickers:stickers" "T-Shirts:shirts"; do
  wp term create acme_product_cat "${t%%:*}" --slug="${t##*:}" --porcelain >/dev/null
done

i=0
product() { # title sku price status cats(comma)
  i=$((i+1))
  local id
  id=$(wp post create --post_type=acme_product --post_status="$4" --post_title="$1" --post_content="<!-- wp:paragraph --><p>$1 from the Acme shop.</p><!-- /wp:paragraph -->" --post_date="2026-0$(( (i % 9) + 1 ))-1$(( i % 10 )) 10:00:00" --porcelain)
  wp post meta update "$id" _acme_sku "$2" >/dev/null
  wp post meta update "$id" _acme_price "$3" >/dev/null
  if [ -n "$5" ]; then wp post term set "$id" acme_product_cat ${5//,/ } --by=slug >/dev/null; fi
}
product "Blue Mug" MUG-BLU 12 publish mugs
product "Red Mug" MUG-RED 12 publish mugs
product "Café Mug" MUG-CAF 14.5 publish mugs
product "Travel Mug" MUG-TRV 19.9 publish mugs
product "Blue T-Shirt" TEE-BLU 25 publish shirts
product "Logo T-Shirt" TEE-LOG 22 publish shirts
product "Vintage T-Shirt" TEE-VIN 29 publish shirts
product "Mountain Poster" PST-MNT 18 publish posters
product "Ocean Poster" PST-OCN 18.5 publish posters
product "Mug & Poster Bundle" BND-001 27 publish mugs,posters
product "Sticker Pack" STK-001 5 publish stickers
product "Gift Card" GIFT-25 25 publish ""
product "Secret Prototype" MUG-XXX 99 draft mugs

page() { # slug title file
  wp post create "seed/pages/$3" --post_type=page --post_status=publish --post_name="$1" --post_title="$2" --porcelain >/dev/null
}
page shop "Shop" shop.html
page featured "Featured" featured.html
page two-grids "Kitchen and apparel" two-grids.html
page classic-shop "Classic shop" classic-shop.html
