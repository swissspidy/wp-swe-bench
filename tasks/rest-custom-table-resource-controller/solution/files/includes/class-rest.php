<?php
/**
 * REST API v1.
 *
 * - GET  /acme-leads/v1/leads  Leads for the CRM sync job (managers only).
 * - POST /acme-leads/v1/leads  Public lead form submissions.
 *
 * @package Acme\Leads
 */

namespace Acme\Leads;

defined( 'ABSPATH' ) || exit;

/**
 * v1 routes.
 */
class Rest {

	const NAMESPACE_V1 = 'acme-leads/v1';

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'serve_not_modified' ), 10, 4 );
	}

	/**
	 * 304 responses have no body.
	 *
	 * @param bool              $served  Whether the request was served.
	 * @param \WP_HTTP_Response $result  Result.
	 * @param \WP_REST_Request  $request Request.
	 * @param \WP_REST_Server   $server  Server.
	 * @return bool
	 */
	public function serve_not_modified( $served, $result, $request, $server ) {
		if ( ! $served && $result instanceof \WP_HTTP_Response && 304 === $result->get_status() && 0 === strpos( $request->get_route(), '/acme-leads/' ) ) {
			return true;
		}
		return $served;
	}

	/**
	 * Routes.
	 */
	public function register_routes() {
		( new Leads_Controller() )->register_routes();

		register_rest_route(
			self::NAMESPACE_V1,
			'/leads',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_leads' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
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
						'orderby'  => array(
							'type'    => 'string',
							'default' => 'created_at',
							'enum'    => Repository::SORTABLE,
						),
						'order'    => array(
							'type'              => 'string',
							'default'           => 'DESC',
							'validate_callback' => array( $this, 'validate_order' ),
						),
						'status'   => array(
							'type' => 'string',
							'enum' => Repository::STATUSES,
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'submit' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'name'    => array(
							'type'      => 'string',
							'required'  => true,
							'minLength' => 1,
							'maxLength' => 191,
						),
						'email'   => array(
							'type'     => 'string',
							'format'   => 'email',
							'required' => true,
						),
						'company' => array(
							'type'      => 'string',
							'maxLength' => 191,
							'default'   => '',
						),
						'message' => array(
							'type'    => 'string',
							'default' => '',
						),
						'website' => array(
							'description' => 'Honeypot, must stay empty.',
							'type'        => 'string',
							'default'     => '',
						),
					),
				),
			)
		);
	}

	/**
	 * `order` must be asc or desc (any case).
	 *
	 * @param mixed $value Value.
	 * @return true|\WP_Error
	 */
	public function validate_order( $value ) {
		if ( is_string( $value ) && in_array( strtolower( $value ), array( 'asc', 'desc' ), true ) ) {
			return true;
		}
		return new \WP_Error( 'rest_invalid_param', __( 'order must be asc or desc.', 'acme-leads' ), array( 'status' => 400 ) );
	}

	/**
	 * Permission: sales managers.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( Installer::CAP_MANAGE );
	}

	/**
	 * GET /leads.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_leads( $request ) {
		$result = Repository::query(
			array(
				'status'   => (string) $request['status'],
				'orderby'  => $request['orderby'],
				'order'    => $request['order'],
				'per_page' => $request['per_page'],
				'page'     => $request['page'],
			)
		);

		$response = rest_ensure_response( array_map( array( $this, 'format_v1' ), $result['items'] ) );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $result['total'] / $request['per_page'] ) );
		return $response;
	}

	/**
	 * v1 lead format (no notes).
	 *
	 * @param array $row Row.
	 * @return array
	 */
	public function format_v1( $row ) {
		return array(
			'id'      => (int) $row['id'],
			'name'    => $row['name'],
			'email'   => $row['email'],
			'company' => $row['company'],
			'status'  => $row['status'],
			'source'  => $row['source'],
			'score'   => (int) $row['score'],
			'owner'   => (int) $row['owner_id'],
			'created' => $row['created_at'],
		);
	}

	/**
	 * POST /leads: lead form.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function submit( $request ) {
		if ( '' !== $request['website'] ) {
			// Bots fill every field. Pretend it worked.
			return new \WP_REST_Response( array( 'received' => true ), 201 );
		}
		$id = Repository::insert(
			array(
				'name'    => $request['name'],
				'email'   => $request['email'],
				'company' => $request['company'],
				'notes'   => $request['message'],
				'source'  => 'form',
				'score'   => acme_leads_initial_score( $request['email'], $request['company'] ),
			)
		);
		if ( ! $id ) {
			return new \WP_Error( 'acme_leads_db_error', __( 'Could not save your request.', 'acme-leads' ), array( 'status' => 500 ) );
		}
		return new \WP_REST_Response( array( 'received' => true ), 201 );
	}
}
