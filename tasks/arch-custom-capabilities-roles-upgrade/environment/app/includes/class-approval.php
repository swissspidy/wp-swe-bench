<?php
/**
 * Editorial approval of stories.
 *
 * @package Acme\Newsroom
 */

namespace Acme\Newsroom;

defined( 'ABSPATH' ) || exit;

/**
 * Stores approval state and exposes it (REST field, hooks).
 */
class Approval {

	const META_APPROVED    = '_acme_approved';
	const META_APPROVED_BY = '_acme_approved_by';
	const META_APPROVED_AT = '_acme_approved_at';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_rest_field' ) );
		add_filter( 'display_post_states', array( $this, 'post_states' ), 10, 2 );
	}

	/**
	 * Who may approve stories.
	 *
	 * @param int $story_id Story ID.
	 * @return bool
	 */
	public static function current_user_can_approve( $story_id ) {
		unset( $story_id );
		// Everybody who can edit other people's posts is on the desk.
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * Approves a story.
	 *
	 * @param int $story_id Story ID.
	 * @param int $user_id  Approving user.
	 */
	public static function approve( $story_id, $user_id ) {
		update_post_meta( $story_id, self::META_APPROVED, '1' );
		update_post_meta( $story_id, self::META_APPROVED_BY, (int) $user_id );
		update_post_meta( $story_id, self::META_APPROVED_AT, current_time( 'mysql', true ) );

		/**
		 * Fires after a story was approved (the Slack bot listens to this).
		 *
		 * @since 2.0.0
		 *
		 * @param int $story_id Story ID.
		 * @param int $user_id  Approving user.
		 */
		do_action( 'acme_newsroom_story_approved', $story_id, $user_id );
	}

	/**
	 * Withdraws the approval.
	 *
	 * @param int $story_id Story ID.
	 * @param int $user_id  User withdrawing it.
	 */
	public static function unapprove( $story_id, $user_id ) {
		delete_post_meta( $story_id, self::META_APPROVED );
		delete_post_meta( $story_id, self::META_APPROVED_BY );
		delete_post_meta( $story_id, self::META_APPROVED_AT );

		/**
		 * Fires after the approval of a story was withdrawn.
		 *
		 * @since 2.0.0
		 *
		 * @param int $story_id Story ID.
		 * @param int $user_id  User.
		 */
		do_action( 'acme_newsroom_story_unapproved', $story_id, $user_id );
	}

	/**
	 * Read-only `approval` field on /wp/v2/stories.
	 */
	public function register_rest_field() {
		register_rest_field(
			Story_Post_Type::POST_TYPE,
			'approval',
			array(
				'get_callback' => static function ( $data ) {
					return get_approval( $data['id'] );
				},
				'schema'       => array(
					'description' => __( 'Editorial approval of the story.', 'acme-newsroom' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
					'properties'  => array(
						'approved'    => array( 'type' => 'boolean' ),
						'approved_by' => array( 'type' => 'integer' ),
						'approved_at' => array( 'type' => array( 'string', 'null' ) ),
					),
				),
			)
		);
	}

	/**
	 * "Approved" state in the list table.
	 *
	 * @param array    $states States.
	 * @param \WP_Post $post   Post.
	 * @return array
	 */
	public function post_states( $states, $post ) {
		if ( Story_Post_Type::POST_TYPE === $post->post_type && is_approved( $post ) && 'publish' !== $post->post_status ) {
			$states['acme_approved'] = __( 'Approved', 'acme-newsroom' );
		}
		return $states;
	}
}
