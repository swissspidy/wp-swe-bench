<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Bookings
 */

namespace Acme\Bookings;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin.
 */
final class Plugin {

	/**
	 * Instance.
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
	 * Singleton.
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
	 * Wire up the components.
	 */
	private function boot() {
		load_plugin_textdomain( 'acme-bookings', false, dirname( plugin_basename( ACME_BOOKINGS_FILE ) ) . '/languages' );

		$this->components = array(
			'rooms'         => new Rooms(),
			'notifications' => new Notifications(),
			'rest'          => new Rest(),
			'admin'         => new Admin(),
			'widget'        => new Widget(),
		);
		foreach ( $this->components as $component ) {
			$component->register();
		}
	}

	/**
	 * Access a component (e.g. for unhooking).
	 *
	 * @param string $name Component key.
	 * @return object|null
	 */
	public function get( $name ) {
		return isset( $this->components[ $name ] ) ? $this->components[ $name ] : null;
	}
}
