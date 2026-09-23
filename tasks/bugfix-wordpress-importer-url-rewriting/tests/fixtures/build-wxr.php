<?php
/**
 * Builds the WXR fixtures from content/*.html:
 *   php build-wxr.php full   > alpine-trails-full.xml    (hidden tests)
 *   php build-wxr.php public > alpine-trails-export.xml  (the export attached to the bug report)
 */

// phpcs:ignoreFile

$set   = $argv[1] ?? 'full';
$items = array(
	// id, type, slug, title, content file, date, public?
	array( 2, 'page', 'about', 'About', null, '2023-01-10 09:00:00', true ),
	array( 10, 'wp_navigation', 'main-menu', 'Main menu', 'main-menu.html', '2024-02-01 10:00:00', true ),
	array( 20, 'post', 'follow-us', 'Follow us', 'follow-us.html', '2024-03-01 10:00:00', true ),
	array( 30, 'post', 'summer-in-the-alps', 'Summer in the Alps', 'cover-parallax.html', '2024-06-01 10:00:00', true ),
	array( 31, 'post', 'granite-pattern', 'Granite pattern', 'cover-repeated.html', '2024-06-02 10:00:00', false ),
	array( 32, 'post', 'old-landing-page', 'Old landing page', 'html-backgrounds.html', '2024-06-03 10:00:00', false ),
	array( 33, 'post', 'newsletter', 'Newsletter', 'newsletter.html', '2024-06-04 10:00:00', false ),
	array( 34, 'post', 'packing-tips', 'Packing tips', 'links.html', '2024-06-05 10:00:00', true ),
);

$cdata = static fn( $s ) => '<![CDATA[' . str_replace( ']]>', ']]]]><![CDATA[>', $s ) . ']]>';

$out  = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
$out .= '<rss version="2.0" xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:wfw="http://wellformedweb.org/CommentAPI/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:wp="http://wordpress.org/export/1.2/">' . "\n";
$out .= "<channel>\n\t<title>Alpine Trails</title>\n\t<link>https://oldblog.example</link>\n\t<description>Hiking guides</description>\n\t<language>en-US</language>\n";
$out .= "\t<wp:wxr_version>1.2</wp:wxr_version>\n\t<wp:base_site_url>https://oldblog.example</wp:base_site_url>\n\t<wp:base_blog_url>https://oldblog.example</wp:base_blog_url>\n";
$out .= "\t<wp:author><wp:author_id>1</wp:author_id><wp:author_login>" . $cdata( 'trailadmin' ) . '</wp:author_login><wp:author_email>' . $cdata( 'admin@oldblog.example' ) . '</wp:author_email><wp:author_display_name>' . $cdata( 'Trail Admin' ) . "</wp:author_display_name></wp:author>\n";

foreach ( $items as list( $id, $type, $slug, $title, $file, $date, $public ) ) {
	if ( 'public' === $set && ! $public ) {
		continue;
	}
	$content = $file ? rtrim( file_get_contents( __DIR__ . '/content/' . $file ) ) : "<!-- wp:paragraph -->\n<p>We hike.</p>\n<!-- /wp:paragraph -->";
	$excerpt = 'packing-tips' === $slug ? 'All our tips: https://oldblog.example/guides/packing-list/' : '';
	$out    .= "\t<item>\n";
	$out    .= "\t\t<title>" . $cdata( $title ) . "</title>\n";
	$out    .= "\t\t<link>https://oldblog.example/$slug/</link>\n";
	$out    .= "\t\t<pubDate>" . gmdate( 'D, d M Y H:i:s +0000', strtotime( $date . ' UTC' ) ) . "</pubDate>\n";
	$out    .= "\t\t<dc:creator>" . $cdata( 'trailadmin' ) . "</dc:creator>\n";
	$out    .= "\t\t<guid isPermaLink=\"false\">https://oldblog.example/?p=$id</guid>\n";
	$out    .= "\t\t<description></description>\n";
	$out    .= "\t\t<content:encoded>" . $cdata( $content ) . "</content:encoded>\n";
	$out    .= "\t\t<excerpt:encoded>" . $cdata( $excerpt ) . "</excerpt:encoded>\n";
	$out    .= "\t\t<wp:post_id>$id</wp:post_id>\n";
	$out    .= "\t\t<wp:post_date>" . $cdata( $date ) . "</wp:post_date>\n";
	$out    .= "\t\t<wp:post_date_gmt>" . $cdata( $date ) . "</wp:post_date_gmt>\n";
	$out    .= "\t\t<wp:comment_status>" . $cdata( 'closed' ) . "</wp:comment_status>\n";
	$out    .= "\t\t<wp:ping_status>" . $cdata( 'closed' ) . "</wp:ping_status>\n";
	$out    .= "\t\t<wp:post_name>" . $cdata( $slug ) . "</wp:post_name>\n";
	$out    .= "\t\t<wp:status>" . $cdata( 'publish' ) . "</wp:status>\n";
	$out    .= "\t\t<wp:post_parent>0</wp:post_parent>\n";
	$out    .= "\t\t<wp:menu_order>0</wp:menu_order>\n";
	$out    .= "\t\t<wp:post_type>" . $cdata( $type ) . "</wp:post_type>\n";
	$out    .= "\t\t<wp:post_password>" . $cdata( '' ) . "</wp:post_password>\n";
	$out    .= "\t\t<wp:is_sticky>0</wp:is_sticky>\n";
	$out    .= "\t</item>\n";
}
$out .= "</channel>\n</rss>\n";
echo $out;
