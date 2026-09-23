<?php
/**
 * Block registration.
 *
 * @package Acme\ContentBlocks
 */

namespace Acme\ContentBlocks;

defined( 'ABSPATH' ) || exit;

/**
 * Registers acme/notice-box and acme/stat.
 */
class Blocks {

	/**
	 * Blocks shipped by the plugin (directory names in build/).
	 *
	 * @var string[]
	 */
	const BLOCKS = array( 'notice-box', 'stat' );

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the blocks from their compiled block.json files.
	 */
	public function register() {
		foreach ( self::BLOCKS as $dir ) {
			$block = register_block_type( ACME_CONTENT_BLOCKS_DIR . 'build/' . $dir );
			if ( ! $block ) {
				continue;
			}
			$handle = $block->editor_script_handles[0] ?? '';
			if ( $handle ) {
				wp_set_script_translations( $handle, 'acme-content-blocks', ACME_CONTENT_BLOCKS_DIR . 'languages' );
			}
		}

		// Settings for the editor scripts (both blocks share them).
		$settings = wp_json_encode( $this->editor_settings() );
		foreach ( array( 'acme-notice-box-editor-script', 'acme-stat-editor-script' ) as $handle ) {
			wp_add_inline_script( $handle, 'window.acmeContentBlocks = ' . $settings . ';', 'before' );
		}
	}

	/**
	 * Settings exposed to the editor scripts.
	 *
	 * @return array
	 */
	public function editor_settings() {
		$tones = array();
		foreach ( acme_content_blocks_tones() as $slug => $label ) {
			$tones[] = array(
				'value' => $slug,
				'label' => $label,
			);
		}
		return array( 'tones' => $tones );
	}
}
