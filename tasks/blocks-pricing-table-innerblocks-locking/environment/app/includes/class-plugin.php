<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Pricing
 */

namespace Acme\Pricing;

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
	 * Block registration.
	 *
	 * @var Block
	 */
	public $block;

	/**
	 * Structured data.
	 *
	 * @var Schema
	 */
	public $schema;

	/**
	 * Price shortcode.
	 *
	 * @var Shortcode
	 */
	public $shortcode;

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
		$this->block     = new Block();
		$this->schema    = new Schema();
		$this->shortcode = new Shortcode();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		$this->settings->register_hooks();
		$this->block->register_hooks();
		$this->schema->register_hooks();
		$this->shortcode->register_hooks();

		add_filter( 'plugin_action_links_' . plugin_basename( ACME_PRICING_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-pricing', false, dirname( plugin_basename( ACME_PRICING_FILE ) ) . '/languages' );
	}

	/**
	 * Add a "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=acme-pricing' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'acme-pricing' ) . '</a>' );
		return $links;
	}
}
