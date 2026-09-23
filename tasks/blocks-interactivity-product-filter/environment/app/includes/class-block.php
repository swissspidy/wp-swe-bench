<?php
/**
 * The acme/product-grid block.
 *
 * @package Acme\Catalog
 */

namespace Acme\Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the block type.
 */
class Block {

	/**
	 * Register from build/.
	 */
	public function register() {
		$dir = dirname( FILE ) . '/build/product-grid';
		if ( ! file_exists( $dir . '/block.json' ) ) {
			return;
		}
		register_block_type(
			$dir,
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
		add_action( 'wp_enqueue_scripts', array( Grid::class, 'localize' ), 20 );
	}

	/**
	 * Render callback.
	 *
	 * @param array $attributes Attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		return Grid::render( $attributes, get_block_wrapper_attributes() );
	}
}
