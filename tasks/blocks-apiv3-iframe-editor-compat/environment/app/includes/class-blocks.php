<?php
/**
 * Block registration.
 *
 * @package Acme\Charts
 */

namespace Acme\Charts;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the acme/chart and acme/chart-legend blocks.
 */
class Blocks {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_filter( 'block_categories_all', array( $this, 'category' ) );
	}

	/**
	 * Register both blocks from their compiled block.json files.
	 */
	public function register() {
		foreach ( array( 'chart', 'legend' ) as $block ) {
			$type = register_block_type( ACME_CHARTS_DIR . 'build/' . $block );
			if ( ! $type ) {
				continue;
			}
			foreach ( $type->editor_script_handles as $handle ) {
				wp_set_script_translations( $handle, 'acme-charts', ACME_CHARTS_DIR . 'languages' );
			}
		}
	}

	/**
	 * Put our blocks into their own inserter category.
	 *
	 * @param array[] $categories Block categories.
	 * @return array[]
	 */
	public function category( $categories ) {
		foreach ( $categories as $category ) {
			if ( 'acme' === $category['slug'] ) {
				return $categories;
			}
		}
		$categories[] = array(
			'slug'  => 'acme',
			'title' => __( 'Acme', 'acme-charts' ),
			'icon'  => null,
		);
		return $categories;
	}
}
