<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Newsroom
 */

namespace Acme\Newsroom;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin components together.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Components.
	 *
	 * @var object[]
	 */
	public $components = array();

	/**
	 * Get the plugin instance.
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
	 * Hook everything up.
	 */
	public function boot() {
		$this->components = array(
			'press_releases' => new Press_Releases(),
			'blocks'         => new Blocks(),
			'settings'       => new Settings(),
			'workarounds'    => new Editor_Workarounds(),
		);
		foreach ( $this->components as $component ) {
			$component->register_hooks();
		}
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-newsroom', false, dirname( plugin_basename( ACME_NEWSROOM_FILE ) ) . '/languages' );
	}
}
