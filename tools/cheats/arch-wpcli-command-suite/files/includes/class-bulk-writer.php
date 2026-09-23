<?php
/**
 * Direct table writes for bulk operations (faster than the repository).
 *
 * @package Acme\Redirects
 */

namespace Acme\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Writes rules straight into the table.
 */
class Bulk_Writer {

	/**
	 * Inserts a rule.
	 *
	 * @param Rule $rule Rule.
	 * @return int
	 */
	public static function insert( Rule $rule ) {
		global $wpdb;
		$row               = $rule->to_row();
		$row['hits']       = 0;
		$row['created_at'] = current_time( 'mysql', true );
		$row['updated_at'] = $row['created_at'];
		$wpdb->insert( Rule_Repository::table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->insert_id;
	}

	/**
	 * Updates a rule.
	 *
	 * @param Rule $rule Rule.
	 * @return bool
	 */
	public static function update( Rule $rule ) {
		global $wpdb;
		$row               = $rule->to_row();
		$row['updated_at'] = current_time( 'mysql', true );
		return false !== $wpdb->update( Rule_Repository::table(), $row, array( 'id' => $rule->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Deletes a rule.
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( Rule_Repository::table(), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
