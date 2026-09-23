<?php
/**
 * Schema introspection helpers for migrations.
 *
 * @package Acme\CRM
 */

namespace Acme\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * Small, idempotent DDL helpers: migrations must be safe to re-run after a partial failure
 * and must work on sites whose schema is ahead of their recorded version (fresh 1.4 installs).
 */
class Schema {

	/**
	 * Whether a table exists.
	 *
	 * @param string $table Full table name.
	 * @return bool
	 */
	public static function table_exists( $table ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
	}

	/**
	 * Column names of a table.
	 *
	 * @param string $table Full table name.
	 * @return string[]
	 */
	public static function columns( $table ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_map( 'strtolower', (array) $wpdb->get_col( "SHOW COLUMNS FROM $table" ) );
	}

	/**
	 * Whether a column exists.
	 *
	 * @param string $table  Table.
	 * @param string $column Column.
	 * @return bool
	 */
	public static function column_exists( $table, $column ) {
		return in_array( strtolower( $column ), self::columns( $table ), true );
	}

	/**
	 * Add a column unless it exists.
	 *
	 * @param string $table      Table.
	 * @param string $column     Column name.
	 * @param string $definition Column definition (type, null, default).
	 * @return bool True when the column was added.
	 */
	public static function add_column( $table, $column, $definition ) {
		global $wpdb;
		if ( self::column_exists( $table, $column ) ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "ALTER TABLE $table ADD COLUMN $column $definition" );
		return true;
	}

	/**
	 * Drop a column if it exists.
	 *
	 * @param string $table  Table.
	 * @param string $column Column.
	 */
	public static function drop_column( $table, $column ) {
		global $wpdb;
		if ( self::column_exists( $table, $column ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "ALTER TABLE $table DROP COLUMN $column" );
		}
	}

	/**
	 * Index names of a table, keyed by name, value = first column.
	 *
	 * @param string $table Table.
	 * @return array<string,string>
	 */
	public static function indexes( $table ) {
		global $wpdb;
		$out = array();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $wpdb->get_results( "SHOW INDEX FROM $table", ARRAY_A ) as $row ) {
			if ( 1 === (int) $row['Seq_in_index'] ) {
				$out[ $row['Key_name'] ] = strtolower( $row['Column_name'] );
			}
		}
		return $out;
	}

	/**
	 * Add a (non-unique) index on one column unless an index starting with it exists.
	 *
	 * @param string $table  Table.
	 * @param string $name   Index name.
	 * @param string $column Column.
	 */
	public static function add_index( $table, $name, $column ) {
		global $wpdb;
		if ( in_array( strtolower( $column ), self::indexes( $table ), true ) ) {
			return;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "ALTER TABLE $table ADD INDEX $name ($column)" );
	}

	/**
	 * Drop an index if it exists.
	 *
	 * @param string $table Table.
	 * @param string $name  Index name.
	 */
	public static function drop_index( $table, $name ) {
		global $wpdb;
		if ( array_key_exists( $name, self::indexes( $table ) ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "ALTER TABLE $table DROP INDEX $name" );
		}
	}
}
