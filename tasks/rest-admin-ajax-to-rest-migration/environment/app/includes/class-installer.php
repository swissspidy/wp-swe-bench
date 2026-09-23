<?php
/**
 * Tables, role and capability.
 *
 * @package Acme\Inventory
 */

namespace Acme\Inventory;

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
class Installer {

	const DB_VERSION        = 2;
	const DB_VERSION_OPTION = 'acme_inventory_db_version';

	/**
	 * Activation.
	 */
	public static function activate() {
		self::create_tables();
		self::add_caps();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Items + adjustment log.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$items   = Items::table();
		$log     = Items::log_table();

		dbDelta(
			"CREATE TABLE {$items} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			sku varchar(64) NOT NULL DEFAULT '',
			name varchar(191) NOT NULL DEFAULT '',
			stock int(11) NOT NULL DEFAULT 0,
			low_stock_threshold int(11) NOT NULL DEFAULT 5,
			location varchar(100) NOT NULL DEFAULT '',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY sku (sku),
			KEY name (name)
		) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$log} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			item_id bigint(20) unsigned NOT NULL DEFAULT 0,
			delta int(11) NOT NULL DEFAULT 0,
			stock_after int(11) NOT NULL DEFAULT 0,
			reason varchar(191) NOT NULL DEFAULT '',
			source varchar(20) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY item_id (item_id)
		) {$charset};"
		);
	}

	/**
	 * Shop Manager role (if no shop plugin created it) and the capability.
	 */
	public static function add_caps() {
		$cap = acme_inventory_capability();
		if ( ! get_role( 'shop_manager' ) ) {
			add_role(
				'shop_manager',
				__( 'Shop Manager', 'acme-inventory' ),
				array(
					'read'         => true,
					'upload_files' => true,
					'edit_posts'   => true,
					'delete_posts' => true,
				)
			);
		}
		foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role && ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
			}
		}
	}
}
