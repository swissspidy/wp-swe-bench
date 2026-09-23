<?php
/**
 * Install / upgrade.
 *
 * @package Acme\Redirects
 */

namespace Acme\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
class Installer {

	/**
	 * Schema version.
	 *
	 * 1 - 1.0 (source, target, status_code, hits)
	 * 2 - 1.2 added `enabled`, `match_type`, `last_hit`
	 * 3 - 2.0 added `priority`, `note`, `created_at`, `updated_at`
	 */
	const DB_VERSION = 3;

	const DB_VERSION_OPTION = 'acme_redirects_db_version';

	/**
	 * Capability required to manage redirects.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Activation.
	 */
	public static function activate() {
		self::create_table();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		delete_transient( Rule_Repository::CACHE_KEY );
	}

	/**
	 * Upgrade after an in-place update.
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( self::DB_VERSION_OPTION ) >= self::DB_VERSION ) {
			return;
		}
		self::activate();
	}

	/**
	 * Create or update the table.
	 */
	public static function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = Rule_Repository::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source varchar(255) NOT NULL DEFAULT '',
			target text NULL,
			match_type varchar(10) NULL DEFAULT 'exact',
			status_code smallint(3) NOT NULL DEFAULT 301,
			priority smallint(5) NULL DEFAULT 10,
			enabled tinyint(1) NULL DEFAULT 1,
			hits bigint(20) unsigned NOT NULL DEFAULT 0,
			last_hit datetime NULL DEFAULT NULL,
			note varchar(255) NOT NULL DEFAULT '',
			created_at datetime NULL DEFAULT NULL,
			updated_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY source (source(191)),
			KEY priority (priority)
			) {$charset};"
		);
	}
}
