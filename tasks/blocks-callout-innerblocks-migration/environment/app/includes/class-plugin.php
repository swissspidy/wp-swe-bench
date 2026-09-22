<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Callouts
 */

namespace Acme\Callouts;

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
	 * Legacy shortcode.
	 *
	 * @var Shortcode
	 */
	public $shortcode;

	/**
	 * Block registration.
	 *
	 * @var Block
	 */
	public $block;

	/**
	 * Usage statistics.
	 *
	 * @var Stats
	 */
	public $stats;

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
		$this->shortcode = new Shortcode();
		$this->block     = new Block();
		$this->stats     = new Stats();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		$this->settings->register_hooks();
		$this->shortcode->register_hooks();
		$this->block->register_hooks();
		$this->stats->register_hooks();

		add_filter( 'plugin_action_links_' . plugin_basename( ACME_CALLOUTS_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-callouts', false, dirname( plugin_basename( ACME_CALLOUTS_FILE ) ) . '/languages' );
	}

	/**
	 * Add a "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=acme-callouts' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'acme-callouts' ) . '</a>' );
		return $links;
	}
}
