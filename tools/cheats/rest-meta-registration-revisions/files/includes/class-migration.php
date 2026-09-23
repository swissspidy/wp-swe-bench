<?php
/**
 * Upgrade routine: converts 1.x ("flat") specs to the structured format.
 *
 * @package Acme\Specs
 */

namespace Acme\Specs;

defined( 'ABSPATH' ) || exit;

/**
 * Data migrations.
 */
class Migration {

	/**
	 * Option storing the data version.
	 */
	const DB_VERSION_OPTION = 'acme_specs_db_version';

	/**
	 * Current data version.
	 *
	 * 3 - 3.0.0: 1.x specs converted, revisions carry specs.
	 */
	const DB_VERSION = 3;

	/**
	 * Hooks.
	 *
	 * The upgrade runs on the first request after the update, whatever kind of
	 * request that is (the catalogue front end is warmed without ever visiting wp-admin).
	 */
	public function register_hooks() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 30 );
	}

	/**
	 * Upgrade the data once.
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( self::DB_VERSION_OPTION, 0 ) >= self::DB_VERSION ) {
			return;
		}
		// Claim the upgrade first so concurrent requests don't run it twice.
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );

		if ( ! get_option( Meta::TRACKED_SINCE_OPTION ) ) {
			update_option( Meta::TRACKED_SINCE_OPTION, time(), false );
		}
		self::migrate_all();
	}

	/**
	 * IDs of products that still have 1.x data.
	 *
	 * @return int[]
	 */
	public static function legacy_product_ids() {
		global $wpdb;
		$keys         = Legacy::KEYS;
		$placeholders = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = %s AND pm.meta_key IN ( {$placeholders} ) ORDER BY pm.post_id", array_merge( array( Post_Type::POST_TYPE ), $keys ) ) );
		return array_map( 'intval', $ids );
	}

	/**
	 * Migrate every product with 1.x data.
	 *
	 * @return int Number of migrated products.
	 */
	public static function migrate_all() {
		$count = 0;
		foreach ( self::legacy_product_ids() as $post_id ) {
			if ( self::migrate_product( $post_id ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Migrate one product. Structured data (2.x) is newer and wins over 1.x data;
	 * the 1.x fields are removed either way.
	 *
	 * @param int $post_id Product ID.
	 * @return bool Whether the product had 1.x data.
	 */
	public static function migrate_product( $post_id ) {
		$custom = Specs::legacy_meta( $post_id );
		if ( ! $custom ) {
			return false;
		}
		if ( ! Specs::has_structured( $post_id ) ) {
			$specs = Legacy::read( $custom );
			Specs::save( $post_id, $specs );
		}
		foreach ( Legacy::KEYS as $key ) {
			delete_post_meta( $post_id, $key );
		}
		Frontend::flush_cache( $post_id );
		return true;
	}
}
