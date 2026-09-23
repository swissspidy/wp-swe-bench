<?php
/**
 * Database tables and upgrades.
 *
 * @package Acme\Tasks
 */

namespace Acme\Tasks;

defined( 'ABSPATH' ) || exit;

/**
 * Creates/upgrades the custom tables.
 */
class Installer {

	const OPTION = 'acme_tasks_db_version';

	/**
	 * Activation hook.
	 */
	public static function activate() {
		self::install();
		Post_Type::register();
		flush_rewrite_rules( false );
	}

	/**
	 * Run the installer when the stored schema version is outdated.
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( self::OPTION, 0 ) < DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Name of the tasks table.
	 *
	 * @return string
	 */
	public static function tasks_table() {
		global $wpdb;
		return $wpdb->prefix . 'acme_tasks';
	}

	/**
	 * Name of the activity table.
	 *
	 * @return string
	 */
	public static function activity_table() {
		global $wpdb;
		return $wpdb->prefix . 'acme_task_activity';
	}

	/**
	 * Create or update the tables.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$tasks   = self::tasks_table();
		$log     = self::activity_table();

		dbDelta(
			"CREATE TABLE {$tasks} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				list_id bigint(20) unsigned NOT NULL DEFAULT 0,
				title varchar(200) NOT NULL DEFAULT '',
				notes text NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'open',
				position int(11) NOT NULL DEFAULT 0,
				due_date date DEFAULT NULL,
				assignee_id bigint(20) unsigned NOT NULL DEFAULT 0,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				completed_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY list_position (list_id,position)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$log} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				list_id bigint(20) unsigned NOT NULL DEFAULT 0,
				task_id bigint(20) unsigned NOT NULL DEFAULT 0,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				action varchar(40) NOT NULL DEFAULT '',
				summary varchar(255) NOT NULL DEFAULT '',
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY list_id (list_id)
			) {$charset};"
		);

		update_option( self::OPTION, DB_VERSION );
	}
}
