#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"
cp seed/mu-plugins/*.php /wordpress/wp-content/mu-plugins/

mk() { # slug type title file [extra args...]
  local slug="$1" type="$2" title="$3" file="$4"; shift 4
  wp post create "seed/posts/$file" --post_type="$type" --post_status=publish --post_name="$slug" --post_title="$title" --porcelain "$@"
}
wp post delete 1 --force >/dev/null   # "Hello world!"
wp post delete 2 --force >/dev/null   # "Sample Page"
mk welcome-to-the-new-blog post "Welcome to the new blog" welcome.html >/dev/null
mk spring-campaign post "Spring campaign" with-block.html >/dev/null
mk classic-post post "An old classic post" with-shortcode.html >/dev/null
sponsored=$(mk sponsored-review post "Sponsored: the Acme 3000 reviewed" sponsored.html)
wp post term set "$sponsored" post_tag sponsored >/dev/null
mk about page "About us" about.html >/dev/null

# Last year the web team customised the Single Posts template in the Site Editor
# (added a "Thanks for reading" line under the post content).
single=/wordpress/wp-content/themes/twentytwentyfive/templates/single.html
content=$(sed 's#<!-- wp:post-content {"align":"full","layout":{"type":"constrained"}} /-->#<!-- wp:post-content {"align":"full","layout":{"type":"constrained"}} /-->\n\t\t<!-- wp:paragraph {"className":"acme-thanks"} -->\n\t\t<p class="acme-thanks">Thanks for reading the Acme blog.</p>\n\t\t<!-- /wp:paragraph -->#' "$single")
printf '%s' "$content" > /tmp/single.html
wp eval '
$content = file_get_contents( "/tmp/single.html" );
$id = wp_insert_post( array(
	"post_type"    => "wp_template",
	"post_name"    => "single",
	"post_title"   => "Single Posts",
	"post_excerpt" => "Displays a single post on your website unless a custom template has been applied to that post or a dedicated template exists.",
	"post_status"  => "publish",
	"post_content" => $content,
	"tax_input"    => array( "wp_theme" => array( "twentytwentyfive" ) ),
), true );
if ( is_wp_error( $id ) ) { WP_CLI::error( $id ); }
wp_set_object_terms( $id, "twentytwentyfive", "wp_theme" );
echo $id;
' >/dev/null
rm -f /tmp/single.html

# A few existing subscribers.
wp eval '
foreach ( array(
	array( "anna@example.org", "Anna", "content" ),
	array( "ben@example.org", "Ben", "widget" ),
	array( "chloe@example.org", "", "shortcode" ),
) as $s ) {
	$r = Acme\Newsletter\Subscribers::add( array( "email" => $s[0], "name" => $s[1], "source" => $s[2] ) );
	if ( is_wp_error( $r ) ) { WP_CLI::error( $r ); }
}
'
