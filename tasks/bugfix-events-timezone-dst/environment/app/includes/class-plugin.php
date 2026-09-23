<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin's components.
 */
class Plugin {

	/**
	 * Instance.
	 *
	 * @var Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Admin component.
	 *
	 * @var Admin
	 */
	public $admin;

	/**
	 * Front-end component.
	 *
	 * @var Frontend
	 */
	public $frontend;

	/**
	 * iCal feed.
	 *
	 * @var ICal
	 */
	public $ical;

	/**
	 * REST API.
	 *
	 * @var Rest
	 */
	public $rest;

	/**
	 * Gets (and boots) the plugin.
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
	 * Registers hooks.
	 */
	protected function boot() {
		add_action( 'init', array( Post_Type::class, 'register' ) );
		add_action( 'init', array( Upgrader::class, 'maybe_upgrade' ), 5 );
		add_action( 'init', array( $this, 'load_textdomain' ) );

		$this->admin    = new Admin();
		$this->frontend = new Frontend();
		$this->ical     = new ICal();
		$this->rest     = new Rest();

		if ( is_admin() ) {
			$this->admin->register();
		}
		$this->frontend->register();
		$this->ical->register();
		$this->rest->register();
	}

	/**
	 * Loads translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-events', false, dirname( plugin_basename( ACME_EVENTS_FILE ) ) . '/languages' );
	}

	/**
	 * Activation: register the post type and flush rewrite rules.
	 */
	public static function activate() {
		Post_Type::register();
		( new ICal() )->add_feed();
		flush_rewrite_rules();
	}

	/**
	 * Deactivation.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
