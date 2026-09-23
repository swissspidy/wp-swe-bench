<?php
/**
 * Received events (`{prefix}acme_orders_deliveries`): idempotency records + processing queue.
 *
 * Times are stored as Unix timestamps. The payload is stored redacted.
 *
 * @package Acme\OrdersSync
 */

namespace Acme\OrdersSync;

defined( 'ABSPATH' ) || exit;

/**
 * Delivery store.
 */
class Delivery_Store {

	const STATUS_QUEUED     = 'queued';
	const STATUS_PROCESSING = 'processing';
	const STATUS_PROCESSED  = 'processed';
	const STATUS_RETRYING   = 'retrying';
	const STATUS_FAILED     = 'failed';

	/**
	 * Table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'acme_orders_deliveries';
	}

	/**
	 * Creates/updates the table.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id varchar(191) NOT NULL,
			source varchar(64) NOT NULL,
			type varchar(32) NOT NULL,
			body_hash char(64) NOT NULL,
			payload longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'queued',
			attempts int(11) NOT NULL DEFAULT 0,
			received_at bigint(20) NOT NULL DEFAULT 0,
			processed_at bigint(20) DEFAULT NULL,
			next_attempt_at bigint(20) DEFAULT NULL,
			last_error text DEFAULT NULL,
			order_id bigint(20) unsigned DEFAULT NULL,
			response longtext NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_id (event_id),
			KEY status (status)
			) $charset;"
		);
	}

	/**
	 * Finds a delivery by event ID.
	 *
	 * @param string $event_id Event ID.
	 * @return object|null
	 */
	public function find( string $event_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE event_id = %s', self::table(), $event_id ) );
		return $row ? $row : null;
	}

	/**
	 * Inserts a new delivery. Returns false if the event ID exists already.
	 *
	 * @param array $data Columns.
	 */
	public function insert( array $data ): bool {
		global $wpdb;
		$suppress = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert( self::table(), $data );
		$wpdb->suppress_errors( $suppress );
		return (bool) $ok;
	}

	/**
	 * Updates a delivery.
	 *
	 * @param int   $id   Row ID.
	 * @param array $data Columns.
	 */
	public function update( int $id, array $data ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( self::table(), $data, array( 'id' => $id ) );
	}

	/**
	 * Claims a delivery for processing (so two cron runs never process it twice).
	 *
	 * @param int $id Row ID.
	 */
	public function claim( int $id ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$claimed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET status = 'processing', attempts = attempts + 1 WHERE id = %d AND status IN ('queued', 'retrying')",
				self::table(),
				$id
			)
		);
		return 1 === (int) $claimed;
	}

	/**
	 * Deliveries due for an attempt, oldest first.
	 *
	 * @param int $now   Current time.
	 * @param int $limit Max rows.
	 * @return object[]
	 */
	public function due( int $now, int $limit = 50 ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE ( status = 'queued' ) OR ( status = 'retrying' AND next_attempt_at <= %d ) ORDER BY id ASC LIMIT %d",
				self::table(),
				$now,
				$limit
			)
		);
	}

	/**
	 * Earliest time something is waiting for, or null.
	 */
	public function next_due(): ?int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$queued = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = 'queued'", self::table() ) );
		if ( $queued > 0 ) {
			return time();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$next = $wpdb->get_var( $wpdb->prepare( "SELECT MIN(next_attempt_at) FROM %i WHERE status = 'retrying'", self::table() ) );
		return null === $next ? null : (int) $next;
	}

	/**
	 * Public representation (status endpoint).
	 *
	 * @param object $row Row.
	 */
	public static function to_array( $row ): array {
		$time = static fn( $t ) => null === $t || '' === $t ? null : gmdate( 'Y-m-d\TH:i:s\Z', (int) $t );
		return array(
			'event_id'        => (string) $row->event_id,
			'source'          => (string) $row->source,
			'type'            => (string) $row->type,
			'status'          => (string) $row->status,
			'attempts'        => (int) $row->attempts,
			'received_at'     => $time( $row->received_at ),
			'processed_at'    => $time( $row->processed_at ),
			'next_attempt_at' => self::STATUS_RETRYING === $row->status ? $time( $row->next_attempt_at ) : null,
			'last_error'      => null === $row->last_error || '' === $row->last_error ? null : (string) $row->last_error,
			'order_id'        => $row->order_id ? (int) $row->order_id : null,
		);
	}
}
