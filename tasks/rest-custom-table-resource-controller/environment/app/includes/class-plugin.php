<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Leads
 */

namespace Acme\Leads;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the components together.
 */
final class Plugin {

	/**
	 * Instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether boot() ran.
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
		add_action( 'admin_init', array( Installer::class, 'maybe_upgrade' ) );

		( new Rest() )->register_hooks();
		( new Form() )->register_hooks();
		( new Notifications() )->register_hooks();

		if ( is_admin() ) {
			( new Admin() )->register_hooks();
		}
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-leads', false, dirname( plugin_basename( ACME_LEADS_FILE ) ) . '/languages' );
	}
}
