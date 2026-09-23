<?php
/**
 * Seed events (run with `wp eval-file`). Local times are Pacific/Auckland.
 *
 * Legacy formats on purpose:
 * - no _acme_event_end at all (created with 1.0-1.2),
 * - _acme_event_end = 0 (1.3 CSV importer),
 * - _acme_event_status = "canceled" (1.0 spelling), or no status at all.
 */

use function Acme\Events\local_to_timestamp;

$events = array(
	// slug, title, start (local), end (local|null|0), all_day, status (null = none), venue, post_status.
	array( 'winter-festival', 'Winter festival', '2031-06-13 10:00', '2031-06-16 22:00', false, 'scheduled', 'Civic Square', 'publish' ),
	array( 'last-weeks-workshop', 'Last week\'s workshop', '2031-06-08 10:00', '2031-06-08 12:00', false, 'scheduled', 'Studio 2', 'publish' ),
	array( 'morning-yoga', 'Morning yoga', '2031-06-15 07:00', '2031-06-15 08:00', false, null, 'Domain', 'publish' ),
	array( 'sunday-market', 'Sunday market', '2031-06-15 09:00', null, false, null, 'Britomart', 'publish' ),
	array( 'afternoon-talk', 'Afternoon talk', '2031-06-15 13:00', 0, false, 'scheduled', 'Library', 'publish' ),
	array( 'late-show', 'Late show', '2031-06-15 22:00', '2031-06-15 23:30', false, 'scheduled', 'Civic Theatre', 'publish' ),
	array( 'book-club', 'Book club', '2031-06-17 18:00', null, false, 'canceled', 'Library', 'publish' ),
	array( 'board-games-night', 'Board games night', '2031-06-18 19:00', null, false, null, 'The Hub', 'publish' ),
	array( 'wine-tasting', 'Wine tasting', '2031-06-19 17:00', '2031-06-19 19:00', false, 'cancelled', 'Cellar', 'publish' ),
	array( 'winter-meetup', 'Winter meetup', '2031-06-20 18:00', '2031-06-20 20:00', false, 'scheduled', 'The Hub', 'publish' ),
	array( 'harbour-cleanup', 'Harbour cleanup', '2031-06-22 00:00', 0, true, 'scheduled', 'Viaduct', 'publish' ),
	array( 'secret-planning', 'Secret planning session', '2031-06-25 10:00', '2031-06-25 11:00', false, 'scheduled', '', 'draft' ),
	array( 'kayak-trip', 'Kayak trip', '2031-07-02 09:00', '2031-07-02 15:00', false, 'postponed', 'Okahu Bay', 'publish' ),
	array( 'spring-conference', 'Spring conference', '2031-09-10 09:00', '2031-09-10 17:00', false, 'scheduled', 'Aotea Centre', 'publish' ),
);

$i = 0;
foreach ( $events as $e ) {
	list( $slug, $title, $start, $end, $all_day, $status, $venue, $post_status ) = $e;
	// Publish dates in an order unrelated to the event dates.
	$published = gmdate( 'Y-m-d H:i:s', strtotime( '2024-01-01 00:00:00 UTC' ) + ( ( $i * 7919 ) % 97 ) * DAY_IN_SECONDS );
	++$i;
	$id = wp_insert_post(
		array(
			'post_type'     => 'acme_event',
			'post_status'   => $post_status,
			'post_name'     => $slug,
			'post_title'    => $title,
			'post_content'  => '<!-- wp:paragraph --><p>' . esc_html( $title ) . ' — details follow.</p><!-- /wp:paragraph -->',
			'post_date_gmt' => $published,
			'post_date'     => get_date_from_gmt( $published ),
			'post_author'   => 1,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id );
	}
	// Raw meta writes, like the old versions did.
	add_post_meta( $id, '_acme_event_start', (string) local_to_timestamp( $start ) );
	if ( is_string( $end ) ) {
		add_post_meta( $id, '_acme_event_end', (string) local_to_timestamp( $end ) );
	} elseif ( 0 === $end ) {
		add_post_meta( $id, '_acme_event_end', '0' );
	}
	if ( $all_day ) {
		add_post_meta( $id, '_acme_event_all_day', '1' );
	}
	if ( null !== $status ) {
		add_post_meta( $id, '_acme_event_status', $status );
	}
	if ( '' !== $venue ) {
		add_post_meta( $id, '_acme_event_venue', $venue );
	}
}
WP_CLI::log( 'Seeded ' . count( $events ) . ' events.' );
