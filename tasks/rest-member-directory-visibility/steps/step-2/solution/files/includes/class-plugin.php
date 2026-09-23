<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Members
 */

namespace Acme\Members;

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
		// Sites update the plugin in place: import the forum buddy lists on first load.
		add_action( 'init', array( Connections::class, 'maybe_migrate_legacy' ), 5 );

		$this->components = array(
			'profile_page' => new Profile_Page(),
			'directory'    => new Directory(),
			'rest_fields'  => new Rest_Fields(),
			'feeds'        => new Feeds(),
			'sitemaps'     => new Sitemaps(),
			'admin'        => new Admin(),
			'rest'         => new Rest_Controller(),
			'profile_form' => new Profile_Form(),
		);
		foreach ( $this->components as $component ) {
			$component->register();
		}
	}

	/**
	 * A component.
	 *
	 * @param string $name Component name.
	 * @return object|null
	 */
	public function get( $name ) {
		return isset( $this->components[ $name ] ) ? $this->components[ $name ] : null;
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-members', false, dirname( plugin_basename( ACME_MEMBERS_FILE ) ) . '/languages' );
	}
}
