<?php
/**
 * Block registration.
 *
 * @package Acme\Toc
 */

namespace Acme\Toc;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the acme/toc block and passes settings to the editor.
 */
class Block {

	/**
	 * Register the block type from the build directory.
	 */
	public function register() {
		$dir = dirname( FILE ) . '/build/toc';
		if ( ! file_exists( $dir . '/block.json' ) ) {
			return;
		}

		$type = register_block_type( $dir );
		if ( ! $type ) {
			return;
		}

		wp_set_script_translations( $type->editor_script_handles[0] ?? '', 'acme-toc', dirname( FILE ) . '/languages' );

		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_settings' ) );
	}

	/**
	 * Settings the editor script needs (the heading list is computed in the editor).
	 */
	public function editor_settings() {
		$options  = get_options();
		$settings = array(
			'excludedBlocks'  => excluded_blocks(),
			'defaultMaxLevel' => clamp_level( $options['max_level'] ),
		);
		wp_add_inline_script(
			'acme-toc-editor-script',
			'window.acmeTocSettings = ' . wp_json_encode( $settings ) . ';',
			'before'
		);
	}
}
