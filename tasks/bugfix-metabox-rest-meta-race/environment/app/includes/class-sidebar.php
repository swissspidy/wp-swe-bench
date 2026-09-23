<?php
/**
 * Block editor sidebar panel.
 *
 * @package Acme\ProductFields
 */

namespace Acme\ProductFields;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the "Product details" document panel (src/sidebar).
 */
class Sidebar {

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue the panel for products.
	 */
	public function enqueue() {
		$screen = get_current_screen();
		if ( ! $screen || Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}
		$asset_file = ACME_PF_DIR . 'build/sidebar/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;
		wp_enqueue_script( 'acme-pf-sidebar', ACME_PF_URL . 'build/sidebar/index.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'acme-pf-sidebar', 'acme-product-fields', ACME_PF_DIR . 'languages' );
	}
}
