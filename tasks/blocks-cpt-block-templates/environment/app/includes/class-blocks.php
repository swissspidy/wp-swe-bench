<?php
/**
 * Block registration.
 *
 * @package Acme\Courses
 */

namespace Acme\Courses;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin's blocks from blocks/<name>/block.json.
 */
class Blocks {

	/**
	 * Block directories (relative to blocks/).
	 *
	 * @var string[]
	 */
	protected $blocks = array( 'course-list' );

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Registers the blocks.
	 */
	public function register_blocks() {
		foreach ( $this->blocks as $block ) {
			register_block_type( ACME_COURSES_DIR . 'blocks/' . $block );
		}
	}
}
