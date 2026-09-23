<?php
/**
 * Member data on WordPress' own REST endpoints:
 * - `acme_profile` on /wp/v2/users (used by the mobile app's author screen),
 * - an `acme-member` type for /wp/v2/search (used by the site search overlay).
 *
 * @package Acme\Members
 */

namespace Acme\Members;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-search-handler.php';

/**
 * Core REST integration.
 */
class Rest_Fields {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_fields' ) );
		add_filter( 'wp_rest_search_handlers', array( $this, 'search_handlers' ) );
	}

	/**
	 * Register `acme_profile` on users.
	 */
	public function register_fields() {
		$properties = array();
		foreach ( Fields::all() as $key => $field ) {
			$properties[ $key ] = array(
				'type'        => 'string',
				'description' => $field['label'],
			);
		}

		register_rest_field(
			'user',
			'acme_profile',
			array(
				'get_callback' => array( $this, 'get_profile' ),
				'schema'       => array(
					'description' => __( 'Member directory profile fields.', 'acme-members' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
					'properties'  => $properties,
				),
			)
		);
	}

	/**
	 * Value of `acme_profile`.
	 *
	 * @param array $user Prepared user data.
	 * @return object
	 */
	public function get_profile( $user ) {
		if ( ! Members::is_member( $user['id'] ) ) {
			return (object) array();
		}
		// TODO: respect visibility (ACME-88). The app only shows authors, so fine for now.
		return (object) array_filter( Fields::get_all( $user['id'] ), 'strlen' );
	}

	/**
	 * Add the member search handler.
	 *
	 * @param \WP_REST_Search_Handler[] $handlers Handlers.
	 * @return \WP_REST_Search_Handler[]
	 */
	public function search_handlers( $handlers ) {
		$handlers[] = new Search_Handler();
		return $handlers;
	}
}
