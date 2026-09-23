<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Contact
 */

namespace Acme\Contact;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the components together.
 */
final class Plugin {

	/**
	 * Singleton.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Components.
	 *
	 * @var object[]
	 */
	private $components = array();

	/**
	 * Instance.
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
		add_action( 'init', array( $this, 'load_textdomain' ) );
		// The plugin is updated in place (never re-activated): create/upgrade the table on load.
		add_action( 'plugins_loaded', array( Entries::class, 'maybe_install' ) );
		$this->components = array(
			'shortcode'     => new Shortcode(),
			'handler'       => new Submission_Handler(),
			'settings'      => new Settings(),
			'blocks'        => new Blocks(),
			'rest'          => new Rest(),
			'entries_admin' => new Entries_Admin(),
		);
		foreach ( $this->components as $component ) {
			$component->register();
		}
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-contact', false, dirname( plugin_basename( ACME_CONTACT_FILE ) ) . '/languages' );
	}
}
