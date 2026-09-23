<?php
/**
 * Event post type, category taxonomy and meta.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `acme_event` post type and the `acme_event_category` taxonomy.
 */
class Post_Type {

	const POST_TYPE = 'acme_event';
	const TAXONOMY  = 'acme_event_category';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register post type, taxonomy and meta.
	 */
	public function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Events', 'acme-events' ),
					'singular_name' => __( 'Event', 'acme-events' ),
					'add_new_item'  => __( 'Add New Event', 'acme-events' ),
					'edit_item'     => __( 'Edit Event', 'acme-events' ),
					'all_items'     => __( 'All Events', 'acme-events' ),
					'menu_name'     => __( 'Events', 'acme-events' ),
				),
				'public'       => true,
				'has_archive'  => 'events',
				'rewrite'      => array( 'slug' => 'event' ),
				'menu_icon'    => 'dashicons-calendar-alt',
				'show_in_rest' => true,
				'rest_base'    => 'events',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Event categories', 'acme-events' ),
					'singular_name' => __( 'Event category', 'acme-events' ),
				),
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rest_base'         => 'event-categories',
				'rewrite'           => array( 'slug' => 'event-category' ),
			)
		);

		$auth = static function ( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		};
		foreach ( array( ACME_EVENTS_META_START, ACME_EVENTS_META_END, ACME_EVENTS_META_VENUE ) as $key ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => $auth,
				)
			);
		}
	}
}
