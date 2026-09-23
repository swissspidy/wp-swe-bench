<?php
/**
 * Ticket data access and formatting.
 *
 * @package Acme\Support
 */

namespace Acme\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Tickets repository.
 */
class Tickets {

	/**
	 * Query tickets.
	 *
	 * @param array $args {
	 *     @type string $status   Ticket status slug, or '' for any.
	 *     @type string $search   Search term.
	 *     @type int    $author   Restrict to a customer (post_author). 0 = no restriction.
	 *     @type int    $page     Page number.
	 *     @type int    $per_page Page size.
	 * }
	 * @return array{items: \WP_Post[], total: int}
	 */
	public static function query( array $args ) {
		$query_args = array(
			'post_type'      => Post_Types::TICKET,
			'post_status'    => 'publish',
			'posts_per_page' => isset( $args['per_page'] ) ? (int) $args['per_page'] : 20,
			'paged'          => isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $args['status'] ) && array_key_exists( $args['status'], ticket_statuses() ) ) {
			$query_args['meta_query'] = array(
				array(
					'key'   => '_acme_status',
					'value' => $args['status'],
				),
			);
		}
		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = $args['search'];
		}
		if ( ! empty( $args['author'] ) ) {
			$query_args['author'] = (int) $args['author'];
		}

		$query = new \WP_Query( $query_args );

		return array(
			'items' => $query->posts,
			'total' => (int) $query->found_posts,
		);
	}

	/**
	 * Get a ticket post by ID.
	 *
	 * @param int $id Ticket ID.
	 * @return \WP_Post|null
	 */
	public static function get( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post || Post_Types::TICKET !== $post->post_type ) {
			return null;
		}
		return $post;
	}

	/**
	 * Create a ticket.
	 *
	 * @param array $data Ticket fields.
	 * @return int|\WP_Error
	 */
	public static function create( array $data ) {
		$post_id = wp_insert_post(
			array(
				'post_type'    => Post_Types::TICKET,
				'post_status'  => 'publish',
				'post_author'  => (int) $data['customer'],
				'post_title'   => sanitize_text_field( $data['subject'] ),
				'post_content' => wp_kses_post( $data['description'] ),
				'post_excerpt' => '',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		update_post_meta( $post_id, '_acme_status', 'acme_open' );
		update_post_meta( $post_id, '_acme_customer_email', sanitize_email( $data['customer_email'] ) );
		update_post_meta( $post_id, '_acme_priority', 'normal' );
		update_post_meta( $post_id, '_acme_agent', 0 );

		/**
		 * Fires when a ticket is created.
		 *
		 * @param int $post_id Ticket ID.
		 */
		do_action( 'acme_support_ticket_created', $post_id );

		return $post_id;
	}

	/**
	 * Update the mutable fields of a ticket.
	 *
	 * @param int   $id   Ticket ID.
	 * @param array $data Fields (status, priority, agent).
	 * @return \WP_Post|\WP_Error
	 */
	public static function update( $id, array $data ) {
		$post = self::get( $id );
		if ( ! $post ) {
			return new \WP_Error( 'acme_support_not_found', __( 'Ticket not found.', 'acme-support' ), array( 'status' => 404 ) );
		}

		if ( isset( $data['status'] ) && array_key_exists( $data['status'], ticket_statuses() ) ) {
			update_post_meta( $post->ID, '_acme_status', $data['status'] );
		}
		if ( isset( $data['priority'] ) && array_key_exists( $data['priority'], ticket_priorities() ) ) {
			update_post_meta( $post->ID, '_acme_priority', $data['priority'] );
		}
		if ( isset( $data['agent'] ) ) {
			update_post_meta( $post->ID, '_acme_agent', (int) $data['agent'] );
		}

		return self::get( $id );
	}

	/**
	 * Shape a ticket for the API.
	 *
	 * @param \WP_Post $post Ticket.
	 * @return array<string, mixed>
	 */
	public static function to_array( \WP_Post $post ) {
		return array(
			'id'             => $post->ID,
			'subject'        => $post->post_title,
			'description'    => $post->post_content,
			'status'         => (string) get_post_meta( $post->ID, '_acme_status', true ),
			'priority'       => (string) get_post_meta( $post->ID, '_acme_priority', true ),
			'customer'       => (int) $post->post_author,
			'customer_email' => (string) get_post_meta( $post->ID, '_acme_customer_email', true ),
			'agent'          => (int) get_post_meta( $post->ID, '_acme_agent', true ),
			'created'        => to_rfc3339( $post->post_date_gmt ),
			'updated'        => to_rfc3339( $post->post_modified_gmt ),
		);
	}
}
