<?php
/**
 * Template tags and helpers for themes.
 *
 * @package Acme\Related
 */

defined( 'ABSPATH' ) || exit;

/**
 * Related post IDs for a post, in display order.
 *
 * @param int|WP_Post|null $post  Post (defaults to the current post).
 * @param int              $count Number of posts (0 = use the setting).
 * @return int[]
 */
function acme_related_get_ids( $post = null, $count = 0 ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return array();
	}
	return Acme\Related\Plugin::instance()->engine->get_related_ids( $post->ID, $count );
}

/**
 * Related items (the data behind the list) for a post.
 *
 * @param int|WP_Post|null $post  Post (defaults to the current post).
 * @param int              $count Number of posts (0 = use the setting).
 * @return array[]
 */
function acme_related_get_items( $post = null, $count = 0 ) {
	$items = array();
	foreach ( acme_related_get_ids( $post, $count ) as $id ) {
		$item = Acme\Related\Item::from_post( $id );
		if ( $item ) {
			$items[] = $item;
		}
	}
	return $items;
}

/**
 * Print the related posts list for a post (for themes that want it somewhere else).
 *
 * @param int|WP_Post|null $post Post (defaults to the current post).
 * @param array            $args Optional. `count`, `heading`.
 */
function acme_related_the_list( $post = null, $args = array() ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return;
	}
	$plugin = Acme\Related\Plugin::instance();
	$count  = isset( $args['count'] ) ? (int) $args['count'] : 0;
	echo $plugin->renderer->render_list( $post->ID, acme_related_get_items( $post, $count ), $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the renderer.
}

/**
 * Reading time in minutes for a post (stored at save time since 2.0, estimated for older posts).
 *
 * @param int|WP_Post $post Post.
 * @return int
 */
function acme_related_reading_time( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return 0;
	}
	$stored = get_post_meta( $post->ID, '_acme_reading_time', true );
	if ( '' !== $stored && is_numeric( $stored ) ) {
		return max( 1, (int) $stored );
	}
	return acme_related_estimate_reading_time( $post->post_content );
}

/**
 * Estimate reading time from content (220 words per minute).
 *
 * @param string $content Post content.
 * @return int Minutes, at least 1.
 */
function acme_related_estimate_reading_time( $content ) {
	$words = str_word_count( wp_strip_all_tags( strip_shortcodes( (string) $content ) ) );
	return max( 1, (int) ceil( $words / 220 ) );
}
