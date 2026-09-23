<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Redirects
 */

namespace Acme\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the services together.
 */
final class Plugin {

	/**
	 * Singleton.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Rule storage.
	 *
	 * @var Rule_Repository
	 */
	public $rules;

	/**
	 * Front-end redirector.
	 *
	 * @var Redirector
	 */
	public $redirector;

	/**
	 * Admin screens (only in wp-admin).
	 *
	 * @var Admin|null
	 */
	public $admin = null;

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
	 * Boot.
	 */
	private function boot() {
		load_plugin_textdomain( 'acme-redirects', false, dirname( plugin_basename( ACME_REDIRECTS_FILE ) ) . '/languages' );

		Installer::maybe_upgrade();

		$this->rules      = new Rule_Repository();
		$this->redirector = new Redirector( $this->rules );
		$this->redirector->register();

		if ( is_admin() ) {
			$this->admin = new Admin( $this->rules );
			$this->admin->register();
		}

		/**
		 * Fires once Acme Redirects is loaded.
		 *
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'acme_redirects_loaded', $this );
	}
}
