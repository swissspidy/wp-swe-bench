<?php
/**
 * REST API: acme-importer/v1/imports.
 *
 * @package Acme\Importer
 */

namespace Acme\Importer;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Imports resource.
 */
class Rest_Controller {

	const NAMESPACE = 'acme-importer/v1';

	/**
	 * Queue.
	 *
	 * @var Queue
	 */
	protected $queue;

	/**
	 * Constructor.
	 *
	 * @param Queue $queue Queue.
	 */
	public function __construct( Queue $queue ) {
		$this->queue = $queue;
	}

	/**
	 * Routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/imports',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/imports/(?P<id>\d+)',
			array(
				'args' => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/imports/(?P<id>\d+)/cancel',
			array(
				'args' => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'cancel_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * Only shop managers.
	 *
	 * @return true|WP_Error
	 */
	public function permissions_check() {
		if ( current_user_can( Installer::CAPABILITY ) ) {
			return true;
		}
		return new WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to import products.', 'acme-importer' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * GET /imports.
	 *
	 * @return WP_REST_Response
	 */
	public function get_items() {
		return rest_ensure_response( array_map( array( $this, 'prepare' ), $this->queue->jobs()->recent( 100 ) ) );
	}

	/**
	 * GET /imports/<id>.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( WP_REST_Request $request ) {
		$job = $this->queue->jobs()->get( (int) $request['id'] );
		if ( ! $job ) {
			return new WP_Error( 'acme_importer_not_found', __( 'Import not found.', 'acme-importer' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $this->prepare( $job ) );
	}

	/**
	 * POST /imports (multipart, field "file").
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		$file  = $files['file'] ?? null;
		if ( ! is_array( $file ) || empty( $file['tmp_name'] ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'acme_importer_no_file', __( 'Please upload a CSV file in the "file" field.', 'acme-importer' ), array( 'status' => 400 ) );
		}

		$job = $this->queue->enqueue( $file['tmp_name'], (string) $file['name'], get_current_user_id() );
		if ( is_wp_error( $job ) ) {
			return $job;
		}

		$response = rest_ensure_response( $this->prepare( $job ) );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( self::NAMESPACE . '/imports/' . $job->id ) );
		return $response;
	}

	/**
	 * POST /imports/<id>/cancel.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function cancel_item( WP_REST_Request $request ) {
		$job = $this->queue->cancel( (int) $request['id'] );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		return rest_ensure_response( $this->prepare( $job ) );
	}

	/**
	 * Response data for a job.
	 *
	 * @param object $job Job.
	 * @return array
	 */
	public function prepare( $job ) {
		return Job_Store::to_array( $job );
	}
}
