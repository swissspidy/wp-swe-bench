<?php
/**
 * Installation.
 *
 * @package Acme\CRM
 */

namespace Acme\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * Activation. The schema itself is managed by versioned migrations (see Migrations).
 */
class Installer {

	const VERSION_OPTION  = 'acme_crm_version';
	const SETTINGS_OPTION = 'acme_crm_settings';

	/**
	 * Table name helper.
	 *
	 * @param string $name 'contacts' or 'notes'.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'acme_crm_' . $name;
	}

	/**
	 * Activation hook: bring the schema up to date (fresh installs run every migration).
	 */
	public static function activate() {
		add_option(
			self::SETTINGS_OPTION,
			array(
				'notify_email' => get_option( 'admin_email' ),
				'form_stage'   => 'lead',
			)
		);
		// An explicit activation is also an explicit retry.
		delete_option( Migrator::FAILURE_OPTION );
		Migrator::run();
		update_option( self::VERSION_OPTION, VERSION );
	}
}
