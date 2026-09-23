<?php
/**
 * GET /acme-orders/v1/deliveries/<event_id>: processing status of a received event.
 *
 * @package Acme\OrdersSync
 */

namespace Acme\OrdersSync;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Deliveries controller.
 */
class Deliveries_Controller {

	/**
	 * Store.
	 *
	 * @var Delivery_Store
	 */
	private $store;

	/**
	 * Constructor.
	 *
	 * @param Delivery_Store $store Store.
	 */
	public function __construct( Delivery_Store $store ) {
		$this->store = $store;
	}

	/**
	 * Registers the route.
	 */
	public function register_routes(): void {
		register_rest_route(
			REST_NAMESPACE,
			'/deliveries/(?P<event_id>[A-Za-z0-9_.:\-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'event_id' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Only site managers.
	 *
	 * @return true|WP_Error
	 */
	public function permissions_check() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return new WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to view deliveries.', 'acme-orders-sync' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * The status of one delivery.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( WP_REST_Request $request ) {
		$row = $this->store->find( (string) $request['event_id'] );
		if ( ! $row ) {
			return new WP_Error( 'acme_delivery_not_found', __( 'Unknown event.', 'acme-orders-sync' ), array( 'status' => 404 ) );
		}
		return new WP_REST_Response( Delivery_Store::to_array( $row ) );
	}
}
