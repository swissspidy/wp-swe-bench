<?php
/**
 * Opaque, tamper-proof pagination cursors.
 *
 * A cursor is `base64url(json payload) . "." . base64url(hmac)`. The payload
 * holds the sort it was issued for and the position of the last lead on the
 * page (sort value + id), so the next page continues after that lead no matter
 * how many leads were added in the meantime.
 *
 * @package Acme\Leads
 */

namespace Acme\Leads;

defined( 'ABSPATH' ) || exit;

/**
 * Cursor codec.
 */
class Cursor {

	/**
	 * Encode a payload.
	 *
	 * @param array $payload Payload.
	 * @return string
	 */
	public static function encode( array $payload ) {
		$json = (string) wp_json_encode( $payload );
		return self::b64( $json ) . '.' . self::b64( self::sign( $json ) );
	}

	/**
	 * Decode and verify a cursor.
	 *
	 * @param string $cursor Cursor.
	 * @return array|null Payload, or null when the cursor is malformed or was modified.
	 */
	public static function decode( $cursor ) {
		if ( ! is_string( $cursor ) || ! preg_match( '/^([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)$/', $cursor, $m ) ) {
			return null;
		}
		$json = self::unb64( $m[1] );
		$sig  = self::unb64( $m[2] );
		if ( false === $json || false === $sig || ! hash_equals( self::sign( $json ), $sig ) ) {
			return null;
		}
		$payload = json_decode( $json, true );
		return is_array( $payload ) ? $payload : null;
	}

	/**
	 * HMAC of a payload.
	 *
	 * @param string $json Payload JSON.
	 * @return string Raw HMAC.
	 */
	private static function sign( $json ) {
		return hash_hmac( 'sha256', $json, wp_salt( 'auth' ) . '|acme-leads-cursor', true );
	}

	/**
	 * URL-safe base64 without padding.
	 *
	 * @param string $data Data.
	 * @return string
	 */
	private static function b64( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decode URL-safe base64 (strict).
	 *
	 * @param string $data Data.
	 * @return string|false
	 */
	private static function unb64( $data ) {
		return base64_decode( strtr( $data, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}
}
