<?php
/**
 * Seed rooms, bookings (incl. 1.0/1.1-era rows) and the widget page.
 */

use Acme\Bookings\Repository;

$room = static function ( $slug, $title, $capacity, $rate, $status = 'publish' ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'acme_room',
			'post_status'  => $status,
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_content' => '<!-- wp:paragraph --><p>' . $title . ' with a view.</p><!-- /wp:paragraph -->',
			'post_author'  => 1,
		)
	);
	update_post_meta( $id, 'acme_capacity', $capacity );
	update_post_meta( $id, 'acme_rate', $rate );
	return $id;
};

$garden = $room( 'garden-room', 'Garden Room', 2, '129.00' );
$lake   = $room( 'lake-suite', 'Lake Suite', 4, '245.50' );
$attic  = $room( 'attic-single', 'Attic Single', 1, '79,00' ); // 1.0 stored rates with a comma.
$tower  = $room( 'tower-room', 'Tower Room (coming soon)', 2, '199.00', 'draft' );

$alice = get_user_by( 'login', 'alice' )->ID;
$bob   = get_user_by( 'login', 'bob' )->ID;

global $wpdb;
$table = Repository::table();
$n     = 0;
$row   = static function ( array $data ) use ( $wpdb, $table, &$n ) {
	$n++;
	$data += array(
		'guests'      => 1,
		'notes'       => '',
		'admin_notes' => '',
		'created_at'  => gmdate( 'Y-m-d H:i:s', strtotime( '2026-06-01 10:00:00 UTC' ) + $n * 3600 ),
	);
	$wpdb->insert( $table, $data );
	return $wpdb->insert_id;
};

// Hand-picked rows (see notes).
$row( array( 'room_id' => $garden, 'customer_id' => $alice, 'start_date' => '2026-11-02 14:00:00', 'end_date' => '2026-11-04 11:00:00', 'status' => 'confirmed', 'guests' => 2, 'total' => '258.00', 'notes' => 'Anniversary trip', 'admin_notes' => 'VIP - upgrade if possible' ) );
$row( array( 'room_id' => $lake, 'customer_id' => $alice, 'start_date' => '2026-12-20 14:00:00', 'end_date' => '2026-12-27 11:00:00', 'status' => 'approved', 'guests' => 3, 'total' => null, 'notes' => 'seed:legacy-approved' ) );
$row( array( 'room_id' => $garden, 'customer_id' => $bob, 'start_date' => '2026-11-10 14:00:00', 'end_date' => '2026-11-12 11:00:00', 'status' => 'canceled', 'guests' => 1, 'total' => '258.00', 'notes' => 'seed:legacy-canceled' ) );
$row( array( 'room_id' => $attic, 'customer_id' => $bob, 'start_date' => '2026-11-05 14:00:00', 'end_date' => '2026-11-06 11:00:00', 'status' => 'pending', 'guests' => 1, 'total' => '79.00', 'notes' => 'Late arrival' ) );
$row( array( 'room_id' => $garden, 'customer_id' => $bob, 'start_date' => '2026-11-20 14:00:00', 'end_date' => '2026-11-23 11:00:00', 'status' => 'pending', 'guests' => 2, 'total' => '387.00', 'notes' => 'seed:garden-late-november' ) );

// Twenty more over the winter.
$statuses = array( 'pending', 'confirmed', 'cancelled', 'confirmed' );
for ( $i = 0; $i < 20; $i++ ) {
	$room_id = $i % 2 ? $lake : $attic;
	$start   = strtotime( '2027-01-01 14:00:00 UTC' ) + $i * 7 * DAY_IN_SECONDS;
	$end     = $start + 2 * DAY_IN_SECONDS - 3 * HOUR_IN_SECONDS;
	$row(
		array(
			'room_id'     => $room_id,
			'customer_id' => 0 === $i % 3 ? $alice : $bob,
			'start_date'  => gmdate( 'Y-m-d H:i:s', $start ),
			'end_date'    => gmdate( 'Y-m-d H:i:s', $end ),
			'status'      => $statuses[ $i % 4 ],
			'guests'      => 1,
			'total'       => number_format( 2 * ( $i % 2 ? 245.5 : 79 ), 2, '.', '' ),
			'notes'       => 'seed:winter-' . $i,
		)
	);
}

wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'book-garden-room',
		'post_title'   => 'Book the Garden Room',
		'post_content' => "<!-- wp:paragraph --><p>Our quietest room, right by the garden.</p><!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->[acme_availability room=\"{$garden}\"]<!-- /wp:shortcode -->",
		'post_author'  => 1,
	)
);

echo "seeded rooms {$garden},{$lake},{$attic},{$tower} and {$n} bookings\n";
