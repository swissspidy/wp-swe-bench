<?php
/**
 * Subscriber storage.
 *
 * @package Acme\Newsletter
 */

namespace Acme\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Small repository around the subscribers table.
 */
class Subscribers {

	/**
	 * Find a subscriber by email.
	 *
	 * @param string $email Email address.
	 * @return object|null
	 */
	public static function find_by_email( $email ) {
		global $wpdb;
		$table = Installer::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s", strtolower( $email ) ) );
	}

	/**
	 * Add a subscriber.
	 *
	 * @param array $data { email, name, source }.
	 * @return int|\WP_Error Subscriber ID.
	 */
	public static function add( array $data ) {
		global $wpdb;

		$email = strtolower( sanitize_email( $data['email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'invalid', __( 'Please enter a valid email address.', 'acme-newsletter' ) );
		}
		if ( self::find_by_email( $email ) ) {
			return new \WP_Error( 'exists', __( 'You are already subscribed.', 'acme-newsletter' ) );
		}

		$row = array(
			'email'       => $email,
			'name'        => sanitize_text_field( $data['name'] ?? '' ),
			'source'      => is_valid_source( $data['source'] ?? '' ) ? $data['source'] : '',
			'status'      => 'pending',
			'confirm_key' => wp_generate_password( 32, false ),
			'created_at'  => current_time( 'mysql', true ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert( Installer::table(), $row, array( '%s', '%s', '%s', '%s', '%s', '%s' ) );
		if ( ! $ok ) {
			return new \WP_Error( 'db', __( 'Could not save your subscription.', 'acme-newsletter' ) );
		}

		$id = (int) $wpdb->insert_id;

		/**
		 * Fires after a subscriber was stored.
		 *
		 * @param int   $id  Subscriber ID.
		 * @param array $row Stored data.
		 */
		do_action( 'acme_newsletter_subscribed', $id, $row );

		return $id;
	}

	/**
	 * Latest subscribers.
	 *
	 * @param int $limit  Max rows.
	 * @param int $offset Offset.
	 * @return object[]
	 */
	public static function latest( $limit = 50, $offset = 0 ) {
		global $wpdb;
		$table = Installer::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ) );
	}

	/**
	 * Total count.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;
		$table = Installer::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Subscriber counts per source.
	 *
	 * @return array<string, int>
	 */
	public static function count_by_source() {
		global $wpdb;
		$table = Installer::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = (array) $wpdb->get_results( "SELECT source, COUNT(*) AS total FROM {$table} GROUP BY source" );
		$out  = array();
		foreach ( $rows as $row ) {
			$out[ (string) $row->source ] = (int) $row->total;
		}
		return $out;
	}
}
