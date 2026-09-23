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

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'widgets_init', array( $this, 'register_widgets' ) );

		$this->post_type->register_hooks();
		$this->admin->register_hooks();
		$this->shortcode->register_hooks();
		$this->blocks->register_hooks();

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

	/**
	 * Register the classic widget.
	 */
	public function register_widgets() {
		register_widget( __NAMESPACE__ . '\\Upcoming_Events_Widget' );
	}
}
