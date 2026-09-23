<?php
/**
 * Helpers for the Acme Support IDOR tests.
 */

namespace WPSB\Support;

/**
 * Seeded user ID by login.
 *
 * @param string $login Login.
 * @return int
 */
function user_id( string $login ): int {
	$user = get_user_by( 'login', $login );
	if ( ! $user ) {
		throw new \RuntimeException( "Seeded user '$login' not found" );
	}
	return (int) $user->ID;
}

/**
 * Ticket ID by subject (post_title).
 *
 * @param string $subject_like Substring of the subject.
 * @return int
 */
function ticket_id( string $subject_like ): int {
	global $wpdb;
	$id = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'acme_ticket' AND post_title LIKE %s ORDER BY ID ASC LIMIT 1",
			'%' . $wpdb->esc_like( $subject_like ) . '%'
		)
	);
	if ( ! $id ) {
		throw new \RuntimeException( "Seeded ticket like '$subject_like' not found" );
	}
	return $id;
}

/**
 * First attachment ID of a ticket.
 *
 * @param int $ticket_id Ticket ID.
 * @return int
 */
function attachment_id( int $ticket_id ): int {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_parent = %d ORDER BY ID ASC LIMIT 1",
			$ticket_id
		)
	);
}

/**
 * A reply ID that is an internal note on the given ticket.
 *
 * @param int $ticket_id Ticket ID.
 * @return int
 */
function internal_reply_id( int $ticket_id ): int {
	global $wpdb;
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'acme_reply' AND post_parent = %d",
			$ticket_id
		)
	);
	foreach ( $ids as $id ) {
		if ( (int) get_post_meta( (int) $id, '_acme_reply_internal', true ) ) {
			return (int) $id;
		}
	}
	return 0;
}
