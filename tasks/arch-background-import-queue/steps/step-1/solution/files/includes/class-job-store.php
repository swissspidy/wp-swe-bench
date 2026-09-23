<?php
/**
 * Import jobs ({prefix}acme_import_jobs).
 *
 * @package Acme\Importer
 */

namespace Acme\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Storage of queued imports.
 *
 * Every read goes to the database (no caching): jobs are changed by several
 * processes at once (cron runners, the admin cancelling an import).
 */
class Job_Store {

	const QUEUED    = 'queued';
	const RUNNING   = 'running';
	const COMPLETED = 'completed';
	const CANCELLED = 'cancelled';
	const FAILED    = 'failed';

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'acme_import_jobs';
	}

	/**
	 * Creates the table.
	 */
	public static function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			status varchar(20) NOT NULL DEFAULT 'queued',
			file_name varchar(255) NOT NULL DEFAULT '',
			file_path text NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			total int(10) unsigned NOT NULL DEFAULT 0,
			processed int(10) unsigned NOT NULL DEFAULT 0,
			created int(10) unsigned NOT NULL DEFAULT 0,
			updated int(10) unsigned NOT NULL DEFAULT 0,
			skipped int(10) unsigned NOT NULL DEFAULT 0,
			failed int(10) unsigned NOT NULL DEFAULT 0,
			file_offset bigint(20) unsigned NOT NULL DEFAULT 0,
			last_row int(10) unsigned NOT NULL DEFAULT 1,
			lock_token varchar(64) NOT NULL DEFAULT '',
			locked_at bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NULL DEFAULT NULL,
			started_at datetime NULL DEFAULT NULL,
			finished_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status (status)
			) {$charset};"
		);
	}

	/**
	 * Inserts a job.
	 *
	 * @param array $data file_name, file_path, user_id, total.
	 * @return int Job ID (0 on failure).
	 */
	public function insert( array $data ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert(
			self::table(),
			array(
				'status'     => self::QUEUED,
				'file_name'  => $data['file_name'],
				'file_path'  => $data['file_path'],
				'user_id'    => (int) $data['user_id'],
				'total'      => (int) $data['total'],
				'created_at' => current_time( 'mysql', true ),
			)
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Gets a job (fresh from the database).
	 *
	 * @param int $id Job ID.
	 * @return object|null
	 */
	public function get( $id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	/**
	 * Current status of a job (fresh from the database).
	 *
	 * @param int $id Job ID.
	 * @return string|null
	 */
	public function status( $id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE id = %d", $id ) );
	}

	/**
	 * Lists jobs, newest first.
	 *
	 * @param int $limit Max jobs.
	 * @return object[]
	 */
	public function recent( $limit = 50 ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ) );
	}

	/**
	 * Unfinished jobs, oldest first.
	 *
	 * @return object[]
	 */
	public function unfinished() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status IN (%s, %s) ORDER BY id ASC", self::QUEUED, self::RUNNING ) );
	}

	/**
	 * Claims a job for a runner. Atomic: only one runner can hold the claim; a
	 * claim that was not refreshed for $timeout seconds is considered abandoned.
	 *
	 * @param int    $id      Job ID.
	 * @param string $token   Runner token.
	 * @param int    $timeout Claim timeout in seconds.
	 * @return bool
	 */
	public function claim( $id, $token, $timeout ) {
		global $wpdb;
		$table = self::table();
		$now   = time();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET lock_token = %s, locked_at = %d WHERE id = %d AND ( lock_token = '' OR locked_at < %d )", $token, $now, $id, $now - (int) $timeout ) );
		return 1 === (int) $rows;
	}

	/**
	 * Releases a claim (only the holder can).
	 *
	 * @param int    $id    Job ID.
	 * @param string $token Runner token.
	 */
	public function release( $id, $token ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET lock_token = '', locked_at = 0 WHERE id = %d AND lock_token = %s", $id, $token ) );
	}

	/**
	 * Marks a job as running (first batch).
	 *
	 * @param int $id Job ID.
	 */
	public function start( $id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = %s, started_at = %s WHERE id = %d AND status = %s", self::RUNNING, current_time( 'mysql', true ), $id, self::QUEUED ) );
	}

	/**
	 * Records a processed row: counter, read position, claim heartbeat.
	 *
	 * @param int    $id         Job ID.
	 * @param string $result     created|updated|skipped|failed.
	 * @param int    $position   Byte offset of the next record.
	 * @param int    $row_number Row number of the processed record.
	 * @param string $token      Runner token.
	 * @return bool False if the runner lost its claim.
	 */
	public function record_row( $id, $result, $position, $row_number, $token ) {
		global $wpdb;
		if ( ! in_array( $result, array( 'created', 'updated', 'skipped', 'failed' ), true ) ) {
			return false;
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET {$result} = {$result} + 1, processed = processed + 1, file_offset = %d, last_row = %d, locked_at = %d WHERE id = %d AND lock_token = %s", $position, $row_number, time(), $id, $token ) );
		return 1 === (int) $rows;
	}

	/**
	 * Moves the read position without counting a row (blank lines at the end).
	 *
	 * @param int    $id         Job ID.
	 * @param int    $position   Byte offset.
	 * @param int    $row_number Row number.
	 * @param string $token      Runner token.
	 */
	public function advance( $id, $position, $row_number, $token ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET file_offset = %d, last_row = %d, locked_at = %d WHERE id = %d AND lock_token = %s", $position, $row_number, time(), $id, $token ) );
	}

	/**
	 * Ends a job.
	 *
	 * @param int      $id     Job ID.
	 * @param string   $status completed|cancelled|failed.
	 * @param string[] $from   Only if the job currently has one of these statuses.
	 * @return bool Whether the job was changed.
	 */
	public function finish( $id, $status, array $from = array( self::QUEUED, self::RUNNING ) ) {
		global $wpdb;
		$table = self::table();
		$in    = implode( ',', array_fill( 0, count( $from ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = %s, finished_at = %s WHERE id = %d AND status IN ({$in})", array_merge( array( $status, current_time( 'mysql', true ), $id ), $from ) ) );
		return 1 === (int) $rows;
	}

	/**
	 * Public representation (REST, admin).
	 *
	 * @param object $job Row.
	 * @return array
	 */
	public static function to_array( $job ) {
		$total     = (int) $job->total;
		$processed = (int) $job->processed;
		if ( $total > 0 ) {
			$progress = (int) floor( $processed * 100 / $total );
		} else {
			$progress = self::COMPLETED === $job->status ? 100 : 0;
		}
		return array(
			'id'          => (int) $job->id,
			'status'      => (string) $job->status,
			'file_name'   => (string) $job->file_name,
			'total'       => $total,
			'processed'   => $processed,
			'created'     => (int) $job->created,
			'updated'     => (int) $job->updated,
			'skipped'     => (int) $job->skipped,
			'failed'      => (int) $job->failed,
			'progress'    => min( 100, $progress ),
			'user'        => (int) $job->user_id,
			'created_at'  => self::date( $job->created_at ),
			'started_at'  => self::date( $job->started_at ),
			'finished_at' => self::date( $job->finished_at ),
		);
	}

	/**
	 * ISO 8601 (UTC) or null.
	 *
	 * @param string|null $mysql MySQL datetime (UTC).
	 * @return string|null
	 */
	private static function date( $mysql ) {
		if ( empty( $mysql ) || '0000-00-00 00:00:00' === $mysql ) {
			return null;
		}
		return gmdate( 'c', strtotime( $mysql . ' UTC' ) );
	}

	/**
	 * Status labels.
	 *
	 * @return array<string,string>
	 */
	public static function labels() {
		return array(
			self::QUEUED    => __( 'Queued', 'acme-importer' ),
			self::RUNNING   => __( 'Running', 'acme-importer' ),
			self::COMPLETED => __( 'Completed', 'acme-importer' ),
			self::CANCELLED => __( 'Cancelled', 'acme-importer' ),
			self::FAILED    => __( 'Failed', 'acme-importer' ),
		);
	}
}
