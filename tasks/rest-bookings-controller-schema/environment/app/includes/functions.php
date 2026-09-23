<?php
/**
 * Template tags and small helpers.
 *
 * @package Acme\Bookings
 */

defined( 'ABSPATH' ) || exit;

/**
 * Capability that makes a user a "booking manager" (the office staff).
 *
 * Administrators and users with the Booking Manager role have it. Filterable so
 * that sites can map the office to an existing capability.
 *
 * @return string
 */
function acme_bookings_manager_capability() {
	/**
	 * Filters the capability required to manage all bookings.
	 *
	 * @param string $capability Default 'manage_acme_bookings'.
	 */
	return (string) apply_filters( 'acme_bookings_manager_capability', 'manage_acme_bookings' );
}

/**
 * Whether a user may see and manage every booking.
 *
 * @param int|null $user_id User ID, defaults to the current user.
 * @return bool
 */
function acme_bookings_user_is_manager( $user_id = null ) {
	$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
	if ( ! $user_id ) {
		return false;
	}
	return user_can( $user_id, acme_bookings_manager_capability() );
}

/**
 * Human readable label of a booking status.
 *
 * @param string $status Status slug (legacy spellings accepted).
 * @return string
 */
function acme_bookings_status_label( $status ) {
	$labels = array(
		'pending'   => __( 'Pending', 'acme-bookings' ),
		'confirmed' => __( 'Confirmed', 'acme-bookings' ),
		'cancelled' => __( 'Cancelled', 'acme-bookings' ),
	);
	$status = Acme\Bookings\Repository::normalize_status( $status );
	return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
}

/**
 * Convert a DB datetime (UTC, 'Y-m-d H:i:s') to a timestamp.
 *
 * @param string|null $mysql DB value.
 * @return int|null
 */
function acme_bookings_mysql_to_timestamp( $mysql ) {
	if ( empty( $mysql ) || '0000-00-00 00:00:00' === $mysql ) {
		return null;
	}
	$dt = date_create_immutable_from_format( 'Y-m-d H:i:s', $mysql, new DateTimeZone( 'UTC' ) );
	return $dt ? $dt->getTimestamp() : null;
}
