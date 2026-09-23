<?php
/**
 * [acme_newsletter] shortcode.
 *
 * @package Acme\Newsletter
 */

namespace Acme\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode for classic content.
 */
class Shortcode {

	const TAG = 'acme_newsletter';

	/**
	 * Register the shortcode.
	 */
	public function register() {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Render callback.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$settings = get_settings();
		$atts     = shortcode_atts(
			array(
				'heading' => $settings['heading'],
				'button'  => $settings['button_label'],
			),
			$atts,
			self::TAG
		);
		return render_form(
			array(
				'heading'      => $atts['heading'],
				'button_label' => $atts['button'],
				'source'       => 'shortcode',
			)
		);
	}
}
