<?php
/**
 * Block registration.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin's blocks from their compiled block.json files in build/.
 */
class Blocks {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register blocks.
	 */
	public function register() {
		$block = register_block_type( ACME_EVENTS_DIR . 'build/event-details' );
		if ( $block && ! empty( $block->editor_script_handles[0] ) ) {
			wp_set_script_translations( $block->editor_script_handles[0], 'acme-events', ACME_EVENTS_DIR . 'languages' );
		}
	}
}
