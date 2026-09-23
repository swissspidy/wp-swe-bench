<?php
/**
 * Activation, deactivation and upgrades.
 *
 * @package Acme\Directory
 */

namespace Acme\Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Sets the plugin up on a site.
 */
class Installer {

	const DB_VERSION_OPTION   = 'acme_directory_db_version';
	const INSTALLED_AT_OPTION = 'acme_directory_installed_at';
	const CLEANUP_HOOK        = 'acme_directory_daily_cleanup';

	/**
	 * Activation hook.
	 *
	 * @param bool $network_wide Whether the plugin is being network-activated.
	 */
	public static function activate( $network_wide = false ) {
		// @todo Multisite: only the current site is set up.
		self::install();
	}

	/**
	 * Deactivation hook. Data stays until the plugin is deleted (see uninstall.php).
	 *
	 * @param bool $network_wide Whether the plugin is being network-deactivated.
	 */
	public static function deactivate( $network_wide = false ) {
		wp_clear_scheduled_hook( self::CLEANUP_HOOK );
	}

	/**
	 * Set the plugin up on the current site: tables, options, default category, cron.
	 * Safe to run more than once.
	 */
	public static function install() {
		Schema::create_tables();

		add_option( Settings::OPTION, Settings::defaults() );
		add_option( self::INSTALLED_AT_OPTION, time(), '', false );
		update_option( self::DB_VERSION_OPTION, DB_VERSION, false );

		Categories::ensure_default();
		delete_transient( Categories::COUNTS_TRANSIENT );

		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( self::next_cleanup_time(), 'daily', self::CLEANUP_HOOK );
		}

		/**
		 * Fires after the directory was set up (or upgraded) on the current site.
		 *
		 * The network mu-plugin uses this to add network-wide categories.
		 */
		do_action( 'acme_directory_installed' );
	}

	/**
	 * Re-run the installer after an update that changed the schema.
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( self::DB_VERSION_OPTION, 0 ) < DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Next 03:00 UTC.
	 *
	 * @return int
	 */
	private static function next_cleanup_time() {
		$next = strtotime( 'today 03:00 UTC' );
		return $next <= time() ? $next + DAY_IN_SECONDS : $next;
	}
}
