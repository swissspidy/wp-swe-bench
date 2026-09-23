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
 * Ratings go from 0 to 5 in half stars, 0 meaning "not rated". Values in between are
 * rounded to the nearest half star, values outside are clamped. Keep in sync with
 * normalizeRating() in src/testimonial/utils.js.
 *
 * @param mixed $rating Raw rating (number or numeric string).
 * @return int|float Rating 0–5 (an int for whole stars, a float for half stars).
 */
function acme_testimonials_normalize_rating( $rating ) {
	if ( is_string( $rating ) ) {
		$rating = trim( $rating );
	}
	if ( '' === $rating || null === $rating || is_bool( $rating ) || ! is_numeric( $rating ) ) {
		return 0;
	}
	$rating = round( max( 0.0, min( 5.0, (float) $rating ) ) * 2 ) / 2;
	return floor( $rating ) === $rating ? (int) $rating : $rating;
}

/**
 * State of the five stars for a rating.
 *
 * @param int|float $rating Normalised rating.
 * @return string[] 'full', 'half' or 'empty' for each star.
 */
function acme_testimonials_star_states( $rating ) {
	$states = array();
	for ( $star = 1; $star <= 5; $star++ ) {
		if ( $rating >= $star ) {
			$states[] = 'full';
		} elseif ( $rating >= $star - 0.5 ) {
			$states[] = 'half';
		} else {
			$states[] = 'empty';
		}
	}
	return $states;
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
