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
	 * Table columns.
	 */
	const COLUMNS = array( 'id', 'name', 'email', 'company', 'status', 'source', 'score', 'owner_id', 'notes', 'created_at', 'updated_at' );

	/**
	 * Columns the lists can be ordered by.
	 */
	const SORTABLE = array( 'created_at', 'name', 'score' );

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
		// Never trust these: they end up in the SQL as identifiers/keywords.
		$orderby         = in_array( $args['orderby'], self::SORTABLE, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$args['orderby'] = $orderby;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order}, id {$order} LIMIT {$per_page} OFFSET {$offset}", ARRAY_A );
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

	/**
	 * Only known columns, in table order.
	 *
	 * @param string[] $columns Requested columns.
	 * @return string[]
	 */
	public static function sanitize_columns( array $columns ) {
		$columns = array_values( array_intersect( self::COLUMNS, $columns ) );
		return $columns ? $columns : array( 'id' );
	}

	/**
	 * Find one lead, reading only some columns.
	 *
	 * @param int      $id      Lead ID.
	 * @param string[] $columns Columns.
	 * @return array|null
	 */
	public static function find_columns( $id, array $columns ) {
		global $wpdb;
		$table  = self::table();
		$select = implode( ', ', self::sanitize_columns( $columns ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT {$select} FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? $row : null;
	}

	/**
	 * Keyset ("seek") query used by the v2 API.
	 *
	 * @param array $args {
	 *     @type string[]    $columns        Columns to read.
	 *     @type string[]    $statuses       Status filter.
	 *     @type int         $owner          Owner filter (0: any).
	 *     @type string|null $created_after  UTC `Y-m-d H:i:s`, inclusive.
	 *     @type string|null $created_before UTC `Y-m-d H:i:s`, exclusive.
	 *     @type string      $search         Substring of name or email.
	 *     @type string      $orderby        created_at|name|score|id.
	 *     @type string      $order          asc|desc.
	 *     @type array|null  $after          Position to continue after: array( 'value' => ..., 'id' => ... ).
	 *     @type int         $limit          Max rows.
	 * }
	 * @return array[]
	 */
	public static function query_keyset( array $args ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'columns'        => self::COLUMNS,
				'statuses'       => array(),
				'owner'          => 0,
				'created_after'  => null,
				'created_before' => null,
				'search'         => '',
				'orderby'        => 'created_at',
				'order'          => 'desc',
				'after'          => null,
				'limit'          => 20,
			)
		);

		$orderby = in_array( $args['orderby'], array_merge( self::SORTABLE, array( 'id' ) ), true ) ? $args['orderby'] : 'created_at';
		$desc    = 'asc' !== strtolower( (string) $args['order'] );
		$dir     = $desc ? 'DESC' : 'ASC';
		$op      = $desc ? '<' : '>';

		$where  = array();
		$params = array();

		$statuses = array_values( array_intersect( (array) $args['statuses'], self::STATUSES ) );
		if ( $statuses ) {
			$where[] = 'status IN (' . implode( ', ', array_fill( 0, count( $statuses ), '%s' ) ) . ')';
			$params  = array_merge( $params, $statuses );
		}
		if ( $args['owner'] ) {
			$where[]  = 'owner_id = %d';
			$params[] = (int) $args['owner'];
		}
		if ( $args['created_after'] ) {
			$where[]  = 'created_at >= %s';
			$params[] = $args['created_after'];
		}
		if ( $args['created_before'] ) {
			$where[]  = 'created_at < %s';
			$params[] = $args['created_before'];
		}
		if ( '' !== (string) $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[]  = '(name LIKE %s OR email LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}
		if ( is_array( $args['after'] ) ) {
			$after_id = (int) $args['after']['id'];
			if ( 'id' === $orderby ) {
				$where[]  = "id {$op} %d";
				$params[] = $after_id;
			} else {
				$placeholder = 'score' === $orderby ? '%d' : '%s';
				$where[]     = "({$orderby} {$op} {$placeholder} OR ({$orderby} = {$placeholder} AND id {$op} %d))";
				$value       = 'score' === $orderby ? (int) $args['after']['value'] : (string) $args['after']['value'];
				$params[]    = $value;
				$params[]    = $value;
				$params[]    = $after_id;
			}
		}

		$columns = self::sanitize_columns( array_merge( (array) $args['columns'], array( 'id', $orderby ) ) );
		$select  = implode( ', ', $columns );
		$table   = self::table();
		$sql     = "SELECT {$select} FROM {$table}";
		if ( $where ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}
		$sql     .= 'id' === $orderby ? " ORDER BY id {$dir}" : " ORDER BY {$orderby} {$dir}, id {$dir}";
		$sql     .= ' LIMIT %d';
		$params[] = max( 1, (int) $args['limit'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		return $rows ? $rows : array();
	}

	/**
	 * Current status of several leads.
	 *
	 * @param int[] $ids IDs.
	 * @return array<int, string> ID => status (missing leads are left out).
	 */
	public static function statuses_for( array $ids ) {
		global $wpdb;
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		$table = self::table();
		$in    = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, status FROM {$table} WHERE id IN ({$in})", $ids ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['id'] ] = $row['status'];
		}
		return $out;
	}

	/**
	 * Set the status of several leads with one query. Does not fire actions.
	 *
	 * @param int[]  $ids    IDs.
	 * @param string $status Status.
	 */
	public static function bulk_set_status( array $ids, $status ) {
		global $wpdb;
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		if ( ! $ids || ! in_array( $status, self::STATUSES, true ) ) {
			return;
		}
		$table  = self::table();
		$in     = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$params = array_merge( array( $status, current_time( 'mysql', true ) ), $ids );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = %s, updated_at = %s WHERE id IN ({$in})", $params ) );
	}

	/**
	 * Update notes / owner of a lead.
	 *
	 * @param int   $id   Lead ID.
	 * @param array $data `notes`, `owner_id`.
	 */
	public static function update_details( $id, array $data ) {
		global $wpdb;
		$data = array_intersect_key( $data, array_flip( array( 'notes', 'owner_id' ) ) );
		if ( ! $data ) {
			return;
		}
		if ( isset( $data['notes'] ) ) {
			$data['notes'] = sanitize_textarea_field( $data['notes'] );
		}
		if ( isset( $data['owner_id'] ) ) {
			$data['owner_id'] = (int) $data['owner_id'];
		}
		$data['updated_at'] = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( self::table(), $data, array( 'id' => (int) $id ) );
	}
}
