<?php
/**
 * Helpers for the Acme Real Estate tests.
 */

namespace WPSB\RealEstate;

const FEATURES = array( 'pool', 'pool-heated', 'garage', 'garden', 'fireplace', 'balcony', 'air-conditioning', 'solar-panels', 'elevator', 'sea-view', 'pets-allowed' );

/** Search combinations (see fixtures/combos.php). */
function combos(): array {
	return require __DIR__ . '/fixtures/combos.php';
}

/** Expected results generated from Acme Real Estate 1.6.2 on the seeded site. */
function expected(): array {
	static $expected = null;
	if ( null === $expected ) {
		$expected = json_decode( file_get_contents( __DIR__ . '/fixtures/search-expected.json' ), true );
	}
	return $expected;
}

/** Listing ID by slug. */
function id( string $slug ): int {
	global $wpdb;
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'acme_listing'", $slug ) );
	if ( ! $id ) {
		throw new \RuntimeException( "Listing $slug not found" );
	}
	return $id;
}

/** Slugs for IDs. */
function slugs( array $ids ): array {
	global $wpdb;
	if ( ! $ids ) {
		return array();
	}
	$ids = array_map( 'intval', $ids );
	$map = $wpdb->get_results( "SELECT ID, post_name FROM {$wpdb->posts} WHERE ID IN (" . implode( ',', $ids ) . ')', OBJECT_K );
	return array_map( static fn( $id ) => isset( $map[ $id ] ) ? $map[ $id ]->post_name : "#$id", $ids );
}

/** Documented argument normalization (see includes/class-search.php). */
function parse_args( array $args ): array {
	$money  = static function ( $v ) {
		$d = is_scalar( $v ) ? preg_replace( '/[^\d]/', '', (string) $v ) : '';
		return '' === $d ? 0 : (int) $d;
	};
	$feats  = $args['features'] ?? array();
	$feats  = is_string( $feats ) ? explode( ',', $feats ) : (array) $feats;
	$feats  = array_values( array_unique( array_intersect( array_map( 'sanitize_key', array_map( 'strval', $feats ) ), FEATURES ) ) );
	$parsed = array(
		'min_price' => $money( $args['min_price'] ?? 0 ),
		'max_price' => $money( $args['max_price'] ?? 0 ),
		'beds'      => max( 0, min( 10, (int) ( $args['beds'] ?? 0 ) ) ),
		'city'      => trim( sanitize_text_field( (string) ( $args['city'] ?? '' ) ) ),
		'features'  => $feats,
		'status'    => in_array( $args['status'] ?? 'active', array( 'active', 'for-sale', 'pending', 'sold', 'all' ), true ) ? ( $args['status'] ?? 'active' ) : 'active',
		'sort'      => in_array( $args['sort'] ?? 'newest', array( 'newest', 'price_asc', 'price_desc', 'beds_desc' ), true ) ? ( $args['sort'] ?? 'newest' ) : 'newest',
		'page'      => 1,
		'per_page'  => -1,
	);
	if ( $parsed['max_price'] && $parsed['min_price'] > $parsed['max_price'] ) {
		list( $parsed['min_price'], $parsed['max_price'] ) = array( $parsed['max_price'], $parsed['min_price'] );
	}
	return apply_filters( 'acme_re_search_args', $parsed, $args );
}

/** Stored features => slugs (both storage formats, case-insensitive). */
function normalize_features( $value ): array {
	if ( ! is_array( $value ) ) {
		return array();
	}
	$out = array();
	foreach ( $value as $k => $v ) {
		$slug = sanitize_key( is_string( $k ) ? $k : ( is_scalar( $v ) ? (string) $v : '' ) );
		if ( '' !== $slug ) {
			$out[ $slug ] = true;
		}
	}
	return array_keys( $out );
}

/**
 * Reference model of the search, straight from post meta (slow, test-only).
 *
 * @return string[] Slugs in result order.
 */
function brute_force_search( array $raw ): array {
	global $wpdb;
	$args     = parse_args( $raw );
	$statuses = array(
		'active'   => array( 'for-sale', 'pending' ),
		'for-sale' => array( 'for-sale' ),
		'pending'  => array( 'pending' ),
		'sold'     => array( 'sold' ),
		'all'      => array( 'for-sale', 'pending', 'sold' ),
	)[ $args['status'] ];

	$posts = $wpdb->get_results( "SELECT ID, post_name, post_date FROM {$wpdb->posts} WHERE post_type = 'acme_listing' AND post_status = 'publish'" );
	$rows  = array();
	foreach ( $posts as $p ) {
		$meta = get_post_meta( (int) $p->ID );
		$one  = static fn( $key ) => isset( $meta[ $key ][0] ) ? $meta[ $key ][0] : null;

		$status = $one( '_acme_status' );
		if ( null === $status || ! in_array( $status, $statuses, true ) ) {
			continue;
		}
		$price_raw = $one( '_acme_price' );
		$price     = (int) $price_raw;
		if ( $args['min_price'] || $args['max_price'] ) {
			if ( null === $price_raw || $price < max( 1, $args['min_price'] ) ) {
				continue;
			}
			if ( $args['max_price'] && $price > $args['max_price'] ) {
				continue;
			}
		}
		$beds_raw = $one( '_acme_bedrooms' );
		if ( $args['beds'] && ( null === $beds_raw || (int) $beds_raw < $args['beds'] ) ) {
			continue;
		}
		if ( '' !== $args['city'] && $one( '_acme_city' ) !== $args['city'] ) {
			continue;
		}
		if ( $args['features'] ) {
			$have = normalize_features( maybe_unserialize( (string) $one( '_acme_features' ) ) );
			if ( array_diff( $args['features'], $have ) ) {
				continue;
			}
		}
		if ( in_array( $args['sort'], array( 'price_asc', 'price_desc' ), true ) && null === $price_raw ) {
			continue;
		}
		if ( 'beds_desc' === $args['sort'] && null === $beds_raw ) {
			continue;
		}
		$rows[] = array(
			'id'    => (int) $p->ID,
			'slug'  => $p->post_name,
			'date'  => $p->post_date,
			'price' => $price,
			'beds'  => (int) $beds_raw,
		);
	}

	usort(
		$rows,
		static function ( $a, $b ) use ( $args ) {
			$primary = 0;
			if ( 'price_asc' === $args['sort'] ) {
				$primary = $a['price'] <=> $b['price'];
			} elseif ( 'price_desc' === $args['sort'] ) {
				$primary = $b['price'] <=> $a['price'];
			} elseif ( 'beds_desc' === $args['sort'] ) {
				$primary = $b['beds'] <=> $a['beds'];
			}
			return $primary ?: ( strcmp( $b['date'], $a['date'] ) ?: $b['id'] <=> $a['id'] );
		}
	);
	return array_column( $rows, 'slug' );
}

/** Queries touching post meta. */
function postmeta_queries( array $queries ): array {
	global $wpdb;
	return array_values( array_filter( $queries, static fn( $q ) => false !== stripos( $q, $wpdb->postmeta ) ) );
}
