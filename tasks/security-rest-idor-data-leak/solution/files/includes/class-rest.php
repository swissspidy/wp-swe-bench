<?php
/**
 * The Acme Support REST API (namespace `acme-support/v1`).
 *
 * These routes back the customer portal and the agent dashboard. Every route
 * enforces per-object access: customers only ever see the tickets they opened,
 * agents see everything (including internal notes), and nobody sees anything
 * while logged out.
 *
 * @package Acme\Support
 */

namespace Acme\Support;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller.
 */
class Rest {

	const NS = 'acme-support/v1';

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/tickets',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_tickets' ),
					'permission_callback' => array( $this, 'require_login' ),
					'args'                => array(
						'status'   => array(
							'type' => 'string',
							'enum' => array_keys( ticket_statuses() ),
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
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_ticket' ),
					'permission_callback' => array( $this, 'require_login' ),
					'args'                => array(
						'subject'        => array(
							'type'     => 'string',
							'required' => true,
						),
						'description'    => array(
							'type'     => 'string',
							'required' => true,
						),
						'customer_email' => array(
							'type'   => 'string',
							'format' => 'email',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/tickets/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_ticket' ),
					'permission_callback' => array( $this, 'require_login' ),
				),
				array(
					'methods'             => array( 'PATCH', 'POST', 'PUT' ),
					'callback'            => array( $this, 'update_ticket' ),
					'permission_callback' => array( $this, 'require_login' ),
					'args'                => array(
						'status'   => array(
							'type' => 'string',
							'enum' => array_keys( ticket_statuses() ),
						),
						'priority' => array(
							'type' => 'string',
							'enum' => array_keys( ticket_priorities() ),
						),
						'agent'    => array( 'type' => 'integer' ),
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/tickets/(?P<id>\d+)/replies',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_replies' ),
					'permission_callback' => array( $this, 'require_login' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_reply' ),
					'permission_callback' => array( $this, 'require_login' ),
					'args'                => array(
						'body'     => array(
							'type'     => 'string',
							'required' => true,
						),
						'internal' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/tickets/(?P<id>\d+)/attachments',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_attachments' ),
					'permission_callback' => array( $this, 'require_login' ),
				),
			)
		);
	}

	/**
	 * Permission callback: any logged-in user (per-object checks happen in the
	 * callbacks).
	 *
	 * @return bool|\WP_Error
	 */
	public function require_login() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'acme_support_unauthorized', __( 'You must be logged in.', 'acme-support' ), array( 'status' => 401 ) );
		}
		return true;
	}

	/**
	 * A "not found" error that does not reveal whether the ticket exists.
	 *
	 * @return \WP_Error
	 */
	private function not_found() {
		return new \WP_Error( 'acme_support_not_found', __( 'Ticket not found.', 'acme-support' ), array( 'status' => 404 ) );
	}

	/**
	 * Load a ticket the current user is allowed to read, or a 404 error.
	 *
	 * @param int $id Ticket ID.
	 * @return \WP_Post|\WP_Error
	 */
	private function readable_ticket( $id ) {
		$ticket = Tickets::get( $id );
		if ( ! $ticket || ! Access::can_read_ticket( $ticket, get_current_user_id() ) ) {
			return $this->not_found();
		}
		return $ticket;
	}

	/**
	 * GET /tickets
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_tickets( $request ) {
		$user_id = get_current_user_id();

		$args = array(
			'status'   => (string) $request->get_param( 'status' ),
			'search'   => (string) $request->get_param( 'search' ),
			'page'     => (int) $request->get_param( 'page' ),
			'per_page' => (int) $request->get_param( 'per_page' ),
		);

		// Customers only ever see their own tickets.
		if ( ! Access::is_agent( $user_id ) ) {
			$args['author'] = $user_id;
		}

		$result = Tickets::query( $args );

		$data = array();
		foreach ( $result['items'] as $post ) {
			$data[] = Tickets::to_array( $post );
		}

		$response = new \WP_REST_Response( $data );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		return $response;
	}

	/**
	 * POST /tickets
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_ticket( $request ) {
		$user_id = get_current_user_id();
		$email   = $request->get_param( 'customer_email' );
		if ( ! $email ) {
			$user  = get_userdata( $user_id );
			$email = $user ? $user->user_email : '';
		}

		$ticket_id = Tickets::create(
			array(
				'customer'       => $user_id,
				'customer_email' => $email,
				'subject'        => (string) $request->get_param( 'subject' ),
				'description'    => (string) $request->get_param( 'description' ),
			)
		);
		if ( is_wp_error( $ticket_id ) ) {
			return $ticket_id;
		}

		return new \WP_REST_Response( Tickets::to_array( Tickets::get( $ticket_id ) ), 201 );
	}

	/**
	 * GET /tickets/<id>
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_ticket( $request ) {
		$ticket = $this->readable_ticket( $request['id'] );
		if ( is_wp_error( $ticket ) ) {
			return $ticket;
		}
		return new \WP_REST_Response( Tickets::to_array( $ticket ) );
	}

	/**
	 * PATCH /tickets/<id>
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_ticket( $request ) {
		$ticket = Tickets::get( $request['id'] );
		if ( ! $ticket ) {
			return $this->not_found();
		}
		$user_id = get_current_user_id();
		// A customer must not learn about tickets that are not theirs.
		if ( ! Access::can_read_ticket( $ticket, $user_id ) ) {
			return $this->not_found();
		}
		// Only agents may change a ticket.
		if ( ! Access::is_agent( $user_id ) ) {
			return new \WP_Error( 'acme_support_forbidden', __( 'You are not allowed to change this ticket.', 'acme-support' ), array( 'status' => 403 ) );
		}

		$data = array();
		foreach ( array( 'status', 'priority', 'agent' ) as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				$data[ $field ] = $request->get_param( $field );
			}
		}

		$updated = Tickets::update( $ticket->ID, $data );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}
		return new \WP_REST_Response( Tickets::to_array( $updated ) );
	}

	/**
	 * GET /tickets/<id>/replies
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function list_replies( $request ) {
		$ticket = $this->readable_ticket( $request['id'] );
		if ( is_wp_error( $ticket ) ) {
			return $ticket;
		}

		$is_agent = Access::is_agent( get_current_user_id() );

		$data = array();
		foreach ( Replies::for_ticket( $ticket->ID ) as $reply ) {
			// Internal notes are only ever visible to agents.
			if ( ! $is_agent && Replies::is_internal( $reply ) ) {
				continue;
			}
			$data[] = Replies::to_array( $reply );
		}
		return new \WP_REST_Response( $data );
	}

	/**
	 * POST /tickets/<id>/replies
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_reply( $request ) {
		$ticket = $this->readable_ticket( $request['id'] );
		if ( is_wp_error( $ticket ) ) {
			return $ticket;
		}

		$user_id  = get_current_user_id();
		$is_agent = Access::is_agent( $user_id );

		// Only agents can write internal notes; a customer's reply is always public.
		$internal = $is_agent ? (bool) $request->get_param( 'internal' ) : false;

		$reply_id = Replies::create(
			$ticket->ID,
			$user_id,
			(string) $request->get_param( 'body' ),
			$internal
		);
		if ( is_wp_error( $reply_id ) ) {
			return $reply_id;
		}

		/**
		 * Fires when a reply is added.
		 *
		 * @param int $reply_id  Reply ID.
		 * @param int $ticket_id Ticket ID.
		 */
		do_action( 'acme_support_reply_created', $reply_id, $ticket->ID );

		return new \WP_REST_Response( Replies::to_array( get_post( $reply_id ) ), 201 );
	}

	/**
	 * GET /tickets/<id>/attachments
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function list_attachments( $request ) {
		$ticket = $this->readable_ticket( $request['id'] );
		if ( is_wp_error( $ticket ) ) {
			return $ticket;
		}

		$data = array();
		foreach ( Attachments::for_ticket( $ticket->ID ) as $attachment ) {
			$data[] = Attachments::to_array( $attachment );
		}
		return new \WP_REST_Response( $data );
	}
}
