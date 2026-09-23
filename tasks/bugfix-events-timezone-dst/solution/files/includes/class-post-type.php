<?php
/**
 * Event post type.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `acme_event` post type and the event category taxonomy.
 */
class Post_Type {

	const NAME     = 'acme_event';
	const TAXONOMY = 'acme_event_category';

	/**
	 * Registers the post type and taxonomy.
	 */
	public static function register() {
		register_post_type(
			self::NAME,
			array(
				'labels'       => array(
					'name'          => __( 'Events', 'acme-events' ),
					'singular_name' => __( 'Event', 'acme-events' ),
					'add_new_item'  => __( 'Add New Event', 'acme-events' ),
					'edit_item'     => __( 'Edit Event', 'acme-events' ),
					'all_items'     => __( 'All Events', 'acme-events' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-calendar-alt',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
				'rewrite'      => array( 'slug' => 'events' ),
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::NAME,
			array(
				'label'             => __( 'Event categories', 'acme-events' ),
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'event-category' ),
			)
		);
	}
}
