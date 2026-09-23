<?php
/**
 * Bootstrap.
 *
 * @package Acme\Directory
 */

namespace Acme\Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Wires everything up on plugins_loaded.
 */
class Plugin {

	/**
	 * Boot the plugin.
	 */
	public static function boot() {
		load_plugin_textdomain( 'acme-directory', false, dirname( plugin_basename( ACME_DIRECTORY_FILE ) ) . '/languages' );

		add_action( 'admin_init', array( Installer::class, 'maybe_upgrade' ), 5 );
		add_action( Installer::CLEANUP_HOOK, array( Cleanup::class, 'run' ) );
		add_action( 'rest_api_init', array( REST_Controller::class, 'register_routes' ) );
		add_action( 'init', array( Shortcodes::class, 'register' ) );

		if ( is_admin() ) {
			Admin::register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command(
				'acme-directory cleanup',
				static function () {
					$result = Cleanup::run();
					/* translators: 1: expired listings, 2: purged submissions */
					\WP_CLI::success( sprintf( __( 'Expired %1$d listing(s), purged %2$d submission(s).', 'acme-directory' ), $result['expired'], $result['purged'] ) );
				}
			);
		}
	}
}
