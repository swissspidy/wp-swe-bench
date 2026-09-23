<?php
/**
 * Signup block (server-rendered).
 *
 * @package Acme\Newsletter
 */

namespace Acme\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Registers acme/newsletter-signup.
 */
class Block {

	const NAME = 'acme/newsletter-signup';

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register the block type from the build directory.
	 */
	public function register_block() {
		register_block_type(
			ACME_NEWSLETTER_DIR . 'build/signup',
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		$settings = get_settings();
		$heading  = isset( $attributes['heading'] ) && '' !== $attributes['heading'] ? $attributes['heading'] : $settings['heading'];
		$button   = isset( $attributes['buttonLabel'] ) && '' !== $attributes['buttonLabel'] ? $attributes['buttonLabel'] : $settings['button_label'];

		return render_form(
			array(
				'heading'            => $heading,
				'button_label'       => $button,
				'show_name'          => ! empty( $attributes['showName'] ),
				'source'             => 'block',
				'wrapper_attributes' => get_block_wrapper_attributes( array( 'class' => 'acme-newsletter acme-newsletter--block' ) ),
			)
		);
	}
}
