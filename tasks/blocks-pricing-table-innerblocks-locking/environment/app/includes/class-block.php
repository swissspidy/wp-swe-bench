<?php
/**
 * Block registration.
 *
 * @package Acme\Pricing
 */

namespace Acme\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the pricing table block and passes settings to the editor.
 */
class Block {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_data' ) );
	}

	/**
	 * Register the block type from its (built) block.json.
	 */
	public function register() {
		register_block_type( ACME_PRICING_DIR . 'build/pricing-table' );
	}

	/**
	 * Data the editor script needs: currencies (with formatting rules) and the default currency.
	 *
	 * @return array
	 */
	public static function editor_settings() {
		$currencies = array();
		foreach ( acme_pricing_currencies() as $code => $rules ) {
			$currencies[ $code ] = array(
				'label'     => $rules['label'],
				'symbol'    => $rules['symbol'],
				'position'  => $rules['position'],
				'space'     => (bool) $rules['space'],
				'decimal'   => $rules['decimal'],
				'thousands' => $rules['thousands'],
				'whole'     => $rules['whole'],
			);
		}

		return array(
			'currencies'      => $currencies,
			'defaultCurrency' => acme_pricing_get_option( 'default_currency' ),
		);
	}

	/**
	 * Print the editor settings before the block's editor script.
	 */
	public function editor_data() {
		$handle = generate_block_asset_handle( 'acme/pricing-table', 'editorScript' );
		wp_add_inline_script(
			$handle,
			'window.acmePricing = ' . wp_json_encode( self::editor_settings() ) . ';',
			'before'
		);
	}
}
