<?php
/**
 * Data retention: daily clean-up of personal data we no longer need.
 *
 * @package Acme\Loyalty
 */

namespace Acme\Loyalty;

defined( 'ABSPATH' ) || exit;

/**
 * Retention job.
 */
class Retention {

	const HOOK = 'acme_loyalty_retention_cleanup';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
	}

	/**
	 * Make sure the daily event is scheduled.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * Remove the event (deactivation).
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Configured retention in months (0 = keep forever).
	 *
	 * @return int
	 */
	public static function months() {
		$settings = acme_loyalty_settings();
		return max( 0, (int) $settings['retention_months'] );
	}

	/**
	 * Run the clean-up.
	 *
	 * @return array{subscribers_deleted:int, ledger_rows_cleaned:int}
	 */
	public static function run() {
		global $wpdb;
		$result = array(
			'subscribers_deleted' => 0,
			'ledger_rows_cleaned' => 0,
		);
		$months = self::months();
		if ( $months <= 0 ) {
			return $result;
		}
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $months . ' months' ) );
		$subs   = Installer::subscribers_table();
		$ledger = Installer::ledger_table();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names.
		// Never confirmed.
		$pending = (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$subs} WHERE status = %s AND subscribed_at < %s", Newsletter::STATUS_PENDING, $cutoff )
		);
		// Unsubscribed (1.x rows have no unsubscribe date: fall back to the sign-up date).
		$unsubscribed = (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$subs} WHERE status = %s AND COALESCE(unsubscribed_at, subscribed_at) < %s", Newsletter::STATUS_UNSUBSCRIBED, $cutoff )
		);
		$cleaned = (int) $wpdb->query(
			$wpdb->prepare( "UPDATE {$ledger} SET ip_address = '' WHERE created_at < %s AND ip_address <> ''", $cutoff )
		);
		// phpcs:enable

		$result['subscribers_deleted'] = $pending + $unsubscribed;
		$result['ledger_rows_cleaned'] = $cleaned;

		/**
		 * Fires after the retention clean-up ran.
		 *
		 * @param array $result Counts.
		 * @param int   $months Retention period.
		 */
		do_action( 'acme_loyalty_retention_cleanup_done', $result, $months );

		return $result;
	}
}
