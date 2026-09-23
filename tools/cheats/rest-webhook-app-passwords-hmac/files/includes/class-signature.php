<?php
/**
 * Acme Shop webhook signatures (API v2):
 *
 *     X-Acme-Timestamp: 1790000000
 *     X-Acme-Signature: v1=<hex hmac-sha256 of "<timestamp>.<raw body>">[,v1=…]
 *
 * @package Acme\OrdersSync
 */

namespace Acme\OrdersSync;

defined( 'ABSPATH' ) || exit;

/**
 * Signature verification.
 */
class Signature {

	const SCHEME    = 'v1';
	const TOLERANCE = 300;

	/**
	 * Expected signature for a payload.
	 *
	 * @param string $timestamp Timestamp header value.
	 * @param string $body      Raw body.
	 * @param string $secret    Source secret.
	 */
	public static function compute( string $timestamp, string $body, string $secret ): string {
		return hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
	}

	/**
	 * Parses the header into the list of v1 signatures.
	 *
	 * @param string $header Header value.
	 * @return string[]
	 */
	public static function parse( string $header ): array {
		$signatures = array();
		foreach ( explode( ',', $header ) as $part ) {
			$pair = explode( '=', trim( $part ), 2 );
			if ( 2 === count( $pair ) && self::SCHEME === trim( $pair[0] ) && '' !== trim( $pair[1] ) ) {
				$signatures[] = strtolower( trim( $pair[1] ) );
			}
		}
		return $signatures;
	}

	/**
	 * Whether any v1 signature in the header matches (constant-time comparison).
	 *
	 * @param string $header    Header value.
	 * @param string $timestamp Timestamp header value.
	 * @param string $body      Raw body.
	 * @param string $secret    Source secret.
	 */
	public static function verify( string $header, string $timestamp, string $body, string $secret ): bool {
		if ( '' === $secret ) {
			return false;
		}
		$expected = self::compute( $timestamp, $body, $secret );
		$valid    = false;
		foreach ( self::parse( $header ) as $candidate ) {
			// No early exit: every candidate is compared.
			$valid = hash_equals( $expected, $candidate ) || $valid;
		}
		return $valid;
	}

	/**
	 * Whether the timestamp header is a plain integer.
	 *
	 * @param string $timestamp Timestamp header value.
	 */
	public static function is_valid_timestamp( string $timestamp ): bool {
		return 1 === preg_match( '/^\d{1,12}$/', $timestamp );
	}

	/**
	 * Whether the timestamp is within the tolerance.
	 *
	 * @param string $timestamp Timestamp header value.
	 * @param int    $now       Current time.
	 */
	public static function is_fresh( string $timestamp, int $now ): bool {
		return abs( $now - (int) $timestamp ) <= self::TOLERANCE;
	}
}
