<?php
/**
 * Directory REST API: /acme-members/v1/members.
 *
 * @package Acme\Members
 */

namespace Acme\Members;

defined( 'ABSPATH' ) || exit;

/**
 * Members controller.
 */
class Rest_Controller extends \WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'acme-members/v1';
		$this->rest_base = 'members';
	}

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/me',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_me' ),
					'permission_callback' => array( $this, 'me_permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_me' ),
					'permission_callback' => array( $this, 'me_permissions_check' ),
					'args'                => $this->get_update_args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/me/connections',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_connections' ),
					'permission_callback' => array( $this, 'me_permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_connection' ),
					'permission_callback' => array( $this, 'me_permissions_check' ),
					'args'                => array(
						'member' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/me/connections/(?P<member>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_connection' ),
					'permission_callback' => array( $this, 'me_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Collection params.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'page'     => array(
				'type'    => 'integer',
				'default' => 1,
				'minimum' => 1,
			),
			'per_page' => array(
				'type'    => 'integer',
				'default' => 10,
				'minimum' => 1,
				'maximum' => 50,
			),
			'search'   => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'city'     => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Update args (validated by Profile_Service in the callback).
	 *
	 * @return array
	 */
	private function get_update_args() {
		$args = array();
		foreach ( Fields::all() as $key => $field ) {
			$args[ $key ] = array(
				'type'        => 'string',
				'description' => $field['label'],
			);
		}
		$args['visibility']       = array(
			'type' => 'string',
			'enum' => array_keys( Visibility::levels() ),
		);
		$args['field_visibility'] = array(
			'type' => 'object',
		);
		return $args;
	}

	/**
	 * GET /members.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		$viewer   = get_current_user_id();
		$ids      = Profile_Service::visible_ids( $viewer, (string) $request['search'], (string) $request['city'] );
		$per_page = (int) $request['per_page'];
		$page     = (int) $request['page'];
		$total    = count( $ids );
		$pages    = (int) ceil( $total / $per_page );

		$data = array();
		foreach ( array_slice( $ids, ( $page - 1 ) * $per_page, $per_page ) as $id ) {
			$data[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $id, $request ) );
		}

		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );
		return $response;
	}

	/**
	 * GET /members/<id>.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$id = (int) $request['id'];
		if ( ! Visibility::can_view_profile( $id, get_current_user_id() ) ) {
			return new \WP_Error( 'acme_members_not_found', __( 'Member not found.', 'acme-members' ), array( 'status' => 404 ) );
		}
		return $this->prepare_item_for_response( $id, $request );
	}

	/**
	 * Permission for /members/me.
	 *
	 * @return true|\WP_Error
	 */
	public function me_permissions_check() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'rest_not_logged_in', __( 'You are not currently logged in.', 'acme-members' ), array( 'status' => 401 ) );
		}
		if ( ! Members::is_member( get_current_user_id() ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'Only members have a directory profile.', 'acme-members' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * GET /members/me.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_me( $request ) {
		return $this->prepare_item_for_response( get_current_user_id(), $request );
	}

	/**
	 * POST/PUT/PATCH /members/me.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_me( $request ) {
		$input = array();
		foreach ( array_merge( Fields::keys(), array( 'visibility', 'field_visibility' ) ) as $key ) {
			if ( $request->has_param( $key ) ) {
				$input[ $key ] = $request->get_param( $key );
			}
		}
		$result = Profile_Service::validate( $input );
		if ( $result['errors'] ) {
			return new \WP_Error(
				'rest_invalid_param',
				/* translators: %s: comma separated parameter names */
				sprintf( __( 'Invalid parameter(s): %s', 'acme-members' ), implode( ', ', array_keys( $result['errors'] ) ) ),
				array(
					'status' => 400,
					'params' => $result['errors'],
				)
			);
		}
		$user_id = get_current_user_id();
		Profile_Service::save( $user_id, $result['changes'] );
		return $this->prepare_item_for_response( $user_id, $request );
	}

	/**
	 * GET /members/me/connections.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_connections() {
		$user_id = get_current_user_id();
		return rest_ensure_response(
			array(
				'connections' => Connections::connected( $user_id ),
				'incoming'    => Connections::incoming( $user_id ),
				'outgoing'    => Connections::outgoing( $user_id ),
			)
		);
	}

	/**
	 * POST /members/me/connections: request (or accept) a connection.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_connection( $request ) {
		$user_id = get_current_user_id();
		$other   = (int) $request['member'];
		if ( $other === $user_id ) {
			$message = __( 'You cannot connect with yourself.', 'acme-members' );
			return new \WP_Error(
				'rest_invalid_param',
				$message,
				array(
					'status' => 400,
					'params' => array( 'member' => $message ),
				)
			);
		}
		// Only members you can see (don't reveal hidden ones), unless they already asked you.
		$known = in_array( $other, Connections::incoming( $user_id ), true ) || Connections::are_connected( $user_id, $other );
		if ( ! Members::is_member( $other ) || ( ! $known && ! Visibility::can_view_profile( $other, $user_id ) ) ) {
			return new \WP_Error( 'acme_members_not_found', __( 'Member not found.', 'acme-members' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response(
			array(
				'member' => $other,
				'status' => Connections::request( $user_id, $other ),
			)
		);
	}

	/**
	 * DELETE /members/me/connections/<member>: remove, cancel or decline.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_connection( $request ) {
		$other = (int) $request['member'];
		if ( ! Connections::remove( get_current_user_id(), $other ) ) {
			return new \WP_Error( 'acme_members_not_found', __( 'No connection or request with this member.', 'acme-members' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response(
			array(
				'member' => $other,
				'status' => 'none',
			)
		);
	}

	/**
	 * Member object.
	 *
	 * @param int              $item    User ID.
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$response = rest_ensure_response( Profile_Service::to_array( (int) $item, get_current_user_id() ) );
		$response->add_link( 'author', rest_url( 'wp/v2/users/' . (int) $item ) );
		return $response;
	}

	/**
	 * Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}
		$fields = array();
		foreach ( Fields::all() as $key => $field ) {
			$fields[ $key ] = array(
				'type'        => 'string',
				'description' => $field['label'],
			);
		}
		$levels       = array_keys( Visibility::levels() );
		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'acme-member',
			'type'       => 'object',
			'properties' => array(
				'id'         => array(
					'type'     => 'integer',
					'readonly' => true,
				),
				'name'       => array(
					'type'     => 'string',
					'readonly' => true,
				),
				'slug'       => array(
					'type'     => 'string',
					'readonly' => true,
				),
				'link'       => array(
					'type'     => 'string',
					'format'   => 'uri',
					'readonly' => true,
				),
				'fields'     => array(
					'type'        => 'object',
					'description' => __( 'Profile fields the current user may see.', 'acme-members' ),
					'properties'  => $fields,
				),
				'visibility' => array(
					'type'        => 'object',
					'description' => __( 'Visibility settings (only for the member and user editors).', 'acme-members' ),
					'properties'  => array(
						'profile' => array(
							'type' => 'string',
							'enum' => $levels,
						),
						'fields'  => array( 'type' => 'object' ),
					),
				),
			),
		);
		return $this->add_additional_fields_schema( $this->schema );
	}
}
