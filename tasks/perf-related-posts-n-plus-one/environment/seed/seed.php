<?php
/**
 * Seeds the Acme Journal: 6 authors, 8 categories, 30 tags, 12 images, ~215 posts,
 * editor picks in both storage formats, view counts. Deterministic (fixed seed).
 */

mt_srand( 20260923 );

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

function seed_pick( array $a ) {
	return $a[ mt_rand( 0, count( $a ) - 1 ) ];
}

function seed_pick_n( array $a, $n ) {
	$out = array();
	while ( count( $out ) < $n ) {
		$v = seed_pick( $a );
		if ( ! in_array( $v, $out, true ) ) {
			$out[] = $v;
		}
	}
	return $out;
}

// Authors.
$authors = array();
foreach ( array(
	array( 'amelia', 'Amelia Hart', 'editor' ),
	array( 'bruno', 'Bruno Costa', 'author' ),
	array( 'chiara', 'Chiara Rossi', 'author' ),
	array( 'dmitri', 'Dmitri Volkov', 'author' ),
	array( 'esme', 'Esmé O’Neill', 'author' ),
	array( 'farid', 'Farid Haddad', 'contributor' ),
) as $u ) {
	$authors[] = wp_insert_user(
		array(
			'user_login'   => $u[0],
			'user_pass'    => 'password',
			'user_email'   => $u[0] . '@journal.example',
			'display_name' => $u[1],
			'first_name'   => strtok( $u[1], ' ' ),
			'role'         => $u[2],
		)
	);
}

// Categories.
$cats = array();
foreach ( array( 'News', 'Guides', 'Reviews', 'Opinion', 'Interviews', 'Culture', 'Science', 'Sponsored' ) as $name ) {
	$t                                 = wp_insert_term( $name, 'category' );
	$cats[ sanitize_title( $name ) ] = $t['term_id'];
}
$regular_cats = array_values( array_diff_key( $cats, array( 'sponsored' => 1 ) ) );

// Tags.
$tag_names = array( 'climate', 'energy', 'cities', 'transport', 'food', 'travel', 'health', 'sleep', 'running', 'books', 'film', 'music', 'design', 'architecture', 'ai', 'privacy', 'open source', 'space', 'oceans', 'birds', 'gardening', 'coffee', 'cycling', 'photography', 'history', 'languages', 'math', 'chess', 'podcasts', 'education' );
$tags      = array();
foreach ( $tag_names as $name ) {
	$t      = wp_insert_term( $name, 'post_tag' );
	$tags[] = $t['term_id'];
}

// Images (generated, deterministic).
$uploads = wp_upload_dir();
$images  = array();
$palette = array( array( 214, 69, 65 ), array( 52, 152, 219 ), array( 46, 204, 113 ), array( 241, 196, 15 ), array( 155, 89, 182 ), array( 26, 188, 156 ), array( 230, 126, 34 ), array( 52, 73, 94 ), array( 236, 240, 241 ), array( 149, 165, 166 ), array( 192, 57, 43 ), array( 39, 174, 96 ) );
foreach ( $palette as $i => $rgb ) {
	$file = $uploads['path'] . '/journal-photo-' . ( $i + 1 ) . '.png';
	$img  = imagecreatetruecolor( 640, 400 );
	imagefill( $img, 0, 0, imagecolorallocate( $img, $rgb[0], $rgb[1], $rgb[2] ) );
	imagefilledellipse( $img, 120 + 30 * $i, 200, 180, 180, imagecolorallocate( $img, 255 - $rgb[0], 255 - $rgb[1], 255 - $rgb[2] ) );
	imagepng( $img, $file );
	imagedestroy( $img );
	$att_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => 'Journal photo ' . ( $i + 1 ),
			'post_status'    => 'inherit',
			'post_author'    => 1,
			'post_date'      => '2023-12-01 09:00:00',
		),
		$file
	);
	wp_update_attachment_metadata( $att_id, wp_generate_attachment_metadata( $att_id, $file ) );
	if ( 0 === $i % 3 ) {
		update_post_meta( $att_id, '_wp_attachment_image_alt', 'Photo ' . ( $i + 1 ) . ': colourful shapes' );
	}
	$images[] = $att_id;
}

