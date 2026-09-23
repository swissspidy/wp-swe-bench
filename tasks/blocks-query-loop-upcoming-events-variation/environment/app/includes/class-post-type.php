<?php
/**
 * Event post type.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the acme_event post type.
 */
class Post_Type {

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Register the post type.
	 */
	public function register_post_type() {
		register_post_type(
			POST_TYPE,
			array(
				'labels'        => array(
					'name'               => __( 'Events', 'acme-events-lite' ),
					'singular_name'      => __( 'Event', 'acme-events-lite' ),
					'add_new_item'       => __( 'Add New Event', 'acme-events-lite' ),
					'edit_item'          => __( 'Edit Event', 'acme-events-lite' ),
					'new_item'           => __( 'New Event', 'acme-events-lite' ),
					'view_item'          => __( 'View Event', 'acme-events-lite' ),
					'search_items'       => __( 'Search Events', 'acme-events-lite' ),
					'not_found'          => __( 'No events found.', 'acme-events-lite' ),
					'not_found_in_trash' => __( 'No events found in Trash.', 'acme-events-lite' ),
					'all_items'          => __( 'All Events', 'acme-events-lite' ),
					'menu_name'          => __( 'Events', 'acme-events-lite' ),
				),
				'public'        => true,
				'has_archive'   => 'events',
				'rewrite'       => array(
					'slug'       => 'events',
					'with_front' => false,
				),
				'menu_icon'     => 'dashicons-calendar-alt',
				'menu_position' => 21,
				'show_in_rest'  => true,
				'rest_base'     => 'events',
				'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
			)
		);
	}
}
