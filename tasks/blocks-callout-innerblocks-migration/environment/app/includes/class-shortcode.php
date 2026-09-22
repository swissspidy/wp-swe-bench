<?php
/**
 * Legacy [callout] shortcode (pre-block content).
 *
 * @package Acme\Callouts
 */

namespace Acme\Callouts;

defined( 'ABSPATH' ) || exit;

/**
 * [callout type="warning" title="Heads up"]Body text[/callout]
 */
class Shortcode {

	const TAG = 'callout';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the shortcode unless it was disabled in the settings.
	 */
	public function register() {
		if ( acme_callouts_get_option( 'enable_shortcode' ) ) {
			add_shortcode( self::TAG, array( $this, 'render' ) );
		}
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array|string $atts    Attributes.
	 * @param string|null  $content Enclosed content.
	 * @return string
	 */
	public function render( $atts, $content = null ) {
		$atts = shortcode_atts(
			array(
				'type'  => acme_callouts_get_option( 'default_type' ),
				'title' => '',
			),
			$atts,
			self::TAG
		);

		$types = acme_callouts_get_types();
		$type  = sanitize_key( $atts['type'] );
		if ( ! isset( $types[ $type ] ) ) {
			$type = 'info';
		}

		$html  = '<div class="acme-callout acme-callout--' . esc_attr( $type ) . '">';
		if ( '' !== $atts['title'] ) {
			$html .= '<p class="acme-callout__title">' . esc_html( $atts['title'] ) . '</p>';
		}
		$html .= '<div class="acme-callout__content">' . do_shortcode( wp_kses_post( (string) $content ) ) . '</div>';
		$html .= '</div>';

		return $html;
	}
}
