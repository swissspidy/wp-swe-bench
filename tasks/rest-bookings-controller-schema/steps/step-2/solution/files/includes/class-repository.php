<?php
/**
 * Data access for the bookings table.
 *
 * All dates in the table are UTC ('Y-m-d H:i:s').
 *
 * @package Acme\Bookings
 */

namespace Acme\Bookings;

defined( 'ABSPATH' ) || exit;

/**
 * Repository.
 */
class Repository {

	/** Current status values. */
	const STATUSES = array( 'pending', 'confirmed', 'cancelled' );

	/**
	 * Spellings used by 1.0/1.1 that are still in the table on older sites.
	 *
	 * @var array<string,string>
	 */
	const LEGACY_STATUSES = array(
		'approved' => 'confirmed',
		'canceled' => 'cancelled',
	);

	/** Columns that may be written. */
	const COLUMNS = array( 'room_id', 'customer_id', 'start_date', 'end_date', 'status', 'guests', 'notes', 'admin_notes', 'total', 'timezone' );

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'acme_bookings';
	}

	/**
	 * Map legacy status spellings to the current ones.
	 *
	 * @param string $status Raw status.
	 * @return string
	 */
	public static function normalize_status( $status ) {
		$status = strtolower( trim( (string) $status ) );
		return isset( self::LEGACY_STATUSES[ $status ] ) ? self::LEGACY_STATUSES[ $status ] : $status;
	}

	/**
	 * All raw values that mean a given (current) status.
	 *
	 * @param string $status Current status.
	 * @return string[]
	 */
	public static function stored_values_for( $status ) {
		$status = self::normalize_status( $status );
		$values = array( $status );
		foreach ( self::LEGACY_STATUSES as $legacy => $current ) {
			if ( $current === $status ) {
				$values[] = $legacy;
			}
		}
		return $values;
	}

	/**
	 * One booking.
	 *
	 * @param int $id Booking ID.
	 * @return object|null Row.
	 */
	public static function find( $id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
		return $row ? $row : null;
	}

	/**
	 * Build the WHERE clause for query()/count().
	 *
	 * @param array $args See query().
	 * @return string
	 */
	protected static function where( array $args ) {
		global $wpdb;
		$where = array( '1=1' );
		if ( ! empty( $args['room'] ) ) {
			$where[] = $wpdb->prepare( 'room_id = %d', $args['room'] );
		}
		if ( ! empty( $args['customer'] ) ) {
			$where[] = $wpdb->prepare( 'customer_id = %d', $args['customer'] );
		}
		if ( ! empty( $args['status'] ) ) {
			$values = array();
			foreach ( (array) $args['status'] as $status ) {
				$values = array_merge( $values, self::stored_values_for( $status ) );
			}
			$values  = array_unique( $values );
			$where[] = 'status IN (' . implode( ',', array_map( static fn( $v ) => $wpdb->prepare( '%s', $v ), $values ) ) . ')';
		}
		if ( ! empty( $args['ends_after'] ) ) {
			$where[] = $wpdb->prepare( 'end_date > %s', $args['ends_after'] );
		}
		if ( ! empty( $args['starts_before'] ) ) {
			$where[] = $wpdb->prepare( 'start_date < %s', $args['starts_before'] );
		}
		return implode( ' AND ', $where );
	}

	/**
	 * Query bookings.
	 *
	 * @param array $args {
	 *     @type int          $room          Room ID.
	 *     @type int          $customer      Customer user ID.
	 *     @type string|array $status        Status(es), current spelling.
	 *     @type string       $ends_after    UTC 'Y-m-d H:i:s'.
	 *     @type string       $starts_before UTC 'Y-m-d H:i:s'.
	 *     @type string       $orderby       id|start|created. Default start.
	 *     @type string       $order         ASC|DESC. Default ASC.
	 *     @type int          $limit         Default 20.
	 *     @type int          $offset        Default 0.
	 * }
	 * @return object[]
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$args    = wp_parse_args(
			$args,
			array(
				'orderby' => 'start',
				'order'   => 'ASC',
				'limit'   => 20,
				'offset'  => 0,
			)
		);
		$columns = array(
			'id'      => 'id',
			'start'   => 'start_date',
			'created' => 'created_at',
		);
		$orderby = isset( $columns[ $args['orderby'] ] ) ? $columns[ $args['orderby'] ] : 'start_date';
		$order   = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';
		$table   = self::table();
		$where   = self::where( $args );
		$sql     = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order}, id {$order}";
		$sql    .= $wpdb->prepare( ' LIMIT %d OFFSET %d', max( 1, (int) $args['limit'] ), max( 0, (int) $args['offset'] ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql );
	}

	/**
	 * Count bookings matching query() args.
	 *
	 * @param array $args See query().
	 * @return int
	 */
	public static function count( array $args = array() ) {
		global $wpdb;
		$table = self::table();
		$where = self::where( $args );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
	}

	/**
	 * Insert a booking.
	 *
	 * @param array $data Column => value.
	 * @return int|false New ID.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$data               = array_intersect_key( $data, array_flip( self::COLUMNS ) );
		$data['created_at'] = gmdate( 'Y-m-d H:i:s' );
		if ( isset( $data['status'] ) ) {
			$data['status'] = self::normalize_status( $data['status'] );
		}
		$ok = $wpdb->insert( self::table(), $data );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update a booking.
	 *
	 * @param int   $id   Booking ID.
	 * @param array $data Column => value.
	 * @return bool
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$data = array_intersect_key( $data, array_flip( self::COLUMNS ) );
		if ( isset( $data['status'] ) ) {
			$data['status'] = self::normalize_status( $data['status'] );
		}
		if ( ! $data ) {
			return true;
		}
		return false !== $wpdb->update( self::table(), $data, array( 'id' => (int) $id ) );
	}

	/**
	 * Delete a booking.
	 *
	 * @param int $id Booking ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}

	/**
	 * Does [start, end) overlap an active (not cancelled) booking of the room?
	 * Touching ranges (check-out 11:00, next check-in 11:00) don't overlap.
	 *
	 * @param int    $room_id    Room ID.
	 * @param string $start      UTC 'Y-m-d H:i:s'.
	 * @param string $end        UTC 'Y-m-d H:i:s'.
	 * @param int    $exclude_id Booking to ignore (the one being edited).
	 * @return int ID of the first conflicting booking, 0 if none.
	 */
	public static function find_overlap( $room_id, $start, $end, $exclude_id = 0 ) {
		global $wpdb;
		$table     = self::table();
		$cancelled = self::stored_values_for( 'cancelled' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE room_id = %d AND id != %d AND status NOT IN (%s, %s) AND start_date < %s AND end_date > %s ORDER BY start_date ASC LIMIT 1",
				$room_id,
				$exclude_id,
				$cancelled[0],
				$cancelled[1],
				$end,
				$start
			)
		);
		// phpcs:enable
	}

	/**
	 * Booked (not cancelled) ranges of a room in a date window, for the widget.
	 *
	 * @param int    $room_id Room ID.
	 * @param string $from    UTC 'Y-m-d H:i:s'.
	 * @param string $to      UTC 'Y-m-d H:i:s'.
	 * @return object[] Rows with start_date/end_date.
	 */
	public static function booked_ranges( $room_id, $from, $to ) {
		global $wpdb;
		$table     = self::table();
		$cancelled = self::stored_values_for( 'cancelled' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT start_date, end_date FROM {$table} WHERE room_id = %d AND status NOT IN (%s, %s) AND start_date < %s AND end_date > %s ORDER BY start_date ASC",
				$room_id,
				$cancelled[0],
				$cancelled[1],
				$to,
				$from
			)
		);
		// phpcs:enable
	}
}
