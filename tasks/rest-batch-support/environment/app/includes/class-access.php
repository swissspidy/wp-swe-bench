<?php
/**
 * Who may see and change which list.
 *
 * @package Acme\Tasks
 */

namespace Acme\Tasks;

defined( 'ABSPATH' ) || exit;

/**
 * Access rules.
 *
 * - Using task lists at all requires the `edit_posts` capability.
 * - The owner (post author) and the members of a list can see it and work on its tasks.
 * - Users who can `edit_others_posts` (editors, administrators) can see and work on every list.
 * - Only the owner and `edit_others_posts` users may change the list itself (title, color,
 *   members, archiving) or delete it.
 *
 * Meta capabilities: `acme_view_task_list`, `acme_manage_task_list` (arg: list ID).
 */
class Access {

	/**
	 * Map the plugin's meta capabilities to primitive ones.
	 *
	 * @param string[] $caps    Required primitive caps.
	 * @param string   $cap     Requested capability.
	 * @param int      $user_id User ID.
	 * @param array    $args    Extra args (list ID first).
	 * @return string[]
	 */
	public static function map_meta_cap( $caps, $cap, $user_id, $args ) {
		if ( 'acme_view_task_list' !== $cap && 'acme_manage_task_list' !== $cap ) {
			return $caps;
		}

		$list = isset( $args[0] ) ? Post_Type::get_list( $args[0], true ) : null;
		if ( ! $list ) {
			return array( 'do_not_allow' );
		}

		if ( (int) $list->post_author === (int) $user_id ) {
			return array( 'edit_posts' );
		}

		if ( 'acme_view_task_list' === $cap && in_array( (int) $user_id, Post_Type::members( $list->ID ), true ) ) {
			return array( 'edit_posts' );
		}

		return array( 'edit_others_posts' );
	}

	/**
	 * Can the current user use task lists at all?
	 *
	 * @return bool
	 */
	public static function can_use() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Can the given user see the list and work on its tasks?
	 *
	 * @param int      $list_id List ID.
	 * @param int|null $user_id User (default: current).
	 * @return bool
	 */
	public static function can_view_list( $list_id, $user_id = null ) {
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		return $user_id && user_can( $user_id, 'acme_view_task_list', (int) $list_id );
	}

	/**
	 * Can the given user change or delete the list itself?
	 *
	 * @param int      $list_id List ID.
	 * @param int|null $user_id User (default: current).
	 * @return bool
	 */
	public static function can_manage_list( $list_id, $user_id = null ) {
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		return $user_id && user_can( $user_id, 'acme_manage_task_list', (int) $list_id );
	}

	/**
	 * Standard "not allowed" error for REST permission callbacks.
	 *
	 * @param string $message Message.
	 * @return \WP_Error
	 */
	public static function forbidden( $message = '' ) {
		return new \WP_Error(
			'rest_forbidden',
			$message ? $message : __( 'Sorry, you are not allowed to do that.', 'acme-tasks' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}
}
