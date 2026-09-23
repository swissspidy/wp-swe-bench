<?php
/**
 * Listing search, shared by the search form, the REST API and WP-CLI.
 *
 * Search arguments (all optional):
 * - min_price, max_price  USD (ints; "$250,000" is accepted). As soon as either is set,
 *                         listings with the price on request (0) are left out.
 * - beds                  minimum number of bedrooms (listings without a bedroom count are left out).
 * - city                  exact city name.
 * - features              slugs (array or comma separated); a listing must have ALL of them.
 *                         Unknown slugs are ignored.
 * - status                'active' (default: for sale + pending), 'for-sale', 'pending', 'sold', 'all'.
 * - sort                  'newest' (default), 'price_asc', 'price_desc', 'beds_desc'.
 *                         Ties are broken by date (newest first), then by ID (highest first).
 *                         'beds_desc' only lists listings that have a bedroom count.
 * - page, per_page        1-based page; per_page -1 = everything.
 *
 * Only published listings are ever returned.
 *
 * @package Acme\RealEstate
 */

namespace Acme\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Search.
 */
class Search {

	const DEFAULT_PER_PAGE = 12;

	/**
	 * Allowed sort orders.
	 *
	 * @return array<string, string>
	 */
	public static function sorts() {
		return array(
			'newest'     => __( 'Newest first', 'acme-real-estate' ),
			'price_asc'  => __( 'Price: low to high', 'acme-real-estate' ),
			'price_desc' => __( 'Price: high to low', 'acme-real-estate' ),
			'beds_desc'  => __( 'Most bedrooms', 'acme-real-estate' ),
		);
	}

	/**
	 * Allowed status filters => listing statuses.
	 *
	 * @return array<string, string[]>
	 */
	public static function status_filters() {
		return array(
			'active'   => array( 'for-sale', 'pending' ),
			'for-sale' => array( 'for-sale' ),
			'pending'  => array( 'pending' ),
			'sold'     => array( 'sold' ),
			'all'      => array( 'for-sale', 'pending', 'sold' ),
		);
	}

	/**
	 * Normalize raw search arguments.
	 *
	 * @param array $args Raw arguments (form, REST or CLI).
	 * @return array
	 */
	public static function parse_args( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'min_price' => 0,
				'max_price' => 0,
				'beds'      => 0,
				'city'      => '',
				'features'  => array(),
				'status'    => 'active',
				'sort'      => 'newest',
				'page'      => 1,
				'per_page'  => self::DEFAULT_PER_PAGE,
			)
		);

		$parsed = array(
			'min_price' => self::money( $args['min_price'] ),
			'max_price' => self::money( $args['max_price'] ),
			'beds'      => max( 0, min( 10, (int) $args['beds'] ) ),
			'city'      => trim( sanitize_text_field( (string) $args['city'] ) ),
			'features'  => Features::sanitize_list( $args['features'] ),
			'status'    => isset( self::status_filters()[ $args['status'] ] ) ? (string) $args['status'] : 'active',
			'sort'      => isset( self::sorts()[ $args['sort'] ] ) ? (string) $args['sort'] : 'newest',
			'page'      => max( 1, (int) $args['page'] ),
			'per_page'  => (int) $args['per_page'],
		);

		if ( $parsed['max_price'] && $parsed['min_price'] > $parsed['max_price'] ) {
			list( $parsed['min_price'], $parsed['max_price'] ) = array( $parsed['max_price'], $parsed['min_price'] );
		}
		if ( -1 !== $parsed['per_page'] ) {
			$parsed['per_page'] = max( 1, min( 100, $parsed['per_page'] ) );
		}

		/**
		 * Filters the normalized search arguments (e.g. city aliases).
		 *
		 * @param array $parsed Normalized arguments.
		 * @param array $args   Raw arguments.
		 */
		return apply_filters( 'acme_re_search_args', $parsed, $args );
	}

	/**
	 * "$250,000" => 250000.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	private static function money( $value ) {
		if ( ! is_scalar( $value ) ) {
			return 0;
		}
		$digits = preg_replace( '/[^\d]/', '', (string) $value );
		return '' === $digits ? 0 : (int) $digits;
	}

	/**
	 * WP_Query arguments for parsed search arguments.
	 *
	 * @param array $args Parsed arguments.
	 * @return array
	 */
	public static function query_args( array $args ) {
		$meta = array(
			'relation' => 'AND',
			'status'   => array(
				'key'     => Listing::META_STATUS,
				'value'   => self::status_filters()[ $args['status'] ],
				'compare' => 'IN',
			),
		);

		if ( $args['min_price'] || $args['max_price'] ) {
			$meta['price_min'] = array(
				'key'     => Listing::META_PRICE,
				'value'   => max( 1, $args['min_price'] ),
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
			if ( $args['max_price'] ) {
				$meta['price_max'] = array(
					'key'     => Listing::META_PRICE,
					'value'   => $args['max_price'],
					'compare' => '<=',
					'type'    => 'NUMERIC',
				);
			}
		}

		if ( $args['beds'] ) {
			$meta['beds'] = array(
				'key'     => Listing::META_BEDROOMS,
				'value'   => $args['beds'],
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		}

		if ( '' !== $args['city'] ) {
			$meta['city'] = array(
				'key'   => Listing::META_CITY,
				'value' => $args['city'],
			);
		}

		// Features are stored serialized: match the quoted slug.
		foreach ( $args['features'] as $i => $feature ) {
			$meta[ 'feature_' . $i ] = array(
				'key'     => Listing::META_FEATURES,
				'value'   => '"' . $feature . '"',
				'compare' => 'LIKE',
			);
		}

		$orderby = array(
			'date' => 'DESC',
			'ID'   => 'DESC',
		);
		switch ( $args['sort'] ) {
			case 'price_asc':
			case 'price_desc':
				$meta['price_sort'] = array(
					'key'     => Listing::META_PRICE,
					'compare' => 'EXISTS',
					'type'    => 'NUMERIC',
				);
				$orderby            = array(
					'price_sort' => 'price_asc' === $args['sort'] ? 'ASC' : 'DESC',
					'date'       => 'DESC',
					'ID'         => 'DESC',
				);
				break;
			case 'beds_desc':
				$meta['beds_sort'] = array(
					'key'     => Listing::META_BEDROOMS,
					'compare' => 'EXISTS',
					'type'    => 'NUMERIC',
				);
				$orderby           = array(
					'beds_sort' => 'DESC',
					'date'      => 'DESC',
					'ID'        => 'DESC',
				);
				break;
		}

		return array(
			'post_type'           => Listing::POST_TYPE,
			'post_status'         => 'publish',
			'meta_query'          => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'orderby'             => $orderby,
			'posts_per_page'      => $args['per_page'],
			'paged'               => $args['page'],
			'fields'              => 'ids',
			'ignore_sticky_posts' => true,
		);
	}

	/**
	 * Run a search.
	 *
	 * @param array $args Raw search arguments.
	 * @return array{ids: int[], total: int, pages: int, page: int, per_page: int, args: array}
	 */
	public static function run( array $args ) {
		$args  = self::parse_args( $args );
		$query = new \WP_Query( self::query_args( $args ) );
		$total = (int) $query->found_posts;

		return array(
			'ids'      => array_map( 'intval', $query->posts ),
			'total'    => $total,
			'pages'    => -1 === $args['per_page'] ? 1 : (int) ceil( $total / $args['per_page'] ),
			'page'     => $args['page'],
			'per_page' => $args['per_page'],
			'args'     => $args,
		);
	}
}
