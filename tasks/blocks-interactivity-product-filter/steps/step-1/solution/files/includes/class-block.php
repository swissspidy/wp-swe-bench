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
	}

	/**
	 * Render callback. Directives are processed by WordPress for interactive blocks.
	 *
	 * @param array $attributes Attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		return Grid::render( $attributes, get_block_wrapper_attributes() );
	}
}
