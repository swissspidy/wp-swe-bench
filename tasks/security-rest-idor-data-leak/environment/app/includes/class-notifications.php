<?php
/**
 * E-mail notifications.
 *
 * @package Acme\Support
 */

namespace Acme\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Notifications.
 */
class Notifications {

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'acme_support_ticket_created', array( $this, 'on_ticket_created' ) );
		add_action( 'acme_support_reply_created', array( $this, 'on_reply_created' ), 10, 2 );
	}

	/**
	 * Notify the support inbox when a ticket is created.
	 *
	 * @param int $ticket_id Ticket ID.
	 */
	public function on_ticket_created( $ticket_id ) {
		$ticket = Tickets::get( $ticket_id );
		if ( ! $ticket ) {
			return;
		}
		$inbox = get_option( 'acme_support_inbox', get_option( 'admin_email' ) );
		wp_mail(
			$inbox,
			/* translators: %s: ticket subject. */
			sprintf( __( 'New ticket: %s', 'acme-support' ), $ticket->post_title ),
			$ticket->post_content
		);
	}

	/**
	 * Notify the customer when an agent posts a public reply.
	 *
	 * @param int $reply_id  Reply ID.
	 * @param int $ticket_id Ticket ID.
	 */
	public function on_reply_created( $reply_id, $ticket_id ) {
		$reply = get_post( $reply_id );
		if ( ! $reply || Replies::is_internal( $reply ) ) {
			return;
		}
		$email = get_post_meta( $ticket_id, '_acme_customer_email', true );
		if ( $email ) {
			wp_mail(
				$email,
				__( 'A new reply on your ticket', 'acme-support' ),
				$reply->post_content
			);
		}
	}
}