// Posts.
$adjectives = array( 'Quiet', 'Hidden', 'Practical', 'Unexpected', 'Small', 'Radical', 'Honest', 'Slow', 'Brave', 'Curious', 'Modern', 'Forgotten', 'Simple', 'Wild', 'Careful' );
$nouns      = array( 'guide to', 'case for', 'history of', 'future of', 'notes on', 'lessons from', 'map of', 'economics of', 'science of', 'return of' );
$words      = array( 'lorem', 'ipsum', 'dolor', 'sit', 'amet', 'river', 'window', 'garden', 'signal', 'harbor', 'lantern', 'meadow', 'engine', 'paper', 'orbit', 'thread', 'marble', 'canvas', 'pepper', 'violet', 'granite', 'echo', 'summit', 'tunnel', 'basket', 'feather', 'copper', 'willow', 'anchor', 'ember' );

$posts = array();
$base  = strtotime( '2024-01-03 08:00:00 UTC' );
for ( $n = 1; $n <= 200; $n++ ) {
	$topic_tags = seed_pick_n( $tags, mt_rand( 1, 4 ) );
	$title      = seed_pick( $adjectives ) . ' ' . seed_pick( $nouns ) . ' ' . $tag_names[ array_search( $topic_tags[0], $tags, true ) ] . ' (' . $n . ')';
	$paras      = array();
	for ( $p = 0, $pc = mt_rand( 2, 9 ); $p < $pc; $p++ ) {
		$sentence = array();
		for ( $w = 0, $wc = mt_rand( 40, 140 ); $w < $wc; $w++ ) {
			$sentence[] = seed_pick( $words );
		}
		$paras[] = "<!-- wp:paragraph -->\n<p>" . ucfirst( implode( ' ', $sentence ) ) . ".</p>\n<!-- /wp:paragraph -->";
	}
	$is_sponsored = ( 0 === $n % 23 );
	$post_cats    = $is_sponsored ? array( $cats['sponsored'] ) : array( seed_pick( $regular_cats ) );
	if ( ! $is_sponsored && 0 === $n % 5 ) {
		$post_cats[] = seed_pick( array_values( array_diff( $regular_cats, $post_cats ) ) );
	}
	$date = gmdate( 'Y-m-d H:i:s', $base + $n * 3 * DAY_IN_SECONDS + mt_rand( 0, 36000 ) );
	$id   = wp_insert_post(
		array(
			'post_title'    => $title,
			'post_content'  => implode( "\n\n", $paras ),
			'post_status'   => 'publish',
			'post_author'   => seed_pick( $authors ),
			'post_date'     => $date,
			'post_date_gmt' => $date,
			'post_category' => $post_cats,
			'tags_input'    => array_map(
				static function ( $t ) {
					return (int) $t;
				},
				$topic_tags
			),
		)
	);
	if ( mt_rand( 1, 10 ) <= 7 ) {
		set_post_thumbnail( $id, seed_pick( $images ) );
	}
	if ( mt_rand( 1, 10 ) <= 6 ) {
		update_post_meta( $id, '_acme_reading_time', acme_related_estimate_reading_time( get_post_field( 'post_content', $id ) ) );
	}
	if ( count( $post_cats ) > 1 && mt_rand( 0, 1 ) ) {
		update_post_meta( $id, '_acme_primary_category', $post_cats[1] );
	}
	if ( 0 === $n % 13 ) {
		update_post_meta( $id, '_acme_editors_pick', '1' );
	}
	$posts[ $n ] = $id;
}

