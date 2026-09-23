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
			3 => array(
				'description' => __( 'Add first_name and last_name to contacts', 'acme-crm' ),
				'up'          => array( __CLASS__, 'up_3' ),
			),
			4 => array(
				'description' => __( 'Split full names into first and last names', 'acme-crm' ),
				'up'          => array( __CLASS__, 'up_4' ),
			),
			5 => array(
				'description' => __( 'Drop contacts.full_name', 'acme-crm' ),
				'up'          => array( __CLASS__, 'up_5' ),
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

	/**
	 * Option holding the backfill cursor (last contact ID handled) of migration 4.
	 */
	const BACKFILL_CURSOR_OPTION = 'acme_crm_name_backfill_cursor';

	/**
	 * Rows handled per run of migration 4.
	 */
	const BACKFILL_BATCH = 100000;

	/**
	 * 3: first_name / last_name.
	 *
	 * @return bool
	 */
	public static function up_3() {
		$contacts = Installer::table( 'contacts' );
		Schema::add_column( $contacts, 'first_name', "varchar(100) NOT NULL DEFAULT ''" );
		Schema::add_column( $contacts, 'last_name', "varchar(100) NOT NULL DEFAULT ''" );
		Schema::add_index( $contacts, 'last_name', 'last_name' );
		return Schema::column_exists( $contacts, 'first_name' ) && Schema::column_exists( $contacts, 'last_name' );
	}

	/**
	 * 4: backfill first/last names from full_name, one batch per run.
	 *
	 * Rows that already have a first or last name were written by the new code after the
	 * deploy and are left alone; updated_at is not touched (this is not an edit).
	 *
	 * @return bool|string True when done, Migrator::INCOMPLETE when more rows remain.
	 */
	public static function up_4() {
		global $wpdb;
		$contacts = Installer::table( 'contacts' );
		if ( ! Schema::column_exists( $contacts, 'full_name' ) ) {
			return true;
		}
		$cursor = (int) get_option( self::BACKFILL_CURSOR_OPTION, 0 );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, full_name, first_name, last_name FROM $contacts WHERE id > %d ORDER BY id ASC LIMIT %d", $cursor, self::BACKFILL_BATCH ) );
		if ( null === $rows ) {
			return false;
		}
		foreach ( $rows as $row ) {
			$cursor = (int) $row->id;
			if ( '' !== $row->first_name || '' !== $row->last_name ) {
				continue;
			}
			list( $first, $last ) = Names::split( $row->full_name );
			if ( '' === $first && '' === $last ) {
				continue;
			}
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"UPDATE $contacts SET first_name = %s, last_name = %s WHERE id = %d AND first_name = '' AND last_name = ''",
					$first,
					$last,
					$row->id
				)
			);
		}
		if ( count( $rows ) < self::BACKFILL_BATCH ) {
			delete_option( self::BACKFILL_CURSOR_OPTION );
			return true;
		}
		update_option( self::BACKFILL_CURSOR_OPTION, $cursor, false );
		return Migrator::INCOMPLETE;
	}

	/**
	 * 5: drop full_name (the name lives in first_name/last_name now).
	 *
	 * @return bool
	 */
	public static function up_5() {
		$contacts = Installer::table( 'contacts' );
		Schema::drop_column( $contacts, 'full_name' );
		return ! Schema::column_exists( $contacts, 'full_name' );
	}
}
