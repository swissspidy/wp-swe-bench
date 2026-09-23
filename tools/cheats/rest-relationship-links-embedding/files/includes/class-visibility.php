<?php
/**
 * Who may see which related post.
 *
 * A related book/author is only exposed (IDs, links, embeds, filters, the relation
 * routes) to users who could read that post through its own REST endpoint.
 *
 * @package Acme\Library
 */

namespace Acme\Library;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Visibility rules.
 */
class Visibility {

	/**
	 * Whether the current user may see the post (of the given type).
	 *
	 * @param int|WP_Post|null $post Post or ID.
	 * @param string           $type Expected post type.
	 */
	public static function can_see( $post, string $type ): bool {
		$post = get_post( $post );
		if ( ! $post || $type !== $post->post_type || 'trash' === $post->post_status ) {
			return false;
		}
		if ( 'publish' === $post->post_status ) {
			return true;
		}
		return current_user_can( 'read_post', $post->ID );
	}

	/**
	 * Filters a list of IDs down to the visible posts, keeping the order.
	 *
	 * @param int[]  $ids  Post IDs.
	 * @param string $type Post type.
	 * @return int[]
	 */
	public static function filter( array $ids, string $type ): array {
		if ( $ids ) {
			_prime_post_caches( $ids, false, false );
		}
		return array_values( array_filter( $ids, static fn( $id ) => self::can_see( (int) $id, $type ) ) );
	}

	/**
	 * Visible authors of a book.
	 *
	 * @param int $book_id Book ID.
	 * @return int[]
	 */
	public static function book_authors( int $book_id ): array {
		return self::filter( Relationships::get_author_ids( $book_id ), Post_Types::AUTHOR );
	}

	/**
	 * Visible books of an author.
	 *
	 * @param int $author_id Author ID.
	 * @return int[]
	 */
	public static function author_books( int $author_id ): array {
		return self::filter( Relationships::get_book_ids( $author_id ), Post_Types::BOOK );
	}
}
