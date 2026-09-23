<?php
/**
 * Appends the related list to post content.
 *
 * @package Acme\Related
 */

namespace Acme\Related;

defined( 'ABSPATH' ) || exit;

/**
 * `the_content` integration.
 */
class Content {

	/** @var Settings */
	private $settings;

	/** @var Engine */
	private $engine;

	/** @var Renderer */
	private $renderer;

	/** @var bool Recursion guard (excerpts of related posts run the_content). */
	private $rendering = false;

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
		// After wpautop (10) and shortcodes (11), so the list markup is left alone.
		add_filter( 'the_content', array( $this, 'append' ), 20 );
	}

	/**
	 * Append the list below the content where the settings say so.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public function append( $content ) {
		if ( $this->rendering || ! $this->should_append() ) {
			return $content;
		}
		$post = get_post();

		$this->rendering = true;
		$items           = $this->engine->get_items( $post->ID );
		$html            = $this->renderer->render_list( $post->ID, $items );
		$this->rendering = false;

		return $content . $html;
	}

	/**
	 * Whether the current `the_content` call should get a list.
	 *
	 * @return bool
	 */
	private function should_append() {
		if ( is_admin() || is_feed() || is_embed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}
		if ( doing_filter( 'get_the_excerpt' ) || doing_filter( 'wp_trim_excerpt' ) ) {
			return false;
		}
		$post = get_post();
		if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
			return false;
		}

		$display = $this->settings->get( 'display' );
		if ( 'none' === $display ) {
			$show = false;
		} elseif ( 'everywhere' === $display ) {
			$show = is_singular( 'post' ) || is_home() || is_archive() || is_search();
		} else {
			$show = is_singular( 'post' ) && (int) get_queried_object_id() === (int) $post->ID;
		}

		/**
		 * Filters whether the related list is appended to this post's content.
		 *
		 * @param bool     $show Whether to append.
		 * @param \WP_Post $post Post.
		 */
		$show = apply_filters( 'acme_related_show_in_content', $show, $post );

		return $show && ! $this->engine->is_hidden_for( $post->ID );
	}
}
