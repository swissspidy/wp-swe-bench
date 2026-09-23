<?php
/**
 * Read-only REST API for the log (used by the Acme dashboard widget app).
 *
 * @package Acme\ActivityLog
 */

namespace Acme\ActivityLog;

defined( 'ABSPATH' ) || exit;

/**
 * REST routes.
 */
class Rest {

	const NS = 'acme-activity/v1';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Routes.
	 */
	public function routes() {
		register_rest_route(
			self::NS,
			'/entries',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_entries' ),
				'permission_callback' => array( $this, 'can_read' ),
				'args'                => array(
					'action'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'user'     => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
					'search'   => array( 'type' => 'string' ),
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
			)
		);
		register_rest_route(
			self::NS,
			'/entries/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_entry' ),
				'permission_callback' => array( $this, 'can_read' ),
			)
		);
	}

	/**
	 * Only administrators.
	 *
	 * @return bool
	 */
	public function can_read() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /entries.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_entries( \WP_REST_Request $request ) {
		$args = array(
			'per_page' => (int) $request['per_page'],
			'page'     => (int) $request['page'],
		);
		if ( $request['action'] ) {
			$args['action'] = $request['action'];
		}
		if ( $request['user'] ) {
			$args['user_id'] = (int) $request['user'];
		}
		if ( $request['search'] ) {
			$args['search'] = (string) $request['search'];
		}

		$result   = Plugin::instance()->store->query( $args );
		$response = rest_ensure_response( array_map( array( $this, 'prepare' ), $result['entries'] ) );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $result['total'] / $args['per_page'] ) );
		return $response;
	}

	/**
	 * GET /entries/{id}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_entry( \WP_REST_Request $request ) {
		$entry = Plugin::instance()->store->get( (int) $request['id'] );
		if ( ! $entry ) {
			return new \WP_Error( 'acme_activity_not_found', __( 'Entry not found.', 'acme-activity-log' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $this->prepare( $entry ) );
	}

	/**
	 * Entry => response item.
	 *
	 * @param array $entry Entry.
	 * @return array
	 */
	public function prepare( array $entry ) {
		return array(
			'id'          => $entry['id'],
			'date_gmt'    => gmdate( 'Y-m-d\TH:i:s', $entry['time'] ),
			'user'        => $entry['user_id'],
			'action'      => $entry['action'],
			'object_type' => $entry['object_type'],
			'object_id'   => $entry['object_id'],
			'message'     => $entry['message'],
			'context'     => (object) $entry['context'],
		);
	}
}
