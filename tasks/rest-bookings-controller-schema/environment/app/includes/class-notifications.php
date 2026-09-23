<?php
/**
 * E-mail notifications (office + customer).
 *
 * Other code (the Acme channel-manager sync, the office's Slack relay) listens
 * to the same actions, so they are part of the plugin's public API:
 *
 *  - acme_bookings_booking_created( int $booking_id, object $row )
 *  - acme_bookings_status_changed( int $booking_id, string $new_status, string $old_status )
 *
 * @package Acme\Bookings
 */

namespace Acme\Bookings;

defined( 'ABSPATH' ) || exit;

/**
 * Notifications.
 */
class Notifications {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'acme_bookings_booking_created', array( $this, 'notify_office' ), 10, 2 );
		add_action( 'acme_bookings_status_changed', array( $this, 'notify_customer' ), 10, 3 );
	}

	/**
	 * Office address (Settings → General admin e-mail unless overridden).
	 *
	 * @return string
	 */
	protected function office_email() {
		$email = get_option( 'acme_bookings_office_email' );
		return is_email( $email ) ? $email : get_option( 'admin_email' );
	}

	/**
	 * New booking → office.
	 *
	 * @param int         $booking_id Booking ID.
	 * @param object|null $row        Row.
	 */
	public function notify_office( $booking_id, $row = null ) {
		$row = $row ? $row : Repository::find( $booking_id );
		if ( ! $row ) {
			return;
		}
		$customer = get_userdata( (int) $row->customer_id );
		/* translators: %d: booking ID. */
		$subject = sprintf( __( '[Acme Bookings] New booking request #%d', 'acme-bookings' ), $booking_id );
		$message = sprintf(
			/* translators: 1: room, 2: customer, 3: start, 4: end, 5: guests */
			__( "Room: %1\$s\nCustomer: %2\$s\nFrom: %3\$s\nTo: %4\$s\nGuests: %5\$d", 'acme-bookings' ),
			get_the_title( (int) $row->room_id ),
			$customer ? $customer->display_name : '-',
			$row->start_date,
			$row->end_date,
			(int) $row->guests
		);
		wp_mail( $this->office_email(), $subject, $message );
	}

	/**
	 * Status change → customer.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 */
	public function notify_customer( $booking_id, $new_status, $old_status ) {
		$new_status = Repository::normalize_status( $new_status );
		if ( Repository::normalize_status( $old_status ) === $new_status ) {
			return;
		}
		$row      = Repository::find( $booking_id );
		$customer = $row ? get_userdata( (int) $row->customer_id ) : false;
		if ( ! $customer ) {
			return;
		}
		if ( 'confirmed' === $new_status ) {
			/* translators: %d: booking ID. */
			$subject = sprintf( __( 'Your booking #%d is confirmed', 'acme-bookings' ), $booking_id );
		} elseif ( 'cancelled' === $new_status ) {
			/* translators: %d: booking ID. */
			$subject = sprintf( __( 'Your booking #%d was cancelled', 'acme-bookings' ), $booking_id );
		} else {
			return;
		}
		wp_mail( $customer->user_email, $subject, sprintf( "%s\n%s - %s", get_the_title( (int) $row->room_id ), $row->start_date, $row->end_date ) );
	}
}
