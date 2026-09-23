<?php
/**
 * Seeds the Acme Newsroom: 8 users, 6 categories, ~320 published posts plus drafts, pending,
 * scheduled, private and trashed posts, pages, ~1,000 comments. Deterministic.
 */

mt_srand( 424242 );

function seed_pick( array $a ) {
	return $a[ mt_rand( 0, count( $a ) - 1 ) ];
}

$users = array();
foreach ( array(
	array( 'eva', 'Eva Editor', 'editor' ),
	array( 'bruno', 'Bruno Costa', 'author' ),
	array( 'chiara', 'Chiara Rossi', 'author' ),
	array( 'dmitri', 'Dmitri Volkov', 'author' ),
	array( 'esme', 'Esmé O’Neill', 'author' ),
	array( 'farid', 'Farid Haddad', 'contributor' ),
	array( 'gina', 'Gina Reader', 'subscriber' ),
	array( 'hugo', 'Hugo New', 'author' ), // No posts yet.
) as $u ) {
	$users[ $u[0] ] = wp_insert_user(
		array(
			'user_login'   => $u[0],
			'user_pass'    => 'password',
			'user_email'   => $u[0] . '@newsroom.example',
			'display_name' => $u[1],
			'role'         => $u[2],
		)
	);
}
$writers = array( 1, $users['eva'], $users['bruno'], $users['bruno'], $users['chiara'], $users['chiara'], $users['dmitri'], $users['esme'], $users['farid'] );

$cats = array();
foreach ( array( 'Politics', 'Business', 'Culture', 'Sport', 'Science', 'Local' ) as $name ) {
	$t      = wp_insert_term( $name, 'category' );
	$cats[] = $t['term_id'];
}

$words = array( 'council', 'budget', 'river', 'stadium', 'museum', 'election', 'harbour', 'school', 'market', 'bridge', 'festival', 'research', 'vaccine', 'tram', 'library', 'mayor', 'strike', 'rain', 'match', 'orchestra', 'garden', 'factory', 'startup', 'rent', 'climate' );

function seed_content( $words ) {
	$paras = array();
	for ( $p = 0, $n = mt_rand( 1, 6 ); $p < $n; $p++ ) {
		$w = array();
		for ( $i = 0, $c = mt_rand( 20, 160 ); $i < $c; $i++ ) {
			$w[] = seed_pick( $words );
		}
		$text = ucfirst( implode( ' ', $w ) ) . '.';
		if ( 0 === mt_rand( 0, 3 ) ) {
			$text = str_replace( ' market ', ' <a href="https://example.org/market">market</a> ', $text );
		}
		$paras[] = "<!-- wp:paragraph -->\n<p>$text</p>\n<!-- /wp:paragraph -->";
	}
	return implode( "\n\n", $paras );
}

$base      = strtotime( '2023-06-01 06:00:00 UTC' );
$published = array();
for ( $n = 1; $n <= 320; $n++ ) {
	$ts = $base + $n * 2 * DAY_IN_SECONDS + mt_rand( 0, 20 * HOUR_IN_SECONDS );
	if ( 0 === $n % 29 ) {
		// Late evening on the last day of a month (UTC) = next month in Berlin.
		$ts = strtotime( gmdate( 'Y-m-t 22:30:00', $ts ) . ' UTC' );
	}
	$gmt  = gmdate( 'Y-m-d H:i:s', $ts );
	$post = array(
		'post_title'    => ucfirst( seed_pick( $words ) ) . ' ' . seed_pick( $words ) . ' report #' . $n,
		'post_content'  => seed_content( $words ),
		'post_status'   => 'publish',
		'post_author'   => seed_pick( $writers ),
		'post_date'     => get_date_from_gmt( $gmt ),
		'post_date_gmt' => $gmt,
		'post_category' => 0 === $n % 6 ? array( seed_pick( $cats ), seed_pick( $cats ) ) : array( seed_pick( $cats ) ),
	);
	if ( 0 === $n % 41 ) {
		$post['post_category'] = array(); // Falls back to Uncategorized.
	}
	$published[] = wp_insert_post( $post );
}

$others = array();
foreach ( array( 'draft' => 15, 'pending' => 5, 'future' => 5, 'private' => 3, 'trash' => 4 ) as $status => $count ) {
	for ( $i = 1; $i <= $count; $i++ ) {
		$gmt      = 'future' === $status ? '2030-0' . $i . '-15 09:00:00' : gmdate( 'Y-m-d H:i:s', $base + 600 * DAY_IN_SECONDS + $i * HOUR_IN_SECONDS );
		$others[] = wp_insert_post(
			array(
				'post_title'    => ucfirst( $status ) . ' piece ' . $i,
				'post_content'  => seed_content( $words ),
				'post_status'   => $status,
				'post_author'   => seed_pick( $writers ),
				'post_date'     => get_date_from_gmt( $gmt ),
				'post_date_gmt' => $gmt,
				'post_category' => array( seed_pick( $cats ) ),
			)
		);
	}
}

wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'about',
		'post_title'   => 'About the Newsroom',
		'post_content' => "<!-- wp:paragraph -->\n<p>We are an independent local newsroom.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[acme_stats]\n<!-- /wp:shortcode -->",
	)
);
wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'my-numbers',
		'post_title'   => 'My numbers',
		'post_content' => "<!-- wp:shortcode -->\n[acme_stats scope=\"me\"]\n<!-- /wp:shortcode -->",
	)
);
for ( $i = 1; $i <= 3; $i++ ) {
	wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Service page ' . $i,
			'post_content' => seed_content( $words ),
		)
	);
}

// Comments.
$targets = array_merge( $published, $published, array_slice( $others, 0, 10 ) );
for ( $i = 1; $i <= 1000; $i++ ) {
	$r        = mt_rand( 1, 100 );
	$approved = $r <= 70 ? '1' : ( $r <= 85 ? '0' : ( $r <= 95 ? 'spam' : 'trash' ) );
	$post_id  = seed_pick( $targets );
	wp_insert_comment(
		array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => 'Reader ' . mt_rand( 1, 200 ),
			'comment_author_email' => 'reader' . mt_rand( 1, 200 ) . '@mail.example',
			'comment_content'      => 'Comment ' . $i . ' about ' . seed_pick( $words ),
			'comment_approved'     => $approved,
			'comment_type'         => 0 === $i % 25 ? 'pingback' : 'comment',
			'comment_date'         => '2025-06-01 12:00:00',
			'comment_date_gmt'     => '2025-06-01 10:00:00',
		)
	);
}

WP_CLI::log( sprintf( 'Seeded %d published posts, %d others.', count( $published ), count( $others ) ) );
