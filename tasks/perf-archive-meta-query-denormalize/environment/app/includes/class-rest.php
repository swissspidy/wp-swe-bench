<?php
/**
 * Public REST API: GET /acme-re/v1/listings (used by the mobile app and partner portals).
 *
 * @package Acme\RealEstate
 */

namespace Acme\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * REST routes.
 */
class Rest {

	const NS = 'acme-re/v1';

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
			'/listings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'search' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'min_price' => array( 'type' => array( 'integer', 'string' ) ),
					'max_price' => array( 'type' => array( 'integer', 'string' ) ),
					'beds'      => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
					'city'      => array( 'type' => 'string' ),
					'features'  => array(
						'type'  => array( 'array', 'string' ),
						'items' => array( 'type' => 'string' ),
					),
					'status'    => array(
						'type'    => 'string',
						'enum'    => array_keys( Search::status_filters() ),
						'default' => 'active',
					),
					'sort'      => array(
						'type'    => 'string',
						'enum'    => array_keys( Search::sorts() ),
						'default' => 'newest',
					),
					'page'      => array(
						'type'    => 'integer',
						'minimum' => 1,
						'default' => 1,
					),
					'per_page'  => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 48,
						'default' => Search::DEFAULT_PER_PAGE,
					),
				),
			)
		);
	}

	/**
	 * GET /listings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function search( \WP_REST_Request $request ) {
		$args = array();
		foreach ( array( 'min_price', 'max_price', 'beds', 'city', 'features', 'status', 'sort', 'page', 'per_page' ) as $key ) {
			if ( null !== $request[ $key ] ) {
				$args[ $key ] = $request[ $key ];
			}
		}

		$result = acme_re_search( $args );
		$items  = array();
		foreach ( $result['ids'] as $id ) {
			$items[] = acme_re_get_listing( $id );
		}

		$response = rest_ensure_response(
			array(
				'total' => $result['total'],
				'pages' => $result['pages'],
				'page'  => $result['page'],
				'items' => $items,
			)
		);
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) $result['pages'] );
		return $response;
	}
}
