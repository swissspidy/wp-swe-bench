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
 * Since 1.7.0 the search runs against the search index (see Index) instead of
 * post meta queries.
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
	 * SQL for parsed search arguments, against the search index (see Index).
	 *
	 * @param array $args Parsed arguments.
	 * @return array{from: string, where: string, order: string}
	 */
	public static function sql( array $args ) {
		global $wpdb;
		$index    = Index::table();
		$features = Index::features_table();

		$statuses = self::status_filters()[ $args['status'] ];
		$where    = array(
			$wpdb->prepare( 'p.post_type = %s', Listing::POST_TYPE ),
			"p.post_status = 'publish'",
			$wpdb->prepare( 'i.status IN (' . implode( ', ', array_fill( 0, count( $statuses ), '%s' ) ) . ')', $statuses ), // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQL.NotPrepared
		);

		if ( $args['min_price'] || $args['max_price'] ) {
			$where[] = $wpdb->prepare( 'i.price >= %d', max( 1, $args['min_price'] ) );
			if ( $args['max_price'] ) {
				$where[] = $wpdb->prepare( 'i.price <= %d', $args['max_price'] );
			}
		}
		if ( $args['beds'] ) {
			$where[] = $wpdb->prepare( 'i.bedrooms >= %d', $args['beds'] );
		}
		if ( '' !== $args['city'] ) {
			$where[] = $wpdb->prepare( 'i.city = %s', $args['city'] );
		}
		if ( $args['features'] ) {
			$in      = implode( ', ', array_fill( 0, count( $args['features'] ), '%s' ) );
			$where[] = $wpdb->prepare( "p.ID IN ( SELECT f.post_id FROM {$features} f WHERE f.feature IN ({$in}) GROUP BY f.post_id HAVING COUNT(*) = %d )", array_merge( $args['features'], array( count( $args['features'] ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		switch ( $args['sort'] ) {
			case 'price_asc':
				$where[] = 'i.price IS NOT NULL';
				$order   = 'i.price ASC, p.post_date DESC, p.ID DESC';
				break;
			case 'price_desc':
				$where[] = 'i.price IS NOT NULL';
				$order   = 'i.price DESC, p.post_date DESC, p.ID DESC';
				break;
			case 'beds_desc':
				$where[] = 'i.bedrooms IS NOT NULL';
				$order   = 'i.bedrooms DESC, p.post_date DESC, p.ID DESC';
				break;
			default:
				$order = 'p.post_date DESC, p.ID DESC';
		}

		return array(
			'from'  => "{$wpdb->posts} p INNER JOIN {$index} i ON i.post_id = p.ID",
			'where' => implode( ' AND ', $where ),
			'order' => $order,
		);
	}

	/**
	 * Run a search.
	 *
	 * @param array $args Raw search arguments.
	 * @return array{ids: int[], total: int, pages: int, page: int, per_page: int, args: array}
	 */
	public static function run( array $args ) {
		global $wpdb;
		$args = self::parse_args( $args );
		$sql  = self::sql( $args );

		$select = "SELECT p.ID FROM {$sql['from']} WHERE {$sql['where']} ORDER BY {$sql['order']}";
		if ( -1 !== $args['per_page'] ) {
			$select .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $args['per_page'], ( $args['page'] - 1 ) * $args['per_page'] );
		}
		$ids = array_map( 'intval', $wpdb->get_col( $select ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery

		if ( -1 === $args['per_page'] || ( 1 === $args['page'] && count( $ids ) < $args['per_page'] ) ) {
			$total = count( $ids );
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sql['from']} WHERE {$sql['where']}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		}

		// Load the posts and meta of this page in two queries instead of two per listing.
		if ( $ids ) {
			_prime_post_caches( $ids, false, true );
		}

		return array(
			'ids'      => $ids,
			'total'    => $total,
			'pages'    => -1 === $args['per_page'] ? 1 : (int) ceil( $total / $args['per_page'] ),
			'page'     => $args['page'],
			'per_page' => $args['per_page'],
			'args'     => $args,
		);
	}
}
