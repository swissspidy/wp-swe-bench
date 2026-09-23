<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Migrate
 */

namespace Acme\Migrate;

defined( 'ABSPATH' ) || exit;

/**
 * Wires up the admin screen and translations.
 */
final class Plugin {

	/**
	 * Singleton.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Admin screen.
	 *
	 * @var Admin_Page
	 */
	public $admin;

	/**
	 * Get (and boot) the plugin.
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
	 * Register hooks.
	 */
	private function boot() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		if ( is_admin() ) {
			$this->admin = new Admin_Page();
			$this->admin->register_hooks();
		}
	}

	/**
	 * Translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-migrate', false, dirname( plugin_basename( ACME_MIGRATE_FILE ) ) . '/languages' );
	}
}
