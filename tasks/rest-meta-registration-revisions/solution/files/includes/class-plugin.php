<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Specs
 */

namespace Acme\Specs;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the components together.
 */
final class Plugin {

	/**
	 * Option holding the version of the plugin that last ran its install routine.
	 */
	const VERSION_OPTION = 'acme_specs_version';

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Get the instance.
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
	 * Register hooks.
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_action( 'init', array( $this, 'load_textdomain' ) );

		( new Post_Type() )->register_hooks();
		( new Meta() )->register_hooks();
		( new Migration() )->register_hooks();
		( new Frontend() )->register_hooks();

		if ( is_admin() ) {
			( new Metabox() )->register_hooks();
			( new Admin_Columns() )->register_hooks();
		}
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-specs', false, dirname( plugin_basename( ACME_SPECS_FILE ) ) . '/languages' );
	}

	/**
	 * Activation: register the post type so its rewrite rules exist, remember the version.
	 */
	public static function activate() {
		Post_Type::register_post_type();
		flush_rewrite_rules();
		update_option( self::VERSION_OPTION, ACME_SPECS_VERSION );
		Migration::maybe_upgrade();
	}

	/**
	 * Deactivation.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
