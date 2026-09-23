<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\CTA
 */

namespace Acme\CTA;

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
	 * Settings screen.
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * Campaign click tracking.
	 *
	 * @var Tracking
	 */
	public $tracking;

	/**
	 * Block registration.
	 *
	 * @var Block
	 */
	public $block;

	/**
	 * CTA inventory (Tools → CTA inventory, `wp acme-cta list`).
	 *
	 * @var Inventory
	 */
	public $inventory;

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
		$this->settings  = new Settings();
		$this->tracking  = new Tracking();
		$this->block     = new Block( $this->tracking );
		$this->inventory = new Inventory();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		$this->settings->register_hooks();
		$this->tracking->register_hooks();
		$this->block->register_hooks();
		$this->inventory->register_hooks();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'acme-cta', new CLI( $this->inventory ) );
		}
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-cta', false, dirname( plugin_basename( ACME_CTA_FILE ) ) . '/languages' );
	}
}
