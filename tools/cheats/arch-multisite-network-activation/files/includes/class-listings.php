<?php
/**
 * Listings repository.
 *
 * @package Acme\Directory
 */

namespace Acme\Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for the listings table.
 */
class Listings {

	const STATUSES = array( 'pending', 'published', 'expired', 'rejected' );

	/**
	 * Query listings.
	 *
	 * @param array $args {
	 *     @type string $status   Status (default 'published'; 'any' for all).
	 *     @type string $category Category slug.
	 *     @type int    $per_page Page size.
	 *     @type int    $page     1-based page.
	 *     @type string $search   Search in name/description.
	 * }
	 * @return array<int,object>
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'status'   => 'published',
				'category' => '',
				'per_page' => (int) Settings::get( 'per_page' ),
				'page'     => 1,
				'search'   => '',
			)
		);

		$where  = array( '1=1' );
		$params = array();
		if ( 'any' !== $args['status'] ) {
			$where[]  = 'l.status = %s';
			$params[] = $args['status'];
		}
		if ( '' !== $args['category'] ) {
			$where[]  = 'c.slug = %s';
			$params[] = $args['category'];
		}
		if ( '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(l.name LIKE %s OR l.description LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}
		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;
		$params[] = $per_page;
		$params[] = $offset;

		$sql = 'SELECT l.*, c.slug AS category_slug, c.name AS category_name FROM ' . Schema::listings() . ' l'
			. ' LEFT JOIN ' . Schema::categories() . ' c ON c.id = l.category_id'
			. ' WHERE ' . implode( ' AND ', $where )
			. ' ORDER BY l.featured DESC, l.name ASC LIMIT %d OFFSET %d';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * One listing.
	 *
	 * @param int $id ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'SELECT l.*, c.slug AS category_slug, c.name AS category_name FROM ' . Schema::listings() . ' l LEFT JOIN ' . Schema::categories() . ' c ON c.id = l.category_id WHERE l.id = %d',
				$id
			)
		);
	}

	/**
	 * Insert a listing.
	 *
	 * @param array $data Listing fields.
	 * @return int|\WP_Error New ID.
	 */
	public static function create( array $data ) {
		global $wpdb;
		$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		if ( '' === $name ) {
			return new \WP_Error( 'acme_directory_name_required', __( 'A listing needs a name.', 'acme-directory' ) );
		}
		$now    = current_time( 'mysql', true );
		$status = isset( $data['status'] ) && in_array( $data['status'], self::STATUSES, true ) ? $data['status'] : 'pending';
		$row    = array(
			'category_id'  => isset( $data['category_id'] ) ? absint( $data['category_id'] ) : 0,
			'name'         => $name,
			'slug'         => sanitize_title( $name ),
			'description'  => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
			'url'          => isset( $data['url'] ) ? esc_url_raw( $data['url'] ) : '',
			'phone'        => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '',
			'email'        => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '',
			'status'       => $status,
			'featured'     => empty( $data['featured'] ) ? 0 : 1,
			'submitted_by' => get_current_user_id(),
			'expires_at'   => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS * (int) Settings::get( 'expire_days' ) ),
			'created_at'   => $now,
			'updated_at'   => $now,
		);
		if ( ! $wpdb->insert( Schema::listings(), $row ) ) {
			return new \WP_Error( 'acme_directory_db', __( 'Could not save the listing.', 'acme-directory' ) );
		}
		$id = (int) $wpdb->insert_id;
		delete_transient( Categories::COUNTS_TRANSIENT );

		/**
		 * Fires after a listing was created.
		 *
		 * @param int   $id   Listing ID.
		 * @param array $row  Stored row.
		 */
		do_action( 'acme_directory_listing_saved', $id, $row );
		return $id;
	}

	/**
	 * Change a listing's status.
	 *
	 * @param int    $id     ID.
	 * @param string $status New status.
	 * @return bool
	 */
	public static function set_status( $id, $status ) {
		global $wpdb;
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}
		$ok = $wpdb->update(
			Schema::listings(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $id )
		);
		delete_transient( Categories::COUNTS_TRANSIENT );
		return false !== $ok;
	}

	/**
	 * Delete a listing.
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$ok = $wpdb->delete( Schema::listings(), array( 'id' => (int) $id ) );
		delete_transient( Categories::COUNTS_TRANSIENT );
		return (bool) $ok;
	}

	/**
	 * Number of listings per status.
	 *
	 * @return array<string,int>
	 */
	public static function status_counts() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows   = $wpdb->get_results( 'SELECT status, COUNT(*) AS total FROM ' . Schema::listings() . ' GROUP BY status' );
		$counts = array_fill_keys( self::STATUSES, 0 );
		foreach ( (array) $rows as $row ) {
			$counts[ $row->status ] = (int) $row->total;
		}
		return $counts;
	}

	/**
	 * Public representation (REST + templates).
	 *
	 * @param object $row DB row.
	 * @return array
	 */
	public static function prepare( $row ) {
		return array(
			'id'          => (int) $row->id,
			'name'        => $row->name,
			'slug'        => $row->slug,
			'description' => $row->description,
			'url'         => $row->url,
			'phone'       => $row->phone,
			'category'    => $row->category_slug ? $row->category_slug : null,
			'featured'    => (bool) $row->featured,
			'status'      => $row->status,
			'expires_at'  => $row->expires_at ? mysql_to_rfc3339( $row->expires_at ) : null,
		);
	}
}
