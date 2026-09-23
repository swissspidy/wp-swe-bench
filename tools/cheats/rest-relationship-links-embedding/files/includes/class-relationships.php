<?php
/**
 * The book <-> author relation.
 *
 * Stored in `{prefix}acme_library_book_authors` (one row per book/author pair, `position`
 * = order of the author on the book, 0-based). Books that were never re-saved since 1.x
 * may still only have the legacy `_acme_author_ids` meta ("12,15"); every reader goes
 * through this class, which falls back to it.
 *
 * Lookups are cached per request; prime_books() / prime_authors() load the relations of
 * many posts with one query (REST collections, embeds).
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
	 * Book ID => ordered author IDs.
	 *
	 * @var array<int, int[]>
	 */
	private static $book_authors = array();

	/**
	 * Author ID => book IDs.
	 *
	 * @var array<int, int[]>
	 */
	private static $author_books = array();

	/**
	 * Legacy books (book ID => author IDs), loaded once per request.
	 *
	 * @var array<int, int[]>|null
	 */
	private static $legacy = null;

	/**
	 * Table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'acme_library_book_authors';
	}

	/**
	 * Forgets everything cached (after writes).
	 */
	public static function flush_cache(): void {
		self::$book_authors = array();
		self::$author_books = array();
		self::$legacy       = null;
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
	 * Loads the authors of many books at once.
	 *
	 * @param int[] $book_ids Book IDs.
	 */
	public static function prime_books( array $book_ids ): void {
		global $wpdb;
		$book_ids = array_values( array_diff( array_unique( array_map( 'intval', $book_ids ) ), array_keys( self::$book_authors ), array( 0 ) ) );
		if ( ! $book_ids ) {
			return;
		}
		$placeholders = implode( ',', array_fill( 0, count( $book_ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT book_id, author_id FROM %i WHERE book_id IN ($placeholders) ORDER BY book_id ASC, position ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( self::table() ), $book_ids )
			)
		);
		$found = array();
		foreach ( (array) $rows as $row ) {
			$found[ (int) $row->book_id ][] = (int) $row->author_id;
		}
		$legacy = self::legacy_books();
		foreach ( $book_ids as $book_id ) {
			if ( isset( $found[ $book_id ] ) ) {
				self::$book_authors[ $book_id ] = $found[ $book_id ];
			} else {
				self::$book_authors[ $book_id ] = $legacy[ $book_id ] ?? array();
			}
		}
	}

	/**
	 * Loads the books of many authors at once.
	 *
	 * @param int[] $author_ids Author IDs.
	 */
	public static function prime_authors( array $author_ids ): void {
		global $wpdb;
		$author_ids = array_values( array_diff( array_unique( array_map( 'intval', $author_ids ) ), array_keys( self::$author_books ), array( 0 ) ) );
		if ( ! $author_ids ) {
			return;
		}
		$placeholders = implode( ',', array_fill( 0, count( $author_ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT book_id, author_id FROM %i WHERE author_id IN ($placeholders) ORDER BY id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( self::table() ), $author_ids )
			)
		);
		$books = array_fill_keys( $author_ids, array() );
		foreach ( (array) $rows as $row ) {
			$books[ (int) $row->author_id ][] = (int) $row->book_id;
		}
		foreach ( self::legacy_books() as $book_id => $ids ) {
			foreach ( $ids as $author_id ) {
				if ( isset( $books[ $author_id ] ) && ! in_array( $book_id, $books[ $author_id ], true ) ) {
					$books[ $author_id ][] = $book_id;
				}
			}
		}
		foreach ( $books as $author_id => $ids ) {
			self::$author_books[ $author_id ] = array_values( array_unique( $ids ) );
		}
	}

	/**
	 * Author post IDs of a book, in order. Includes authors of any status: callers
	 * decide what they show.
	 *
	 * @param int $book_id Book ID.
	 * @return int[]
	 */
	public static function get_author_ids( int $book_id ): array {
		if ( ! isset( self::$book_authors[ $book_id ] ) ) {
			self::prime_books( array( $book_id ) );
		}
		return self::$book_authors[ $book_id ] ?? array();
	}

	/**
	 * Book post IDs of an author (any status).
	 *
	 * @param int $author_id Author ID.
	 * @return int[]
	 */
	public static function get_book_ids( int $author_id ): array {
		if ( ! isset( self::$author_books[ $author_id ] ) ) {
			self::prime_authors( array( $author_id ) );
		}
		return self::$author_books[ $author_id ] ?? array();
	}

	/**
	 * Books related to any of the given authors (any status).
	 *
	 * @param int[] $author_ids Author IDs.
	 * @return int[]
	 */
	public static function get_book_ids_for_authors( array $author_ids ): array {
		self::prime_authors( $author_ids );
		$ids = array();
		foreach ( $author_ids as $author_id ) {
			$ids = array_merge( $ids, self::get_book_ids( (int) $author_id ) );
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Books that only have the legacy meta: book ID => author IDs.
	 *
	 * @return array<int, int[]>
	 */
	public static function legacy_books(): array {
		global $wpdb;
		if ( null !== self::$legacy ) {
			return self::$legacy;
		}
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
			$ids = self::parse_legacy( maybe_unserialize( $row->meta_value ) );
			if ( $ids ) {
				$books[ (int) $row->post_id ] = $ids;
			}
		}
		self::$legacy = $books;
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
		self::flush_cache();

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
