<?php
/**
 * Database table + upgrades.
 *
 * @package Acme\Newsletter
 */

namespace Acme\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Creates/upgrades the subscribers table.
 */
class Installer {

	const DB_VERSION        = '3';
	const DB_VERSION_OPTION = 'acme_newsletter_db_version';

	/**
	 * Activation hook.
	 */
	public static function activate() {
		self::install();
	}

	/**
	 * Runs the installer when the stored DB version is outdated.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'acme_newsletter_subscribers';
	}

	/**
	 * Create or update the table.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		// Version 2 added `source`, version 3 added `status` + `confirm_key`.
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				email varchar(190) NOT NULL,
				name varchar(190) NOT NULL DEFAULT '',
				source varchar(40) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'pending',
				confirm_key varchar(64) NOT NULL DEFAULT '',
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				UNIQUE KEY email (email),
				KEY source (source)
			) {$charset};"
		);

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}
}
