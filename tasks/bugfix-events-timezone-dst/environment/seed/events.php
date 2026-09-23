<?php
/**
 * Events as they exist in production (run with `wp eval-file`).
 *
 * 1.6 events have local strings plus the timestamps 1.6 computed when they were saved (with the
 * UTC offset in effect *on the day they were saved*). 1.0 events only have a local timestamp and
 * a duration.
 */

global $wpdb;

$make = static function ( $slug, $title, $content = '' ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'acme_event',
			'post_status'  => 'publish',
			'post_author'  => 1,
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_content' => $content ? $content : "<!-- wp:paragraph -->\n<p>Join us!</p>\n<!-- /wp:paragraph -->",
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id );
	}
	return $id;
};

$meta = static function ( $id, array $values ) use ( $wpdb ) {
	foreach ( $values as $key => $value ) {
		$wpdb->insert(
			$wpdb->postmeta,
			array(
				'post_id'    => $id,
				'meta_key'   => $key,
				'meta_value' => (string) $value,
			)
		);
	}
};

// 1.6 event: local strings + timestamps computed with the offset of the day it was saved.
$v16 = static function ( $slug, $title, $start, $end, $saved_offset_hours, $all_day = false, $location = '' ) use ( $make, $meta ) {
	$id     = $make( $slug, $title );
	$offset = $saved_offset_hours * HOUR_IN_SECONDS;
	$values = array(
		'_acme_event_start'    => $start,
		'_acme_event_end'      => $end,
		'_acme_event_start_ts' => strtotime( $start . ' UTC' ) - $offset,
		'_acme_event_end_ts'   => strtotime( $end . ' UTC' ) - $offset,
	);
	if ( $all_day ) {
		$values['_acme_event_all_day'] = '1';
	}
	if ( $location ) {
		$values['_acme_event_location'] = $location;
	}
	$meta( $id, $values );
	return $id;
};

$ids = array();

$ids[] = $v16( 'board-meeting', 'Board meeting', '2026-01-20 18:00:00', '2026-01-20 20:00:00', -4, false, 'Room 101' );
$ids[] = $v16( 'spring-hack-night', 'Spring forward hack night', '2026-03-07 22:00:00', '2026-03-08 04:00:00', -5, false, 'Makerspace' );
$ids[] = $v16( 'summer-concert', 'Summer concert', '2026-07-18 19:30:00', '2026-07-18 22:00:00', -5, false, 'Riverside park' );
$ids[] = $v16( 'independence-day-picnic', 'Independence Day picnic', '2026-07-04 00:00:00', '2026-07-04 23:59:59', -4, true, 'Riverside park' );
$ids[] = $v16( 'fall-back-social', 'Fall back social', '2026-11-01 00:30:00', '2026-11-01 03:00:00', -4 );
$ids[] = $v16( 'winter-retreat', 'Winter retreat', '2026-12-28 00:00:00', '2026-12-30 23:59:59', -4, true, 'Lake cabin' );
$ids[] = $v16( 'kickoff-2025', 'Kickoff 2025', '2025-02-01 10:00:00', '2025-02-01 12:00:00', -5, false, 'Main hall' );

// 1.0 events: local timestamp + duration in minutes.
$nye = $make( 'nye-party', "New Year's Eve party" );
$meta(
	$nye,
	array(
		'_acme_event_timestamp' => strtotime( '2026-12-31 22:00:00 UTC' ),
		'_acme_event_duration'  => 240,
		'_acme_event_location'  => 'Rooftop',
	)
);
$founders = $make( 'founders-day-2019', 'Founders day 2019' );
$meta(
	$founders,
	array(
		'_acme_event_timestamp' => strtotime( '2019-05-10 00:00:00 UTC' ),
		'_acme_event_duration'  => 1440,
		'_acme_event_all_day'   => '1',
	)
);

// An event without dates yet.
$make( 'tba-meetup', 'Meetup (date TBA)' );

// "What's on" page with the upcoming events list.
wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'whats-on',
		'post_title'   => "What's on",
		'post_content' => "<!-- wp:shortcode -->\n[acme_upcoming_events limit=\"10\"]\n<!-- /wp:shortcode -->",
	)
);

wp_cache_flush();
WP_CLI::log( 'Seeded ' . ( count( $ids ) + 3 ) . ' events.' );
