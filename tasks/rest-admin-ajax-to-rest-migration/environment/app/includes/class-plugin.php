<?php
/**
 * Bootstrap.
 *
 * @package Acme\Inventory
 */

namespace Acme\Inventory;

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
	 * Wire up.
	 */
	private function boot() {
		load_plugin_textdomain( 'acme-inventory', false, dirname( plugin_basename( ACME_INVENTORY_FILE ) ) . '/languages' );
		$this->components = array(
			'alerts' => new Alerts(),
			'ajax'   => new Ajax(),
			'admin'  => new Admin(),
		);
		foreach ( $this->components as $component ) {
			$component->register();
		}
	}

	/**
	 * Component accessor.
	 *
	 * @param string $name Key.
	 * @return object|null
	 */
	public function get( $name ) {
		return isset( $this->components[ $name ] ) ? $this->components[ $name ] : null;
	}
}
