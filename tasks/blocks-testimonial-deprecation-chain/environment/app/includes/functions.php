<?php
/**
 * Helpers.
 *
 * @package Acme\Testimonials
 */

defined( 'ABSPATH' ) || exit;

/**
 * Normalise a rating coming from block attributes, CSV files or old content.
 *
 * Ratings are whole stars from 1 to 5; anything else means "not rated" (0).
 *
 * @param mixed $rating Raw rating (number or numeric string).
 * @return int Rating 0–5.
 */
function acme_testimonials_normalize_rating( $rating ) {
	if ( is_string( $rating ) ) {
		$rating = trim( $rating );
	}
	if ( '' === $rating || null === $rating || ! is_numeric( $rating ) ) {
		return 0;
	}
	return max( 0, min( 5, (int) $rating ) );
}

/**
 * Find all testimonial blocks in a list of parsed blocks (recursively).
 *
 * @param array[] $blocks Parsed blocks (parse_blocks()).
 * @return array[] Testimonial blocks.
 */
function acme_testimonials_find_blocks( array $blocks ) {
	$found = array();
	foreach ( $blocks as $block ) {
		if ( 'acme/testimonial' === $block['blockName'] ) {
			$found[] = $block;
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			$found = array_merge( $found, acme_testimonials_find_blocks( $block['innerBlocks'] ) );
		}
	}
	return $found;
}
