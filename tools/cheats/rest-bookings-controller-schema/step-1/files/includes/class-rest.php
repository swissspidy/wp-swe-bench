<?php
/**
 * REST API (namespace acme-bookings/v1).
 *
 *   /bookings, /bookings/<id>   Bookings_Controller (schema, permissions, pagination)
 *   GET /availability           booked ranges of a room (widget, public)
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
		$controller = new Bookings_Controller( self::NAMESPACE );
		$controller->register_routes();

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
