<?php
/**
 * Block registration.
 *
 * @package Acme\Callouts
 */

namespace Acme\Callouts;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the acme/callout block and passes editor settings to it.
 */
class Block {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the block from its compiled block.json.
	 */
	public function register() {
		$block = register_block_type( ACME_CALLOUTS_DIR . 'build/callout' );
		if ( ! $block ) {
			return;
		}

		$handle = $block->editor_script_handles[0] ?? '';
		if ( $handle ) {
			wp_add_inline_script(
				$handle,
				'window.acmeCallouts = ' . wp_json_encode( $this->editor_settings() ) . ';',
				'before'
			);
			wp_set_script_translations( $handle, 'acme-callouts', ACME_CALLOUTS_DIR . 'languages' );
		}
	}

	/**
	 * Settings exposed to the editor script.
	 *
	 * @return array
	 */
	public function editor_settings() {
		$types = array();
		foreach ( acme_callouts_get_types() as $slug => $label ) {
			$types[] = array(
				'value' => $slug,
				'label' => $label,
			);
		}
		return array(
			'types'       => $types,
			'defaultType' => acme_callouts_get_option( 'default_type' ),
		);
	}
}
