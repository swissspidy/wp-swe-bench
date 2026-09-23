<?php
/**
 * Ticket attachments (files customers and agents add to a ticket).
 *
 * @package Acme\Support
 */

namespace Acme\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Attachments repository.
 */
class Attachments {

	/**
	 * Attachments of a ticket.
	 *
	 * @param int $ticket_id Ticket ID.
	 * @return \WP_Post[]
	 */
	public static function for_ticket( $ticket_id ) {
		return get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_parent'    => (int) $ticket_id,
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * Shape an attachment for the API.
	 *
	 * @param \WP_Post $attachment Attachment.
	 * @return array<string, mixed>
	 */
	public static function to_array( \WP_Post $attachment ) {
		return array(
			'id'        => $attachment->ID,
			'ticket'    => (int) $attachment->post_parent,
			'filename'  => wp_basename( get_attached_file( $attachment->ID ) ),
			'mime_type' => $attachment->post_mime_type,
			'url'       => wp_get_attachment_url( $attachment->ID ),
		);
	}
}
