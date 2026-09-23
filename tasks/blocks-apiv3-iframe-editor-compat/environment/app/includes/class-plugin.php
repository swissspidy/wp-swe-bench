<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Charts
 */

namespace Acme\Charts;

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
	 * Script and style loading.
	 *
	 * @var Assets
	 */
	public $assets;

	/**
	 * Block registration.
	 *
	 * @var Blocks
	 */
	public $blocks;

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
		$this->settings = new Settings();
		$this->assets   = new Assets();
		$this->blocks   = new Blocks();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		$this->settings->register_hooks();
		$this->assets->register_hooks();
		$this->blocks->register_hooks();

		add_filter( 'plugin_action_links_' . plugin_basename( ACME_CHARTS_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-charts', false, dirname( plugin_basename( ACME_CHARTS_FILE ) ) . '/languages' );
	}

	/**
	 * Add a "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=acme-charts' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'acme-charts' ) . '</a>' );
		return $links;
	}
}
