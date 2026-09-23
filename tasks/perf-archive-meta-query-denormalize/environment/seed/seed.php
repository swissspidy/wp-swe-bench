<?php
/**
 * Seeds the production-like listing inventory (run with `wp eval-file`).
 * Deterministic: the hidden tests' fixtures were generated from this data.
 */

use Acme\RealEstate\Listing;

mt_srand( 4242 );

$cities   = array( 'Springfield', 'Springfield', 'Springfield', 'Shelbyville', 'Shelbyville', 'Capital City', 'Ogdenville', 'North Haverbrook', 'New York', 'New York', 'San Francisco', "Coeur d'Alene" );
$features = array( 'pool', 'pool-heated', 'garage', 'garden', 'fireplace', 'balcony', 'air-conditioning', 'solar-panels', 'elevator', 'sea-view', 'pets-allowed' );
$types    = array( 'house', 'apartment', 'townhouse', 'loft', 'cottage', 'villa' );

/**
 * Random pick.
 *
 * @param array $list List.
 * @return mixed
 */
function wpsb_re_pick( array $list ) {
	return $list[ mt_rand( 0, count( $list ) - 1 ) ];
}

$base_time = strtotime( '2023-01-02 09:00:00 UTC' );
$prev_date = null;

for ( $n = 1; $n <= 430; $n++ ) {
	$roll = mt_rand( 1, 100 );

	if ( $n > 400 ) {
		$post_status = array( 'draft', 'private', 'trash', 'future', 'pending' )[ $n % 5 ];
	} else {
		$post_status = 'publish';
	}

	$beds = mt_rand( 0, 6 );
	$land = mt_rand( 1, 100 ) <= 5;
	$city = wpsb_re_pick( $cities );
	$type = $land ? 'lot' : wpsb_re_pick( $types );

	// Listing dates: some share the exact same timestamp (bulk imports).
	if ( $prev_date && mt_rand( 1, 100 ) <= 6 ) {
		$date = $prev_date;
	} else {
		$date = gmdate( 'Y-m-d H:i:s', $base_time + mt_rand( 0, 3 * 365 ) * DAY_IN_SECONDS + mt_rand( 0, 86399 ) );
	}
	if ( 'future' === $post_status ) {
		$date = '2031-06-01 12:00:00';
	}
	$prev_date = $date;

	$title = $land ? sprintf( 'Building lot in %s', $city ) : sprintf( '%d-bedroom %s in %s', $beds, $type, $city );

	$id = wp_insert_post(
		array(
			'post_type'     => Listing::POST_TYPE,
			'post_status'   => $post_status,
			'post_title'    => $title,
			'post_name'     => sprintf( 'listing-%04d', $n ),
			'post_date'     => $date,
			'post_date_gmt' => $date,
			'post_content'  => 'Lovely property. Call us for a viewing.',
			'post_author'   => 1,
		)
	);
	if ( 'trash' === $post_status ) {
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'trash',
			)
		);
	}

	// Price: ~5% on request, a few legacy decimal strings, some identical prices.
	$p = mt_rand( 1, 100 );
	if ( $p <= 5 ) {
		$price = 0 === $n % 2 ? '0' : '';
	} elseif ( $p <= 8 ) {
		$price = number_format( mt_rand( 150, 900 ) * 1000, 2, '.', '' );
	} elseif ( $p <= 14 ) {
		$price = (string) wpsb_re_pick( array( 250000, 499000, 750000 ) );
	} else {
		$price = (string) ( mt_rand( 90, 2500 ) * 1000 );
	}
	add_post_meta( $id, Listing::META_PRICE, $price );

	if ( ! $land ) {
		add_post_meta( $id, Listing::META_BEDROOMS, (string) $beds );
		add_post_meta( $id, Listing::META_BATHROOMS, (string) max( 1, (int) ceil( $beds / 2 ) ) );
		add_post_meta( $id, Listing::META_SQFT, (string) ( 400 + $beds * mt_rand( 250, 450 ) ) );
	}
	add_post_meta( $id, Listing::META_CITY, $city );
	add_post_meta( $id, Listing::META_STATUS, wpsb_re_pick( array( 'for-sale', 'for-sale', 'for-sale', 'for-sale', 'for-sale', 'pending', 'sold', 'sold' ) ) );

	// Features in the formats that exist in production.
	$pick = array();
	foreach ( $features as $f ) {
		if ( mt_rand( 1, 100 ) <= 30 ) {
			$pick[] = $f;
		}
	}
	$f = mt_rand( 1, 100 );
	if ( $f <= 4 ) {
		// No features at all (sometimes stored as an empty string).
		add_post_meta( $id, Listing::META_FEATURES, 0 === $n % 2 ? array() : '' );
	} elseif ( $f <= 20 ) {
		// 1.0/1.1 importer: slug => 'yes', spelled like in the agency CSV.
		$map = array();
		foreach ( $pick as $slug ) {
			$map[ 0 === $n % 3 ? ucfirst( $slug ) : $slug ] = 'yes';
		}
		add_post_meta( $id, Listing::META_FEATURES, $map );
	} elseif ( $f <= 24 && $pick ) {
		// Hand-edited by the CRM sync: capitalized slugs.
		add_post_meta( $id, Listing::META_FEATURES, array_map( 'ucfirst', $pick ) );
	} else {
		add_post_meta( $id, Listing::META_FEATURES, $pick );
	}

	if ( 0 === $n % 50 ) {
		add_post_meta( $id, '_acme_ref', 'MLS-' . ( 100000 + $n ) );
	}
}

WP_CLI::log( 'Seeded listings.' );
