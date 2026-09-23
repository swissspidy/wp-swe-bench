<?php
/**
 * Main plugin class: wires up the components.
 *
 * @package Acme\ActivityLog
 */

namespace Acme\ActivityLog;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap.
 */
final class Plugin {

	/**
	 * Option holding the installed version (used for upgrades).
	 */
	const VERSION_OPTION = 'acme_activity_version';

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Log storage.
	 *
	 * @var Log_Store
	 */
	public $store;

	/**
	 * Per-user UI state.
	 *
	 * @var User_State
	 */
	public $state;

	/**
	 * Get (and on first call, boot) the plugin.
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
	 * Wire hooks.
	 */
	private function boot() {
		$this->store = new Log_Store();
		$this->state = new User_State();

		load_plugin_textdomain( 'acme-activity-log', false, dirname( plugin_basename( ACME_ACTIVITY_FILE ) ) . '/languages' );

		( new Tracker() )->register();
		( new Settings() )->register();
		( new Rest() )->register();

		if ( is_admin() ) {
			( new Admin_Page( $this->store, $this->state ) )->register();
		}

		add_action( 'init', array( $this, 'maybe_upgrade' ), 5 );
	}

	/**
	 * Activation: make sure our options exist.
	 */
	public static function activate() {
		if ( false === get_option( Settings::OPTION ) ) {
			add_option( Settings::OPTION, Settings::defaults() );
		}
		if ( false === get_option( Log_Store::OPTION ) ) {
			add_option( Log_Store::OPTION, array(), '', true );
		}
		update_option( self::VERSION_OPTION, ACME_ACTIVITY_VERSION );
	}

	/**
	 * Run upgrade routines after an in-place update.
	 */
	public function maybe_upgrade() {
		$installed = get_option( self::VERSION_OPTION, '0' );
		if ( version_compare( $installed, ACME_ACTIVITY_VERSION, '>=' ) ) {
			return;
		}

		// 2.0: settings got the "tracked" list.
		$settings = get_option( Settings::OPTION, array() );
		if ( ! is_array( $settings ) || ! isset( $settings['tracked'] ) ) {
			update_option( Settings::OPTION, wp_parse_args( is_array( $settings ) ? $settings : array(), Settings::defaults() ) );
		}

		update_option( self::VERSION_OPTION, ACME_ACTIVITY_VERSION );
	}
}
