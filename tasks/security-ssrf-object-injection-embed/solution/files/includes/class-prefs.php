<?php
/**
 * Per-visitor preview preferences, stored in a cookie.
 *
 * Preferences are stored as JSON. Cookies written by older versions used PHP's
 * serialize(), so they are still read — but never in a way that instantiates an
 * object (which would be a PHP object-injection vector).
 *
 * @package Acme\LinkPreviews
 */

namespace Acme\LinkPreviews;

defined( 'ABSPATH' ) || exit;

/**
 * Preferences.
 */
class Prefs {

	/**
	 * The current visitor's preferences (from their cookie, or the defaults).
	 *
	 * @return array<string, mixed>
	 */
	public static function current() {
		$raw = isset( $_COOKIE[ prefs_cookie_name() ] ) ? (string) wp_unslash( $_COOKIE[ prefs_cookie_name() ] ) : '';
		if ( '' === $raw ) {
			return default_prefs();
		}

		$decoded = base64_decode( $raw, true );
		if ( false === $decoded ) {
			return default_prefs();
		}

		$data = self::decode( $decoded );
		if ( ! is_array( $data ) ) {
			return default_prefs();
		}

		return self::sanitize( $data );
	}

	/**
	 * Encode preferences for storage (JSON).
	 *
	 * @param array $prefs Preferences.
	 * @return string Base64 of the JSON payload.
	 */
	public static function encode( array $prefs ) {
		return base64_encode( wp_json_encode( self::sanitize( $prefs ) ) );
	}

	/**
	 * Decode a stored payload as data only.
	 *
	 * Accepts the current JSON format and legacy PHP-serialized data, but never
	 * turns serialized data into objects.
	 *
	 * @param string $payload Raw (base64-decoded) payload.
	 * @return array|null
	 */
	private static function decode( $payload ) {
		$json = json_decode( $payload, true );
		if ( is_array( $json ) ) {
			return $json;
		}
		// Legacy: PHP-serialized data. Read it without instantiating any objects.
		$legacy = @unserialize( $payload, array( 'allowed_classes' => false ) );
		return is_array( $legacy ) ? $legacy : null;
	}

	/**
	 * Clamp preferences to the known keys/values.
	 *
	 * @param array $data Raw preferences.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $data ) {
		$prefs = default_prefs();
		if ( isset( $data['theme'] ) && in_array( $data['theme'], array( 'light', 'dark' ), true ) ) {
			$prefs['theme'] = $data['theme'];
		}
		if ( isset( $data['show_images'] ) ) {
			$prefs['show_images'] = (bool) $data['show_images'];
		}
		return $prefs;
	}
}
