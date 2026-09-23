<?php
/**
 * Story capabilities, the Freelancer role and meta capability mapping.
 *
 * @package Acme\Newsroom
 */

namespace Acme\Newsroom;

defined( 'ABSPATH' ) || exit;

/**
 * Capability model of the newsroom.
 *
 * Primitive capabilities: the story equivalents of the post capabilities
 * plus `approve_stories`. Meta capabilities: `edit_story`, `delete_story`,
 * `read_story` (mapped by core) and `approve_story` (mapped here).
 */
class Capabilities {

	const APPROVE         = 'approve_stories';
	const APPROVE_META    = 'approve_story';
	const FREELANCER_ROLE = 'acme_freelancer';

	/**
	 * Post capability => story capability.
	 */
	const POST_TO_STORY = array(
		'edit_posts'             => 'edit_stories',
		'edit_others_posts'      => 'edit_others_stories',
		'edit_published_posts'   => 'edit_published_stories',
		'edit_private_posts'     => 'edit_private_stories',
		'publish_posts'          => 'publish_stories',
		'delete_posts'           => 'delete_stories',
		'delete_others_posts'    => 'delete_others_stories',
		'delete_published_posts' => 'delete_published_stories',
		'delete_private_posts'   => 'delete_private_stories',
		'read_private_posts'     => 'read_private_stories',
	);

	/**
	 * Capabilities of the Freelancer role.
	 */
	const FREELANCER_CAPS = array(
		'read'           => true,
		'upload_files'   => true,
		'edit_stories'   => true,
		'delete_stories' => true,
	);

	/**
	 * Hooks.
	 */
	public function register() {
		add_filter( 'map_meta_cap', array( $this, 'map_meta_cap' ), 10, 4 );
	}

	/**
	 * Every primitive story capability.
	 *
	 * @return string[]
	 */
	public static function story_caps() {
		return array_merge( array_values( self::POST_TO_STORY ), array( self::APPROVE ) );
	}

	/**
	 * Grants the story capabilities to the default roles.
	 */
	public static function grant_to_roles() {
		$grants = array(
			'administrator' => self::story_caps(),
			'editor'        => self::story_caps(),
			'author'        => array( 'edit_stories', 'edit_published_stories', 'publish_stories', 'delete_stories', 'delete_published_stories' ),
			'contributor'   => array( 'edit_stories', 'delete_stories' ),
		);
		foreach ( $grants as $name => $caps ) {
			$role = get_role( $name );
			if ( ! $role ) {
				continue;
			}
			foreach ( $caps as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Creates the Freelancer role if it doesn't exist.
	 */
	public static function add_freelancer_role() {
		if ( ! get_role( self::FREELANCER_ROLE ) ) {
			add_role( self::FREELANCER_ROLE, __( 'Freelancer', 'acme-newsroom' ), self::FREELANCER_CAPS );
		}
	}

	/**
	 * Maps `approve_story` and locks approved stories.
	 *
	 * @param string[] $caps    Required primitive caps.
	 * @param string   $cap     Requested capability.
	 * @param int      $user_id User ID.
	 * @param array    $args    Arguments (story ID first).
	 * @return string[]
	 */
	public function map_meta_cap( $caps, $cap, $user_id, $args ) {
		if ( self::APPROVE_META === $cap ) {
			$story = isset( $args[0] ) ? get_post( (int) $args[0] ) : null;
			if ( ! $story || Story_Post_Type::POST_TYPE !== $story->post_type || 'trash' === $story->post_status ) {
				return array( 'do_not_allow' );
			}
			// Four eyes: nobody approves their own story.
			if ( (int) $story->post_author === (int) $user_id ) {
				return array( 'do_not_allow' );
			}
			return array( self::APPROVE );
		}

		$locked = array(
			'edit_post'    => 'edit_others_stories',
			'edit_story'   => 'edit_others_stories',
			'delete_post'  => 'delete_others_stories',
			'delete_story' => 'delete_others_stories',
		);
		if ( isset( $locked[ $cap ] ) && ! empty( $args[0] ) ) {
			$story = get_post( (int) $args[0] );
			// Once the desk approved a story, only people who can handle others' stories may change it.
			if ( $story && Story_Post_Type::POST_TYPE === $story->post_type && is_approved( $story ) && ! in_array( $locked[ $cap ], $caps, true ) ) {
				$caps[] = $locked[ $cap ];
			}
		}
		return $caps;
	}
}
