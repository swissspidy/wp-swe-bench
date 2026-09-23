<?php
/**
 * Public REST API.
 *
 * @package Acme\Directory
 */

namespace Acme\Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under /acme-directory/v1. Used by the mobile app and the partner sites.
 */
class REST_Controller {

	const NAMESPACE_V1 = 'acme-directory/v1';

	/**
	 * Register routes.
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/listings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_listings' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'category' => array(
							'type'              => 'string',
							'sanitize_callback' => static function ( $value ) {
								return sanitize_title( (string) $value );
							},
							'default'           => '',
						),
						'search'   => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'default'           => '',
						),
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_listing' ),
					'permission_callback' => 'is_user_logged_in',
					'args'                => array(
						'name'        => array(
							'type'     => 'string',
							'required' => true,
						),
						'category'    => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'url'         => array(
							'type'   => 'string',
							'format' => 'uri',
						),
						'phone'       => array( 'type' => 'string' ),
						'email'       => array(
							'type'   => 'string',
							'format' => 'email',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/listings/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_listing' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/categories',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_categories' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * GET /listings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function get_listings( \WP_REST_Request $request ) {
		$rows = Listings::query(
			array(
				'category' => $request['category'],
				'search'   => $request['search'],
				'page'     => $request['page'],
				'per_page' => $request['per_page'] ? $request['per_page'] : (int) Settings::get( 'per_page' ),
			)
		);
		return rest_ensure_response( array_map( array( Listings::class, 'prepare' ), $rows ) );
	}

	/**
	 * GET /listings/<id>. Only published listings are public.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_listing( \WP_REST_Request $request ) {
		$row = Listings::get( (int) $request['id'] );
		if ( ! $row || ( 'published' !== $row->status && ! current_user_can( 'manage_options' ) ) ) {
			return new \WP_Error( 'acme_directory_not_found', __( 'Listing not found.', 'acme-directory' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( Listings::prepare( $row ) );
	}

	/**
	 * POST /listings (members can suggest listings).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function create_listing( \WP_REST_Request $request ) {
		$category = $request['category'] ? Categories::get_by_slug( sanitize_title( $request['category'] ) ) : null;
		$status   = ( Settings::get( 'moderation' ) && ! current_user_can( 'manage_options' ) ) ? 'pending' : 'published';
		$id       = Listings::create(
			array(
				'name'        => $request['name'],
				'category_id' => $category ? (int) $category->id : 0,
				'description' => (string) $request['description'],
				'url'         => (string) $request['url'],
				'phone'       => (string) $request['phone'],
				'email'       => (string) $request['email'],
				'status'      => $status,
			)
		);
		if ( is_wp_error( $id ) ) {
			$id->add_data( array( 'status' => 400 ) );
			return $id;
		}
		$response = rest_ensure_response( Listings::prepare( Listings::get( $id ) ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * GET /categories.
	 *
	 * @return \WP_REST_Response
	 */
	public static function get_categories() {
		$counts = Categories::counts();
		$out    = array();
		foreach ( Categories::all() as $category ) {
			$out[] = array(
				'id'    => (int) $category->id,
				'name'  => $category->name,
				'slug'  => $category->slug,
				'count' => isset( $counts[ (int) $category->id ] ) ? $counts[ (int) $category->id ] : 0,
			);
		}
		return rest_ensure_response( $out );
	}
}
