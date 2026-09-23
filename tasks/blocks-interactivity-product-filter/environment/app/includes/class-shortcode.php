<?php
/**
 * [acme_products] shortcode (classic pages and widgets).
 *
 * @package Acme\Catalog
 */

namespace Acme\Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Usage: [acme_products category="mugs,posters" default="mugs" search="no" orderby="price" heading="Our mugs"]
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
			),
			$atts,
			'acme_products'
		);

		wp_enqueue_script( Grid::VIEW_SCRIPT );
		wp_enqueue_style( 'acme-product-grid-style' );
		Grid::localize();

		return Grid::render(
			array(
				'heading'         => $atts['heading'],
				'categories'      => $atts['category'],
				'defaultCategory' => $atts['default'],
				'showSearch'      => ! in_array( strtolower( (string) $atts['search'] ), array( 'no', 'false', '0', 'off' ), true ),
				'orderBy'         => $atts['orderby'],
				'columns'         => $atts['columns'],
			)
		);
	}
}
