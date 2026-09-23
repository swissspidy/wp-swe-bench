<?php
/**
 * Helpers for the Acme Library tests.
 *
 * In-process tests are read-only against the seeded catalogue (so no implementation
 * cache can go stale through rolled-back writes); writes go through the HTTP server.
 */

abstract class AcmeLibraryCase extends WPSB\TestCase {

	const REL_AUTHOR = 'acme:author';
	const REL_BOOK   = 'acme:book';

	/** Seeded post ID by type + slug (any status). */
	protected function seeded( string $type, string $slug ): int {
		global $wpdb;
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s AND post_status <> 'trash' ORDER BY ID ASC LIMIT 1", $type, $slug ) );
		if ( ! $id ) {
			// Drafts have no post_name until published: fall back to the title.
			$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s ORDER BY ID ASC LIMIT 1", $type, ucwords( str_replace( '-', ' ', $slug ) ) ) );
		}
		$this->assertGreaterThan( 0, $id, "seeded $type $slug missing" );
		return $id;
	}

	protected function author( string $slug ): int {
		return $this->seeded( 'author', $slug );
	}

	protected function book( string $slug ): int {
		return $this->seeded( 'book', $slug );
	}

	protected function user( string $login ): int {
		$user = get_user_by( 'login', $login );
		$this->assertNotFalse( $user, "seeded user $login missing" );
		return $user->ID;
	}

	protected function status_of( int $id ): string {
		global $wpdb;
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d", $id ) );
	}

	/**
	 * Ground truth straight from storage: the relation table, or the 1.x meta for books
	 * that have no rows.
	 *
	 * @return int[] ordered author IDs of a book (any status)
	 */
	protected function stored_authors( int $book_id ): array {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT author_id FROM {$wpdb->prefix}acme_library_book_authors WHERE book_id = %d ORDER BY position ASC, id ASC", $book_id ) );
		if ( $ids ) {
			return array_map( 'intval', $ids );
		}
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_acme_author_ids'", $book_id ) );
		return array_values( array_filter( array_map( 'intval', array_map( 'trim', explode( ',', (string) $raw ) ) ) ) );
	}

	/** @return int[] book IDs of an author (any status), sorted. */
	protected function stored_books( int $author_id ): array {
		global $wpdb;
		$books = array();
		foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'book' AND post_status <> 'trash'" ) as $book_id ) {
			if ( in_array( $author_id, $this->stored_authors( (int) $book_id ), true ) ) {
				$books[] = (int) $book_id;
			}
		}
		sort( $books );
		return $books;
	}

	protected function published( array $ids ): array {
		return array_values( array_filter( $ids, fn( $id ) => 'publish' === $this->status_of( $id ) ) );
	}

	protected static function sorted( array $ids ): array {
		$ids = array_map( 'intval', $ids );
		sort( $ids );
		return $ids;
	}

	/** Link hrefs of a relation in response data. */
	protected static function link_ids( array $data, string $rel ): array {
		$ids = array();
		foreach ( $data['_links'][ $rel ] ?? array() as $link ) {
			$ids[] = (int) preg_replace( '#^.*/(\d+)/?$#', '$1', $link['href'] );
		}
		return $ids;
	}

	protected static function embedded( array $data, string $rel ): array {
		return $data['_embedded'][ $rel ] ?? array();
	}

	/** GET in-process as $user (0 = anonymous), with embeds. */
	protected function get( string $route, array $query = array(), int $user = 0, bool $embed = true ): array {
		$prev = get_current_user_id();
		wp_set_current_user( $user );
		$response = $this->rest( 'GET', $route, $query );
		$data     = $this->rest_data( $response, $embed );
		wp_set_current_user( $prev );
		return array( $response, $data );
	}

	protected function assertLinksAndEmbeds( array $data, string $rel, array $expected_ids, string $type, string $message ): void {
		$this->assertSame( $expected_ids, self::link_ids( $data, $rel ), "$message: $rel links" );
		foreach ( $data['_links'][ $rel ] ?? array() as $link ) {
			$this->assertTrue( ! empty( $link['embeddable'] ), "$message: $rel link must be embeddable" );
			$this->assertStringContainsString( "/wp-json/wp/v2/{$type}s/", $link['href'], $message );
		}
		$embedded = self::embedded( $data, $rel );
		$this->assertSame( $expected_ids, array_map( static fn( $e ) => (int) ( $e['id'] ?? 0 ), $embedded ), "$message: _embedded $rel" );
		foreach ( $embedded as $item ) {
			$this->assertArrayNotHasKey( 'code', $item, "$message: error object embedded" );
			$this->assertSame( $type, $item['type'] ?? null, $message );
			$this->assertArrayHasKey( 'rendered', $item['title'] ?? array(), $message );
		}
	}
}
