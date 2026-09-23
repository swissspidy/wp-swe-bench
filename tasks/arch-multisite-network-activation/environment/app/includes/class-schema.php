<?php
/**
 * Custom tables.
 *
 * @package Acme\Directory
 */

namespace Acme\Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Table names and DDL for the directory tables.
 *
 * Listings and categories live in custom tables (not posts/terms) since 2.0: the
 * directory on the main site has ~40k listings and the moderation queue queries
 * were too slow as post meta.
 */
class Schema {

	/**
	 * Listings table name (with prefix).
	 *
	 * @var string
	 */
	public static $listings = '';

	/**
	 * Categories table name (with prefix).
	 *
	 * @var string
	 */
	public static $categories = '';

	/**
	 * Resolve the table names. Called once when the plugin file loads.
	 */
	public static function init() {
		global $wpdb;
		self::$listings   = $wpdb->prefix . 'acme_dir_listings';
		self::$categories = $wpdb->prefix . 'acme_dir_categories';
	}

	/**
	 * Unprefixed table slugs, keyed by the name used by acme_directory_table().
	 *
	 * @return array<string,string>
	 */
	public static function slugs() {
		return array(
			'listings'   => 'acme_dir_listings',
			'categories' => 'acme_dir_categories',
		);
	}

	/**
	 * CREATE TABLE statements (dbDelta format: two spaces after PRIMARY KEY).
	 *
	 * @return string[]
	 */
	public static function sql() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$listings = 'CREATE TABLE ' . self::$listings . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  category_id bigint(20) unsigned NOT NULL DEFAULT 0,
  name varchar(200) NOT NULL DEFAULT '',
  slug varchar(191) NOT NULL DEFAULT '',
  description text NOT NULL,
  url varchar(255) NOT NULL DEFAULT '',
  phone varchar(50) NOT NULL DEFAULT '',
  email varchar(100) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'pending',
  featured tinyint(1) NOT NULL DEFAULT 0,
  submitted_by bigint(20) unsigned NOT NULL DEFAULT 0,
  expires_at datetime DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  KEY status (status),
  KEY category_id (category_id),
  KEY slug (slug)
) $charset;";

		$categories = 'CREATE TABLE ' . self::$categories . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL DEFAULT '',
  slug varchar(100) NOT NULL DEFAULT '',
  description text NOT NULL,
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  UNIQUE KEY slug (slug)
) $charset;";

		return array( $listings, $categories );
	}

	/**
	 * Create or update the tables.
	 */
	public static function create_tables() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::sql() );
	}

	/**
	 * Drop the tables (uninstall only).
	 */
	public static function drop_tables() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::$listings );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::$categories );
	}

	/**
	 * Whether both tables exist.
	 *
	 * @return bool
	 */
	public static function tables_exist() {
		global $wpdb;
		foreach ( array( self::$listings, self::$categories ) as $table ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
				return false;
			}
		}
		return true;
	}
}
