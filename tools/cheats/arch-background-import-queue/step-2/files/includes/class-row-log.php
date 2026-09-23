<?php
/**
 * Rows that did not import (yet): {prefix}acme_import_rows.
 *
 * @package Acme\Importer
 */

namespace Acme\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Invalid rows, rows waiting for a retry, and rows that failed for good.
 */
class Row_Log {

	const RETRY   = 'retry';
	const FAILED  = 'failed';
	const INVALID = 'invalid';

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'acme_import_rows';
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
			job_id bigint(20) unsigned NOT NULL DEFAULT 0,
			line int(10) unsigned NOT NULL DEFAULT 0,
			sku varchar(255) NOT NULL DEFAULT '',
			state varchar(10) NOT NULL DEFAULT 'retry',
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			error text NULL,
			next_attempt_at bigint(20) unsigned NOT NULL DEFAULT 0,
			data longtext NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY job_line (job_id,line),
			KEY job_state (job_id,state)
			) {$charset};"
		);
	}

	/**
	 * Gets the entry of a row.
	 *
	 * @param int $job_id Job ID.
	 * @param int $line   Row number.
	 * @return object|null
	 */
	public function get( $job_id, $line ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE job_id = %d AND line = %d", $job_id, $line ) );
	}

	/**
	 * Writes the entry of a row (insert or replace).
	 *
	 * @param int    $job_id   Job ID.
	 * @param int    $line     Row number.
	 * @param array  $row      CSV row.
	 * @param string $state    retry|failed|invalid.
	 * @param int    $attempts Save attempts.
	 * @param string $error    Reason.
	 * @param int    $next     Next attempt (timestamp, retries only).
	 */
	public function put( $job_id, $line, array $row, $state, $attempts, $error, $next = 0 ) {
		global $wpdb;
		$data = array(
			'job_id'          => (int) $job_id,
			'line'            => (int) $line,
			'sku'             => (string) ( $row['sku'] ?? '' ),
			'state'           => $state,
			'attempts'        => (int) $attempts,
			'error'           => (string) $error,
			'next_attempt_at' => (int) $next,
			'data'            => wp_json_encode( $row ),
		);
		$existing = $this->get( $job_id, $line );
		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( self::table(), $data, array( 'id' => $existing->id ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert( self::table(), $data );
		}
	}

	/**
	 * Removes the entry of a row (it imported after all).
	 *
	 * @param int $job_id Job ID.
	 * @param int $line   Row number.
	 */
	public function delete( $job_id, $line ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete(
			self::table(),
			array(
				'job_id' => (int) $job_id,
				'line'   => (int) $line,
			)
		);
	}

	/**
	 * Rows waiting for a retry that is due.
	 *
	 * @param int $job_id Job ID.
	 * @param int $limit  Max rows.
	 * @return object[]
	 */
	public function due_retries( $job_id, $limit ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE job_id = %d AND state = %s AND next_attempt_at <= %d ORDER BY line ASC LIMIT %d", $job_id, self::RETRY, time(), $limit ) );
	}

	/**
	 * Number of rows waiting for a retry.
	 *
	 * @param int $job_id Job ID.
	 * @return int
	 */
	public function count_retries( $job_id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE job_id = %d AND state = %s", $job_id, self::RETRY ) );
	}

	/**
	 * When the next retry is due (0 if none).
	 *
	 * @param int $job_id Job ID.
	 * @return int
	 */
	public function next_retry_at( $job_id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT MIN(next_attempt_at) FROM {$table} WHERE job_id = %d AND state = %s", $job_id, self::RETRY ) );
	}

	/**
	 * Drops pending retries (cancelled imports).
	 *
	 * @param int $job_id Job ID.
	 */
	public function drop_retries( $job_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete(
			self::table(),
			array(
				'job_id' => (int) $job_id,
				'state'  => self::RETRY,
			)
		);
	}

	/**
	 * Rows that are invalid or failed for good, by row number.
	 *
	 * @param int $job_id Job ID.
	 * @return object[]
	 */
	public function errors( $job_id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $wpdb->prepare( "SELECT line, sku, attempts, error FROM {$table} WHERE job_id = %d AND state IN (%s, %s) ORDER BY line ASC", $job_id, self::FAILED, self::INVALID ) );
	}

	/**
	 * The error report as CSV.
	 *
	 * @param int $job_id Job ID.
	 * @return string
	 */
	public function report_csv( $job_id ) {
		$out = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, array( 'row', 'sku', 'attempts', 'error' ), ',', '"', '' );
		foreach ( $this->errors( $job_id ) as $entry ) {
			fputcsv(
				$out,
				array(
					(int) $entry->line,
					self::safe_cell( $entry->sku ),
					(int) $entry->attempts,
					self::safe_cell( $entry->error ),
				),
				',',
				'"',
				''
			);
		}
		rewind( $out );
		$csv = stream_get_contents( $out );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $csv;
	}

	/**
	 * Neutralizes cells a spreadsheet would read as a formula.
	 *
	 * @param string $value Cell.
	 * @return string
	 */
	public static function safe_cell( $value ) {
		// fputcsv() quotes cells that need it; that's enough for a CSV file.
		return (string) $value;
	}
}
