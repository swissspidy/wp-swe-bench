<?php
/**
 * Submissions repository (custom table).
 *
 * @package Acme\Forms
 */

namespace Acme\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes rows of `{prefix}acme_form_submissions`.
 *
 * Columns: id, form_id, status (`new`|`read`), email, ip, data (JSON object field name => value;
 * rows from 1.x hold a serialized PHP array), files (JSON object field name => file info),
 * created_at (UTC).
 *
 * Field values are stored exactly as the visitor sent them (trimmed); they are formatted when
 * they are displayed.
 */
class Submissions {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'acme_form_submissions';
	}

	/**
	 * Insert a submission.
	 *
	 * @param array $row form_id, email, ip, data (array), files (array).
	 * @return int New ID (0 on failure).
	 */
	public static function insert( array $row ) {
		global $wpdb;
		$ok = $wpdb->insert(
			self::table(),
			array(
				'form_id'    => (int) $row['form_id'],
				'status'     => 'new',
				'email'      => isset( $row['email'] ) ? (string) $row['email'] : '',
				'ip'         => isset( $row['ip'] ) ? (string) $row['ip'] : '',
				'data'       => wp_json_encode( isset( $row['data'] ) ? $row['data'] : array() ),
				'files'      => wp_json_encode( isset( $row['files'] ) ? (object) $row['files'] : new \stdClass() ),
				'created_at' => isset( $row['created_at'] ) ? $row['created_at'] : current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * A single submission.
	 *
	 * @param int $id ID.
	 * @return object|null Hydrated row.
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Delete a submission and its uploaded files.
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$submission = self::get( $id );
		if ( ! $submission ) {
			return false;
		}
		$base = acme_forms_upload_base();
		foreach ( $submission->files as $file ) {
			if ( ! empty( $file['path'] ) ) {
				$path = $base['dir'] . '/' . ltrim( $file['path'], '/' );
				if ( is_file( $path ) ) {
					wp_delete_file( $path );
				}
			}
		}
		$deleted = (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
		if ( $deleted ) {
			/**
			 * Fires after a submission was deleted.
			 *
			 * @param int    $id         Submission ID.
			 * @param object $submission The deleted submission.
			 */
			do_action( 'acme_forms_submission_deleted', (int) $id, $submission );
		}
		return $deleted;
	}

	/**
	 * Mark as read.
	 *
	 * @param int $id ID.
	 */
	public static function mark_read( $id ) {
		global $wpdb;
		$wpdb->update( self::table(), array( 'status' => 'read' ), array( 'id' => (int) $id ), array( '%s' ), array( '%d' ) );
	}

	/**
	 * Query submissions.
	 *
	 * @param array $args {
	 *     @type int    $form_id   Only this form.
	 *     @type string $status    `new` or `read`.
	 *     @type string $search    Search in e-mail and field values.
	 *     @type string $date_from Earliest day (Y-m-d), inclusive.
	 *     @type string $date_to   Latest day (Y-m-d), inclusive.
	 *     @type string $orderby   `created_at`, `email` or `id`.
	 *     @type string $order     ASC|DESC.
	 *     @type int    $per_page  0 = all.
	 *     @type int    $page      1-based.
	 * }
	 * @return object[] Hydrated rows.
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'orderby'  => 'created_at',
				'order'    => 'DESC',
				'per_page' => 20,
				'page'     => 1,
			)
		);

		$table   = self::table();
		$where   = self::where_sql( $args );
		$orderby = in_array( $args['orderby'], array( 'created_at', 'email', 'id' ), true ) ? $args['orderby'] : 'created_at';
		$order   = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$sql     = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order}, id {$order}";

		if ( (int) $args['per_page'] > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $args['per_page'], max( 0, ( (int) $args['page'] - 1 ) * (int) $args['per_page'] ) );
		}

		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array_map( array( __CLASS__, 'hydrate' ), $rows ? $rows : array() );
	}

	/**
	 * Count submissions matching the same arguments as query().
	 *
	 * @param array $args Arguments.
	 * @return int
	 */
	public static function count( array $args = array() ) {
		global $wpdb;
		$table = self::table();
		$where = self::where_sql( $args );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * WHERE clause for query() and count().
	 *
	 * @param array $args Arguments.
	 * @return string
	 */
	protected static function where_sql( array $args ) {
		global $wpdb;
		$where = array( '1=1' );

		if ( ! empty( $args['form_id'] ) ) {
			$where[] = $wpdb->prepare( 'form_id = %d', (int) $args['form_id'] );
		}
		if ( ! empty( $args['status'] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}
		if ( isset( $args['search'] ) && '' !== $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = $wpdb->prepare( '(email LIKE %s OR data LIKE %s)', $like, $like );
		}
		// Date range (export screen). Values come from the date pickers.
		if ( ! empty( $args['date_from'] ) ) {
			$where[] = "created_at >= '" . $args['date_from'] . " 00:00:00'";
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[] = "created_at <= '" . $args['date_to'] . " 23:59:59'";
		}

		return implode( ' AND ', $where );
	}

	/**
	 * Decode the JSON (or legacy serialized) columns.
	 *
	 * @param object $row Raw row.
	 * @return object
	 */
	public static function hydrate( $row ) {
		$row->id      = (int) $row->id;
		$row->form_id = (int) $row->form_id;
		$row->data    = self::decode( $row->data );
		$row->files   = self::decode( $row->files );
		return $row;
	}

	/**
	 * Decode a stored column.
	 *
	 * @param string $raw Stored value.
	 * @return array
	 */
	protected static function decode( $raw ) {
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		$json = json_decode( $raw, true );
		if ( is_array( $json ) ) {
			return $json;
		}
		// Acme Forms 1.x stored serialized arrays.
		if ( is_serialized( $raw ) ) {
			$legacy = @unserialize( $raw, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
			return is_array( $legacy ) ? $legacy : array();
		}
		return array();
	}

	/**
	 * The e-mail address a submission was made with (from the first e-mail field).
	 *
	 * @param array $fields Field definitions.
	 * @param array $data   Values.
	 * @return string
	 */
	public static function find_email( array $fields, array $data ) {
		foreach ( $fields as $field ) {
			if ( 'email' === $field['type'] && ! empty( $data[ $field['name'] ] ) ) {
				return (string) $data[ $field['name'] ];
			}
		}
		return '';
	}
}
