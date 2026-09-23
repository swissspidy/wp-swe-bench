<?php
/**
 * The Acme Support REST API (namespace `acme-support/v1`).
 *
 * These routes back the customer portal and the agent dashboard.
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
	 * Permission callback: any logged-in user.
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
	 * GET /tickets
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_tickets( $request ) {
		$result = Tickets::query(
			array(
				'status'   => (string) $request->get_param( 'status' ),
				'search'   => (string) $request->get_param( 'search' ),
				'page'     => (int) $request->get_param( 'page' ),
				'per_page' => $request->get_param( 'per_page' ) ? (int) $request->get_param( 'per_page' ) : 20,
			)
		);

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

		$response = new \WP_REST_Response( Tickets::to_array( Tickets::get( $ticket_id ) ), 201 );
		return $response;
	}

	/**
	 * GET /tickets/<id>
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_ticket( $request ) {
		$ticket = Tickets::get( $request['id'] );
		if ( ! $ticket ) {
			return new \WP_Error( 'acme_support_not_found', __( 'Ticket not found.', 'acme-support' ), array( 'status' => 404 ) );
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
			return new \WP_Error( 'acme_support_not_found', __( 'Ticket not found.', 'acme-support' ), array( 'status' => 404 ) );
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
		$ticket = Tickets::get( $request['id'] );
		if ( ! $ticket ) {
			return new \WP_Error( 'acme_support_not_found', __( 'Ticket not found.', 'acme-support' ), array( 'status' => 404 ) );
		}

		$data = array();
		foreach ( Replies::for_ticket( $ticket->ID ) as $reply ) {
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
		$ticket = Tickets::get( $request['id'] );
		if ( ! $ticket ) {
			return new \WP_Error( 'acme_support_not_found', __( 'Ticket not found.', 'acme-support' ), array( 'status' => 404 ) );
		}

		$reply_id = Replies::create(
			$ticket->ID,
			get_current_user_id(),
			(string) $request->get_param( 'body' ),
			(bool) $request->get_param( 'internal' )
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

		$reply = get_post( $reply_id );
		return new \WP_REST_Response( Replies::to_array( $reply ), 201 );
	}

	/**
	 * GET /tickets/<id>/attachments
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function list_attachments( $request ) {
		$ticket = Tickets::get( $request['id'] );
		if ( ! $ticket ) {
			return new \WP_Error( 'acme_support_not_found', __( 'Ticket not found.', 'acme-support' ), array( 'status' => 404 ) );
		}

		$data = array();
		foreach ( Attachments::for_ticket( $ticket->ID ) as $attachment ) {
			$data[] = Attachments::to_array( $attachment );
		}
		return new \WP_REST_Response( $data );
	}
}
