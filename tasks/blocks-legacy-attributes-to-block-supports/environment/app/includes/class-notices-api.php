<?php
/**
 * REST API used by the Acme mobile app to show the notices of an article
 * natively (the app doesn't render HTML).
 *
 * GET /wp-json/acme-blocks/v1/posts/<id>/notices
 *
 * @package Acme\ContentBlocks
 */

namespace Acme\ContentBlocks;

defined( 'ABSPATH' ) || exit;

/**
 * Lists the notice boxes of a post.
 */
class Notices_API {

	const NAMESPACE_V1 = 'acme-blocks/v1';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the route.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/posts/(?P<id>\d+)/notices',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_notices' ),
				'permission_callback' => array( $this, 'can_read' ),
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Only posts the current user can read.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function can_read( $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post ) {
			return new \WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.', 'acme-content-blocks' ), array( 'status' => 404 ) );
		}
		if ( is_post_publicly_viewable( $post ) && ! post_password_required( $post ) ) {
			return true;
		}
		return current_user_can( 'edit_post', $post->ID );
	}

	/**
	 * The notices of a post, in document order.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_notices( $request ) {
		$post    = get_post( (int) $request['id'] );
		$notices = array();
		foreach ( acme_content_blocks_find( parse_blocks( $post->post_content ), 'acme/notice-box' ) as $block ) {
			$notices[] = $this->prepare_notice( $block );
		}
		return rest_ensure_response( $notices );
	}

	/**
	 * Shape of one notice for the app.
	 *
	 * @param array $block Parsed acme/notice-box block.
	 * @return array{tone:string, background:?string, text:?string, padding:?int, font_size:?int, outlined:bool, content:string}
	 */
	public function prepare_notice( array $block ) {
		$attrs = $block['attrs'] ?? array();
		$tone  = isset( $attrs['tone'] ) ? sanitize_key( $attrs['tone'] ) : 'info';

		// 1.0 notices always had colours (the defaults were not stored).
		$is_v1 = false !== strpos( (string) ( $block['innerHTML'] ?? '' ), 'acme-notice--' );

		$background = Colors::normalize_hex( $attrs['bgColor'] ?? ( $is_v1 ? '#fff8e1' : '' ) );
		$text       = Colors::normalize_hex( $attrs['textColor'] ?? ( $is_v1 ? '#3e2723' : '' ) );
		$padding    = isset( $attrs['padding'] ) ? (int) $attrs['padding'] : ( $is_v1 ? 20 : 0 );
		$font_size  = isset( $attrs['fontSize'] ) ? (int) $attrs['fontSize'] : ( $is_v1 ? 16 : 0 );

		return array(
			'tone'       => array_key_exists( $tone, acme_content_blocks_tones() ) ? $tone : 'info',
			'background' => '' !== $background ? $background : null,
			'text'       => '' !== $text ? $text : null,
			'padding'    => $padding > 0 ? $padding : null,
			'font_size'  => $font_size > 0 ? $font_size : null,
			'outlined'   => ! empty( $attrs['bordered'] ),
			'content'    => trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( render_block( $block ) ) ) ),
		);
	}
}
