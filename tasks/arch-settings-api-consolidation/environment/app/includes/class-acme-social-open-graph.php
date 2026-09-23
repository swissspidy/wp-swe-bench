<?php
/**
 * Open Graph + Twitter card tags.
 *
 * @package Acme_Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Outputs social meta tags in the document head.
 *
 * The tags of singular views are cached in a transient per post
 * (`acme_social_og_{post_id}`), because resolving images is expensive on
 * sites with large media libraries.
 */
class Acme_Social_Open_Graph {

	const CACHE_PREFIX = 'acme_social_og_';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'wp_head', array( $this, 'output' ), 5 );
		add_action( 'save_post', array( $this, 'flush_post' ) );
	}

	/**
	 * Prints the tags.
	 */
	public function output() {
		if ( ! acme_social_og_enabled() ) {
			return;
		}

		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			$html    = get_transient( self::CACHE_PREFIX . $post_id );
			if ( false === $html ) {
				$html = $this->render( $this->tags_for_post( get_post( $post_id ) ) );
				set_transient( self::CACHE_PREFIX . $post_id, $html, 12 * HOUR_IN_SECONDS );
			}
		} else {
			$html = $this->render( $this->tags_for_site() );
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
	}

	/**
	 * Deletes the cached tags of a post.
	 *
	 * @param int $post_id Post ID.
	 */
	public function flush_post( $post_id ) {
		delete_transient( self::CACHE_PREFIX . $post_id );
	}

	/**
	 * Tags for a singular view.
	 *
	 * @param WP_Post $post Post.
	 * @return array<int, array{0: string, 1: string, 2: string}> List of [attribute, name, content].
	 */
	public function tags_for_post( $post ) {
		$tags   = $this->common_tags();
		$tags[] = array( 'property', 'og:type', 'article' );
		$tags[] = array( 'property', 'og:title', html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) );
		/**
		 * Filters the URL that is shared for a post (share buttons and og:url).
		 *
		 * @since 1.2.0
		 *
		 * @param string  $url  Permalink.
		 * @param WP_Post $post Post.
		 */
		$tags[] = array( 'property', 'og:url', (string) apply_filters( 'acme_social_share_url', get_permalink( $post ), $post ) );

		$excerpt = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 30, '…' );
		if ( '' !== $excerpt ) {
			$tags[] = array( 'property', 'og:description', $excerpt );
		}

		$image_id = get_post_thumbnail_id( $post ) ? get_post_thumbnail_id( $post ) : acme_social_og_default_image_id();
		$tags     = array_merge( $tags, $this->image_tags( $image_id ) );
		return $tags;
	}

	/**
	 * Tags for everything that is not singular.
	 *
	 * @return array
	 */
	public function tags_for_site() {
		$tags   = $this->common_tags();
		$tags[] = array( 'property', 'og:type', 'website' );
		$tags[] = array( 'property', 'og:title', get_bloginfo( 'name' ) );
		$tags[] = array( 'property', 'og:url', home_url( '/' ) );
		$tags   = array_merge( $tags, $this->image_tags( acme_social_og_default_image_id() ) );
		return $tags;
	}

	/**
	 * Tags on every page.
	 *
	 * @return array
	 */
	private function common_tags() {
		$tags = array(
			array( 'property', 'og:site_name', get_bloginfo( 'name' ) ),
			array( 'name', 'twitter:card', acme_social_twitter_card() ),
		);
		$handle = acme_social_twitter_handle();
		if ( '' !== $handle ) {
			$tags[] = array( 'name', 'twitter:site', '@' . $handle );
		}
		$app_id = acme_social_fb_app_id();
		if ( '' !== $app_id ) {
			$tags[] = array( 'property', 'fb:app_id', $app_id );
		}
		return $tags;
	}

	/**
	 * og:image tags.
	 *
	 * @param int $image_id Attachment ID.
	 * @return array
	 */
	private function image_tags( $image_id ) {
		if ( ! $image_id ) {
			return array();
		}
		$src = wp_get_attachment_image_src( $image_id, 'full' );
		if ( ! $src ) {
			return array();
		}
		return array(
			array( 'property', 'og:image', $src[0] ),
			array( 'property', 'og:image:width', (string) $src[1] ),
			array( 'property', 'og:image:height', (string) $src[2] ),
		);
	}

	/**
	 * Renders a list of tags.
	 *
	 * @param array $tags Tags.
	 * @return string
	 */
	private function render( array $tags ) {
		/**
		 * Filters the social meta tags before output.
		 *
		 * @since 1.4.0
		 *
		 * @param array $tags List of [attribute, name, content].
		 */
		$tags = (array) apply_filters( 'acme_social_meta_tags', $tags );
		$html = "<!-- Acme Social -->\n";
		foreach ( $tags as $tag ) {
			$html .= sprintf( '<meta %1$s="%2$s" content="%3$s" />' . "\n", esc_attr( $tag[0] ), esc_attr( $tag[1] ), esc_attr( $tag[2] ) );
		}
		return $html;
	}
}
