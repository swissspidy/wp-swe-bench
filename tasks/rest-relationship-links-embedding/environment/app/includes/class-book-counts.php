<?php
/**
 * Keeps `_acme_book_count` (number of *published* books) on author posts up to date.
 * The theme shows it on the author archive, the newsletter plugin sorts by it.
 *
 * @package Acme\Library
 */

namespace Acme\Library;

defined( 'ABSPATH' ) || exit;

/**
 * Book counters.
 */
class Book_Counts {

	const META = '_acme_book_count';

	/**
	 * Hooks.
	 */
	public static function hooks(): void {
		add_action( 'acme_library_book_authors_updated', array( __CLASS__, 'on_authors_updated' ), 10, 3 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_status_change' ), 10, 3 );
		add_action( 'before_delete_post', array( Relationships::class, 'delete_for_post' ) );
	}

	/**
	 * Relation changed.
	 *
	 * @param int   $book_id Book ID.
	 * @param int[] $new     New authors.
	 * @param int[] $old     Old authors.
	 */
	public static function on_authors_updated( $book_id, $new, $old ): void {
		foreach ( array_unique( array_merge( (array) $new, (array) $old ) ) as $author_id ) {
			self::recount( (int) $author_id );
		}
	}

	/**
	 * A book was published/unpublished.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 */
	public static function on_status_change( $new_status, $old_status, $post ): void {
		if ( Post_Types::BOOK !== $post->post_type || $new_status === $old_status ) {
			return;
		}
		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}
		foreach ( Relationships::get_author_ids( $post->ID ) as $author_id ) {
			self::recount( $author_id );
		}
	}

	/**
	 * Recomputes one counter.
	 *
	 * @param int $author_id Author ID.
	 */
	public static function recount( int $author_id ): void {
		$count = 0;
		foreach ( Relationships::get_book_ids( $author_id ) as $book_id ) {
			if ( 'publish' === get_post_status( $book_id ) ) {
				++$count;
			}
		}
		update_post_meta( $author_id, self::META, $count );
	}
}
