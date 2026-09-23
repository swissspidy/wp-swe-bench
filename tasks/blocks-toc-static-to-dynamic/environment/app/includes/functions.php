<?php
/**
 * Helper functions.
 *
 * @package Acme\Toc
 */

namespace Acme\Toc;

defined( 'ABSPATH' ) || exit;

/**
 * Default plugin options.
 *
 * @return array{max_level:int, smooth_scroll:bool}
 */
function default_options() {
	return array(
		'max_level'     => 3,
		'smooth_scroll' => false,
	);
}

/**
 * Plugin options merged with the defaults.
 *
 * @return array{max_level:int, smooth_scroll:bool}
 */
function get_options() {
	$options = get_option( 'acme_toc_options', array() );
	if ( ! is_array( $options ) ) {
		$options = array();
	}
	return array_merge( default_options(), $options );
}

/**
 * Block types whose headings are never listed in a table of contents.
 *
 * Headings inside collapsed or decorative containers don't make sense as
 * navigation targets. Themes and plugins can change the list with the
 * `acme_toc_excluded_blocks` filter.
 *
 * @return string[] Block names, e.g. 'core/details'.
 */
function excluded_blocks() {
	/**
	 * Filters the block types whose headings are skipped by the TOC.
	 *
	 * @since 1.2.0
	 *
	 * @param string[] $blocks Block names.
	 */
	$blocks = apply_filters( 'acme_toc_excluded_blocks', array( 'core/details' ) );
	return array_values( array_unique( array_filter( array_map( 'strval', (array) $blocks ) ) ) );
}

/**
 * The heading levels a TOC may be configured with.
 *
 * @return int[]
 */
function allowed_levels() {
	return array( 2, 3, 4, 5, 6 );
}

/**
 * Clamp a heading level to the allowed range.
 *
 * @param mixed $level   Raw level.
 * @param int   $fallback Level to use when $level is not numeric.
 * @return int
 */
function clamp_level( $level, $fallback = 3 ) {
	if ( ! is_numeric( $level ) ) {
		return $fallback;
	}
	return max( 2, min( 6, (int) $level ) );
}
