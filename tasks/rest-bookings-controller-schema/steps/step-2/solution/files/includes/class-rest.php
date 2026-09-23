<?php
/**
 * REST API (namespace acme-bookings/v1).
 *
 *   /bookings, /bookings/<id>   Bookings_Controller (schema, permissions, pagination)
 *   GET /availability           booked ranges of a room (widget, public)
 *
 * Namespace acme-bookings/v2: /bookings, /bookings/<id> (Bookings_V2_Controller).
 * The v1 booking routes are deprecated in favour of v2.
 *
 * @package Acme\Bookings
 */

namespace Acme\Bookings;

defined( 'ABSPATH' ) || exit;

/**
 * REST.
 */
class Rest {

	const NAMESPACE = 'acme-bookings/v1';

	/** Deprecation date of the v1 booking routes (2026-09-01, RFC 9745 format). */
	const V1_DEPRECATION = '@1788220800';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'announce_v1_deprecation' ), 10, 3 );
	}

	/**
	 * Routes.
	 */
	public function register_routes() {
		$controller = new Bookings_Controller( self::NAMESPACE );
		$controller->register_routes();

		$v2 = new Bookings_V2_Controller();
		$v2->register_routes();

		register_rest_route(
			self::NAMESPACE,
			'/availability',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'availability' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Deprecation headers on every response of the v1 booking routes.
	 *
	 * @param \WP_HTTP_Response $response Response.
	 * @param \WP_REST_Server   $server   Server.
	 * @param \WP_REST_Request  $request  Request.
	 * @return \WP_HTTP_Response
	 */
	public function announce_v1_deprecation( $response, $server, $request ) {
		if ( ! $response instanceof \WP_HTTP_Response || ! preg_match( '#^/' . preg_quote( self::NAMESPACE, '#' ) . '/bookings(/\d+)?/?$#', $request->get_route(), $m ) ) {
			return $response;
		}
		$response->header( 'Deprecation', self::V1_DEPRECATION );
		$successor = rest_url( Bookings_V2_Controller::NAMESPACE_V2 . '/bookings' . ( isset( $m[1] ) ? untrailingslashit( $m[1] ) : '' ) );
		$link      = sprintf( '<%s>; rel="successor-version"', esc_url_raw( $successor ) );
		$headers   = $response->get_headers();
		$response->header( 'Link', empty( $headers['Link'] ) ? $link : $headers['Link'] . ', ' . $link );
		return $response;
	}

	/**
	 * GET /availability?room=<id>&from=<Y-m-d>&to=<Y-m-d>
	 *
	 * Public: only dates, never who booked.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function availability( $request ) {
		$room_id = (int) $request->get_param( 'room' );
		if ( ! Rooms::is_bookable( $room_id ) ) {
			return new \WP_Error( 'acme_bookings_invalid_room', __( 'Unknown room.', 'acme-bookings' ), array( 'status' => 404 ) );
		}
		$from = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $request->get_param( 'from' ) ) ? $request->get_param( 'from' ) : gmdate( 'Y-m-d' );
		$to   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $request->get_param( 'to' ) ) ? $request->get_param( 'to' ) : gmdate( 'Y-m-d', strtotime( $from . ' +60 days' ) );

		$booked = array();
		foreach ( Repository::booked_ranges( $room_id, $from . ' 00:00:00', $to . ' 23:59:59' ) as $range ) {
			$booked[] = array(
				'start' => substr( $range->start_date, 0, 10 ),
				'end'   => substr( $range->end_date, 0, 10 ),
			);
		}

		return array(
			'room'     => $room_id,
			'from'     => $from,
			'to'       => $to,
			'capacity' => Rooms::capacity( $room_id ),
			'booked'   => $booked,
		);
	}
}
