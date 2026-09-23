<?php
/**
 * The data shown for one related post.
 *
 * @package Acme\Related
 */

namespace Acme\Related;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the data array for a related post. The same array feeds the HTML list,
 * the block and the `acme_related` REST field.
 */
class Item {

	/** Meta key: primary category chosen by the editor (term ID). */
	const META_PRIMARY_CATEGORY = '_acme_primary_category';

	/**
	 * Data for one related post.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null Null if the post can't be shown.
	 */
	public static function from_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return null;
		}

		$data = array(
			'id'           => (int) $post->ID,
			'title'        => get_the_title( $post ),
			'link'         => get_permalink( $post ),
			'date'         => get_post_time( 'c', true, $post ),
			'date_display' => get_the_date( '', $post ),
			'author'       => self::author( $post ),
			'category'     => self::primary_category( $post ),
			'image'        => self::image( $post ),
			'views'        => Plugin::instance()->views->get( $post->ID ),
			'reading_time' => acme_related_reading_time( $post ),
		);

		/**
		 * Filters the data of a related post.
		 *
		 * @param array    $data Item data.
		 * @param \WP_Post $post The related post.
		 */
		return apply_filters( 'acme_related_item_data', $data, $post );
	}

	/**
	 * Author data.
	 *
	 * @param \WP_Post $post Post.
	 * @return array|null
	 */
	private static function author( \WP_Post $post ) {
		$user = get_userdata( (int) $post->post_author );
		if ( ! $user ) {
			return null;
		}
		return array(
			'id'   => (int) $user->ID,
			'name' => $user->display_name,
			'link' => get_author_posts_url( $user->ID, $user->user_nicename ),
		);
	}

	/**
	 * Primary category: the one chosen by the editor if it is still assigned, else the first by name.
	 *
	 * @param \WP_Post $post Post.
	 * @return array|null
	 */
	private static function primary_category( \WP_Post $post ) {
		$categories = get_the_category( $post->ID );
		if ( ! $categories ) {
			return null;
		}
		$primary = null;
		$chosen  = (int) get_post_meta( $post->ID, self::META_PRIMARY_CATEGORY, true );
		foreach ( $categories as $category ) {
			if ( $chosen && (int) $category->term_id === $chosen ) {
				$primary = $category;
				break;
			}
		}
		if ( ! $primary ) {
			$primary = reset( $categories );
		}
		return array(
			'id'   => (int) $primary->term_id,
			'name' => $primary->name,
			'slug' => $primary->slug,
			'link' => get_category_link( $primary ),
		);
	}

	/**
	 * Featured image (thumbnail size).
	 *
	 * @param \WP_Post $post Post.
	 * @return array|null
	 */
	private static function image( \WP_Post $post ) {
		$thumbnail_id = (int) get_post_thumbnail_id( $post );
		if ( ! $thumbnail_id ) {
			return null;
		}
		$attachment = get_post( $thumbnail_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return null;
		}
		$src = wp_get_attachment_image_src( $thumbnail_id, 'thumbnail' );
		if ( ! $src ) {
			return null;
		}
		$alt = trim( wp_strip_all_tags( (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) ) );
		return array(
			'id'     => $thumbnail_id,
			'src'    => $src[0],
			'width'  => (int) $src[1],
			'height' => (int) $src[2],
			'alt'    => '' !== $alt ? $alt : $attachment->post_title,
		);
	}
}
