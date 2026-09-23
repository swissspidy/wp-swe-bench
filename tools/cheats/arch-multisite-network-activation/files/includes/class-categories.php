<?php
/**
 * Categories repository.
 *
 * @package Acme\Directory
 */

namespace Acme\Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for the categories table.
 */
class Categories {

	const COUNTS_TRANSIENT = 'acme_directory_counts';

	/**
	 * All categories, ordered by name.
	 *
	 * @return array<int,object>
	 */
	public static function all() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( 'SELECT id, name, slug, description FROM ' . Schema::categories() . ' ORDER BY name ASC' );
	}

	/**
	 * One category by slug.
	 *
	 * @param string $slug Slug.
	 * @return object|null
	 */
	public static function get_by_slug( $slug ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( 'SELECT id, name, slug, description FROM ' . Schema::categories() . ' WHERE slug = %s', $slug ) );
	}

	/**
	 * One category by ID.
	 *
	 * @param int $id ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( 'SELECT id, name, slug, description FROM ' . Schema::categories() . ' WHERE id = %d', $id ) );
	}

	/**
	 * Insert a category.
	 *
	 * @param string $name        Name.
	 * @param string $slug        Slug (derived from the name when empty).
	 * @param string $description Description.
	 * @return int|false New ID.
	 */
	public static function create( $name, $slug = '', $description = '' ) {
		global $wpdb;
		$slug = sanitize_title( $slug ? $slug : $name );
		$ok   = $wpdb->insert(
			Schema::categories(),
			array(
				'name'        => sanitize_text_field( $name ),
				'slug'        => $slug,
				'description' => sanitize_textarea_field( $description ),
				'created_at'  => current_time( 'mysql', true ),
			)
		);
		delete_transient( self::COUNTS_TRANSIENT );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Make sure the default "General" category exists.
	 */
	public static function ensure_default() {
		if ( ! self::get_by_slug( 'general' ) ) {
			self::create( __( 'General', 'acme-directory' ), 'general', __( 'Everything else.', 'acme-directory' ) );
		}
	}

	/**
	 * Published listing counts per category ID (cached for an hour).
	 *
	 * @return array<int,int>
	 */
	public static function counts() {
		$counts = get_transient( self::COUNTS_TRANSIENT );
		if ( is_array( $counts ) ) {
			return $counts;
		}
		global $wpdb;
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( 'SELECT category_id, COUNT(*) AS total FROM ' . Schema::listings() . ' WHERE status = %s GROUP BY category_id', 'published' )
		);
		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row->category_id ] = (int) $row->total;
		}
		set_transient( self::COUNTS_TRANSIENT, $counts, HOUR_IN_SECONDS );
		return $counts;
	}
}
