<?php
/**
 * Install / upgrade routines: custom table, role and capabilities.
 *
 * @package Acme\Bookings
 */

namespace Acme\Bookings;

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
class Installer {

	/**
	 * Schema version of the bookings table. Bump when the table changes.
	 *
	 * History:
	 *  1 - 1.0 (status column used "approved"/"canceled")
	 *  2 - 1.2 added `total` (NULL for bookings made before 1.2)
	 *  3 - 1.4 added `admin_notes`, normalized new statuses
	 */
	const DB_VERSION = 3;

	const DB_VERSION_OPTION = 'acme_bookings_db_version';

	/**
	 * Activation: tables, role, caps.
	 */
	public static function activate() {
		self::create_tables();
		self::add_roles();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		Rooms::register_post_type();
		flush_rewrite_rules();
	}

	/**
	 * Upgrade the table when the plugin was updated in place.
	 *
	 * Hooked to admin_init: an admin visiting the dashboard after an update
	 * triggers it.
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( self::DB_VERSION_OPTION ) >= self::DB_VERSION ) {
			return;
		}
		self::create_tables();
		self::add_roles();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Create/alter the bookings table.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = Repository::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			room_id bigint(20) unsigned NOT NULL DEFAULT 0,
			customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			start_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			end_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			status varchar(20) NOT NULL DEFAULT 'pending',
			guests smallint(5) unsigned NOT NULL DEFAULT 1,
			notes text NULL,
			admin_notes text NULL,
			total decimal(10,2) NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY room_id (room_id),
			KEY customer_id (customer_id),
			KEY start_date (start_date)
		) {$charset};";

		dbDelta( $sql );
	}

	/**
	 * Booking Manager role + capability for administrators.
	 */
	public static function add_roles() {
		$cap = acme_bookings_manager_capability();
		if ( ! get_role( 'booking_manager' ) ) {
			add_role(
				'booking_manager',
				__( 'Booking Manager', 'acme-bookings' ),
				array(
					'read'       => true,
					'list_users' => true,
					$cap         => true,
				)
			);
		}
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( $cap ) ) {
			$admin->add_cap( $cap );
		}
	}
}
