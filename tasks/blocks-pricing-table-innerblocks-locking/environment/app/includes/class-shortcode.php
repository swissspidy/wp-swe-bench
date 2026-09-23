<?php
/**
 * [acme_price] shortcode for prices in running text.
 *
 * Usage: [acme_price amount="19.50" currency="EUR"] → 19,50 €
 *
 * @package Acme\Pricing
 */

namespace Acme\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Inline price shortcode.
 */
class Shortcode {

	const TAG = 'acme_price';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the shortcode.
	 */
	public function register() {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'amount'   => '',
				'currency' => acme_pricing_get_option( 'default_currency' ),
			),
			$atts,
			self::TAG
		);

		$formatted = Currency::format( $atts['amount'], $atts['currency'] );
		if ( '' === $formatted ) {
			return '';
		}
		return '<span class="acme-price">' . esc_html( $formatted ) . '</span>';
	}
}
