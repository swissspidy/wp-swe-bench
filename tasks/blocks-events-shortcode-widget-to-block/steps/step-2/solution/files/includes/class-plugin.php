<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

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
	 * Post type + taxonomy.
	 *
	 * @var Post_Type
	 */
	public $post_type;

	/**
	 * Admin screens (meta box, list columns).
	 *
	 * @var Admin
	 */
	public $admin;

	/**
	 * The [acme_events] shortcode.
	 *
	 * @var Shortcode
	 */
	public $shortcode;

	/**
	 * Blocks.
	 *
	 * @var Blocks
	 */
	public $blocks;

	/**
	 * One-time upgrade of classic widgets.
	 *
	 * @var Widget_Migration
	 */
	public $widget_migration;

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
		$this->post_type = new Post_Type();
		$this->admin     = new Admin();
		$this->shortcode = new Shortcode();
		$this->blocks    = new Blocks();

		$this->widget_migration = new Widget_Migration();

		add_action( 'init', array( $this, 'load_textdomain' ) );

		$this->post_type->register_hooks();
		$this->admin->register_hooks();
		$this->shortcode->register_hooks();
		$this->blocks->register_hooks();
		$this->widget_migration->register_hooks();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'acme-events', __NAMESPACE__ . '\\CLI' );
		}
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-events', false, dirname( plugin_basename( ACME_EVENTS_FILE ) ) . '/languages' );
	}
}
