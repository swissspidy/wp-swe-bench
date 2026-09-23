<?php
/**
 * Database tables, upgrades and activation.
 *
 * @package Acme\Loyalty
 */

namespace Acme\Loyalty;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the plugin's custom tables.
 *
 * History:
 * - v1 (1.0): subscribers table (email, name, status, created).
 * - v2 (1.4): points ledger.
 * - v3 (2.0): subscribers.user_id, subscribers.source, ledger.ip_address.
 * - v4 (2.2): ledger.note, subscribers.unsubscribed_at.
 */
class Installer {

	/**
	 * Ledger table name.
	 *
	 * @return string
	 */
	public static function ledger_table() {
		global $wpdb;
		return $wpdb->prefix . 'acme_loyalty_ledger';
	}

	/**
	 * Newsletter subscribers table name.
	 *
	 * @return string
	 */
	public static function subscribers_table() {
		global $wpdb;
		return $wpdb->prefix . 'acme_loyalty_subscribers';
	}

	/**
	 * Activation: create tables, register the member role.
	 */
	public static function activate() {
		self::install();
		Members::register_role();
	}

	/**
	 * Deactivation hook.
	 */
	public static function deactivate() {
		// Nothing to clean up (yet).
	}

	/**
	 * Run dbDelta when the stored schema version is behind.
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( 'acme_loyalty_db_version', 0 ) < ACME_LOYALTY_DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Create/upgrade tables.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$ledger  = self::ledger_table();
		$subs    = self::subscribers_table();

		dbDelta(
			"CREATE TABLE {$ledger} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				email varchar(190) NOT NULL DEFAULT '',
				points int(11) NOT NULL DEFAULT 0,
				reason varchar(20) NOT NULL DEFAULT 'manual',
				order_id bigint(20) unsigned NOT NULL DEFAULT 0,
				note text NULL,
				ip_address varchar(45) NOT NULL DEFAULT '',
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY user_id (user_id),
				KEY created_at (created_at)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$subs} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				email varchar(190) NOT NULL DEFAULT '',
				first_name varchar(100) NOT NULL DEFAULT '',
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				status varchar(20) NOT NULL DEFAULT 'pending',
				source varchar(40) NOT NULL DEFAULT '',
				token varchar(64) NOT NULL DEFAULT '',
				ip_address varchar(45) NOT NULL DEFAULT '',
				subscribed_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				confirmed_at datetime NULL DEFAULT NULL,
				unsubscribed_at datetime NULL DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY email (email),
				KEY status (status)
			) {$charset};"
		);

		update_option( 'acme_loyalty_db_version', ACME_LOYALTY_DB_VERSION );
	}
}
