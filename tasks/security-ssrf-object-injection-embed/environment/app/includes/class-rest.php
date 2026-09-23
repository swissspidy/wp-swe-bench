<?php
/**
 * REST API (`acme-link-previews/v1`).
 *
 * @package Acme\LinkPreviews
 */

namespace Acme\LinkPreviews;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller.
 */
class Rest {

	const NS = 'acme-link-previews/v1';

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
			'/fetch',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'fetch' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'url' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/prefs',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_prefs' ),
				'permission_callback' => array( $this, 'is_logged_in' ),
			)
		);

		register_rest_route(
			self::NS,
			'/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'export' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'import' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'data' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * @return bool|\WP_Error
	 */
	public function is_logged_in() {
		return is_user_logged_in() ? true : new \WP_Error( 'acme_lp_unauthorized', __( 'Log in first.', 'acme-link-previews' ), array( 'status' => 401 ) );
	}

	/**
	 * @return bool|\WP_Error
	 */
	public function can_edit() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'acme_lp_unauthorized', __( 'Log in first.', 'acme-link-previews' ), array( 'status' => 401 ) );
		}
		return current_user_can( 'edit_posts' ) ? true : new \WP_Error( 'acme_lp_forbidden', __( 'You cannot do that.', 'acme-link-previews' ), array( 'status' => 403 ) );
	}

	/**
	 * @return bool|\WP_Error
	 */
	public function can_manage() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'acme_lp_unauthorized', __( 'Log in first.', 'acme-link-previews' ), array( 'status' => 401 ) );
		}
		return current_user_can( 'manage_options' ) ? true : new \WP_Error( 'acme_lp_forbidden', __( 'You cannot do that.', 'acme-link-previews' ), array( 'status' => 403 ) );
	}

	/**
	 * POST /fetch
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function fetch( $request ) {
		$preview = Fetcher::fetch( (string) $request->get_param( 'url' ) );
		if ( is_wp_error( $preview ) ) {
			return $preview;
		}
		$preview['html'] = Card::render( $preview );
		return new \WP_REST_Response( $preview );
	}

	/**
	 * GET /prefs
	 *
	 * @return \WP_REST_Response
	 */
	public function get_prefs() {
		return new \WP_REST_Response( Prefs::current() );
	}

	/**
	 * GET /export
	 *
	 * @return \WP_REST_Response
	 */
	public function export() {
		return new \WP_REST_Response( array( 'data' => Porter::export() ) );
	}

	/**
	 * POST /import
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function import( $request ) {
		$result = Porter::import( (string) $request->get_param( 'data' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new \WP_REST_Response( array( 'imported' => $result ) );
	}
}
