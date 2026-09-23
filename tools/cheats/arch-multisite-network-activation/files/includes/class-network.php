<?php
/**
 * Multisite support.
 *
 * @package Acme\Directory
 */

namespace Acme\Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Network activation, new and deleted sites, network deactivation and uninstall.
 */
class Network {

	const SETUP_HOOK   = 'acme_directory_network_setup';
	const STATE_OPTION = 'acme_directory_network_setup';

	/**
	 * Hook up the multisite handlers.
	 */
	public static function register() {
		if ( ! is_multisite() ) {
			return;
		}
		add_action( 'wp_initialize_site', array( __CLASS__, 'on_initialize_site' ), 100 );
		add_filter( 'wpmu_drop_tables', array( __CLASS__, 'drop_tables' ), 10, 2 );
	}

	/**
	 * Whether the plugin is network-activated.
	 *
	 * @return bool
	 */
	public static function is_network_active() {
		$plugins = get_site_option( 'active_sitewide_plugins', array() );
		return is_array( $plugins ) && isset( $plugins[ plugin_basename( ACME_DIRECTORY_FILE ) ] );
	}

	/**
	 * Network activation: set up every site.
	 */
	public static function activate() {
		foreach ( get_sites( array( 'fields' => 'ids' ) ) as $site_id ) {
			Installer::install_site( (int) $site_id );
		}
	}

	/**
	 * Network deactivation: remove the cleanup event everywhere.
	 */
	public static function deactivate() {
		self::each_site(
			static function () {
				wp_clear_scheduled_hook( Installer::CLEANUP_HOOK );
			}
		);
	}

	/**
	 * New site.
	 *
	 * @param \WP_Site $site Site.
	 */
	public static function on_initialize_site( $site ) {
		if ( self::is_network_active() ) {
			Installer::install_site( (int) $site->blog_id );
		}
	}

	/**
	 * Drop our tables with the site.
	 *
	 * @param string[] $tables  Tables.
	 * @param int      $site_id Site ID.
	 * @return string[]
	 */
	public static function drop_tables( $tables, $site_id = 0 ) {
		return array_merge( (array) $tables, Schema::tables_for_site( $site_id ) );
	}

	/**
	 * Run a callback on every site.
	 *
	 * @param callable $callback Callback.
	 */
	public static function each_site( callable $callback ) {
		foreach ( get_sites( array( 'fields' => 'ids' ) ) as $id ) {
			switch_to_blog( (int) $id );
			$callback( (int) $id );
			restore_current_blog();
		}
	}
}
