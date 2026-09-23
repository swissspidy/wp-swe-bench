<?php
/**
 * The book <-> author relation.
 *
 * Stored in `{prefix}acme_library_book_authors` (one row per book/author pair, `position`
 * = order of the author on the book, 0-based). Books that were never re-saved since 1.x
 * may still only have the legacy `_acme_author_ids` meta ("12,15"); every reader goes
 * through this class, which falls back to it.
 *
 * @package Acme\Library
 */

namespace Acme\Library;

defined( 'ABSPATH' ) || exit;

/**
 * Relationship repository.
 */
class Relationships {

	const LEGACY_META = '_acme_author_ids';

	/**
	 * Table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'acme_library_book_authors';
	}

	/**
	 * Parses the 1.x meta format.
	 *
	 * @param mixed $raw "12,15" (older sites also have "12, 15" or a serialized array).
	 * @return int[]
	 */
	public static function parse_legacy( $raw ): array {
		if ( is_array( $raw ) ) {
			$list = $raw;
		} else {
			$list = explode( ',', (string) $raw );
		}
		$ids = array();
		foreach ( $list as $id ) {
			$id = (int) trim( (string) $id );
			if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * Author post IDs of a book, in order. Includes authors of any status: callers
	 * decide what they show.
	 *
	 * @param int $book_id Book ID.
	 * @return int[]
	 */
	public static function get_author_ids( int $book_id ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col(
			$wpdb->prepare( 'SELECT author_id FROM %i WHERE book_id = %d ORDER BY position ASC, id ASC', self::table(), $book_id )
		);
		if ( $ids ) {
			return array_map( 'intval', $ids );
		}
		return self::parse_legacy( get_post_meta( $book_id, self::LEGACY_META, true ) );
	}

	/**
	 * Book post IDs of an author (any status), newest relation last.
	 *
	 * @param int $author_id Author ID.
	 * @return int[]
	 */
	public static function get_book_ids( int $author_id ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare( 'SELECT book_id FROM %i WHERE author_id = %d ORDER BY id ASC', self::table(), $author_id )
			)
		);

		// Books still in the 1.x format.
		foreach ( self::legacy_books() as $book_id => $author_ids ) {
			if ( in_array( $author_id, $author_ids, true ) && ! in_array( $book_id, $ids, true ) ) {
				$ids[] = $book_id;
			}
		}
		return $ids;
	}

	/**
	 * Books that only have the legacy meta: book ID => author IDs.
	 *
	 * @return array<int, int[]>
	 */
	public static function legacy_books(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT pm.post_id, pm.meta_value FROM %i pm WHERE pm.meta_key = %s AND pm.post_id NOT IN ( SELECT DISTINCT book_id FROM %i )',
				$wpdb->postmeta,
				self::LEGACY_META,
				self::table()
			)
		);
		$books = array();
		foreach ( (array) $rows as $row ) {
			$books[ (int) $row->post_id ] = self::parse_legacy( maybe_unserialize( $row->meta_value ) );
		}
		return $books;
	}

	/**
	 * Replaces the authors of a book.
	 *
	 * @param int   $book_id Book ID.
	 * @param int[] $ids     Author post IDs, in order.
	 */
	public static function set_author_ids( int $book_id, array $ids ): void {
		global $wpdb;
		$old = self::get_author_ids( $book_id );
		$new = self::parse_legacy( $ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( self::table(), array( 'book_id' => $book_id ), array( '%d' ) );
		foreach ( $new as $position => $author_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				self::table(),
				array(
					'book_id'   => $book_id,
					'author_id' => $author_id,
					'position'  => $position,
				),
				array( '%d', '%d', '%d' )
			);
		}
		delete_post_meta( $book_id, self::LEGACY_META );

		/**
		 * Fires after the authors of a book changed.
		 *
		 * @param int   $book_id Book ID.
		 * @param int[] $new     New author IDs (ordered).
		 * @param int[] $old     Previous author IDs (ordered).
		 */
		do_action( 'acme_library_book_authors_updated', $book_id, $new, $old );
	}

	/**
	 * Removes every relation of a post (book or author) that is being deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function delete_for_post( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		if ( Post_Types::BOOK === $post->post_type ) {
			self::set_author_ids( $post_id, array() );
		} elseif ( Post_Types::AUTHOR === $post->post_type ) {
			foreach ( self::get_book_ids( $post_id ) as $book_id ) {
				$ids = array_values( array_diff( self::get_author_ids( $book_id ), array( $post_id ) ) );
				self::set_author_ids( $book_id, $ids );
			}
		}
	}
}
