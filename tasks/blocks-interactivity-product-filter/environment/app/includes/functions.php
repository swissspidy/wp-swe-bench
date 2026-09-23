<?php
/**
 * Helper functions.
 *
 * @package Acme\Catalog
 */

namespace Acme\Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin options merged with defaults.
 *
 * @return array{currency:string, currency_position:string}
 */
function get_options() {
	$options = get_option( 'acme_catalog_options', array() );
	if ( ! is_array( $options ) ) {
		$options = array();
	}
	return array_merge(
		array(
			'currency'          => '$',
			'currency_position' => 'before',
		),
		$options
	);
}

/**
 * Format a price with the shop currency.
 *
 * @param float|string $amount Amount.
 * @return string Plain text, e.g. "12.00 €" or "$12.00".
 */
function format_price( $amount ) {
	$options = get_options();
	$number  = number_format_i18n( (float) $amount, 2 );
	return 'after' === $options['currency_position'] ? $number . ' ' . $options['currency'] : $options['currency'] . $number;
}

/**
 * Normalize a list of category slugs (array or comma-separated string).
 *
 * @param mixed $value Raw value.
 * @return string[]
 */
function parse_slugs( $value ) {
	if ( is_string( $value ) ) {
		$value = explode( ',', $value );
	}
	if ( ! is_array( $value ) ) {
		return array();
	}
	return array_values( array_unique( array_filter( array_map( 'sanitize_title', $value ) ) ) );
}
