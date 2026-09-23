<?php
/**
 * Helper functions.
 *
 * @package Acme\Redirects
 */

namespace Acme\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Status codes a rule may use, with their labels.
 *
 * @return array<int,string>
 */
function status_codes() {
	return array(
		301 => __( '301 Moved Permanently', 'acme-redirects' ),
		302 => __( '302 Found', 'acme-redirects' ),
		307 => __( '307 Temporary Redirect', 'acme-redirects' ),
		308 => __( '308 Permanent Redirect', 'acme-redirects' ),
		410 => __( '410 Gone', 'acme-redirects' ),
	);
}

/**
 * Match types, with their labels.
 *
 * @return array<string,string>
 */
function match_types() {
	return array(
		'exact'  => __( 'Exact path', 'acme-redirects' ),
		'prefix' => __( 'Path prefix', 'acme-redirects' ),
		'regex'  => __( 'Regular expression', 'acme-redirects' ),
	);
}

/**
 * Normalizes a request path for comparison.
 *
 * - decodes percent-encoding,
 * - collapses duplicate slashes,
 * - forces a leading slash,
 * - lower-cases (paths are matched case-insensitively),
 * - strips the trailing slash (except for "/").
 *
 * @param string $path Raw path (no query string).
 * @return string
 */
function normalize_path( $path ) {
	$path = rawurldecode( (string) $path );
	$path = preg_replace( '#/{2,}#', '/', $path );
	$path = '/' . ltrim( $path, '/' );
	$path = strtolower( $path );
	if ( '/' !== $path ) {
		$path = untrailingslashit( $path );
	}
	return $path;
}

/**
 * Parses a query string into a sorted array (order-insensitive comparison).
 *
 * @param string $query Query string without "?".
 * @return array
 */
function parse_query( $query ) {
	$args = array();
	if ( '' !== (string) $query ) {
		wp_parse_str( (string) $query, $args );
	}
	ksort( $args );
	return $args;
}

/**
 * Builds the regex used for a regex rule source.
 *
 * Sources are stored without delimiters (e.g. `^/blog/(\d+)/?$`) and are
 * matched case-insensitively against the decoded request path.
 *
 * @param string $source Rule source.
 * @return string
 */
function regex_for( $source ) {
	return '#' . str_replace( '#', '\#', (string) $source ) . '#i';
}

/**
 * Formats a GMT MySQL date for display in the site's timezone.
 *
 * @param string $gmt MySQL datetime in UTC.
 * @return string
 */
function format_date( $gmt ) {
	if ( empty( $gmt ) || '0000-00-00 00:00:00' === $gmt ) {
		return '—';
	}
	return get_date_from_gmt( $gmt, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
}
