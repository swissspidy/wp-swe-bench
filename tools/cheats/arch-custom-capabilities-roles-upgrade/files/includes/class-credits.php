<?php
/**
 * Front-end credit line for freelance stories.
 *
 * @package Acme\Newsroom
 */

namespace Acme\Newsroom;

defined( 'ABSPATH' ) || exit;

/**
 * Appends "Freelance contribution by …" to stories written by freelancers.
 */
class Credits {

	/**
	 * Hooks.
	 */
	public function register() {
		add_filter( 'the_content', array( $this, 'append_credit' ), 20 );
	}

	/**
	 * Adds the credit line.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public function append_credit( $content ) {
		$post = get_post();
		if ( ! $post || Story_Post_Type::POST_TYPE !== $post->post_type || ! is_singular( Story_Post_Type::POST_TYPE ) ) {
			return $content;
		}
		if ( ! is_freelancer( (int) $post->post_author ) ) {
			return $content;
		}
		$author = get_userdata( (int) $post->post_author );

		/**
		 * Filters the credit line of freelance stories.
		 *
		 * @since 2.1.0
		 *
		 * @param string   $credit Credit HTML.
		 * @param \WP_Post $post   Story.
		 */
		$credit = apply_filters(
			'acme_newsroom_freelance_credit',
			sprintf(
				'<p class="acme-story-credit">%s</p>',
				/* translators: %s: author name */
				esc_html( sprintf( __( 'Freelance contribution by %s', 'acme-newsroom' ), $author ? $author->display_name : '' ) )
			),
			$post
		);
		return $content . $credit;
	}
}
