<?php
/**
 * REST controller for bookings: acme-bookings/v1/bookings.
 *
 * @package Acme\Bookings
 */

namespace Acme\Bookings;

defined( 'ABSPATH' ) || exit;

/**
 * Bookings controller.
 */
class Bookings_Controller extends \WP_REST_Controller {

	/**
	 * Fields a customer may send when updating their own booking.
	 *
	 * @var string[]
	 */
	const CUSTOMER_UPDATABLE = array( 'guests', 'notes', 'status' );

	/**
	 * Constructor.
	 *
	 * @param string $namespace Route namespace.
	 */
	public function __construct( $namespace = 'acme-bookings/v1' ) {
		$this->namespace = $namespace;
		$this->rest_base = 'bookings';
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
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Unique identifier of the booking.', 'acme-bookings' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'view' ) ),
					),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	// ---------------------------------------------------------------------
	// Permissions
	// ---------------------------------------------------------------------

	/**
	 * Error for logged-out users.
	 *
	 * @return \WP_Error
	 */
	protected function not_logged_in_error() {
		return new \WP_Error( 'rest_forbidden', __( 'You must be logged in to manage bookings.', 'acme-bookings' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * 403 error.
	 *
	 * @param string $message Message.
	 * @param string $code    Code.
	 * @return \WP_Error
	 */
	protected function forbidden( $message, $code = 'rest_forbidden' ) {
		return new \WP_Error( $code, $message, array( 'status' => 403 ) );
	}

	/**
	 * Load a booking or return a 404 error.
	 *
	 * @param int $id Booking ID.
	 * @return object|\WP_Error
	 */
	protected function get_booking( $id ) {
		$row = (int) $id > 0 ? Repository::find( (int) $id ) : null;
		if ( ! $row ) {
			return new \WP_Error( 'acme_bookings_not_found', __( 'Booking not found.', 'acme-bookings' ), array( 'status' => 404 ) );
		}
		return $row;
	}

	/**
	 * Whether the current user owns a booking.
	 *
	 * @param object $row Booking row.
	 * @return bool
	 */
	protected function is_own( $row ) {
		return get_current_user_id() && (int) $row->customer_id === get_current_user_id();
	}

	/**
	 * Common checks for the context parameter.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	protected function check_context( $request ) {
		if ( 'edit' === $request['context'] && ! acme_bookings_user_is_manager() ) {
			return $this->forbidden( __( 'Sorry, you are not allowed to edit bookings.', 'acme-bookings' ), 'rest_forbidden_context' );
		}
		return true;
	}

	/**
	 * GET /bookings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return $this->not_logged_in_error();
		}
		return $this->check_context( $request );
	}

	/**
	 * GET /bookings/<id>.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return $this->not_logged_in_error();
		}
		$row = $this->get_booking( $request['id'] );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		if ( ! acme_bookings_user_is_manager() && ! $this->is_own( $row ) ) {
			return $this->forbidden( __( 'Sorry, you are not allowed to view this booking.', 'acme-bookings' ) );
		}
		return $this->check_context( $request );
	}

	/**
	 * POST /bookings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return $this->not_logged_in_error();
		}
		if ( acme_bookings_user_is_manager() ) {
			return true;
		}
		if ( isset( $request['customer'] ) && (int) $request['customer'] !== get_current_user_id() ) {
			return $this->forbidden( __( 'Sorry, you can only book for yourself.', 'acme-bookings' ) );
		}
		if ( isset( $request['status'] ) && 'pending' !== $request['status'] ) {
			return $this->forbidden( __( 'Sorry, new bookings must be pending.', 'acme-bookings' ) );
		}
		if ( isset( $request['admin_notes'] ) ) {
			return $this->forbidden( __( 'Sorry, you are not allowed to set internal notes.', 'acme-bookings' ) );
		}
		return true;
	}

	/**
	 * Update permissions.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return $this->not_logged_in_error();
		}
		$row = $this->get_booking( $request['id'] );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		if ( acme_bookings_user_is_manager() ) {
			return true;
		}
		if ( ! $this->is_own( $row ) ) {
			return $this->forbidden( __( 'Sorry, you are not allowed to edit this booking.', 'acme-bookings' ) );
		}
		foreach ( $this->writable_fields() as $field ) {
			if ( ! $this->request_has( $request, $field ) ) {
				continue;
			}
			if ( ! in_array( $field, self::CUSTOMER_UPDATABLE, true ) ) {
				return $this->forbidden( __( 'Sorry, you are not allowed to change this field.', 'acme-bookings' ) );
			}
			if ( 'status' === $field && 'cancelled' !== $request['status'] ) {
				return $this->forbidden( __( 'Sorry, you can only cancel your booking.', 'acme-bookings' ) );
			}
		}
		return true;
	}

	/**
	 * Delete permissions: managers only.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return $this->not_logged_in_error();
		}
		$row = $this->get_booking( $request['id'] );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		if ( ! acme_bookings_user_is_manager() ) {
			return $this->forbidden( __( 'Sorry, you are not allowed to delete bookings.', 'acme-bookings' ) );
		}
		return true;
	}

	/**
	 * Writable top-level schema fields.
	 *
	 * @return string[]
	 */
	protected function writable_fields() {
		$fields = array();
		foreach ( $this->get_item_schema()['properties'] as $name => $prop ) {
			if ( empty( $prop['readonly'] ) ) {
				$fields[] = $name;
			}
		}
		return $fields;
	}

	/**
	 * Was a field sent in the request body/query (not just defaulted)?
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param string           $field   Field.
	 * @return bool
	 */
	protected function request_has( $request, $field ) {
		foreach ( array( $request->get_json_params(), $request->get_body_params(), $request->get_query_params() ) as $params ) {
			if ( is_array( $params ) && array_key_exists( $field, $params ) ) {
				return true;
			}
		}
		return false;
	}

	// ---------------------------------------------------------------------
	// Handlers
	// ---------------------------------------------------------------------

	/**
	 * List bookings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_items( $request ) {
		$args = array(
			'room'     => (int) $request['room'],
			'customer' => (int) $request['customer'],
			'status'   => $request['status'] ? $request['status'] : '',
			'orderby'  => $request['orderby'],
			'order'    => $request['order'],
		);
		if ( $request['after'] ) {
			$args['ends_after'] = gmdate( 'Y-m-d H:i:s', rest_parse_date( $request['after'] ) );
		}
		if ( $request['before'] ) {
			$args['starts_before'] = gmdate( 'Y-m-d H:i:s', rest_parse_date( $request['before'] ) );
		}
		if ( ! acme_bookings_user_is_manager() ) {
			$args['customer'] = get_current_user_id();
		}

		$per_page  = (int) $request['per_page'];
		$page      = (int) $request['page'];
		$total     = Repository::count( $args );
		$max_pages = (int) ceil( $total / $per_page );

		if ( $page > $max_pages && $total > 0 ) {
			return new \WP_Error( 'rest_invalid_page_number', __( 'The page number requested is larger than the number of pages available.', 'acme-bookings' ), array( 'status' => 400 ) );
		}

		$rows  = Repository::query(
			$args + array(
				'limit'  => $per_page,
				'offset' => ( $page - 1 ) * $per_page,
			)
		);
		$items = array();
		foreach ( $rows as $row ) {
			$items[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $row, $request ) );
		}

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $max_pages );

		$base = add_query_arg( urlencode_deep( $request->get_query_params() ), rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ) );
		if ( $page > 1 ) {
			$response->link_header( 'prev', add_query_arg( 'page', min( $page - 1, max( 1, $max_pages ) ), $base ) );
		}
		if ( $page < $max_pages ) {
			$response->link_header( 'next', add_query_arg( 'page', $page + 1, $base ) );
		}
		return $response;
	}

	/**
	 * One booking.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$row = $this->get_booking( $request['id'] );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		return $this->prepare_item_for_response( $row, $request );
	}

	/**
	 * Create.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new \WP_Error( 'rest_booking_exists', __( 'Cannot create existing booking.', 'acme-bookings' ), array( 'status' => 400 ) );
		}
		$data = $this->prepare_item_for_database( $request );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( empty( $data['customer_id'] ) ) {
			$data['customer_id'] = get_current_user_id();
		}
		$data += array(
			'status' => 'pending',
			'guests' => 1,
		);

		$valid = $this->validate_booking( $data );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$conflict = $this->check_conflict( $data, 0 );
		if ( is_wp_error( $conflict ) ) {
			return $conflict;
		}

		$data['total'] = Pricing::quote( (int) $data['room_id'], acme_bookings_mysql_to_timestamp( $data['start_date'] ), acme_bookings_mysql_to_timestamp( $data['end_date'] ) );

		$id = Repository::insert( $data );
		if ( ! $id ) {
			return new \WP_Error( 'acme_bookings_db_error', __( 'Could not save the booking.', 'acme-bookings' ), array( 'status' => 500 ) );
		}
		$row = Repository::find( $id );

		/** This action is documented in includes/class-notifications.php */
		do_action( 'acme_bookings_booking_created', $id, $row );

		$request->set_param( 'context', 'edit' === $request['context'] ? 'edit' : 'view' );
		$response = $this->prepare_item_for_response( $row, $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $id ) ) );
		return $response;
	}

