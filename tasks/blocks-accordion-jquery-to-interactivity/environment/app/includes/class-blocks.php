<?php
/**
 * Block registration.
 *
 * @package Acme\Faq
 */

namespace Acme\Faq;

defined( 'ABSPATH' ) || exit;

/**
 * Registers acme/faq and acme/faq-item.
 */
class Blocks {

	/**
	 * Register block types from the build directory.
	 */
	public function register() {
		$build = dirname( FILE ) . '/build';
		foreach ( array( 'faq', 'faq-item' ) as $block ) {
			if ( file_exists( "$build/$block/block.json" ) ) {
				register_block_type( "$build/$block" );
			}
		}
	}
}
