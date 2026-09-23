<?php
/**
 * Shared helpers.
 *
 * @package Acme\LinkPreviews
 */

namespace Acme\LinkPreviews;

defined( 'ABSPATH' ) || exit;

/**
 * Default user preferences for the preview cards.
 *
 * @return array<string, mixed>
 */
function default_prefs() {
	return array(
		'theme'       => 'light',
		'show_images' => true,
	);
}

/**
 * A stable cache key for a URL.
 *
 * @param string $url URL.
 * @return string
 */
function cache_key( $url ) {
	return md5( strtolower( trim( (string) $url ) ) );
}

/**
 * The name of the cookie that stores per-visitor preview preferences.
 *
 * @return string
 */
function prefs_cookie_name() {
	return 'acme_lp_prefs';
}
