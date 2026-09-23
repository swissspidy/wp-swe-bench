<?php
/**
 * Data access for the leads table.
 *
 * Dates are stored in UTC (`Y-m-d H:i:s`).
 *
 * @package Acme\Leads
 */

namespace Acme\Leads;

defined( 'ABSPATH' ) || exit;

/**
 * Leads repository.
 */
class Repository {

	/**
	 * Lead statuses (pipeline order).
	 */
	const STATUSES = array( 'new', 'contacted', 'qualified', 'won', 'lost' );

	/**
	 * Lead sources.
	 */
	const SOURCES = array( 'form', 'import', 'event', 'phone' );

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'acme_leads';
	}

	/**
	 * Find one lead.
	 *
	 * @param int $id Lead ID.
	 * @return array|null Row.
	 */
	public static function find( $id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? $row : null;
	}

	/**
	 * Query leads (admin screen, dashboard, REST v1).
	 *
	 * @param array $args {
	 *     @type string $status   Status filter.
	 *     @type int    $owner    Owner filter (user ID).
	 *     @type string $orderby  Column to order by. Default created_at.
	 *     @type string $order    ASC|DESC. Default DESC.
	 *     @type int    $per_page Page size. Default 20.
	 *     @type int    $page     Page (1-based). Default 1.
	 * }
	 * @return array{items: array[], total: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'status'   => '',
				'owner'    => 0,
				'orderby'  => 'created_at',
				'order'    => 'DESC',
				'per_page' => 20,
				'page'     => 1,
			)
		);

		$table  = self::table();
		$where  = array( '1=1' );
		$params = array();
		if ( '' !== $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( $args['owner'] ) {
			$where[]  = 'owner_id = %d';
			$params[] = (int) $args['owner'];
		}
		$where_sql = implode( ' AND ', $where );
		if ( $params ) {
			$where_sql = $wpdb->prepare( $where_sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;
		$order    = strtoupper( $args['order'] );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$args['orderby']} {$order} LIMIT {$per_page} OFFSET {$offset}", ARRAY_A );
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" );
		// phpcs:enable

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Number of leads per status.
	 *
	 * @return array<string, int>
	 */
	public static function count_by_status() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows   = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status", ARRAY_A );
		$counts = array_fill_keys( self::STATUSES, 0 );
		foreach ( (array) $rows as $row ) {
			$counts[ $row['status'] ] = (int) $row['n'];
		}
		return $counts;
	}

	/**
	 * Insert a lead.
	 *
	 * @param array $data Name, email, company, source, notes, owner_id, score.
	 * @return int|false New ID.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$now  = current_time( 'mysql', true );
		$data = array(
			'name'       => sanitize_text_field( $data['name'] ?? '' ),
			'email'      => sanitize_email( $data['email'] ?? '' ),
			'company'    => sanitize_text_field( $data['company'] ?? '' ),
			'status'     => in_array( $data['status'] ?? 'new', self::STATUSES, true ) ? ( $data['status'] ?? 'new' ) : 'new',
			'source'     => in_array( $data['source'] ?? 'form', self::SOURCES, true ) ? ( $data['source'] ?? 'form' ) : 'form',
			'score'      => (int) ( $data['score'] ?? 0 ),
			'owner_id'   => (int) ( $data['owner_id'] ?? 0 ),
			'notes'      => sanitize_textarea_field( $data['notes'] ?? '' ),
			'created_at' => $data['created_at'] ?? $now,
			'updated_at' => $data['updated_at'] ?? $now,
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $wpdb->insert( self::table(), $data ) ) {
			return false;
		}
		$id = (int) $wpdb->insert_id;

		/**
		 * Fires after a lead was created.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $id   Lead ID.
		 * @param array $data Lead data.
		 */
		do_action( 'acme_leads_created', $id, $data );

		return $id;
	}

	/**
	 * Change a lead's status.
	 *
	 * @param int    $id     Lead ID.
	 * @param string $status New status.
	 * @return bool Whether the status changed.
	 */
	public static function update_status( $id, $status ) {
		global $wpdb;
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}
		$lead = self::find( $id );
		if ( ! $lead || $lead['status'] === $status ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			self::table(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id )
		);

		/**
		 * Fires when a lead's status changed.
		 *
		 * Notifications (won leads) and the CRM sync listen to this.
		 *
		 * @since 1.2.0
		 *
		 * @param int    $id         Lead ID.
		 * @param string $new_status New status.
		 * @param string $old_status Previous status.
		 */
		do_action( 'acme_leads_status_changed', (int) $id, $status, $lead['status'] );
		return true;
	}
}
