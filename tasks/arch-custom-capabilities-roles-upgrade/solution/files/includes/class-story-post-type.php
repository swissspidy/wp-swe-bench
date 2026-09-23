<?php
/**
 * The `story` post type and the `desk` taxonomy.
 *
 * @package Acme\Newsroom
 */

namespace Acme\Newsroom;

defined( 'ABSPATH' ) || exit;

/**
 * Registers stories and desks.
 */
class Story_Post_Type {

	const POST_TYPE = 'story';
	const TAXONOMY  = 'desk';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_filter( 'post_updated_messages', array( $this, 'messages' ) );
	}

	/**
	 * Post type.
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'        => array(
					'name'               => __( 'Stories', 'acme-newsroom' ),
					'singular_name'      => __( 'Story', 'acme-newsroom' ),
					'add_new_item'       => __( 'Add New Story', 'acme-newsroom' ),
					'edit_item'          => __( 'Edit Story', 'acme-newsroom' ),
					'new_item'           => __( 'New Story', 'acme-newsroom' ),
					'view_item'          => __( 'View Story', 'acme-newsroom' ),
					'search_items'       => __( 'Search Stories', 'acme-newsroom' ),
					'not_found'          => __( 'No stories found.', 'acme-newsroom' ),
					'not_found_in_trash' => __( 'No stories found in Trash.', 'acme-newsroom' ),
					'all_items'          => __( 'All Stories', 'acme-newsroom' ),
				),
				'public'        => true,
				'has_archive'   => true,
				'show_in_rest'  => true,
				'rest_base'     => 'stories',
				'menu_icon'     => 'dashicons-media-document',
				'menu_position' => 5,
				'supports'      => array( 'title', 'editor', 'author', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ),
				'rewrite'       => array( 'slug' => 'stories' ),
				// Primitive caps: edit_stories, publish_stories, … Meta caps: edit_story, … (see Capabilities).
				'capability_type' => array( 'story', 'stories' ),
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Desks (Politics, City, Sports, …).
	 */
	public function register_taxonomy() {
		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Desks', 'acme-newsroom' ),
					'singular_name' => __( 'Desk', 'acme-newsroom' ),
				),
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'desk' ),
				// Everybody who writes stories files them under a desk; managing desks stays with editors.
				'capabilities'      => array(
					'manage_terms' => 'manage_categories',
					'edit_terms'   => 'manage_categories',
					'delete_terms' => 'manage_categories',
					'assign_terms' => 'edit_stories',
				),
			)
		);
	}

	/**
	 * Story specific update messages.
	 *
	 * @param array $messages Messages.
	 * @return array
	 */
	public function messages( $messages ) {
		$messages[ self::POST_TYPE ]    = $messages['post'];
		$messages[ self::POST_TYPE ][1] = __( 'Story updated.', 'acme-newsroom' );
		$messages[ self::POST_TYPE ][6] = __( 'Story published.', 'acme-newsroom' );
		$messages[ self::POST_TYPE ][8] = __( 'Story submitted.', 'acme-newsroom' );
		return $messages;
	}
}
