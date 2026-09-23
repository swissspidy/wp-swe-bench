<?php
/**
 * "Related posts" block (acme/related-posts), rendered on the server.
 *
 * @package Acme\Related
 */

namespace Acme\Related;

defined( 'ABSPATH' ) || exit;

/**
 * Block registration + render callback.
 */
class Block {

	/** @var Settings */
	private $settings;

	/** @var Engine */
	private $engine;

	/** @var Renderer */
	private $renderer;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 * @param Engine   $engine   Engine.
	 * @param Renderer $renderer Renderer.
	 */
	public function __construct( Settings $settings, Engine $engine, Renderer $renderer ) {
		$this->settings = $settings;
		$this->engine   = $engine;
		$this->renderer = $renderer;
	}

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the block type.
	 */
	public function register() {
		wp_register_style( 'acme-related', ACME_RELATED_URL . 'assets/related.css', array(), ACME_RELATED_VERSION );
		wp_register_script(
			'acme-related-block-editor',
			ACME_RELATED_URL . 'blocks/related-posts/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
			ACME_RELATED_VERSION,
			true
		);
		register_block_type(
			ACME_RELATED_DIR . 'blocks/related-posts',
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Render callback.
	 *
	 * @param array     $attributes Attributes.
	 * @param string    $content    Inner content (unused).
	 * @param \WP_Block $block      Block instance.
	 * @return string
	 */
	public function render( $attributes, $content = '', $block = null ) {
		$post_id = ! empty( $attributes['postId'] ) ? (int) $attributes['postId'] : 0;
		if ( ! $post_id && $block instanceof \WP_Block && ! empty( $block->context['postId'] ) ) {
			$post_id = (int) $block->context['postId'];
		}
		if ( ! $post_id ) {
			$post_id = (int) get_the_ID();
		}
		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || ! is_post_type_viewable( $post->post_type ) ) {
			return '';
		}

		$count = ! empty( $attributes['count'] ) ? (int) $attributes['count'] : 0;
		$items = array();
		foreach ( $this->engine->get_related_ids( $post->ID, $count ) as $id ) {
			$item = Item::from_post( $id );
			if ( $item ) {
				$items[] = $item;
			}
		}

		$classes = array( 'wp-block-acme-related-posts' );
		if ( ! empty( $attributes['align'] ) ) {
			$classes[] = 'align' . sanitize_html_class( $attributes['align'] );
		}
		if ( ! empty( $attributes['className'] ) ) {
			foreach ( preg_split( '/\s+/', $attributes['className'] ) as $class ) {
				$classes[] = sanitize_html_class( $class );
			}
		}

		$args = array( 'class' => implode( ' ', array_filter( $classes ) ) );
		if ( isset( $attributes['heading'] ) && '' !== $attributes['heading'] ) {
			$args['heading'] = $attributes['heading'];
		}
		return $this->renderer->render_list( $post->ID, $items, $args );
	}
}
