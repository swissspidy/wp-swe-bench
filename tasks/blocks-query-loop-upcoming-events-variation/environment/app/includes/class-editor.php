<?php
/**
 * Block editor integration (event details sidebar).
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the editor script.
 */
class Editor {

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue build/index.js (the "Event details" panel) on event screens.
	 */
	public function enqueue() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || POST_TYPE !== $screen->post_type ) {
			return;
		}
		$asset_file = ACME_EVENTS_DIR . 'build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;
		wp_enqueue_script(
			'acme-events-editor',
			ACME_EVENTS_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( 'acme-events-editor', 'acme-events-lite' );
	}
}
