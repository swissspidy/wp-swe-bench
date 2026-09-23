<?php
/**
 * The task list post type.
 *
 * @package Acme\Tasks
 */

namespace Acme\Tasks;

defined( 'ABSPATH' ) || exit;

/**
 * Task lists are stored as a private post type; tasks live in their own table.
 */
class Post_Type {

	const NAME         = 'acme_task_list';
	const META_COLOR   = '_acme_list_color';
	const META_MEMBERS = '_acme_list_members';
	const META_ARCHIVE = '_acme_list_archived';

	/**
	 * Register the post type and its meta.
	 */
	public static function register() {
		if ( post_type_exists( self::NAME ) ) {
			return;
		}

		register_post_type(
			self::NAME,
			array(
				'labels'          => array(
					'name'          => __( 'Task lists', 'acme-tasks' ),
					'singular_name' => __( 'Task list', 'acme-tasks' ),
				),
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'supports'        => array( 'title', 'author' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);

		register_post_meta(
			self::NAME,
			self::META_COLOR,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => DEFAULT_COLOR,
				'sanitize_callback' => __NAMESPACE__ . '\\sanitize_color',
			)
		);
	}

	/**
	 * Load a list post, or null when the ID is not a task list.
	 *
	 * @param int  $list_id  List ID.
	 * @param bool $trashed  Whether trashed lists count.
	 * @return \WP_Post|null
	 */
	public static function get_list( $list_id, $trashed = false ) {
		$post = get_post( (int) $list_id );
		if ( ! $post || self::NAME !== $post->post_type ) {
			return null;
		}
		if ( ! $trashed && 'trash' === $post->post_status ) {
			return null;
		}
		return $post;
	}

	/**
	 * Member user IDs of a list (the owner is not stored as a member).
	 *
	 * @param int $list_id List ID.
	 * @return int[]
	 */
	public static function members( $list_id ) {
		$members = get_post_meta( $list_id, self::META_MEMBERS, true );
		if ( is_string( $members ) && '' !== $members ) {
			// 1.0 stored a comma separated string.
			$members = explode( ',', $members );
		}
		return array_values( array_unique( array_filter( array_map( 'absint', (array) $members ) ) ) );
	}

	/**
	 * Replace the member list.
	 *
	 * @param int   $list_id List ID.
	 * @param int[] $members User IDs.
	 */
	public static function set_members( $list_id, array $members ) {
		update_post_meta( $list_id, self::META_MEMBERS, array_values( array_unique( array_filter( array_map( 'absint', $members ) ) ) ) );
	}
}
