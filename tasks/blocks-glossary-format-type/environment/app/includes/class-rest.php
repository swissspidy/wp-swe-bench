<?php
/**
 * Read-only REST endpoint for glossary terms.
 *
 * GET /wp-json/acme-glossary/v1/terms?search=cach&per_page=10
 * → [ { id, slug, title, definition, link }, … ]   (published terms only)
 *
 * Used by the Glossary index block in the editor and by the mobile app.
 *
 * @package Acme\Glossary
 */

namespace Acme\Glossary;

defined( 'ABSPATH' ) || exit;

/**
 * REST routes.
 */
class Rest {

	const NAMESPACE_V1 = 'acme-glossary/v1';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/terms',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_terms' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'search'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
					),
				),
			)
		);
	}

	/**
	 * List published terms, optionally filtered by a search string (title match first).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_terms( $request ) {
		$search = trim( (string) $request['search'] );
		$terms  = array_values( Term_Cache::map()['terms'] );

		if ( '' !== $search ) {
			$needle = Term_Cache::lower( $search );
			$terms  = array_values(
				array_filter(
					$terms,
					static function ( $term ) use ( $needle ) {
						return false !== strpos( Term_Cache::lower( $term['title'] ), $needle )
							|| false !== strpos( $term['slug'], sanitize_title( $needle ) );
					}
				)
			);
			// Titles starting with the search string first.
			usort(
				$terms,
				static function ( $a, $b ) use ( $needle ) {
					$a_starts = 0 === strpos( Term_Cache::lower( $a['title'] ), $needle );
					$b_starts = 0 === strpos( Term_Cache::lower( $b['title'] ), $needle );
					if ( $a_starts !== $b_starts ) {
						return $a_starts ? -1 : 1;
					}
					return strcasecmp( $a['title'], $b['title'] );
				}
			);
		}

		$terms = array_slice( $terms, 0, (int) $request['per_page'] );
		$data  = array_map(
			static function ( $term ) {
				return array(
					'id'         => $term['id'],
					'slug'       => $term['slug'],
					'title'      => $term['title'],
					'definition' => $term['definition'],
					'link'       => $term['url'],
				);
			},
			$terms
		);
		return rest_ensure_response( $data );
	}
}
