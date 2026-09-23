<?php
/**
 * REST routes used by the office screen (assets/admin.js) and the availability
 * widget (assets/widget.js).
 *
 * Namespace: acme-bookings/v1
 *
 *   GET    /bookings                list (office screen)
 *   POST   /bookings                create (office screen + widget)
 *   GET    /bookings/<id>           one booking
 *   POST   /bookings/<id>           update (office screen)
 *   DELETE /bookings/<id>           delete (office screen)
 *   GET    /availability            booked ranges of a room (widget, public)
 *
 * @todo Permissions, validation. These routes were written for the office
 *       screen in a hurry (1.1) and have grown since.
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
		register_rest_route(
			self::NAMESPACE,
			'/bookings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_bookings' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_booking' ),
					'permission_callback' => '__return_true',
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/bookings/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_booking' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_booking' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_booking' ),
					'permission_callback' => '__return_true',
				),
			)
		);
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
	 * Decorate a DB row for the office screen.
	 *
	 * @param object $row Row.
	 * @return array
	 */
	protected function prepare_row( $row ) {
		$data                    = (array) $row;
		$data['status']          = Repository::normalize_status( $row->status );
		$data['status_label']    = acme_bookings_status_label( $row->status );
		$data['room_title']      = get_the_title( (int) $row->room_id );
		$customer                = get_userdata( (int) $row->customer_id );
		$data['customer_name']   = $customer ? $customer->display_name : '';
		$data['total']           = Pricing::total_for( $row );
		$data['total_formatted'] = Pricing::format( $data['total'] );
		return $data;
	}

	/**
	 * Parse a date or datetime string to UTC MySQL format.
	 *
	 * @param string $value    'Y-m-d' or 'Y-m-d H:i:s'.
	 * @param string $time    Default time for date-only values.
	 * @return string|null
	 */
	protected function parse_date( $value, $time ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			$value .= ' ' . $time;
		}
		$ts = strtotime( $value . ' UTC' );
		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : null;
	}

	/**
	 * GET /bookings
	 *
	 * @return array
	 */
	public function list_bookings() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$args = array(
			'room'     => isset( $_GET['room'] ) ? (int) $_GET['room'] : 0,
			'status'   => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '',
			'orderby'  => 'start',
			'order'    => 'DESC',
			'customer' => 0,
		);
		$page     = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$per_page = isset( $_GET['per_page'] ) ? max( 1, (int) $_GET['per_page'] ) : 20;
		// phpcs:enable

		if ( ! acme_bookings_user_is_manager() ) {
			$args['customer'] = get_current_user_id();
		}

		$total  = Repository::count( $args );
		$rows   = Repository::query( $args + array( 'limit' => $per_page, 'offset' => ( $page - 1 ) * $per_page ) );
		$result = array(
			'bookings' => array_map( array( $this, 'prepare_row' ), $rows ),
			'total'    => $total,
			'pages'    => (int) ceil( $total / $per_page ),
		);
		return $result;
	}

	/**
	 * GET /bookings/<id>
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function get_booking( $request ) {
		$row = Repository::find( (int) $request['id'] );
		if ( ! $row ) {
			return new \WP_Error( 'not_found', __( 'Booking not found.', 'acme-bookings' ), array( 'status' => 404 ) );
		}
		return $this->prepare_row( $row );
	}

	/**
	 * POST /bookings
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	public function create_booking( $request ) {
		$room_id = (int) $request->get_param( 'room_id' );
		$start   = $this->parse_date( $request->get_param( 'start_date' ), Pricing::CHECKIN_TIME );
		$end     = $this->parse_date( $request->get_param( 'end_date' ), Pricing::CHECKOUT_TIME );

		if ( ! Rooms::is_bookable( $room_id ) || ! $start || ! $end ) {
			return array(
				'success' => false,
				'message' => __( 'Please choose a room and valid dates.', 'acme-bookings' ),
			);
		}
		if ( Repository::find_overlap( $room_id, $start, $end ) ) {
			return array(
				'success' => false,
				'message' => __( 'Sorry, the room is not available for these dates.', 'acme-bookings' ),
			);
		}

		$customer_id = get_current_user_id();
		$status      = 'pending';
		if ( acme_bookings_user_is_manager() ) {
			if ( $request->get_param( 'customer_id' ) ) {
				$customer_id = (int) $request->get_param( 'customer_id' );
			}
			if ( $request->get_param( 'status' ) ) {
				$status = sanitize_key( $request->get_param( 'status' ) );
			}
		}

		$data = array(
			'room_id'     => $room_id,
			'customer_id' => $customer_id,
			'start_date'  => $start,
			'end_date'    => $end,
			'status'      => $status,
			'guests'      => max( 1, (int) $request->get_param( 'guests' ) ),
			'notes'       => sanitize_textarea_field( (string) $request->get_param( 'notes' ) ),
			'total'       => Pricing::quote( $room_id, strtotime( $start . ' UTC' ), strtotime( $end . ' UTC' ) ),
		);
		$id   = Repository::insert( $data );
		if ( ! $id ) {
			return array(
				'success' => false,
				'message' => __( 'Could not save the booking.', 'acme-bookings' ),
			);
		}

		$row = Repository::find( $id );
		/** This action is documented in includes/class-notifications.php */
		do_action( 'acme_bookings_booking_created', $id, $row );

		return array(
			'success' => true,
			'booking' => $this->prepare_row( $row ),
		);
	}

	/**
	 * POST /bookings/<id>
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function update_booking( $request ) {
		$id  = (int) $request['id'];
		$row = Repository::find( $id );
		if ( ! $row ) {
			return new \WP_Error( 'not_found', __( 'Booking not found.', 'acme-bookings' ), array( 'status' => 404 ) );
		}

		$data = array();
		foreach ( array( 'status', 'notes', 'admin_notes', 'guests' ) as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				$data[ $field ] = $request->get_param( $field );
			}
		}
		if ( $request->get_param( 'start_date' ) ) {
			$data['start_date'] = $this->parse_date( $request->get_param( 'start_date' ), Pricing::CHECKIN_TIME );
		}
		if ( $request->get_param( 'end_date' ) ) {
			$data['end_date'] = $this->parse_date( $request->get_param( 'end_date' ), Pricing::CHECKOUT_TIME );
		}

		Repository::update( $id, $data );

		if ( isset( $data['status'] ) && Repository::normalize_status( $data['status'] ) !== Repository::normalize_status( $row->status ) ) {
			/** This action is documented in includes/class-notifications.php */
			do_action( 'acme_bookings_status_changed', $id, Repository::normalize_status( $data['status'] ), Repository::normalize_status( $row->status ) );
		}

		return array(
			'success' => true,
			'booking' => $this->prepare_row( Repository::find( $id ) ),
		);
	}

	/**
	 * DELETE /bookings/<id>
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	public function delete_booking( $request ) {
		return array( 'deleted' => Repository::delete( (int) $request['id'] ) );
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
