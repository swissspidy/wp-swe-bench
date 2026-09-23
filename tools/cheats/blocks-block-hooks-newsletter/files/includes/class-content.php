<?php
/**
 * Automatic placement of the form after single posts.
 *
 * @package Acme\Newsletter
 */

namespace Acme\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Appends the signup form to the content of single posts.
 */
class Content {

	/**
	 * Register hooks.
	 */
	public function register() {
		add_filter( 'the_content', array( $this, 'append_form' ), 20 );
	}

	/**
	 * Whether a post already contains a signup form (block or shortcode).
	 *
	 * @param \WP_Post|int|null $post Post.
	 * @return bool
	 */
	public static function post_has_form( $post = null ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return false;
		}
		return has_block( Block::NAME, $post ) || has_shortcode( $post->post_content, Shortcode::TAG );
	}

	/**
	 * Whether the automatic form should be shown for the current request.
	 *
	 * @return bool
	 */
	public static function should_auto_insert() {
		if ( ! get_setting( 'auto_insert' ) ) {
			return false;
		}
		if ( ! is_singular( 'post' ) ) {
			return false;
		}
		/**
		 * Filters whether the signup form is added after the post content.
		 *
		 * @param bool     $insert Whether to insert.
		 * @param \WP_Post $post   Current post.
		 */
		return (bool) apply_filters( 'acme_newsletter_auto_insert', ! self::post_has_form(), get_post() );
	}

	/**
	 * the_content filter.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function append_form( $content ) {
		if ( wp_is_block_theme() ) {
			return $content; // Block themes: the block is hooked after core/post-content.
		}
		if ( ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( ! self::should_auto_insert() ) {
			return $content;
		}
		return $content . render_form( array( 'source' => 'content' ) );
	}
}
