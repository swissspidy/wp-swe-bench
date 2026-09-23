<?php
/**
 * The plugin's schema migrations.
 *
 * @package Acme\CRM
 */

namespace Acme\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * Ordered list of migrations. Each migration is identified by an integer version and has a
 * description and an `up` callable. Add-ons and hotfixes can add their own through the
 * `acme_crm_migrations` filter.
 */
class Migrations {

	/**
	 * All migrations, sorted by version.
	 *
	 * @return array<int,array{description:string,up:callable}>
	 */
	public static function all() {
		$migrations = array(
			1 => array(
				'description' => __( 'Create the contacts and notes tables', 'acme-crm' ),
				'up'          => array( __CLASS__, 'up_1' ),
			),
			2 => array(
				'description' => __( 'Add lifecycle stage, updated_at and indexes', 'acme-crm' ),
				'up'          => array( __CLASS__, 'up_2' ),
			),
		);

		/**
		 * Filters the list of schema migrations.
		 *
		 * @param array $migrations Version => array( 'description' => string, 'up' => callable ).
		 */
		$migrations = apply_filters( 'acme_crm_migrations', $migrations );

		$out = array();
		foreach ( (array) $migrations as $version => $migration ) {
			if ( (int) $version > 0 && is_array( $migration ) && isset( $migration['up'] ) && is_callable( $migration['up'] ) ) {
				$migration['description'] = isset( $migration['description'] ) ? (string) $migration['description'] : '';
				$out[ (int) $version ]    = $migration;
			}
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Highest known version.
	 *
	 * @return int
	 */
	public static function latest() {
		$versions = array_keys( self::all() );
		return $versions ? (int) max( $versions ) : 0;
	}

	/**
	 * 1: the original (1.2) tables.
	 *
	 * @return bool
	 */
	public static function up_1() {
		global $wpdb;
		$charset  = $wpdb->get_charset_collate();
		$contacts = Installer::table( 'contacts' );
		$notes    = Installer::table( 'notes' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS $contacts (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				full_name varchar(191) NOT NULL DEFAULT '',
				email varchar(191) NOT NULL DEFAULT '',
				phone varchar(50) NOT NULL DEFAULT '',
				company varchar(191) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'lead',
				owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
				source varchar(50) NOT NULL DEFAULT '',
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY (id)
			) $charset"
		);
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS $notes (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
				author_id bigint(20) unsigned NOT NULL DEFAULT 0,
				body text NOT NULL,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY (id)
			) $charset"
		);
		// phpcs:enable
		return Schema::table_exists( $contacts ) && Schema::table_exists( $notes );
	}

	/**
	 * 2: 1.4.0 schema — stage (from the legacy status), updated_at, indexes.
	 *
	 * Works on sites that were installed fresh with 1.4.0 and already have these columns.
	 *
	 * @return bool
	 */
	public static function up_2() {
		global $wpdb;
		$contacts = Installer::table( 'contacts' );
		$notes    = Installer::table( 'notes' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( Schema::add_column( $contacts, 'stage', "varchar(20) NOT NULL DEFAULT 'lead'" ) ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $contacts SET stage = CASE status WHEN %s THEN %s WHEN %s THEN %s ELSE %s END",
					'customer',
					'customer',
					'inactive',
					'churned',
					'lead'
				)
			);
		}
		Schema::add_column( $contacts, 'updated_at', "datetime NOT NULL DEFAULT '0000-00-00 00:00:00'" );
		$wpdb->query( $wpdb->prepare( "UPDATE $contacts SET updated_at = created_at WHERE updated_at = %s", '0000-00-00 00:00:00' ) );
		// phpcs:enable

		Schema::add_index( $contacts, 'email', 'email' );
		Schema::add_index( $contacts, 'stage', 'stage' );
		Schema::add_index( $notes, 'contact_id', 'contact_id' );

		return Schema::column_exists( $contacts, 'stage' ) && Schema::column_exists( $contacts, 'updated_at' );
	}
}
