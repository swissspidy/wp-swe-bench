<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme_Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires up the plugin's components.
 */
final class Acme_Social_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Acme_Social_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Returns (and on first call boots) the plugin.
	 *
	 * @return Acme_Social_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Registers all hooks.
	 */
	private function boot() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		( new Acme_Social_Upgrader() )->register();
		( new Acme_Social_Settings() )->register();
		( new Acme_Social_Legacy() )->register();
		( new Acme_Social_Share_Buttons() )->register();
		( new Acme_Social_Open_Graph() )->register();
		( new Acme_Social_Profiles() )->register();
		( new Acme_Social_Post_Meta() )->register();

		if ( is_admin() ) {
			( new Acme_Social_Admin() )->register();
		}
	}

	/**
	 * Loads translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-social', false, dirname( plugin_basename( ACME_SOCIAL_FILE ) ) . '/languages' );
	}

	/**
	 * Front-end stylesheet for the share buttons and profile links.
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'acme-social', ACME_SOCIAL_URL . 'assets/css/acme-social.css', array(), ACME_SOCIAL_VERSION );
	}
}
