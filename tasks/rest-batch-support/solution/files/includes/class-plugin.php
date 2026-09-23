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
		// Must be in place before the REST server is created: the batch route's size limit
		// is read when the server registers its own routes, before rest_api_init.
		add_filter( 'rest_get_max_batch_size', array( __CLASS__, 'max_batch_size' ) );

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
	 * Allow batches of up to MAX_BATCH_SIZE requests (the app's sync chunk size).
	 *
	 * @param int $size Maximum batch size.
	 * @return int
	 */
	public static function max_batch_size( $size ) {
		return max( (int) $size, MAX_BATCH_SIZE );
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
