<?php
/**
 * Installation.
 *
 * @package Acme\CRM
 */

namespace Acme\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the CRM tables on activation.
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
	 * Activation hook.
	 */
	public static function activate() {
		self::create_tables();
		add_option(
			self::SETTINGS_OPTION,
			array(
				'notify_email' => get_option( 'admin_email' ),
				'form_stage'   => 'lead',
			)
		);
		update_option( self::VERSION_OPTION, VERSION );
	}

	/**
	 * Create the tables.
	 *
	 * 1.4.0: contacts got `stage` (replaces `status`) and `updated_at`, plus indexes.
	 */
	public static function create_tables() {
		global $wpdb;
		$charset  = $wpdb->get_charset_collate();
		$contacts = self::table( 'contacts' );
		$notes    = self::table( 'notes' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS $contacts (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				full_name varchar(191) NOT NULL DEFAULT '',
				email varchar(191) NOT NULL DEFAULT '',
				phone varchar(50) NOT NULL DEFAULT '',
				company varchar(191) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'lead',
				stage varchar(20) NOT NULL DEFAULT 'lead',
				owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
				source varchar(50) NOT NULL DEFAULT '',
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY (id),
				KEY email (email),
				KEY stage (stage)
			) $charset"
		);

		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS $notes (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
				author_id bigint(20) unsigned NOT NULL DEFAULT 0,
				body text NOT NULL,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY (id),
				KEY contact_id (contact_id)
			) $charset"
		);
		// phpcs:enable
	}
}
