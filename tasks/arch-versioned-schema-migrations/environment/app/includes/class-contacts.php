<?php
/**
 * Contacts repository.
 *
 * @package Acme\CRM
 */

namespace Acme\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for the contacts table.
 */
class Contacts {

	/**
	 * Query contacts, newest first.
	 *
	 * @param array $args {
	 *     @type string $search   Matches name, email or company.
	 *     @type string $stage    Stage slug.
	 *     @type int    $owner    Owner user ID.
	 *     @type int    $per_page Page size (default 20).
	 *     @type int    $page     1-based page.
	 * }
	 * @return array{items: object[], total: int}
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$args  = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'stage'    => '',
				'owner'    => 0,
				'per_page' => 20,
				'page'     => 1,
			)
		);
		$table = Installer::table( 'contacts' );

		$where  = array( '1=1' );
		$params = array();
		if ( '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(full_name LIKE %s OR email LIKE %s OR company LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( '' !== $args['stage'] ) {
			$where[]  = 'stage = %s';
			$params[] = $args['stage'];
		}
		if ( $args['owner'] ) {
			$where[]  = 'owner_id = %d';
			$params[] = (int) $args['owner'];
		}
		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );

		$per_page = max( 1, min( 100, (int) $args['per_page'] ) );
		$offset   = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;
		$params[] = $per_page;
		$params[] = $offset;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE $where_sql ORDER BY id DESC LIMIT %d OFFSET %d", $params ) );

		return array(
			'items' => (array) $items,
			'total' => $total,
		);
	}

	/**
	 * One contact row.
	 *
	 * @param int $id ID.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;
		$table = Installer::table( 'contacts' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
	}

	/**
	 * Look a contact up by email.
	 *
	 * @param string $email Email.
	 * @return object|null
	 */
	public static function find_by_email( $email ) {
		global $wpdb;
		$table = Installer::table( 'contacts' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE email = %s ORDER BY id ASC LIMIT 1", $email ) );
	}

	/**
	 * Sanitize contact input (only the keys present).
	 *
	 * @param array $data Raw data.
	 * @return array
	 */
	private static function sanitize( array $data ) {
		$out = array();
		if ( array_key_exists( 'full_name', $data ) ) {
			$out['full_name'] = sanitize_text_field( (string) $data['full_name'] );
		}
		if ( array_key_exists( 'email', $data ) ) {
			$out['email'] = sanitize_email( (string) $data['email'] );
		}
		if ( array_key_exists( 'phone', $data ) ) {
			$out['phone'] = sanitize_text_field( (string) $data['phone'] );
		}
		if ( array_key_exists( 'company', $data ) ) {
			$out['company'] = sanitize_text_field( (string) $data['company'] );
		}
		if ( array_key_exists( 'stage', $data ) && Stages::exists( $data['stage'] ) ) {
			$out['stage'] = $data['stage'];
		}
		if ( array_key_exists( 'owner_id', $data ) ) {
			$out['owner_id'] = absint( $data['owner_id'] );
		}
		if ( array_key_exists( 'source', $data ) ) {
			$out['source'] = sanitize_key( (string) $data['source'] );
		}
		return $out;
	}

	/**
	 * Create a contact.
	 *
	 * @param array $data Contact fields.
	 * @return int|\WP_Error New ID.
	 */
	public static function create( array $data ) {
		global $wpdb;
		$row = self::sanitize( $data );
		if ( empty( $row['full_name'] ) && empty( $row['email'] ) ) {
			return new \WP_Error( 'acme_crm_empty_contact', __( 'A contact needs a name or an email address.', 'acme-crm' ), array( 'status' => 400 ) );
		}
		$now = current_time( 'mysql', true );
		$row = array_merge(
			array(
				'full_name' => '',
				'email'     => '',
				'phone'     => '',
				'company'   => '',
				'stage'     => 'lead',
				'owner_id'  => get_current_user_id(),
				'source'    => 'manual',
			),
			$row,
			array(
				'created_at' => $now,
				'updated_at' => $now,
			)
		);

		if ( false === $wpdb->insert( Installer::table( 'contacts' ), $row ) ) {
			return new \WP_Error( 'acme_crm_db_error', __( 'Could not save the contact.', 'acme-crm' ), array( 'status' => 500 ) );
		}
		$id = (int) $wpdb->insert_id;

		/**
		 * Fires after a contact was created or updated.
		 *
		 * @param int   $id      Contact ID.
		 * @param array $contact Contact (see acme_crm_get_contact()).
		 */
		do_action( 'acme_crm_contact_saved', $id, self::to_array( self::find( $id ) ) );
		return $id;
	}

	/**
	 * Update a contact.
	 *
	 * @param int   $id   ID.
	 * @param array $data Fields to change.
	 * @return true|\WP_Error
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$row = self::sanitize( $data );
		if ( ! $row ) {
			return true;
		}
		$row['updated_at'] = current_time( 'mysql', true );
		if ( false === $wpdb->update( Installer::table( 'contacts' ), $row, array( 'id' => (int) $id ) ) ) {
			return new \WP_Error( 'acme_crm_db_error', __( 'Could not save the contact.', 'acme-crm' ), array( 'status' => 500 ) );
		}
		/** This action is documented in includes/class-contacts.php */
		do_action( 'acme_crm_contact_saved', (int) $id, self::to_array( self::find( $id ) ) );
		return true;
	}

	/**
	 * Delete a contact and its notes.
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( Installer::table( 'notes' ), array( 'contact_id' => (int) $id ) );
		return (bool) $wpdb->delete( Installer::table( 'contacts' ), array( 'id' => (int) $id ) );
	}

	/**
	 * Mark a contact as recently touched (e.g. a note was added).
	 *
	 * @param int $id ID.
	 */
	public static function touch( $id ) {
		global $wpdb;
		$wpdb->update( Installer::table( 'contacts' ), array( 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $id ) );
	}

	/**
	 * Contact counts per stage.
	 *
	 * @return array<string,int>
	 */
	public static function stage_counts() {
		global $wpdb;
		$table  = Installer::table( 'contacts' );
		$counts = array_fill_keys( array_keys( Stages::all() ), 0 );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT stage, COUNT(*) AS total FROM $table GROUP BY stage" );
		if ( null === $rows || $wpdb->last_error ) {
			return new \WP_Error( 'acme_crm_db_error', $wpdb->last_error );
		}
		foreach ( $rows as $row ) {
			$counts[ $row->stage ] = (int) $row->total;
		}
		return $counts;
	}

	/**
	 * The array shape add-ons get from acme_crm_get_contact().
	 *
	 * @param object|null $row DB row.
	 * @return array|null
	 */
	public static function to_array( $row ) {
		if ( ! $row ) {
			return null;
		}
		return array(
			'id'         => (int) $row->id,
			'full_name'  => (string) $row->full_name,
			'email'      => (string) $row->email,
			'phone'      => (string) $row->phone,
			'company'    => (string) $row->company,
			// Sites upgraded from 1.2/1.3 may still lack the stage column; fall back to the legacy status.
			'stage'      => isset( $row->stage ) ? (string) $row->stage : Stages::from_legacy_status( isset( $row->status ) ? $row->status : '' ),
			'owner_id'   => (int) $row->owner_id,
			'source'     => (string) $row->source,
			'created_at' => (string) $row->created_at,
			'updated_at' => isset( $row->updated_at ) ? (string) $row->updated_at : (string) $row->created_at,
		);
	}
}
