<?php
/**
 * Email notifications.
 *
 * @package Acme\Leads
 */

namespace Acme\Leads;

defined( 'ABSPATH' ) || exit;

/**
 * Sends mails on new and won leads.
 */
class Notifications {

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'acme_leads_created', array( $this, 'on_created' ), 10, 2 );
		add_action( 'acme_leads_status_changed', array( $this, 'on_status_changed' ), 10, 3 );
	}

	/**
	 * New lead from the website: tell the sales inbox.
	 *
	 * @param int   $id   Lead ID.
	 * @param array $data Lead data.
	 */
	public function on_created( $id, $data ) {
		if ( 'form' !== $data['source'] ) {
			return;
		}
		wp_mail(
			get_option( 'acme_leads_inbox', get_option( 'admin_email' ) ),
			/* translators: %s: lead name */
			sprintf( __( 'New lead: %s', 'acme-leads' ), $data['name'] ),
			/* translators: 1: name, 2: email, 3: company */
			sprintf( __( "Name: %1\$s\nEmail: %2\$s\nCompany: %3\$s", 'acme-leads' ), $data['name'], $data['email'], $data['company'] )
		);
	}

	/**
	 * A lead was won: congratulate the owner, copy the sales inbox.
	 *
	 * @param int    $id         Lead ID.
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 */
	public function on_status_changed( $id, $new_status, $old_status ) {
		if ( 'won' !== $new_status ) {
			return;
		}
		$lead = Repository::find( $id );
		if ( ! $lead ) {
			return;
		}
		$to    = array( get_option( 'acme_leads_inbox', get_option( 'admin_email' ) ) );
		$owner = $lead['owner_id'] ? get_userdata( (int) $lead['owner_id'] ) : false;
		if ( $owner ) {
			$to[] = $owner->user_email;
		}
		wp_mail(
			$to,
			/* translators: %s: lead name */
			sprintf( __( 'Lead won: %s', 'acme-leads' ), $lead['name'] ),
			/* translators: 1: lead name, 2: previous status */
			sprintf( __( '%1$s was moved from "%2$s" to "won". Well done!', 'acme-leads' ), $lead['name'], $old_status )
		);
	}
}
