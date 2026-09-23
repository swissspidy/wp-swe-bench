<?php
/**
 * Block registration.
 *
 * @package Acme\Testimonials
 */

namespace Acme\Testimonials;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the acme/testimonial block.
 */
class Block {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the block from its compiled block.json.
	 */
	public function register() {
		$type = register_block_type( ACME_TESTIMONIALS_DIR . 'build/testimonial' );
		if ( ! $type ) {
			return;
		}
		foreach ( $type->editor_script_handles as $handle ) {
			wp_set_script_translations( $handle, 'acme-testimonials', ACME_TESTIMONIALS_DIR . 'languages' );
		}
	}
}
