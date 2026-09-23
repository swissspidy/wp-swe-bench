<?php
/**
 * Bootstrap.
 *
 * @package Acme\SEO
 */

namespace Acme\SEO;

defined( 'ABSPATH' ) || exit;

/**
 * Wires up the components.
 */
class Plugin {

	/**
	 * Runs on plugins_loaded.
	 */
	public static function boot() {
		add_action( 'init', array( Upgrader::class, 'maybe_upgrade' ), 99 );
		add_action( 'init', array( Compat::class, 'init' ), 100 );
		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );

		Settings::init();

		Post_Meta::init();
		Title::init();
		Head::init();
		Robots::init();
		Sitemap::init();
		Editor::init();

		if ( is_admin() ) {
			Settings_Page::init();
		}

		/**
		 * Fires when Acme SEO has loaded.
		 *
		 * @since 1.0.0
		 */
		do_action( 'acme_seo_loaded' );
	}

	/**
	 * Translations.
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'acme-seo', false, dirname( plugin_basename( PLUGIN_FILE ) ) . '/languages' );
	}
}
