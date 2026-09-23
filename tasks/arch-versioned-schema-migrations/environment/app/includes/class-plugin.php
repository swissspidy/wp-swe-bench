<?php
/**
 * Bootstrap.
 *
 * @package Acme\CRM
 */

namespace Acme\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * Wires everything up on plugins_loaded.
 */
class Plugin {

	/**
	 * Boot.
	 */
	public static function boot() {
		load_plugin_textdomain( 'acme-crm', false, dirname( plugin_basename( ACME_CRM_FILE ) ) . '/languages' );

		add_action( 'rest_api_init', array( REST_Controller::class, 'register_routes' ) );
		add_action( 'init', array( Contact_Form::class, 'register' ) );
		Export::register();

		if ( is_admin() ) {
			Admin::register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			CLI::register();
		}
	}
}
