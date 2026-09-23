<?php
/**
 * Central access-control rules for tickets and everything hanging off them.
 *
 * A ticket is readable by the customer who opened it and by support agents.
 * Everything that belongs to a ticket (its replies, its attachments) inherits
 * the ticket's read permission. Only agents may change a ticket; only managers
 * may delete one.
 *
 * The read rule is also wired into WordPress' capability system so that the
 * always-on core media routes cannot be used to read a ticket's attachments.
 *
 * @package Acme\Support
 */

namespace Acme\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Access rules.
 */
class Access {

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_filter( 'map_meta_cap', array( $this, 'map_caps' ), 10, 4 );
	}

	/**
	 * Whether the user is a support agent (works every ticket).
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_agent( $user_id ) {
		return $user_id > 0 && user_can( $user_id, Installer::CAP_AGENT );
	}

	/**
	 * Whether the user is a support manager.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_manager( $user_id ) {
		return $user_id > 0 && user_can( $user_id, Installer::CAP_MANAGE );
	}

	/**
	 * Whether the user may read a ticket.
	 *
	 * @param \WP_Post $ticket  Ticket.
	 * @param int      $user_id User ID.
	 * @return bool
	 */
	public static function can_read_ticket( \WP_Post $ticket, $user_id ) {
		if ( self::is_agent( $user_id ) ) {
			return true;
		}
		return $user_id > 0 && (int) $ticket->post_author === (int) $user_id;
	}

	/**
	 * Resolve the ticket that governs a post's access, if any.
	 *
	 * @param \WP_Post $post Post.
	 * @return \WP_Post|null
	 */
	private function governing_ticket( \WP_Post $post ) {
		if ( Post_Types::TICKET === $post->post_type ) {
			return $post;
		}
		if ( Post_Types::REPLY === $post->post_type ) {
			return Tickets::get( $post->post_parent );
		}
		if ( 'attachment' === $post->post_type && $post->post_parent ) {
			return Tickets::get( $post->post_parent );
		}
		return null;
	}

	/**
	 * Map the read/edit/delete meta capabilities for tickets and anything
	 * attached to them (replies, uploaded files) to the ticket's rules.
	 *
	 * @param string[] $caps    Required primitive capabilities.
	 * @param string   $cap     Capability being checked.
	 * @param int      $user_id User ID.
	 * @param array    $args    Arguments (the object ID is $args[0]).
	 * @return string[]
	 */
	public function map_caps( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, array( 'read_post', 'edit_post', 'delete_post' ), true ) ) {
			return $caps;
		}
		if ( empty( $args[0] ) ) {
			return $caps;
		}
		$post = get_post( $args[0] );
		if ( ! $post instanceof \WP_Post ) {
			return $caps;
		}
		$ticket = $this->governing_ticket( $post );
		if ( ! $ticket ) {
			return $caps;
		}

		$deny = array( 'do_not_allow' );

		if ( 'read_post' === $cap ) {
			return self::can_read_ticket( $ticket, $user_id ) ? array( 'read' ) : $deny;
		}
		if ( 'delete_post' === $cap ) {
			return self::is_manager( $user_id ) ? array( 'read' ) : $deny;
		}
		// edit_post.
		return self::is_agent( $user_id ) ? array( 'read' ) : $deny;
	}
}
