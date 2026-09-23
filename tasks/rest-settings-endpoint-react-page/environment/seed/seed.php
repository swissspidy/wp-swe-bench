<?php
/**
 * Acme SEO fixtures (run with `wp eval-file`): content + the 1.9.2 settings as they are
 * stored on the production site (several of them still in older formats).
 */

foreach ( array(
	'erin'  => 'editor',
	'alice' => 'author',
) as $login => $role ) {
	wp_insert_user(
		array(
			'user_login'   => $login,
			'user_email'   => $login . '@example.org',
			'user_pass'    => 'password',
			'role'         => $role,
			'display_name' => ucfirst( $login ),
		)
	);
}

// Share image in the media library.
$img = imagecreatetruecolor( 1200, 630 );
imagefill( $img, 0, 0, imagecolorallocate( $img, 56, 88, 233 ) );
$file = sys_get_temp_dir() . '/acme-share.png';
imagepng( $img, $file );
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
$upload   = wp_upload_bits( 'acme-share.png', null, file_get_contents( $file ) );
$image_id = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/png',
		'post_title'     => 'Acme share image',
		'post_status'    => 'inherit',
	),
	$upload['file']
);
wp_update_attachment_metadata( $image_id, wp_generate_attachment_metadata( $image_id, $upload['file'] ) );

// Second image, only referenced by URL in the test fixtures of old sites.
$img2 = imagecreatetruecolor( 800, 400 );
imagefill( $img2, 0, 0, imagecolorallocate( $img2, 214, 54, 56 ) );
imagepng( $img2, $file );
$upload2   = wp_upload_bits( 'acme-legacy-share.png', null, file_get_contents( $file ) );
$image2_id = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/png',
		'post_title'     => 'Legacy share image',
		'post_status'    => 'inherit',
	),
	$upload2['file']
);
wp_update_attachment_metadata( $image2_id, wp_generate_attachment_metadata( $image2_id, $upload2['file'] ) );

$tag = wp_insert_term( 'News', 'post_tag' );

wp_insert_post(
	array(
		'post_title'   => 'Widget launch',
		'post_name'    => 'widget-launch',
		'post_status'  => 'publish',
		'post_excerpt' => 'Our new widget is here.',
		'post_content' => '<!-- wp:paragraph --><p>The new widget ships today.</p><!-- /wp:paragraph -->',
		'post_date'    => '2026-05-04 10:00:00',
		'tags_input'   => array( 'News' ),
		'post_author'  => 1,
	)
);
$internal = wp_insert_post(
	array(
		'post_title'   => 'Internal notes',
		'post_name'    => 'internal-notes',
		'post_status'  => 'publish',
		'post_content' => '<!-- wp:paragraph --><p>Not for search engines.</p><!-- /wp:paragraph -->',
		'post_author'  => 1,
	)
);
wp_insert_post(
	array(
		'post_title'   => 'About us',
		'post_name'    => 'about-us',
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_content' => '<!-- wp:paragraph --><p>We make widgets.</p><!-- /wp:paragraph -->',
	)
);
wp_insert_post(
	array(
		'post_title'   => 'Launch party',
		'post_name'    => 'launch-party',
		'post_type'    => 'event',
		'post_status'  => 'publish',
		'post_content' => '<!-- wp:paragraph --><p>Join us.</p><!-- /wp:paragraph -->',
	)
);

// The settings as the 1.9.2 settings screen (and its predecessors) left them.
update_option( 'acme_seo_title_separator', '|' );
update_option( 'acme_seo_home_title', 'Welcome to %%sitename%% %%sep%% %%tagline%%' );
update_option( 'acme_seo_home_description', 'The best widgets in town & more' );
update_option( 'acme_seo_noindex_post_types', array( 'page', 'product' ) );
update_option(
	'acme_seo_noindex_archives',
	array(
		'author' => '1',
		'date'   => '',
		'tag'    => '1',
	)
);
update_option( 'acme_seo_og_enabled', 'yes' );
update_option( 'acme_seo_og_default_image', (string) $image_id );
update_option( 'acme_seo_twitter_handle', '@AcmeCorp' );
update_option(
	'acme_seo_social_profiles',
	array(
		'facebook'  => 'https://www.facebook.com/acmewidgets',
		'instagram' => '',
		'linkedin'  => 'https://www.linkedin.com/company/acme-widgets',
		'youtube'   => 'youtube.com/acme',
		'myspace'   => 'https://myspace.com/acme',
	)
);
update_option( 'acme_seo_sitemap_enabled', '1' );
update_option( 'acme_seo_sitemap_exclude', $internal . ', abc' );
update_option(
	'acme_seo_verification',
	array(
		'google' => '<meta name="google-site-verification" content="AbCdEfGhIjKlMnOpQrStUvWxYz0123456789_-abcd" />',
		'bing'   => '0123456789ABCDEF0123456789ABCDEF',
	)
);
