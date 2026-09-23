<?php
/**
 * REST API for the sales app.
 *
 * @package Acme\CRM
 */

namespace Acme\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under /acme-crm/v1. CRM users are editors and administrators.
 */
class REST_Controller {

	const NS = 'acme-crm/v1';

	/**
	 * Register routes.
	 */
	public static function register_routes() {
		$contact_args = array(
			'name'       => array(
				'type'        => 'string',
				'description' => __( 'Full name (legacy; split into first and last name unless those are given).', 'acme-crm' ),
			),
			'first_name' => array( 'type' => 'string' ),
			'last_name'  => array( 'type' => 'string' ),
			'email'   => array(
				'type'   => 'string',
				'format' => 'email',
			),
			'phone'   => array( 'type' => 'string' ),
			'company' => array( 'type' => 'string' ),
			'stage'   => array(
				'type' => 'string',
				'enum' => array_keys( Stages::all() ),
			),
			'owner'   => array( 'type' => 'integer' ),
		);

		register_rest_route(
			self::NS,
			'/contacts',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_contacts' ),
					'permission_callback' => array( __CLASS__, 'can_use_crm' ),
					'args'                => array(
						'search'   => array(
							'type'    => 'string',
							'default' => '',
						),
						'stage'    => array(
							'type'    => 'string',
							'default' => '',
						),
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_contact' ),
					'permission_callback' => array( __CLASS__, 'can_use_crm' ),
					'args'                => $contact_args,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/contacts/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_contact' ),
					'permission_callback' => array( __CLASS__, 'can_use_crm' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_contact' ),
					'permission_callback' => array( __CLASS__, 'can_use_crm' ),
					'args'                => $contact_args,
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_contact' ),
					'permission_callback' => array( __CLASS__, 'can_use_crm' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/contacts/(?P<id>\d+)/notes',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_notes' ),
					'permission_callback' => array( __CLASS__, 'can_use_crm' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'add_note' ),
					'permission_callback' => array( __CLASS__, 'can_use_crm' ),
					'args'                => array(
						'body' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Editors and administrators use the CRM.
	 *
	 * @return bool
	 */
	public static function can_use_crm() {
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * REST shape of a contact.
	 *
	 * @param object $row Row.
	 * @return array
	 */
	public static function prepare( $row ) {
		$c = Contacts::to_array( $row );
		return array(
			'id'         => $c['id'],
			'name'       => $c['full_name'],
			'first_name' => $c['first_name'],
			'last_name'  => $c['last_name'],
			'email'      => $c['email'],
			'phone'      => $c['phone'],
			'company'    => $c['company'],
			'stage'      => $c['stage'],
			'owner'      => $c['owner_id'],
			'source'     => $c['source'],
			'created_at' => mysql_to_rfc3339( $c['created_at'] ),
			'updated_at' => mysql_to_rfc3339( $c['updated_at'] ),
		);
	}

	/**
	 * Map REST params to repository fields.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	private static function fields( \WP_REST_Request $request ) {
		$map    = array(
			'name'       => 'full_name',
			'first_name' => 'first_name',
			'last_name'  => 'last_name',
			'email'      => 'email',
			'phone'      => 'phone',
			'company'    => 'company',
			'stage'      => 'stage',
			'owner'      => 'owner_id',
		);
		$params = $request->get_params();
		$out    = array();
		foreach ( $map as $param => $field ) {
			if ( array_key_exists( $param, $params ) && null !== $params[ $param ] ) {
				$out[ $field ] = $params[ $param ];
			}
		}
		return $out;
	}

	/**
	 * Fetch a contact or a 404.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return object|\WP_Error
	 */
	private static function contact_or_404( \WP_REST_Request $request ) {
		$row = Contacts::find( (int) $request['id'] );
		return $row ? $row : new \WP_Error( 'acme_crm_not_found', __( 'Contact not found.', 'acme-crm' ), array( 'status' => 404 ) );
	}

	/**
	 * GET /contacts.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function list_contacts( \WP_REST_Request $request ) {
		$result   = Contacts::query(
			array(
				'search'   => sanitize_text_field( $request['search'] ),
				'stage'    => sanitize_key( $request['stage'] ),
				'page'     => $request['page'],
				'per_page' => $request['per_page'],
			)
		);
		$response = rest_ensure_response( array_map( array( __CLASS__, 'prepare' ), $result['items'] ) );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) max( 1, (int) ceil( $result['total'] / max( 1, (int) $request['per_page'] ) ) ) );
		return $response;
	}

	/**
	 * GET /contacts/<id>.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_contact( \WP_REST_Request $request ) {
		$row = self::contact_or_404( $request );
		return is_wp_error( $row ) ? $row : rest_ensure_response( self::prepare( $row ) );
	}

	/**
	 * POST /contacts.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function create_contact( \WP_REST_Request $request ) {
		$id = Contacts::create( array_merge( array( 'source' => 'api' ), self::fields( $request ) ) );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$response = rest_ensure_response( self::prepare( Contacts::find( $id ) ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * PUT/PATCH /contacts/<id>.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function update_contact( \WP_REST_Request $request ) {
		$row = self::contact_or_404( $request );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$result = Contacts::update( (int) $row->id, self::fields( $request ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( self::prepare( Contacts::find( (int) $row->id ) ) );
	}

	/**
	 * DELETE /contacts/<id>.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function delete_contact( \WP_REST_Request $request ) {
		$row = self::contact_or_404( $request );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$previous = self::prepare( $row );
		Contacts::delete( (int) $row->id );
		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => $previous,
			)
		);
	}

	/**
	 * GET /contacts/<id>/notes.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function list_notes( \WP_REST_Request $request ) {
		$row = self::contact_or_404( $request );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		return rest_ensure_response( array_map( array( Notes::class, 'to_array' ), Notes::for_contact( (int) $row->id ) ) );
	}

	/**
	 * POST /contacts/<id>/notes.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function add_note( \WP_REST_Request $request ) {
		$row = self::contact_or_404( $request );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$id = Notes::add( (int) $row->id, $request['body'] );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$notes = wp_list_filter( Notes::for_contact( (int) $row->id ), array( 'id' => (string) $id ) );
		$response = rest_ensure_response( Notes::to_array( reset( $notes ) ) );
		$response->set_status( 201 );
		return $response;
	}
}
