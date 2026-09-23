<?php
/**
 * Registers the ticket and reply content types, their statuses and meta.
 *
 * @package Acme\Support
 */

namespace Acme\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Content types.
 */
class Post_Types {

	const TICKET = 'acme_ticket';
	const REPLY  = 'acme_reply';

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the post types and meta.
	 */
	public static function register() {
		self::register_ticket();
		self::register_reply();
		self::register_meta();
	}

	/**
	 * The ticket type. Exposed over the REST API so the agent dashboard app can query it.
	 */
	private static function register_ticket() {
		register_post_type(
			self::TICKET,
			array(
				'labels'          => array(
					'name'          => __( 'Tickets', 'acme-support' ),
					'singular_name' => __( 'Ticket', 'acme-support' ),
				),
				'public'          => true,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => true,
				'rest_base'       => 'acme_ticket',
				'has_archive'     => false,
				'hierarchical'    => false,
				'supports'        => array( 'title', 'editor', 'excerpt', 'author', 'revisions', 'custom-fields' ),
				'menu_icon'       => 'dashicons-sos',
				// Agents and customers are custom roles, so keep the capability checks
				// simple: any logged-in user has `read`.
				'map_meta_cap'    => false,
				'capabilities'    => array(
					'edit_post'          => 'read',
					'read_post'          => 'read',
					'delete_post'        => 'read',
					'edit_posts'         => 'read',
					'edit_others_posts'  => 'read',
					'publish_posts'      => 'read',
					'read_private_posts' => 'read',
					'delete_posts'       => 'read',
					'create_posts'       => 'read',
				),
			)
		);
	}

	/**
	 * Ticket replies (customer messages and internal agent notes).
	 */
	private static function register_reply() {
		register_post_type(
			self::REPLY,
			array(
				'labels'       => array(
					'name'          => __( 'Replies', 'acme-support' ),
					'singular_name' => __( 'Reply', 'acme-support' ),
				),
				'public'       => true,
				'show_ui'      => false,
				'show_in_rest' => true,
				'rest_base'    => 'acme_reply',
				'hierarchical' => false,
				'supports'     => array( 'editor', 'author', 'custom-fields' ),
				'map_meta_cap' => false,
				'capabilities' => array(
					'edit_post'          => 'read',
					'read_post'          => 'read',
					'delete_post'        => 'read',
					'edit_posts'         => 'read',
					'edit_others_posts'  => 'read',
					'publish_posts'      => 'read',
					'read_private_posts' => 'read',
					'delete_posts'       => 'read',
					'create_posts'       => 'read',
				),
			)
		);
	}

	/**
	 * Registered meta. Exposed in REST so the dashboard app can read/write it.
	 */
	private static function register_meta() {
		register_post_meta(
			self::TICKET,
			'_acme_customer_email',
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
			)
		);
		register_post_meta(
			self::TICKET,
			'_acme_status',
			array(
				'type'         => 'string',
				'single'       => true,
				'default'      => 'acme_open',
				'show_in_rest' => true,
			)
		);
		register_post_meta(
			self::TICKET,
			'_acme_priority',
			array(
				'type'         => 'string',
				'single'       => true,
				'default'      => 'normal',
				'show_in_rest' => true,
			)
		);
		register_post_meta(
			self::TICKET,
			'_acme_agent',
			array(
				'type'         => 'integer',
				'single'       => true,
				'default'      => 0,
				'show_in_rest' => true,
			)
		);
		register_post_meta(
			self::REPLY,
			'_acme_reply_internal',
			array(
				'type'         => 'boolean',
				'single'       => true,
				'default'      => false,
				'show_in_rest' => true,
			)
		);
	}
}
