<?php
/**
 * Template tags and formatting helpers.
 *
 * @package Acme\Specs
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get a product's specs (template tag).
 *
 * @param int|WP_Post|null $post Product (default: current post).
 * @return array{dimensions: array|null, materials: string[], certifications: array[]}
 */
function acme_specs_get( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return Acme\Specs\Specs::empty_specs();
	}
	return Acme\Specs\Specs::get( $post->ID );
}

/**
 * Format a number without useless trailing zeros ("120", "35.5").
 *
 * @param float|int|string $number Number.
 * @return string
 */
function acme_specs_format_number( $number ) {
	$formatted = number_format( (float) $number, 2, '.', '' );
	return rtrim( rtrim( $formatted, '0' ), '.' );
}

/**
 * "120 × 75 × 60 cm".
 *
 * @param array $dimensions Dimensions (width, height, depth, unit).
 * @return string
 */
function acme_specs_format_dimensions( array $dimensions ) {
	return sprintf(
		/* translators: 1: width, 2: height, 3: depth, 4: unit */
		__( '%1$s × %2$s × %3$s %4$s', 'acme-specs' ),
		acme_specs_format_number( $dimensions['width'] ),
		acme_specs_format_number( $dimensions['height'] ),
		acme_specs_format_number( $dimensions['depth'] ),
		$dimensions['unit']
	);
}

/**
 * "since 2019-03-01, valid until 2029-03-01".
 *
 * @param array $cert Certification.
 * @return string
 */
function acme_specs_format_cert_dates( array $cert ) {
	if ( ! empty( $cert['expires'] ) ) {
		/* translators: 1: issue date, 2: expiry date */
		return sprintf( __( 'since %1$s, valid until %2$s', 'acme-specs' ), $cert['issued'], $cert['expires'] );
	}
	/* translators: %s: issue date */
	return sprintf( __( 'since %s', 'acme-specs' ), $cert['issued'] );
}
