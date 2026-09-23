<?php
/**
 * Simple per-IP rate limiting for form submissions.
 *
 * @package Acme\Contact
 */

namespace Acme\Contact;

defined( 'ABSPATH' ) || exit;

/**
 * Fixed-window rate limiter backed by transients.
 */
class Rate_Limiter {

	const WINDOW = 10 * MINUTE_IN_SECONDS;

	/**
	 * Allowed submissions per IP and window.
	 *
	 * @return int
	 */
	public static function limit() {
		/**
		 * Filters how many submissions one IP address may send per 10 minutes.
		 *
		 * @param int $limit Default 5.
		 */
		return max( 1, (int) apply_filters( 'acme_contact_rate_limit', 5 ) );
	}

	/**
	 * Transient key for an IP.
	 *
	 * @param string $ip IP.
	 * @return string
	 */
	private static function key( $ip ) {
		return 'acme_contact_rl_' . md5( $ip );
	}

	/**
	 * Would one more submission from this IP exceed the limit?
	 *
	 * @param string $ip IP.
	 * @return bool
	 */
	public static function is_limited( $ip ) {
		$count = (int) get_transient( self::key( $ip ) );
		return $count >= self::limit();
	}

	/**
	 * Count a submission.
	 *
	 * @param string $ip IP.
	 */
	public static function hit( $ip ) {
		$key   = self::key( $ip );
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, self::WINDOW );
	}
}
