<?php
/**
 * REST: GET /acme-stats/v1/summary[?author=<id>]
 *
 * @package Acme\Stats
 */

namespace Acme\Stats;

defined( 'ABSPATH' ) || exit;

/**
 * REST endpoint (used by the newsroom TV screen and the mobile app).
 */
class Rest {

	/** @var Stats */
	private $stats;

	/**
	 * Constructor.
	 *
	 * @param Stats $stats Stats.
	 */
	public function __construct( Stats $stats ) {
		$this->stats = $stats;
	}

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	/**
	 * Route.
	 */
	public function register() {
		register_rest_route(
			'acme-stats/v1',
			'/summary',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'summary' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array(
					'author' => array(
						'description' => __( 'Only this author (editors and administrators only, or your own ID).', 'acme-dashboard-stats' ),
						'type'        => 'integer',
						'minimum'     => 0,
					),
				),
			)
		);
	}

	/**
	 * Scope of a request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return Scope
	 */
	private function scope( $request ) {
		$user_id = get_current_user_id();
		if ( null !== $request['author'] ) {
			return $request['author'] ? Scope::author( (int) $request['author'] ) : Scope::site();
		}
		return Scope::for_user( $user_id );
	}

	/**
	 * Permission check.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'rest_forbidden', __( 'You need to be logged in.', 'acme-dashboard-stats' ), array( 'status' => 401 ) );
		}
		if ( ! $this->scope( $request )->visible_to( get_current_user_id() ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to see these numbers.', 'acme-dashboard-stats' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Callback.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function summary( $request ) {
		return rest_ensure_response( $this->stats->get( $this->scope( $request ) ) );
	}
}
