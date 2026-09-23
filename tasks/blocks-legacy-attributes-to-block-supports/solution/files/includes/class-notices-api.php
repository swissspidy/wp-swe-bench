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
	 * Works for every stored format: 1.x attributes are converted first, then
	 * presets are resolved to the theme's values.
	 *
	 * @param array $block Parsed acme/notice-box block.
	 * @return array{tone:string, background:?string, text:?string, padding:?int, font_size:?int, outlined:bool, content:string}
	 */
	public function prepare_notice( array $block ) {
		$attrs = Migrator::migrate_notice( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array() );
		$style = isset( $attrs['style'] ) && is_array( $attrs['style'] ) ? $attrs['style'] : array();
		$tone  = isset( $attrs['tone'] ) ? sanitize_key( $attrs['tone'] ) : 'info';

		$classes = preg_split( '/\s+/', (string) ( $attrs['className'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY );

		return array(
			'tone'       => array_key_exists( $tone, acme_content_blocks_tones() ) ? $tone : 'info',
			'background' => $this->color( $attrs['backgroundColor'] ?? null, $style['color']['background'] ?? null ),
			'text'       => $this->color( $attrs['textColor'] ?? null, $style['color']['text'] ?? null ),
			'padding'    => $this->size( $style['spacing']['padding'] ?? null, Presets::spacing_sizes(), 'spacing' ),
			'font_size'  => $this->font_size( $attrs['fontSize'] ?? null, $style['typography']['fontSize'] ?? null ),
			'outlined'   => in_array( 'is-style-outlined', $classes, true ),
			'content'    => trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( render_block( $block ) ) ) ),
		);
	}

	/**
	 * Hex value of a preset slug or a custom colour.
	 *
	 * @param mixed $slug   Preset slug.
	 * @param mixed $custom Custom value (may be "var:preset|color|slug").
	 * @return string|null
	 */
	private function color( $slug, $custom ) {
		$colors = Presets::colors();
		if ( is_string( $slug ) && isset( $colors[ $slug ] ) ) {
			return $colors[ $slug ];
		}
		if ( is_string( $custom ) && 0 === strpos( $custom, 'var:preset|color|' ) ) {
			$ref = substr( $custom, strlen( 'var:preset|color|' ) );
			return $colors[ $ref ] ?? null;
		}
		$hex = Colors::normalize_hex( $custom );
		return '' !== $hex ? $hex : null;
	}

	/**
	 * Pixels of a (possibly per-side) spacing value.
	 *
	 * @param mixed                 $value Spacing value.
	 * @param array<string, string> $sizes Preset sizes.
	 * @param string                $kind  Preset kind (spacing).
	 * @return int|null
	 */
	private function size( $value, array $sizes, $kind ) {
		if ( is_array( $value ) ) {
			$value = $value['top'] ?? reset( $value );
		}
		if ( ! is_string( $value ) ) {
			return null;
		}
		$prefix = 'var:preset|' . $kind . '|';
		if ( 0 === strpos( $value, $prefix ) ) {
			$slug = substr( $value, strlen( $prefix ) );
			return isset( $sizes[ $slug ] ) ? Presets::to_px( $sizes[ $slug ] ) : null;
		}
		return Presets::to_px( $value );
	}

	/**
	 * Pixels of a font size preset or custom value.
	 *
	 * @param mixed $slug   Preset slug.
	 * @param mixed $custom Custom value.
	 * @return int|null
	 */
	private function font_size( $slug, $custom ) {
		$sizes = Presets::font_sizes();
		if ( is_string( $slug ) && isset( $sizes[ $slug ] ) ) {
			return Presets::to_px( $sizes[ $slug ] );
		}
		return $this->size( $custom, $sizes, 'font-size' );
	}
}
