#!/usr/bin/env bash
# Front-end parity fixtures from the STARTING implementation (server running, pristine DB):
#   bash generate-page-fixtures.sh > fixtures/pages.json
set -euo pipefail
urls=( "/page/2/" "/category/guides/" "/tag/coffee/" "/reading-list/" "/weekly-roundup/" )
for n in 3 10 42 88 117 200; do
  slug=$(wp post list --post_type=post --fields=post_name,post_title --format=csv | grep "($n)\"\?$" | cut -d, -f1)
  urls+=( "/$slug/" )
done
php -r '
$out = array();
foreach ( array_slice( $argv, 1 ) as $u ) {
  $html = file_get_contents( "http://127.0.0.1:9400" . $u );
  preg_match_all( "#<section class=\"[^\"]*acme-related.*?</section>#s", $html, $m );
  $out[ $u ] = $m[0];
}
echo json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
' "${urls[@]}"
