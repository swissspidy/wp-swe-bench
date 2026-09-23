<?php
/**
 * Template tags and small helpers.
 *
 * These functions are used by themes (see the "Theme integration" section of
 * readme.txt), so their signatures must stay stable.
 *
 * @package Acme\Pricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default plugin options.
 *
 * @return array
 */
function acme_pricing_default_options() {
	return array(
		'default_currency' => 'USD',
		'schema'           => true,
	);
}

/**
 * Get one plugin option (merged with the defaults).
 *
 * @param string $key Option key.
 * @return mixed|null
 */
function acme_pricing_get_option( $key ) {
	$options = get_option( 'acme_pricing_options', array() );
	$options = wp_parse_args( is_array( $options ) ? $options : array(), acme_pricing_default_options() );
	return isset( $options[ $key ] ) ? $options[ $key ] : null;
}

/**
 * Supported currencies: code => formatting rules.
 *
 * Rules:
 * - symbol:    currency symbol or code.
 * - position:  'before' or 'after' the amount.
 * - space:     whether a (non-breaking in HTML, plain in data) space separates symbol and amount.
 * - decimal:   decimal separator.
 * - thousands: thousands separator.
 * - whole:     suffix used instead of decimals for whole amounts ('' = just drop the decimals).
 *
 * @return array<string, array>
 */
function acme_pricing_currencies() {
	$currencies = array(
		'USD' => array(
			'label'     => __( 'US dollar', 'acme-pricing' ),
			'symbol'    => '$',
			'position'  => 'before',
			'space'     => false,
			'decimal'   => '.',
			'thousands' => ',',
			'whole'     => '',
		),
		'EUR' => array(
			'label'     => __( 'Euro', 'acme-pricing' ),
			'symbol'    => '€',
			'position'  => 'after',
			'space'     => true,
			'decimal'   => ',',
			'thousands' => '.',
			'whole'     => '',
		),
		'GBP' => array(
			'label'     => __( 'Pound sterling', 'acme-pricing' ),
			'symbol'    => '£',
			'position'  => 'before',
			'space'     => false,
			'decimal'   => '.',
			'thousands' => ',',
			'whole'     => '',
		),
		'CHF' => array(
			'label'     => __( 'Swiss franc', 'acme-pricing' ),
			'symbol'    => 'CHF',
			'position'  => 'before',
			'space'     => true,
			'decimal'   => '.',
			'thousands' => "'",
			'whole'     => '.–',
		),
	);

	/**
	 * Filters the currencies available in pricing tables.
	 *
	 * @param array $currencies Currency code => formatting rules.
	 */
	return apply_filters( 'acme_pricing_currencies', $currencies );
}

/**
 * Format an amount in a currency, e.g. "$19", "19,50 €", "CHF 49.–".
 *
 * @param string|float $amount   Amount ("19", "19.5", 19.5).
 * @param string       $currency Currency code.
 * @return string Plain text (not escaped).
 */
function acme_pricing_format_price( $amount, $currency = '' ) {
	return Acme\Pricing\Currency::format( $amount, $currency ? $currency : acme_pricing_get_option( 'default_currency' ) );
}

/**
 * Does a post contain at least one pricing table?
 *
 * @param int|WP_Post|null $post Post.
 * @return bool
 */
function acme_pricing_has_table( $post = null ) {
	$post = get_post( $post );
	return $post && has_block( 'acme/pricing-table', $post );
}