// Drafts, private and password protected posts (never shown as related).
$hidden = array();
for ( $n = 1; $n <= 12; $n++ ) {
	$status   = $n <= 8 ? 'draft' : ( $n <= 10 ? 'private' : 'publish' );
	$hidden[] = wp_insert_post(
		array(
			'post_title'    => 'Unlisted piece ' . $n,
			'post_content'  => '<!-- wp:paragraph --><p>Work in progress.</p><!-- /wp:paragraph -->',
			'post_status'   => $status,
			'post_password' => $n > 10 ? 'secret' : '',
			'post_author'   => seed_pick( $authors ),
			'post_date'     => gmdate( 'Y-m-d H:i:s', $base + 700 * DAY_IN_SECONDS + $n * 3600 ),
			'post_category' => array( seed_pick( $regular_cats ) ),
			'tags_input'    => array_map( 'intval', seed_pick_n( $tags, 3 ) ),
		)
	);
}

// Editor picks: 1.x comma separated strings and 2.x arrays (some point at drafts, pages, deleted or sponsored posts).
for ( $n = 3; $n <= 200; $n += 7 ) {
	$picks = array( $posts[ mt_rand( 1, 200 ) ], $posts[ mt_rand( 1, 200 ) ] );
	if ( 0 === $n % 2 ) {
		$picks[] = seed_pick( $hidden );
	}
	if ( 0 === $n % 3 ) {
		$picks[] = 99999;
	}
	if ( 0 === $n % 5 ) {
		$picks[] = $posts[23]; // Sponsored, but explicitly picked.
	}
	if ( 0 === ( ( $n - 3 ) / 7 ) % 2 ) {
		update_post_meta( $posts[ $n ], '_acme_related_manual', implode( ', ', $picks ) );
	} else {
		update_post_meta( $posts[ $n ], '_acme_related_manual', $picks );
	}
}

// "Never show as related" (1.x: 'yes', 2.x: '1') and "no list under this post".
foreach ( array( 11, 38, 64, 97 ) as $n ) {
	update_post_meta( $posts[ $n ], '_acme_related_exclude', 'yes' );
}
foreach ( array( 120, 151, 177, 199 ) as $n ) {
	update_post_meta( $posts[ $n ], '_acme_related_exclude', '1' );
}
foreach ( array( 50, 150 ) as $n ) {
	update_post_meta( $posts[ $n ], '_acme_related_hide', '1' );
}

// View counts.
global $wpdb;
Acme\Related\Views::install();
foreach ( $posts as $n => $id ) {
	if ( 0 === $n % 9 ) {
		continue; // Never viewed: no row.
	}
	$wpdb->insert(
		$wpdb->prefix . 'acme_related_views',
		array(
			'post_id'     => $id,
			'views'       => mt_rand( 0, 1 ) ? mt_rand( 0, 999 ) : mt_rand( 1000, 250000 ),
			'last_viewed' => '2026-01-15 12:00:00',
		)
	);
}

// Pages with the block.
$reading_list = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'reading-list',
		'post_title'   => 'Reading list',
		'post_author'  => $authors[0],
		'post_content' => sprintf(
			"<!-- wp:paragraph -->\n<p>Where to go next.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:acme/related-posts {\"postId\":%d,\"heading\":\"If you liked the cities piece\"} /-->\n\n<!-- wp:acme/related-posts {\"postId\":%d,\"count\":6} /-->\n\n<!-- wp:acme/related-posts {\"postId\":%d,\"count\":3,\"align\":\"wide\"} /-->",
			$posts[42],
			$posts[3],
			$posts[117]
		),
	)
);
$roundup      = wp_insert_post(
	array(
		'post_status'   => 'publish',
		'post_name'     => 'weekly-roundup',
		'post_title'    => 'Weekly roundup',
		'post_author'   => $authors[0],
		'post_date'     => '2026-01-10 10:00:00',
		'post_date_gmt' => '2026-01-10 10:00:00',
		'post_category' => array( $cats['news'] ),
		'tags_input'    => array( $tags[0], $tags[2] ),
		'post_content'  => sprintf( "<!-- wp:paragraph -->\n<p>This week in the Journal.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:acme/related-posts {\"postId\":%d,\"count\":8,\"heading\":\"More on this\"} /-->", $posts[88] ),
	)
);

WP_CLI::log( sprintf( 'Seeded %d posts, %d hidden, %d images.', count( $posts ), count( $hidden ), count( $images ) ) );
