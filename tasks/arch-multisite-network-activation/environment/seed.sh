#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time): turn the site into a
# subdirectory multisite network with six sites, and set the directory up the way
# production has it (activated individually on the main site and on /south/).
set -euo pipefail
cd "$(dirname "$0")"
cp seed/mu-plugins/*.php /wordpress/wp-content/mu-plugins/

wp core multisite-convert --title="Acme Local Network" >/dev/null
U=http://127.0.0.1:9400
wp site create --slug=north --title="Acme North" --porcelain >/dev/null     # 2
wp site create --slug=south --title="Acme South" --porcelain >/dev/null     # 3
wp site create --slug=east --title="Acme East" --porcelain >/dev/null       # 4
wp site create --slug=west --title="Acme West (closed)" --porcelain >/dev/null  # 5
wp site create --slug=harbour --title="Acme Harbour" --porcelain >/dev/null # 6
wp site archive 5 >/dev/null
for s in "" north/ south/ east/ west/ harbour/; do
  wp --url="$U/$s" option update permalink_structure '/%postname%/' >/dev/null
  wp --url="$U/$s" rewrite flush >/dev/null
done

# Individually activated on the main site and on /south/ (as in production).
wp --url="$U/" plugin activate acme-directory >/dev/null
wp --url="$U/south/" plugin activate acme-directory >/dev/null
wp --url="$U/south/" option update acme_directory_settings '{"per_page":5,"moderation":false,"expire_days":90,"purge_days":14,"notify_email":"south-desk@acme.example"}' --format=json >/dev/null

wp --url="$U/" eval-file seed/listings.php main
wp --url="$U/south/" eval-file seed/listings.php south

wp --url="$U/" post create --post_type=page --post_status=publish --post_name=directory --post_title="Directory" --post_content='[acme_directory]' >/dev/null
wp --url="$U/south/" post create --post_type=page --post_status=publish --post_name=directory --post_title="South directory" --post_content='[acme_directory]' >/dev/null
