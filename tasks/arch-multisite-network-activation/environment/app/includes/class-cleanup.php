<?php
/**
 * Daily cleanup.
 *
 * @package Acme\Directory
 */

namespace Acme\Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Expires old listings and purges stale submissions (daily cron event).
 */
class Cleanup {

	/**
	 * Cron callback.
	 *
	 * @return array{expired:int,purged:int}
	 */
	public static function run() {
		global $wpdb;
		$now = current_time( 'mysql', true );

		$expired = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'UPDATE ' . Schema::$listings . ' SET status = %s, updated_at = %s WHERE status = %s AND expires_at IS NOT NULL AND expires_at < %s',
				'expired',
				$now,
				'published',
				$now
			)
		);

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * (int) Settings::get( 'purge_days' ) );
		$purged = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'DELETE FROM ' . Schema::$listings . ' WHERE status = %s AND created_at < %s',
				'pending',
				$cutoff
			)
		);

		delete_transient( Categories::COUNTS_TRANSIENT );

		$result = array(
			'expired' => (int) $expired,
			'purged'  => (int) $purged,
		);

		/**
		 * Fires after the daily cleanup ran.
		 *
		 * @param array $result Numbers of expired and purged listings.
		 */
		do_action( 'acme_directory_cleanup_done', $result );

		return $result;
	}
}
