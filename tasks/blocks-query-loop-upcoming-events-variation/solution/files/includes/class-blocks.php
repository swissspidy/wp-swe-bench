<?php
/**
 * Blocks.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the blocks from build/.
 */
class Blocks {

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Register block types.
	 */
	public function register_blocks() {
		register_block_type( ACME_EVENTS_DIR . 'build/event-date' );
	}
}
