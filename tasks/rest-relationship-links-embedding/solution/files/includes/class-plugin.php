<?php
/**
 * Bootstrap.
 *
 * @package Acme\Library
 */

namespace Acme\Library;

defined( 'ABSPATH' ) || exit;

/**
 * Registers everything.
 */
final class Plugin {

	/**
	 * Hooked to plugins_loaded.
	 */
	public static function boot(): void {
		Installer::maybe_upgrade();

		add_action( 'init', array( Post_Types::class, 'register' ) );
		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
		add_action( 'rest_api_init', array( new REST_Relations_Controller(), 'register_routes' ) );
		( new REST_Fields() )->hooks();

		Book_Counts::hooks();

		add_filter( 'the_content', __NAMESPACE__ . '\\filter_book_content' );

		if ( is_admin() ) {
			( new Admin\Book_Authors_Metabox() )->hooks();
			( new Admin\List_Columns() )->hooks();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'acme-library', CLI_Command::class );
		}
	}

	/**
	 * Translations.
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 'acme-library', false, dirname( plugin_basename( PLUGIN_FILE ) ) . '/languages' );
	}
}