	/**
	 * Update.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$row = $this->get_booking( $request['id'] );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$changes = $this->prepare_item_for_database( $request );
		if ( is_wp_error( $changes ) ) {
			return $changes;
		}

		$merged = array_merge(
			array(
				'room_id'     => (int) $row->room_id,
				'customer_id' => (int) $row->customer_id,
				'start_date'  => $row->start_date,
				'end_date'    => $row->end_date,
				'status'      => Repository::normalize_status( $row->status ),
				'guests'      => (int) $row->guests,
			),
			$changes
		);
		$valid = $this->validate_booking( $merged, $changes );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$conflict = $this->check_conflict( $merged, (int) $row->id );
		if ( is_wp_error( $conflict ) ) {
			return $conflict;
		}

		if ( array_intersect_key( $changes, array_flip( array( 'room_id', 'start_date', 'end_date' ) ) ) ) {
			$changes['total'] = Pricing::quote( (int) $merged['room_id'], acme_bookings_mysql_to_timestamp( $merged['start_date'] ), acme_bookings_mysql_to_timestamp( $merged['end_date'] ) );
		}

		Repository::update( (int) $row->id, $changes );
		$old_status = Repository::normalize_status( $row->status );
		if ( isset( $changes['status'] ) && $changes['status'] !== $old_status ) {
			/** This action is documented in includes/class-notifications.php */
			do_action( 'acme_bookings_status_changed', (int) $row->id, $changes['status'], $old_status );
		}

		return $this->prepare_item_for_response( Repository::find( (int) $row->id ), $request );
	}

	/**
	 * Delete.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$row = $this->get_booking( $request['id'] );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$request->set_param( 'context', 'edit' );
		$previous = $this->prepare_item_for_response( $row, $request );
		if ( ! Repository::delete( (int) $row->id ) ) {
			return new \WP_Error( 'acme_bookings_cannot_delete', __( 'The booking cannot be deleted.', 'acme-bookings' ), array( 'status' => 500 ) );
		}
		return new \WP_REST_Response(
			array(
				'deleted'  => true,
				'previous' => $previous->get_data(),
			)
		);
	}

	// ---------------------------------------------------------------------
	// Validation helpers
	// ---------------------------------------------------------------------

	/**
	 * A 400 error for one field.
	 *
	 * @param string $field   Field.
	 * @param string $message Message.
	 * @return \WP_Error
	 */
	protected function invalid_param( $field, $message ) {
		return new \WP_Error(
			'rest_invalid_param',
			/* translators: %s: parameter names. */
			sprintf( __( 'Invalid parameter(s): %s', 'acme-bookings' ), $field ),
			array(
				'status'  => 400,
				'params'  => array( $field => $message ),
				'details' => array(),
			)
		);
	}

	/**
	 * Cross-field validation (dates, capacity).
	 *
	 * @param array $data    Merged column values.
	 * @param array $changes Columns changed by the request (update) – used to
	 *                       attribute errors to a field.
	 * @return true|\WP_Error
	 */
	protected function validate_booking( array $data, array $changes = array() ) {
		if ( strcmp( $data['end_date'], $data['start_date'] ) <= 0 ) {
			return $this->invalid_param( isset( $changes['start_date'] ) && ! isset( $changes['end_date'] ) ? 'start' : 'end', __( 'The check-out must be after the check-in.', 'acme-bookings' ) );
		}
		$capacity = Rooms::capacity( (int) $data['room_id'] );
		if ( (int) $data['guests'] > $capacity ) {
			/* translators: %d: capacity. */
			return $this->invalid_param( 'guests', sprintf( __( 'This room sleeps at most %d guests.', 'acme-bookings' ), $capacity ) );
		}
		return true;
	}

	/**
	 * Overlap detection.
	 *
	 * @param array $data       Column values.
	 * @param int   $exclude_id Booking being edited.
	 * @return true|\WP_Error
	 */
	protected function check_conflict( array $data, $exclude_id ) {
		if ( 'cancelled' === Repository::normalize_status( $data['status'] ) ) {
			return true;
		}
		$other = Repository::find_overlap( (int) $data['room_id'], $data['start_date'], $data['end_date'], $exclude_id );
		if ( $other ) {
			return new \WP_Error(
				'acme_bookings_conflict',
				__( 'The room is already booked for (part of) this period.', 'acme-bookings' ),
				array(
					'status'      => 409,
					'conflicting' => $other,
				)
			);
		}
		return true;
	}

	/**
	 * Validate callback: published room.
	 *
	 * @param mixed            $value   Value.
	 * @param \WP_REST_Request $request Request.
	 * @param string           $param   Param.
	 * @return true|\WP_Error
	 */
	public function validate_room( $value, $request, $param ) {
		$valid = rest_validate_request_arg( $value, $request, $param );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		if ( ! Rooms::is_bookable( (int) $value ) ) {
			return new \WP_Error( 'acme_bookings_invalid_room', __( 'Unknown or unavailable room.', 'acme-bookings' ) );
		}
		return true;
	}

	/**
	 * Validate callback: existing user.
	 *
	 * @param mixed            $value   Value.
	 * @param \WP_REST_Request $request Request.
	 * @param string           $param   Param.
	 * @return true|\WP_Error
	 */
	public function validate_customer( $value, $request, $param ) {
		$valid = rest_validate_request_arg( $value, $request, $param );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		if ( ! get_userdata( (int) $value ) ) {
			return new \WP_Error( 'acme_bookings_invalid_customer', __( 'Unknown customer.', 'acme-bookings' ) );
		}
		return true;
	}

	/**
	 * Parse an RFC 3339 date-time to a UTC DB value.
	 *
	 * @param string $value Date-time.
	 * @return string|null
	 */
	protected function to_mysql( $value ) {
		$ts = rest_parse_date( (string) $value );
		return false === $ts || null === $ts ? null : gmdate( 'Y-m-d H:i:s', $ts );
	}

	/**
	 * Format a UTC DB value as RFC 3339 (UTC).
	 *
	 * @param string $mysql DB value.
	 * @return string|null
	 */
	protected function to_rfc3339( $mysql ) {
		$ts = acme_bookings_mysql_to_timestamp( $mysql );
		return null === $ts ? null : gmdate( 'Y-m-d\TH:i:s+00:00', $ts );
	}

	/**
	 * Request → columns (only the fields that were sent).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	protected function prepare_item_for_database( $request ) {
		$data = array();
		$map  = array(
			'room'        => 'room_id',
			'customer'    => 'customer_id',
			'status'      => 'status',
			'guests'      => 'guests',
			'notes'       => 'notes',
			'admin_notes' => 'admin_notes',
		);
		foreach ( $map as $field => $column ) {
			if ( isset( $request[ $field ] ) ) {
				$data[ $column ] = $request[ $field ];
			}
		}
		foreach ( array(
			'start' => 'start_date',
			'end'   => 'end_date',
		) as $field => $column ) {
			if ( isset( $request[ $field ] ) ) {
				$mysql = $this->to_mysql( $request[ $field ] );
				if ( null === $mysql ) {
					return $this->invalid_param( $field, __( 'Invalid date.', 'acme-bookings' ) );
				}
				$data[ $column ] = $mysql;
			}
		}
		foreach ( array( 'room_id', 'customer_id', 'guests' ) as $int ) {
			if ( isset( $data[ $int ] ) ) {
				$data[ $int ] = (int) $data[ $int ];
			}
		}
		/**
		 * Filters a booking before it is written through the API.
		 *
		 * @param array            $data    Columns.
		 * @param \WP_REST_Request $request Request.
		 */
		return apply_filters( 'acme_bookings_rest_pre_insert_booking', $data, $request );
	}

	// ---------------------------------------------------------------------
	// Output
	// ---------------------------------------------------------------------

	/**
	 * Row → response.
	 *
	 * @param object           $item    Row.
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$data = $this->row_to_data( $item );

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $item ) );

		/**
		 * Filters a booking returned from the API.
		 *
		 * @param \WP_REST_Response $response Response.
		 * @param object            $item     Row.
		 * @param \WP_REST_Request  $request  Request.
		 */
		return apply_filters( 'acme_bookings_rest_prepare_booking', $response, $item, $request );
	}

	/**
	 * Row → full data array (before context filtering).
	 *
	 * @param object $item Row.
	 * @return array
	 */
	protected function row_to_data( $item ) {
		return array(
			'id'          => (int) $item->id,
			'room'        => (int) $item->room_id,
			'customer'    => (int) $item->customer_id,
			'start'       => $this->to_rfc3339( $item->start_date ),
			'end'         => $this->to_rfc3339( $item->end_date ),
			'status'      => Repository::normalize_status( $item->status ),
			'guests'      => (int) $item->guests,
			'notes'       => (string) $item->notes,
			'admin_notes' => (string) $item->admin_notes,
			'total'       => (float) Pricing::total_for( $item ),
			'currency'    => Pricing::currency(),
			'created'     => $this->to_rfc3339( $item->created_at ),
		);
	}

	/**
	 * Links.
	 *
	 * @param object $item Row.
	 * @return array
	 */
	protected function prepare_links( $item ) {
		$links = array(
			'self'       => array( 'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $item->id ) ) ),
			'collection' => array( 'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ) ),
		);
		if ( (int) $item->room_id ) {
			$links['room'] = array(
				'href'       => rest_url( 'wp/v2/rooms/' . (int) $item->room_id ),
				'embeddable' => true,
			);
		}
		if ( (int) $item->customer_id ) {
			$links['customer'] = array(
				'href'       => rest_url( 'wp/v2/users/' . (int) $item->customer_id ),
				'embeddable' => true,
			);
		}
		return $links;
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
		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'booking',
			'type'       => 'object',
			'properties' => $this->get_schema_properties(),
		);
		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Schema properties.
	 *
	 * @return array
	 */
	protected function get_schema_properties() {
		return array(
			'id'          => array(
				'description' => __( 'Unique identifier of the booking.', 'acme-bookings' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit', 'embed' ),
				'readonly'    => true,
			),
			'room'        => array(
				'description' => __( 'ID of the booked room.', 'acme-bookings' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit', 'embed' ),
				'required'    => true,
				'arg_options' => array( 'validate_callback' => array( $this, 'validate_room' ) ),
			),
			'customer'    => array(
				'description' => __( 'ID of the user the booking is for. Defaults to the current user.', 'acme-bookings' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit', 'embed' ),
				'arg_options' => array( 'validate_callback' => array( $this, 'validate_customer' ) ),
			),
			'start'       => array(
				'description' => __( 'Check-in (RFC 3339, returned in UTC).', 'acme-bookings' ),
				'type'        => 'string',
				'format'      => 'date-time',
				'context'     => array( 'view', 'edit', 'embed' ),
				'required'    => true,
			),
			'end'         => array(
				'description' => __( 'Check-out (RFC 3339, returned in UTC).', 'acme-bookings' ),
				'type'        => 'string',
				'format'      => 'date-time',
				'context'     => array( 'view', 'edit', 'embed' ),
				'required'    => true,
			),
			'status'      => array(
				'description' => __( 'Booking status.', 'acme-bookings' ),
				'type'        => 'string',
				'enum'        => Repository::STATUSES,
				'default'     => 'pending',
				'context'     => array( 'view', 'edit', 'embed' ),
			),
			'guests'      => array(
				'description' => __( 'Number of guests (at most the room capacity).', 'acme-bookings' ),
				'type'        => 'integer',
				'minimum'     => 1,
				'default'     => 1,
				'context'     => array( 'view', 'edit' ),
			),
			'notes'       => array(
				'description' => __( 'Notes from the customer.', 'acme-bookings' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'arg_options' => array( 'sanitize_callback' => 'sanitize_textarea_field' ),
			),
			'admin_notes' => array(
				'description' => __( 'Internal notes of the office.', 'acme-bookings' ),
				'type'        => 'string',
				'context'     => array( 'edit' ),
				'arg_options' => array( 'sanitize_callback' => 'sanitize_textarea_field' ),
			),
			'total'       => array(
				'description' => __( 'Total price in the shop currency.', 'acme-bookings' ),
				'type'        => 'number',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'currency'    => array(
				'description' => __( 'ISO 4217 currency code.', 'acme-bookings' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'created'     => array(
				'description' => __( 'When the booking was made (UTC).', 'acme-bookings' ),
				'type'        => 'string',
				'format'      => 'date-time',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
		);
	}

	/**
	 * Collection parameters.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();
		unset( $params['search'] );
		$params['context']['default'] = 'view';
		$params['per_page']['default'] = 10;

		$params['room']     = array(
			'description' => __( 'Limit to bookings of a room.', 'acme-bookings' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);
		$params['customer'] = array(
			'description' => __( 'Limit to bookings of a user.', 'acme-bookings' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);
		$params['status']   = array(
			'description' => __( 'Limit to bookings with a status.', 'acme-bookings' ),
			'type'        => 'string',
			'enum'        => Repository::STATUSES,
		);
		$params['after']    = array(
			'description' => __( 'Limit to bookings ending after this date-time.', 'acme-bookings' ),
			'type'        => 'string',
			'format'      => 'date-time',
		);
		$params['before']   = array(
			'description' => __( 'Limit to bookings starting before this date-time.', 'acme-bookings' ),
			'type'        => 'string',
			'format'      => 'date-time',
		);
		$params['orderby']  = array(
			'description' => __( 'Sort by.', 'acme-bookings' ),
			'type'        => 'string',
			'enum'        => array( 'start', 'id', 'created' ),
			'default'     => 'start',
		);
		$params['order']    = array(
			'description' => __( 'Sort direction.', 'acme-bookings' ),
			'type'        => 'string',
			'enum'        => array( 'asc', 'desc' ),
			'default'     => 'asc',
		);
		return $params;
	}
}
