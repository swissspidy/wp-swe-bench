<?php
/**
 * [acme_products] shortcode (classic pages and widgets).
 *
 * @package Acme\Catalog
 */

namespace Acme\Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Usage: [acme_products category="mugs,posters" default="mugs" search="no" orderby="price" heading="Our mugs" per_page="8"]
 */
class Shortcode {

	/**
	 * Register.
	 */
	public function register() {
		add_shortcode( 'acme_products', array( $this, 'render' ) );
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
				'category' => '',
				'default'  => '',
				'search'   => 'yes',
				'orderby'  => 'title',
				'heading'  => '',
				'columns'  => 3,
				'per_page' => 0,
			),
			$atts,
			'acme_products'
		);

		wp_enqueue_script_module( Grid::VIEW_MODULE );
		wp_enqueue_style( 'acme-product-grid-style' );

		// Shortcode output is not a block: process the directives here so that the
		// initial state is rendered on the server, like for the block.
		return wp_interactivity_process_directives(
			Grid::render(
				array(
					'heading'         => $atts['heading'],
					'categories'      => $atts['category'],
					'defaultCategory' => $atts['default'],
					'showSearch'      => ! in_array( strtolower( (string) $atts['search'] ), array( 'no', 'false', '0', 'off' ), true ),
					'orderBy'         => $atts['orderby'],
					'columns'         => $atts['columns'],
					'perPage'         => $atts['per_page'],
				)
			)
		);
	}
}
