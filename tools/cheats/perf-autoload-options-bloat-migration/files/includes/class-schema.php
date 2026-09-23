<?php
/**
 * Database schema of the log table.
 *
 * @package Acme\ActivityLog
 */

namespace Acme\ActivityLog;

defined( 'ABSPATH' ) || exit;

/**
 * Creates / updates the `{prefix}acme_activity_log` table.
 */
class Schema {

	/**
	 * Schema version.
	 *
	 * 1 - 3.0.0 initial table.
	 */
	const DB_VERSION = 1;

	/**
	 * Installed schema version (not autoloaded: only read by the upgrade routine).
	 */
	const DB_VERSION_OPTION = 'acme_activity_db_version';

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'acme_activity_log';
	}

	/**
	 * Is the schema up to date?
	 *
	 * @return bool
	 */
	public static function is_current() {
		return (int) get_option( self::DB_VERSION_OPTION, 0 ) >= self::DB_VERSION;
	}

	/**
	 * Create or update the table.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			logged_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(64) NOT NULL DEFAULT '',
			object_type varchar(32) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			message text NOT NULL,
			ip varchar(45) NOT NULL DEFAULT '',
			context longtext NULL,
			PRIMARY KEY  (id),
			KEY logged_at (logged_at,id),
			KEY user_logged (user_id,logged_at),
			KEY action_logged (action,logged_at),
			KEY object (object_type,object_id)
		) {$charset};"
		);

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Drop the table (uninstall).
	 */
	public static function drop() {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
	}
}
