<?php
/**
 * Plugin bootstrap: wires up all components.
 *
 * @package Acme\Tasks
 */

namespace Acme\Tasks;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 */
class Plugin {

	/**
	 * Hook everything up. Runs on plugins_loaded.
	 */
	public static function boot() {
		Installer::maybe_upgrade();

		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
		add_action( 'init', array( Post_Type::class, 'register' ) );
		add_filter( 'map_meta_cap', array( Access::class, 'map_meta_cap' ), 10, 4 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );

		Activity::init();
		Admin_Page::init();

		/**
		 * Fires once Acme Tasks has been loaded.
		 *
		 * @since 1.0.0
		 */
		do_action( 'acme_tasks_loaded' );
	}

	/**
	 * Load translations.
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'acme-tasks', false, dirname( plugin_basename( PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Register the REST controllers.
	 */
	public static function register_rest_routes() {
		( new Lists_Controller() )->register_routes();
		( new Tasks_Controller() )->register_routes();
		Activity::register_routes();
	}
}
