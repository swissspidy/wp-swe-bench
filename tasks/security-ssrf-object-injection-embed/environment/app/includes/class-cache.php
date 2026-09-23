<?php
/**
 * Preview cache (stored in an option).
 *
 * @package Acme\LinkPreviews
 */

namespace Acme\LinkPreviews;

defined( 'ABSPATH' ) || exit;

/**
 * Cache of fetched previews, keyed by URL hash.
 */
class Cache {

	const OPTION = 'acme_lp_cache';

	/**
	 * All cached previews.
	 *
	 * @return array<string, array>
	 */
	public static function all() {
		$data = get_option( self::OPTION, array() );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Get one preview by URL.
	 *
	 * @param string $url URL.
	 * @return array|null
	 */
	public static function get( $url ) {
		$all = self::all();
		$key = cache_key( $url );
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Store a preview.
	 *
	 * @param string $url     URL.
	 * @param array  $preview Preview data.
	 */
	public static function set( $url, array $preview ) {
		$all         = self::all();
		$all[ cache_key( $url ) ] = $preview;
		update_option( self::OPTION, $all );
	}

	/**
	 * Replace the whole cache (used by import).
	 *
	 * @param array $all Cache data.
	 */
	public static function replace( array $all ) {
		update_option( self::OPTION, $all );
	}
}
