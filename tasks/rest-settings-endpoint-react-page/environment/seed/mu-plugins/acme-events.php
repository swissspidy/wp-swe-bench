<?php
/**
 * Plugin Name: Acme Events
 * Description: Event post type.
 */

add_action(
	'init',
	static function () {
		register_post_type(
			'event',
			array(
				'labels'       => array(
					'name'          => 'Events',
					'singular_name' => 'Event',
				),
				'public'       => true,
				'has_archive'  => true,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'excerpt', 'custom-fields' ),
			)
		);
	}
);
