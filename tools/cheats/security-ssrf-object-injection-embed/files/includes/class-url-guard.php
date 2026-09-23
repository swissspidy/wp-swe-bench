<?php
/**
 * URL safety checks (naive cheat version): blocks the obvious private literals
 * but does not decode alternative IP encodings and does not resolve host names.
 *
 * @package Acme\LinkPreviews
 */

namespace Acme\LinkPreviews;

defined( 'ABSPATH' ) || exit;

/**
 * URL guard.
 */
class Url_Guard {

	const ALLOWED_SCHEMES = array( 'http', 'https' );

	/**
	 * Check that a URL is safe to fetch.
	 *
	 * @param string $url URL.
	 * @return true|\WP_Error
	 */
	public static function check_url( $url ) {
		$parts = wp_parse_url( (string) $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return new \WP_Error( 'acme_lp_invalid_url', __( 'That does not look like a URL.', 'acme-link-previews' ), array( 'status' => 400 ) );
		}
		if ( ! in_array( strtolower( $parts['scheme'] ), self::ALLOWED_SCHEMES, true ) ) {
			return new \WP_Error( 'acme_lp_invalid_url', __( 'Only http and https URLs can be previewed.', 'acme-link-previews' ), array( 'status' => 400 ) );
		}

		$host = strtolower( $parts['host'] );
		$blocked = array(
			'#^127\.#', '#^10\.#', '#^192\.168\.#', '#^172\.(1[6-9]|2[0-9]|3[01])\.#',
			'#^169\.254\.#', '#^0\.0\.0\.0$#', '#^localhost$#', '#^\[?::1\]?$#',
		);
		foreach ( $blocked as $re ) {
			if ( preg_match( $re, $host ) ) {
				return new \WP_Error( 'acme_lp_blocked_host', __( 'That address cannot be previewed.', 'acme-link-previews' ), array( 'status' => 400 ) );
			}
		}
		return true;
	}
}
