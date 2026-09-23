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
 * - "local string": `Y-m-d H:i:s` wall-clock time in the event's timezone (what the editor typed in).
 * - "UTC string": `Y-m-d H:i:s` in UTC.
 * - "timestamp": a real Unix timestamp.
 *
 * Timezones are stored as strings: a named zone (`Europe/Berlin`, `UTC`) or a fixed offset
 * (`+05:30`), exactly like WordPress describes the site's timezone setting.
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
	 * The site's current timezone setting as a string (`America/New_York`, `UTC`, `+05:30`).
	 *
	 * Sites configured with a manual UTC offset have no timezone name, only an offset.
	 *
	 * @return string
	 */
	public static function site_timezone_string() {
		return wp_timezone_string();
	}

	/**
	 * The site's timezone object.
	 *
	 * @return \DateTimeZone
	 */
	public static function site_timezone() {
		return self::timezone( self::site_timezone_string() );
	}

	/**
	 * Timezone object for a stored timezone string. Falls back to the site timezone.
	 *
	 * @param string $name Timezone name or offset.
	 * @return \DateTimeZone
	 */
	public static function timezone( $name ) {
		$name = (string) $name;
		if ( '' !== $name ) {
			try {
				return new \DateTimeZone( $name );
			} catch ( \Exception $e ) {
				// Fall through to the site timezone.
				unset( $e );
			}
		}
		return wp_timezone();
	}

	/**
	 * Local string (wall-clock time in $tz) to a Unix timestamp.
	 *
	 * Uses the UTC offset in effect at that moment, not the current one.
	 *
	 * @param string        $local Local string.
	 * @param \DateTimeZone $tz    Timezone of the local string.
	 * @return int
	 */
	public static function local_to_timestamp( $local, \DateTimeZone $tz ) {
		$date = \DateTimeImmutable::createFromFormat( '!' . self::MYSQL, $local, $tz );
		if ( ! $date ) {
			return 0;
		}
		return $date->getTimestamp();
	}

	/**
	 * Local string (wall-clock time in $tz) to a UTC string.
	 *
	 * @param string        $local Local string.
	 * @param \DateTimeZone $tz    Timezone.
	 * @return string
	 */
	public static function local_to_utc( $local, \DateTimeZone $tz ) {
		return gmdate( self::MYSQL, self::local_to_timestamp( $local, $tz ) );
	}

	/**
	 * Unix timestamp to a local string in $tz.
	 *
	 * @param int           $timestamp Unix timestamp.
	 * @param \DateTimeZone $tz        Timezone.
	 * @return string
	 */
	public static function timestamp_to_local( $timestamp, \DateTimeZone $tz ) {
		return ( new \DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( $tz )->format( self::MYSQL );
	}

	/**
	 * UTC string to a Unix timestamp.
	 *
	 * @param string $utc UTC string.
	 * @return int
	 */
	public static function utc_to_timestamp( $utc ) {
		$date = \DateTimeImmutable::createFromFormat( '!' . self::MYSQL, (string) $utc, new \DateTimeZone( 'UTC' ) );
		return $date ? $date->getTimestamp() : 0;
	}

	/**
	 * Timestamp of midnight at the start of a calendar day in $tz.
	 *
	 * @param string        $date `Y-m-d`.
	 * @param \DateTimeZone $tz   Timezone.
	 * @return int
	 */
	public static function day_start_timestamp( $date, \DateTimeZone $tz ) {
		return self::local_to_timestamp( $date . ' 00:00:00', $tz );
	}

	/**
	 * The calendar day after `Y-m-d` (pure date arithmetic, no timezone involved).
	 *
	 * @param string $date `Y-m-d`.
	 * @return string
	 */
	public static function next_day( $date ) {
		$d = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, new \DateTimeZone( 'UTC' ) );
		return $d ? $d->modify( '+1 day' )->format( 'Y-m-d' ) : $date;
	}

	/**
	 * ISO 8601 representation of a Unix timestamp in a timezone.
	 *
	 * @param int                $timestamp Unix timestamp.
	 * @param \DateTimeZone|null $tz        Timezone (default: site timezone).
	 * @return string
	 */
	public static function iso8601( $timestamp, ?\DateTimeZone $tz = null ) {
		$date = new \DateTimeImmutable( '@' . (int) $timestamp );
		return $date->setTimezone( $tz ? $tz : self::site_timezone() )->format( DATE_ATOM );
	}

	/**
	 * Formats a calendar date (`Y-m-d`) with a date format, without shifting it through any timezone.
	 *
	 * @param string $format PHP date format.
	 * @param string $date   `Y-m-d`.
	 * @return string
	 */
	public static function format_date( $format, $date ) {
		$utc = new \DateTimeZone( 'UTC' );
		return wp_date( $format, self::day_start_timestamp( $date, $utc ), $utc );
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

	/**
	 * ICS date for `Y-m-d`.
	 *
	 * @param string $date `Y-m-d`.
	 * @return string
	 */
	public static function ics_date( $date ) {
		return str_replace( '-', '', $date );
	}
}
