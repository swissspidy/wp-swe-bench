<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\ContentBlocks
 */

namespace Acme\ContentBlocks;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin components together.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Block registration.
	 *
	 * @var Blocks
	 */
	public $blocks;

	/**
	 * REST API for the mobile app.
	 *
	 * @var Notices_API
	 */
	public $notices_api;

	/**
	 * Get the plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook everything up.
	 */
	public function boot() {
		$this->blocks      = new Blocks();
		$this->notices_api = new Notices_API();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		$this->blocks->register_hooks();
		$this->notices_api->register_hooks();
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-content-blocks', false, dirname( plugin_basename( ACME_CONTENT_BLOCKS_FILE ) ) . '/languages' );
	}
}
