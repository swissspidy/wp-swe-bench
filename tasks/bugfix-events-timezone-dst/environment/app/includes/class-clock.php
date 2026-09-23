<?php
/**
 * The plugin clock.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of "now" for everything time dependent (upcoming events, feeds).
 *
 * Staging and the QA team pin the date with the `acme_events_now` filter:
 *
 *     add_filter( 'acme_events_now', fn() => strtotime( '2026-03-08 06:00:00 UTC' ) );
 */
class Clock {

	/**
	 * Current Unix timestamp (UTC), filterable.
	 *
	 * @return int
	 */
	public static function now() {
		/**
		 * Filters the current time used by Acme Events.
		 *
		 * @since 1.4.0
		 *
		 * @param int $now Current Unix timestamp.
		 */
		return (int) apply_filters( 'acme_events_now', time() );
	}

	/**
	 * Current time as a "local" timestamp (the site's wall-clock time expressed as if it were UTC),
	 * like current_time( 'timestamp' ) but honouring the `acme_events_now` filter.
	 *
	 * @return int
	 */
	public static function local_now() {
		return self::now() + (int) ( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
	}
}
