<?php
/**
 * Block registration.
 *
 * @package Acme\Faq
 */

namespace Acme\Faq;

defined( 'ABSPATH' ) || exit;

/**
 * Registers acme/faq and acme/faq-item (rendered on the server).
 */
class Blocks {

	/**
	 * Register block types from the build directory.
	 */
	public function register() {
		$build     = dirname( FILE ) . '/build';
		$callbacks = array(
			'faq'      => array( Renderer::class, 'render_faq' ),
			'faq-item' => array( Renderer::class, 'render_item' ),
		);
		foreach ( $callbacks as $block => $callback ) {
			if ( file_exists( "$build/$block/block.json" ) ) {
				register_block_type( "$build/$block", array( 'render_callback' => $callback ) );
			}
		}
		add_action( 'wp_head', array( Renderer::class, 'reset_ids' ), 0 );
	}
}
