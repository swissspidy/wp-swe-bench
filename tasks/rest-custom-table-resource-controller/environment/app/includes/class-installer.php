<?php
/**
 * Install / upgrade: the leads table, roles and capabilities.
 *
 * @package Acme\Leads
 */

namespace Acme\Leads;

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
class Installer {

	/**
	 * Schema version.
	 *
	 * 1 - 1.0
	 * 2 - 1.4 added `owner_id`
	 * 3 - 1.6 added `score`, `updated_at`
	 */
	const DB_VERSION = 3;

	const DB_VERSION_OPTION = 'acme_leads_db_version';

	/**
	 * Capability: see leads (sales reps see the leads assigned to them).
	 */
	const CAP_VIEW = 'acme_view_leads';

	/**
	 * Capability: see and edit all leads (sales managers).
	 */
	const CAP_MANAGE = 'acme_manage_leads';

	/**
	 * Activation.
	 */
	public static function activate() {
		self::create_table();
		self::add_roles();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
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

		$table   = Repository::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			company varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'new',
			source varchar(40) NOT NULL DEFAULT 'form',
			score smallint(5) NOT NULL DEFAULT 0,
			owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
			notes longtext NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY status (status),
			KEY owner_id (owner_id),
			KEY created_at (created_at)
		) {$charset};"
		);
	}

	/**
	 * Roles and capabilities.
	 */
	public static function add_roles() {
		add_role(
			'acme_sales_rep',
			__( 'Sales Rep', 'acme-leads' ),
			array(
				'read'         => true,
				self::CAP_VIEW => true,
			)
		);
		add_role(
			'acme_sales_manager',
			__( 'Sales Manager', 'acme-leads' ),
			array(
				'read'           => true,
				self::CAP_VIEW   => true,
				self::CAP_MANAGE => true,
			)
		);
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( self::CAP_VIEW );
			$admin->add_cap( self::CAP_MANAGE );
		}
	}
}
