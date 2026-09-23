<?php
/**
 * Bootstrap.
 *
 * @package Acme\UI
 */

namespace Acme\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 */
final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/** @var Assets */
	public $assets;

	/** @var Settings */
	public $settings;

	/**
	 * Instance.
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
	 * Wire everything up.
	 */
	private function boot() {
		$this->settings = new Settings();
		$this->assets   = new Assets( $this->settings );
		$renderer       = new Renderer();

		$this->settings->register_hooks();
		$this->assets->register_hooks();
		( new Blocks( $renderer, $this->assets ) )->register_hooks();
		( new Shortcodes( $renderer, $this->assets ) )->register_hooks();

		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-ui-kit', false, dirname( plugin_basename( ACME_UI_FILE ) ) . '/languages' );
	}
}
