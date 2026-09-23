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
 *
 * The course meta blocks (price, duration, enroll button) take the course from
 * the block context (postId), so they work in templates and inside a Query Loop.
 */
class Blocks {

	/**
	 * Block directories (relative to blocks/).
	 *
	 * @var string[]
	 */
	protected $blocks = array( 'course-list', 'course-price', 'course-duration', 'enroll-button' );

	/**
	 * Script handle of the shared editor script of the course meta blocks.
	 */
	const META_EDITOR_SCRIPT = 'acme-courses-course-meta-editor';

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
		$asset = include ACME_COURSES_DIR . 'blocks/course-meta/editor.asset.php';
		wp_register_script(
			self::META_EDITOR_SCRIPT,
			ACME_COURSES_URL . 'blocks/course-meta/editor.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( self::META_EDITOR_SCRIPT, 'acme-courses', ACME_COURSES_DIR . 'languages' );

		foreach ( $this->blocks as $block ) {
			register_block_type( ACME_COURSES_DIR . 'blocks/' . $block );
		}
	}
}
