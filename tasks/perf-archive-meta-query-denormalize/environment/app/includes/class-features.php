<?php
/**
 * Feature catalog.
 *
 * @package Acme\RealEstate
 */

namespace Acme\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * The amenities a listing can have.
 */
class Features {

	/**
	 * All features: slug => label.
	 *
	 * @return array<string, string>
	 */
	public static function all() {
		$features = array(
			'pool'             => __( 'Pool', 'acme-real-estate' ),
			'pool-heated'      => __( 'Heated pool', 'acme-real-estate' ),
			'garage'           => __( 'Garage', 'acme-real-estate' ),
			'garden'           => __( 'Garden', 'acme-real-estate' ),
			'fireplace'        => __( 'Fireplace', 'acme-real-estate' ),
			'balcony'          => __( 'Balcony', 'acme-real-estate' ),
			'air-conditioning' => __( 'Air conditioning', 'acme-real-estate' ),
			'solar-panels'     => __( 'Solar panels', 'acme-real-estate' ),
			'elevator'         => __( 'Elevator', 'acme-real-estate' ),
			'sea-view'         => __( 'Sea view', 'acme-real-estate' ),
			'pets-allowed'     => __( 'Pets allowed', 'acme-real-estate' ),
		);

		/**
		 * Filters the feature catalog.
		 *
		 * @param array<string, string> $features slug => label.
		 */
		return apply_filters( 'acme_re_features', $features );
	}

	/**
	 * Normalize a stored `_acme_features` value to a list of slugs.
	 *
	 * Formats:
	 * - 1.2+: list of slugs, array( 'pool', 'garage' ).
	 * - 1.0/1.1 (old importer): map of slug => 'yes', array( 'pool' => 'yes' ).
	 * - Missing / empty string: no features.
	 *
	 * Slugs are lower-cased (the old importer kept the spelling of the CSV).
	 *
	 * @param mixed $value Stored value.
	 * @return string[]
	 */
	public static function normalize( $value ) {
		if ( ! is_array( $value ) || ! $value ) {
			return array();
		}
		$slugs = array();
		foreach ( $value as $key => $item ) {
			$slug = is_string( $key ) ? $key : ( is_scalar( $item ) ? (string) $item : '' );
			$slug = sanitize_key( $slug );
			if ( '' !== $slug ) {
				$slugs[ $slug ] = true;
			}
		}
		return array_keys( $slugs );
	}

	/**
	 * Keep only known feature slugs.
	 *
	 * @param mixed $slugs Slugs (array or comma separated string).
	 * @return string[]
	 */
	public static function sanitize_list( $slugs ) {
		if ( is_string( $slugs ) ) {
			$slugs = explode( ',', $slugs );
		}
		$slugs = array_map( 'sanitize_key', array_map( 'strval', (array) $slugs ) );
		return array_values( array_unique( array_intersect( $slugs, array_keys( self::all() ) ) ) );
	}
}
