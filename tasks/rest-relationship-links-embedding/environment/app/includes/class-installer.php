<?php
/**
 * Database table + upgrades.
 *
 * @package Acme\Library
 */

namespace Acme\Library;

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
class Installer {

	const DB_VERSION_OPTION = 'acme_library_db_version';

	/**
	 * Activation.
	 */
	public static function activate(): void {
		Post_Types::register();
		flush_rewrite_rules( false );
		self::maybe_upgrade();
	}

	/**
	 * Creates/upgrades the relationship table.
	 */
	public static function maybe_upgrade(): void {
		$installed = (int) get_option( self::DB_VERSION_OPTION, 0 );
		if ( $installed >= DB_VERSION ) {
			return;
		}

		self::create_table();

		if ( $installed < 2 ) {
			self::migrate_legacy_meta();
		}

		update_option( self::DB_VERSION_OPTION, DB_VERSION );
	}

	/**
	 * The relationship table.
	 */
	public static function create_table(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = Relationships::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			book_id bigint(20) unsigned NOT NULL,
			author_id bigint(20) unsigned NOT NULL,
			position int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY book_id (book_id),
			KEY author_id (author_id)
			) $charset;"
		);
	}

	/**
	 * 1.x stored the authors of a book as a comma separated list of author post IDs in
	 * the `_acme_author_ids` meta. Moves them to the table (DB version 2).
	 */
	private static function migrate_legacy_meta(): void {
		$books = get_posts(
			array(
				'post_type'   => Post_Types::BOOK,
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => Relationships::LEGACY_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);
		foreach ( $books as $book_id ) {
			$ids = Relationships::parse_legacy( get_post_meta( $book_id, Relationships::LEGACY_META, true ) );
			if ( $ids ) {
				Relationships::set_author_ids( $book_id, $ids );
			}
		}
	}
}
