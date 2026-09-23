<?php
/**
 * Date helpers.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Conversions between the formats the plugin stores and displays.
 *
 * Terminology used across the plugin:
 * - "local string": `Y-m-d H:i:s` wall-clock time on the site (what the editor typed in).
 * - "local timestamp": the local string parsed as if it were UTC (like current_time( 'timestamp' )).
 * - "timestamp": a real Unix timestamp.
 */
class Dates {

	const MYSQL = 'Y-m-d H:i:s';

	/**
	 * Normalizes user input (`Y-m-d H:i`, `Y-m-d H:i:s` or `Y-m-d`) to a local string.
	 *
	 * @param string $value    Input.
	 * @param string $fallback Time to use when only a date is given.
	 * @return string|null Local string, or null if the input is not a valid date.
	 */
	public static function normalize_local( $value, $fallback = '00:00:00' ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			$value .= ' ' . $fallback;
		}
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/', $value, $m ) ) {
			return null;
		}
		if ( ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) || (int) $m[4] > 23 || (int) $m[5] > 59 ) {
			return null;
		}
		return sprintf( '%s-%s-%s %s:%s:%s', $m[1], $m[2], $m[3], $m[4], $m[5], isset( $m[6] ) && '' !== $m[6] ? $m[6] : '00' );
	}

	/**
	 * Local string to local timestamp.
	 *
	 * @param string $local Local string.
	 * @return int
	 */
	public static function local_to_local_timestamp( $local ) {
		return (int) strtotime( $local );
	}

	/**
	 * Local string to a Unix timestamp.
	 *
	 * @param string $local Local string.
	 * @return int
	 */
	public static function local_to_timestamp( $local ) {
		return self::local_to_local_timestamp( $local ) - self::offset_seconds();
	}

	/**
	 * Unix timestamp to local string.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	public static function timestamp_to_local( $timestamp ) {
		return gmdate( self::MYSQL, (int) $timestamp + self::offset_seconds() );
	}

	/**
	 * The site's UTC offset in seconds.
	 *
	 * @return int
	 */
	public static function offset_seconds() {
		return (int) ( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
	}

	/**
	 * The site's timezone object.
	 *
	 * @return \DateTimeZone
	 */
	public static function site_timezone() {
		$tz = get_option( 'timezone_string' );
		return new \DateTimeZone( $tz ? $tz : 'UTC' );
	}

	/**
	 * ISO 8601 representation of a Unix timestamp in the site's timezone.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	public static function iso8601( $timestamp ) {
		$date = new \DateTimeImmutable( '@' . (int) $timestamp );
		return $date->setTimezone( self::site_timezone() )->format( DATE_ATOM );
	}

	/**
	 * Date part (`Y-m-d`) of a local string.
	 *
	 * @param string $local Local string.
	 * @return string
	 */
	public static function date_part( $local ) {
		return substr( (string) $local, 0, 10 );
	}

	/**
	 * ICS date-time (UTC) for a Unix timestamp.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	public static function ics_utc( $timestamp ) {
		return gmdate( 'Ymd\THis\Z', (int) $timestamp );
	}
}
