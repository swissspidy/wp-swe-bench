<?php
/**
 * Registers the ticket and reply content types and their meta.
 *
 * Tickets and replies hold private customer data (messages, e-mails, internal
 * agent notes, attachments). They are therefore NOT exposed through the public
 * `/wp/v2/*` content routes or the site search: every programmatic access goes
 * through the plugin's own `acme-support/v1` routes, which apply per-object
 * permission checks. See {@see Access} and {@see Rest}.
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
	 * The ticket type.
	 *
	 * Kept out of the core REST content routes and out of site search: it must
	 * not be listable, searchable or readable except through the plugin's own
	 * permission-checked routes.
	 */
	private static function register_ticket() {
		register_post_type(
			self::TICKET,
			array(
				'labels'              => array(
					'name'          => __( 'Tickets', 'acme-support' ),
					'singular_name' => __( 'Ticket', 'acme-support' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'hierarchical'        => false,
				'supports'            => array( 'title', 'editor', 'excerpt', 'author', 'revisions', 'custom-fields' ),
				'menu_icon'           => 'dashicons-sos',
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
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
				'labels'              => array(
					'name'          => __( 'Replies', 'acme-support' ),
					'singular_name' => __( 'Reply', 'acme-support' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'hierarchical'        => false,
				'supports'            => array( 'editor', 'author', 'custom-fields' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Registered meta.
	 *
	 * The meta is not exposed through the core REST routes; the plugin's own
	 * routes shape the response and only return sensitive fields (like the
	 * customer e-mail) to the ticket owner and to agents.
	 */
	private static function register_meta() {
		foreach ( array( '_acme_status', '_acme_priority' ) as $key ) {
			register_post_meta(
				self::TICKET,
				$key,
				array(
					'type'         => 'string',
					'single'       => true,
					'show_in_rest' => false,
				)
			);
		}
		register_post_meta(
			self::TICKET,
			'_acme_customer_email',
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => false,
			)
		);
		register_post_meta(
			self::TICKET,
			'_acme_agent',
			array(
				'type'         => 'integer',
				'single'       => true,
				'default'      => 0,
				'show_in_rest' => false,
			)
		);
		register_post_meta(
			self::REPLY,
			'_acme_reply_internal',
			array(
				'type'         => 'boolean',
				'single'       => true,
				'default'      => false,
				'show_in_rest' => false,
			)
		);
	}
}
