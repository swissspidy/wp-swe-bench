<?php
/**
 * Retention: deletes entries older than the configured number of days, once a day.
 *
 * @package Acme\ActivityLog
 */

namespace Acme\ActivityLog;

defined( 'ABSPATH' ) || exit;

/**
 * Daily cleanup.
 */
class Retention {

	/**
	 * Daily job hook.
	 */
	const CRON_HOOK = 'acme_activity_prune';

	/**
	 * Store.
	 *
	 * @var Log_Store
	 */
	private $store;

	/**
	 * Constructor.
	 *
	 * @param Log_Store $store Store.
	 */
	public function __construct( Log_Store $store ) {
		$this->store = $store;
	}

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( self::CRON_HOOK, array( $this, 'run' ) );
	}

	/**
	 * Schedule the daily job.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule the daily job.
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Daily job: apply the retention setting.
	 *
	 * @return int Number of entries deleted.
	 */
	public function run() {
		$days = (int) Settings::get()['retention_days'];
		if ( $days <= 0 ) {
			return 0;
		}
		return $this->prune( $days );
	}

	/**
	 * Delete entries older than a number of days.
	 *
	 * @param int $days Days to keep.
	 * @return int Number of entries deleted.
	 */
	public function prune( $days ) {
		$days = (int) $days;
		if ( $days <= 0 ) {
			return 0;
		}
		return $this->store->delete_older_than( time() - $days * DAY_IN_SECONDS );
	}
}
