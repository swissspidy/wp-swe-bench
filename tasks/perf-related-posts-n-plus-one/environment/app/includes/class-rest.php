<?php
/**
 * REST API: the `acme_related` field on posts, the related route and the view beacon.
 *
 * @package Acme\Related
 */

namespace Acme\Related;

defined( 'ABSPATH' ) || exit;

/**
 * REST integration.
 */
class Rest {

	const NAMESPACE = 'acme-related/v1';

	/** @var Settings */
	private $settings;

	/** @var Engine */
	private $engine;

	/** @var Renderer */
	private $renderer;

	/** @var Views */
	private $views;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 * @param Engine   $engine   Engine.
	 * @param Renderer $renderer Renderer.
	 * @param Views    $views    Views.
	 */
	public function __construct( Settings $settings, Engine $engine, Renderer $renderer, Views $views ) {
		$this->settings = $settings;
		$this->engine   = $engine;
		$this->renderer = $renderer;
		$this->views    = $views;
	}

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
	}

	/**
	 * Post meta used by the editor.
	 */
	public function register_meta() {
		register_post_meta(
			'post',
			Engine::META_MANUAL,
			array(
				'type'          => 'array',
				'single'        => true,
				'show_in_rest'  => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
				),
				'auth_callback' => static function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}

	/**
	 * Routes and fields.
	 */
	public function register() {
		register_rest_field(
			'post',
			'acme_related',
			array(
				'get_callback' => array( $this, 'get_field' ),
				'schema'       => array(
					'description' => __( 'Related posts, best first.', 'acme-related' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
					'items'       => self::item_schema(),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/related/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_related' ),
				'permission_callback' => array( $this, 'can_read' ),
				'args'                => array(
					'count' => array(
						'type'    => 'integer',
						'minimum' => 0,
						'maximum' => 12,
						'default' => 0,
					),
					'html'  => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/views/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'count_view' ),
				'permission_callback' => array( $this, 'can_read' ),
			)
		);
	}

	/**
	 * Item schema (shared by the field and the route).
	 *
	 * @return array
	 */
	public static function item_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'           => array( 'type' => 'integer' ),
				'title'        => array( 'type' => 'string' ),
				'link'         => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'date'         => array( 'type' => 'string' ),
				'date_display' => array( 'type' => 'string' ),
				'author'       => array( 'type' => array( 'object', 'null' ) ),
				'category'     => array( 'type' => array( 'object', 'null' ) ),
				'image'        => array( 'type' => array( 'object', 'null' ) ),
				'views'        => array( 'type' => 'integer' ),
				'reading_time' => array( 'type' => 'integer' ),
			),
		);
	}

	/**
	 * `acme_related` field value.
	 *
	 * @param array $post_data Prepared post data.
	 * @return array[]
	 */
	public function get_field( $post_data ) {
		return $this->items_for( (int) $post_data['id'], 0 );
	}

	/**
	 * Related items of a post.
	 *
	 * @param int $post_id Post ID.
	 * @param int $count   Count (0 = setting).
	 * @return array[]
	 */
	private function items_for( $post_id, $count ) {
		$items = array();
		foreach ( $this->engine->get_related_ids( $post_id, $count ) as $id ) {
			$item = Item::from_post( $id );
			if ( $item ) {
				$items[] = $item;
			}
		}
		return $items;
	}

	/**
	 * Public posts only.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function can_read( $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || 'publish' !== $post->post_status || ! is_post_type_viewable( $post->post_type ) || post_password_required( $post ) ) {
			return new \WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.', 'acme-related' ), array( 'status' => 404 ) );
		}
		return true;
	}

	/**
	 * GET /acme-related/v1/related/<id>
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_related( $request ) {
		$post_id = (int) $request['id'];
		$items   = $this->items_for( $post_id, (int) $request['count'] );
		$data    = array( 'items' => $items );
		if ( $request['html'] ) {
			$data['html'] = $this->renderer->render_list( $post_id, $items );
		}
		return rest_ensure_response( $data );
	}

	/**
	 * POST /acme-related/v1/views/<id> (the front-end beacon).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function count_view( $request ) {
		return rest_ensure_response( array( 'views' => $this->views->increment( (int) $request['id'] ) ) );
	}
}
