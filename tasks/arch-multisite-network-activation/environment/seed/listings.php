<?php
/**
 * Seed listings for one site (wp eval-file seed/listings.php main|south).
 */

use Acme\Directory\Categories;
use Acme\Directory\Listings;

$which = isset( $args[0] ) ? $args[0] : 'main';

$sets = array(
	'main'  => array(
		'categories' => array(
			array( 'Food & drink', 'food' ),
			array( 'Services', 'services' ),
			array( 'Shops', 'shops' ),
		),
		'listings'   => array(
			array( 'Bakery Brot', 'food', 'published', 1 ),
			array( 'Cafe Central', 'food', 'published', 0 ),
			array( 'Harbour Fish Bar', 'food', 'published', 0 ),
			array( 'Plumbing Pros', 'services', 'published', 0 ),
			array( 'Quick Fix Bikes', 'services', 'pending', 0 ),
			array( 'Corner Books', 'shops', 'published', 0 ),
			array( 'Old Toy Store', 'shops', 'expired', 0 ),
			array( 'Garden Centre Green', 'general', 'published', 0 ),
		),
	),
	'south' => array(
		'categories' => array(
			array( 'Beaches', 'beaches' ),
			array( 'Food & drink', 'food' ),
		),
		'listings'   => array(
			array( 'South Beach Surf School', 'beaches', 'published', 1 ),
			array( 'Dune Kiosk', 'food', 'published', 0 ),
			array( 'Lighthouse Diner', 'food', 'published', 0 ),
			array( 'Sandcastle Rentals', 'beaches', 'pending', 0 ),
			array( 'Tidepool Tours', 'network-partners', 'published', 0 ),
		),
	),
);

$set = $sets[ $which ];
foreach ( $set['categories'] as $cat ) {
	Categories::create( $cat[0], $cat[1] );
}
wp_set_current_user( 1 );
foreach ( $set['listings'] as $i => $l ) {
	$category = Categories::get_by_slug( $l[1] );
	Listings::create(
		array(
			'name'        => $l[0],
			'category_id' => $category ? (int) $category->id : 0,
			'description' => $l[0] . ' — a local favourite.',
			'url'         => 'https://' . sanitize_title( $l[0] ) . '.example',
			'phone'       => sprintf( '+44 20 7946 %04d', 100 + $i ),
			'status'      => $l[2],
			'featured'    => $l[3],
		)
	);
}
echo "seeded $which\n";
