<?php
/**
 * Block registration.
 *
 * @package Acme\CTA
 */

namespace Acme\CTA;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the acme/cta block and passes editor settings to it.
 */
class Block {

	/**
	 * Tracking component (used for the editor preview of tracked links).
	 *
	 * @var Tracking
	 */
	private $tracking;

	/**
	 * Constructor.
	 *
	 * @param Tracking $tracking Tracking component.
	 */
	public function __construct( Tracking $tracking ) {
		$this->tracking = $tracking;
	}

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
		$block = register_block_type( ACME_CTA_DIR . 'build/cta' );
		if ( ! $block ) {
			return;
		}

		$handle = $block->editor_script_handles[0] ?? '';
		if ( $handle ) {
			wp_add_inline_script(
				$handle,
				'window.acmeCta = ' . wp_json_encode( $this->editor_settings() ) . ';',
				'before'
			);
			wp_set_script_translations( $handle, 'acme-cta', ACME_CTA_DIR . 'languages' );
		}
	}

	/**
	 * Settings exposed to the editor script.
	 *
	 * @return array
	 */
	public function editor_settings() {
		$variants = array();
		foreach ( acme_cta_get_variants() as $slug => $label ) {
			$variants[] = array(
				'value' => $slug,
				'label' => $label,
			);
		}
		return array(
			'variants'        => $variants,
			'trackingEnabled' => $this->tracking->is_enabled(),
		);
	}
}
