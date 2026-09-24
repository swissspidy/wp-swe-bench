<?php
/**
 * Installation and upgrades.
 *
 * @package Acme\Forms
 */

namespace Acme\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the tables, grants capabilities and runs upgrades.
 *
 * Deploys replace the plugin files without re-activating the plugin, so the schema is checked on
 * every load (maybe_upgrade()) and not only on activation.
 */
class Installer {

	/**
	 * Current database schema version.
	 */
	const DB_VERSION = '4';

	/**
	 * Capability for working with submissions (inbox, export, dashboard widget).
	 */
	const CAP = 'acme_forms_manage';

	/**
	 * Activation hook.
	 */
	public static function activate() {
		self::install();
		self::add_caps();
		update_option( 'acme_forms_version', ACME_FORMS_VERSION );
	}

	/**
	 * Run upgrades when the code is newer than the stored schema.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'acme_forms_db_version' ) !== self::DB_VERSION ) {
			self::install();
			self::add_caps();
		}
	}

	/**
	 * Create/upgrade the submissions and audit log tables.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = Submissions::table();
		$collate = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				form_id bigint(20) unsigned NOT NULL DEFAULT 0,
				status varchar(20) NOT NULL DEFAULT 'new',
				email varchar(190) NOT NULL DEFAULT '',
				ip varchar(45) NOT NULL DEFAULT '',
				data longtext NOT NULL,
				files longtext NOT NULL,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY form_id (form_id),
				KEY created_at (created_at)
			) {$collate};"
		);

		// 2.5: audit log of admin actions.
		dbDelta( Audit_Log::schema() );

		update_option( 'acme_forms_db_version', self::DB_VERSION );
	}

	/**
	 * Administrators and editors work the submissions inbox.
	 */
	public static function add_caps() {
		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role && ! $role->has_cap( self::CAP ) ) {
				$role->add_cap( self::CAP );
			}
		}
	}
}
