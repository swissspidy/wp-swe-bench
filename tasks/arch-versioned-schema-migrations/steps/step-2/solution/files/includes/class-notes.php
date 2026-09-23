<?php
/**
 * Notes repository.
 *
 * @package Acme\CRM
 */

namespace Acme\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * Notes attached to contacts.
 */
class Notes {

	/**
	 * Notes of a contact, oldest first.
	 *
	 * @param int $contact_id Contact ID.
	 * @return object[]
	 */
	public static function for_contact( $contact_id ) {
		global $wpdb;
		$table = Installer::table( 'notes' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE contact_id = %d ORDER BY id ASC", $contact_id ) );
	}

	/**
	 * Add a note.
	 *
	 * @param int    $contact_id Contact ID.
	 * @param string $body       Note text.
	 * @return int|\WP_Error Note ID.
	 */
	public static function add( $contact_id, $body ) {
		global $wpdb;
		$body = sanitize_textarea_field( (string) $body );
		if ( '' === trim( $body ) ) {
			return new \WP_Error( 'acme_crm_empty_note', __( 'The note is empty.', 'acme-crm' ), array( 'status' => 400 ) );
		}
		$ok = $wpdb->insert(
			Installer::table( 'notes' ),
			array(
				'contact_id' => (int) $contact_id,
				'author_id'  => get_current_user_id(),
				'body'       => $body,
				'created_at' => current_time( 'mysql', true ),
			)
		);
		if ( false === $ok ) {
			return new \WP_Error( 'acme_crm_db_error', __( 'Could not save the note.', 'acme-crm' ), array( 'status' => 500 ) );
		}
		Contacts::touch( $contact_id );
		return (int) $wpdb->insert_id;
	}

	/**
	 * REST/array shape.
	 *
	 * @param object $row Row.
	 * @return array
	 */
	public static function to_array( $row ) {
		return array(
			'id'         => (int) $row->id,
			'contact_id' => (int) $row->contact_id,
			'author'     => (int) $row->author_id,
			'body'       => (string) $row->body,
			'created_at' => mysql_to_rfc3339( $row->created_at ),
		);
	}
}
