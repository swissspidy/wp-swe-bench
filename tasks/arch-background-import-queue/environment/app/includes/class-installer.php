<?php
/**
 * Install / upgrade: roles and capabilities.
 *
 * @package Acme\Importer
 */

namespace Acme\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
class Installer {

	/**
	 * Schema/settings version.
	 *
	 * 1 - 1.0 (SKUs stored as typed)
	 * 2 - 2.0 (SKUs stored upper-case, prices in cents)
	 */
	const DB_VERSION = 2;

	const DB_VERSION_OPTION = 'acme_importer_db_version';

	/**
	 * Capability required to run imports.
	 */
	const CAPABILITY = 'acme_import_products';

	/**
	 * Shop manager role.
	 */
	const ROLE = 'acme_shop_manager';

	/**
	 * Activation.
	 */
	public static function activate() {
		self::add_roles();
		if ( false === get_option( 'acme_importer_settings' ) ) {
			add_option( 'acme_importer_settings', array( 'default_status' => 'draft' ) );
		}
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
	 * Roles and capabilities.
	 */
	public static function add_roles() {
		if ( ! get_role( self::ROLE ) ) {
			add_role(
				self::ROLE,
				__( 'Shop Manager', 'acme-importer' ),
				array(
					'read'                   => true,
					'edit_posts'             => true,
					'edit_others_posts'      => true,
					'edit_published_posts'   => true,
					'publish_posts'          => true,
					'delete_posts'           => true,
					'read_private_posts'     => true,
					'upload_files'           => true,
					self::CAPABILITY         => true,
				)
			);
		}
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( self::CAPABILITY );
		}
	}
}
