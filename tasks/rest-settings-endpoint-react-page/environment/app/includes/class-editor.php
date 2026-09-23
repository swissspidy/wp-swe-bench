<?php
/**
 * Block editor sidebar panel for the per-post fields.
 *
 * @package Acme\SEO
 */

namespace Acme\SEO;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues build/editor (built from src/editor with `npm run build`).
 */
class Editor {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Enqueue the panel script.
	 */
	public static function enqueue() {
		$dir   = plugin_dir_path( PLUGIN_FILE ) . 'build/editor/';
		$asset = $dir . 'index.asset.php';
		if ( ! file_exists( $asset ) ) {
			return;
		}
		$asset = require $asset;
		wp_enqueue_script(
			'acme-seo-editor',
			plugins_url( 'build/editor/index.js', PLUGIN_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( 'acme-seo-editor', 'acme-seo', plugin_dir_path( PLUGIN_FILE ) . 'languages' );
	}
}
