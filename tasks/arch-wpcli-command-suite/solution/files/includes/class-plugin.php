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
	 * Rule matcher (front end and WP-CLI).
	 *
	 * @var Matcher
	 */
	public $matcher;

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
	 * A validator bound to the repository.
	 *
	 * @return Rule_Validator
	 */
	public function validator() {
		return new Rule_Validator( $this->rules );
	}

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
		$this->matcher    = new Matcher( $this->rules );
		$this->redirector = new Redirector( $this->rules, $this->matcher );
		$this->redirector->register();

		if ( is_admin() ) {
			$this->admin = new Admin( $this->rules );
			$this->admin->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'acme-redirects', new CLI_Command( $this->rules, $this->matcher ) );
		}

		/**
		 * Fires once Acme Redirects is loaded.
		 *
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'acme_redirects_loaded', $this );
	}
}
