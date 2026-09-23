<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\ProductFields
 */

namespace Acme\ProductFields;

defined( 'ABSPATH' ) || exit;

/**
 * Wires everything up.
 */
final class Plugin {

	/**
	 * Instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Register all hooks.
	 */
	private function boot() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		( new Post_Type() )->register_hooks();
		( new Meta() )->register_hooks();
		( new Settings() )->register_hooks();
		( new Metabox() )->register_hooks();
		( new List_Table() )->register_hooks();
		( new Sidebar() )->register_hooks();
		( new Frontend() )->register_hooks();
	}

	/**
	 * Translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-product-fields', false, dirname( plugin_basename( ACME_PF_FILE ) ) . '/languages' );
	}

	/**
	 * Activation: register the post type and flush rewrite rules.
	 */
	public static function activate() {
		( new Post_Type() )->register();
		flush_rewrite_rules();
	}
}
