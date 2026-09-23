<?php
/**
 * Ticket replies (customer messages + internal agent notes).
 *
 * @package Acme\Support
 */

namespace Acme\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Replies repository.
 */
class Replies {

	/**
	 * All replies of a ticket, oldest first.
	 *
	 * @param int $ticket_id Ticket ID.
	 * @return \WP_Post[]
	 */
	public static function for_ticket( $ticket_id ) {
		return get_posts(
			array(
				'post_type'      => Post_Types::REPLY,
				'post_status'    => 'publish',
				'post_parent'    => (int) $ticket_id,
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * Add a reply to a ticket.
	 *
	 * @param int    $ticket_id Ticket ID.
	 * @param int    $author    Author user ID.
	 * @param string $body      Reply body.
	 * @param bool   $internal  Whether the reply is an internal note.
	 * @return int|\WP_Error
	 */
	public static function create( $ticket_id, $author, $body, $internal ) {
		$reply_id = wp_insert_post(
			array(
				'post_type'    => Post_Types::REPLY,
				'post_status'  => 'publish',
				'post_parent'  => (int) $ticket_id,
				'post_author'  => (int) $author,
				'post_content' => wp_kses_post( $body ),
			),
			true
		);
		if ( is_wp_error( $reply_id ) ) {
			return $reply_id;
		}
		update_post_meta( $reply_id, '_acme_reply_internal', $internal ? 1 : 0 );

		// An internal note is also mirrored into the ticket excerpt so the agent
		// list can preview the latest triage note without an extra query.
		if ( $internal ) {
			wp_update_post(
				array(
					'ID'           => (int) $ticket_id,
					'post_excerpt' => wp_trim_words( wp_strip_all_tags( $body ), 40, '…' ),
				)
			);
		}

		return $reply_id;
	}

	/**
	 * Whether a reply is an internal note.
	 *
	 * @param \WP_Post $reply Reply.
	 * @return bool
	 */
	public static function is_internal( \WP_Post $reply ) {
		return (bool) get_post_meta( $reply->ID, '_acme_reply_internal', true );
	}

	/**
	 * Shape a reply for the API.
	 *
	 * @param \WP_Post $reply Reply.
	 * @return array<string, mixed>
	 */
	public static function to_array( \WP_Post $reply ) {
		return array(
			'id'       => $reply->ID,
			'ticket'   => (int) $reply->post_parent,
			'author'   => (int) $reply->post_author,
			'body'     => $reply->post_content,
			'internal' => self::is_internal( $reply ),
			'created'  => to_rfc3339( $reply->post_date_gmt ),
		);
	}
}
