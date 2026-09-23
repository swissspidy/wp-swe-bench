<?php
/**
 * Per-visitor preview preferences, stored in a cookie.
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

		$data = @unserialize( $decoded );
		if ( ! is_array( $data ) ) {
			return default_prefs();
		}

		return self::sanitize( $data );
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
