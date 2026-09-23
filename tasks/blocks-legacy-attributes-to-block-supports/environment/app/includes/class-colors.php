<?php
/**
 * Colour helpers.
 *
 * @package Acme\ContentBlocks
 */

namespace Acme\ContentBlocks;

defined( 'ABSPATH' ) || exit;

/**
 * Hex colour utilities shared by the blocks and the REST API.
 */
class Colors {

	/**
	 * Normalize a hex colour: lowercase, 6 digits, leading "#".
	 *
	 * "#ABC" becomes "#aabbcc". Anything that isn't a 3- or 6-digit hex
	 * colour returns an empty string.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function normalize_hex( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = strtolower( trim( $value ) );
		if ( preg_match( '/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $m ) ) {
			return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
		}
		return preg_match( '/^#[0-9a-f]{6}$/', $value ) ? $value : '';
	}

	/**
	 * Relative luminance of a hex colour (0 = black, 1 = white).
	 *
	 * @param string $hex Hex colour.
	 * @return float
	 */
	public static function luminance( $hex ) {
		$hex = self::normalize_hex( $hex );
		if ( '' === $hex ) {
			return 1.0;
		}
		$rgb = array_map(
			static function ( $channel ) {
				$c = hexdec( $channel ) / 255;
				return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
			},
			str_split( substr( $hex, 1 ), 2 )
		);
		return 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];
	}
}
