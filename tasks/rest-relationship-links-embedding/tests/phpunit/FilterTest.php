<?php
/**
 * `book_author` filter on /wp/v2/books (read-only, in-process).
 */

class FilterTest extends AcmeLibraryCase {

	private function ids( array $data ): array {
		return self::sorted( array_column( $data, 'id' ) );
	}

	private function header( WP_REST_Response $response, string $name ): ?string {
		$headers = array_change_key_case( $response->get_headers(), CASE_LOWER );
		return isset( $headers[ strtolower( $name ) ] ) ? (string) $headers[ strtolower( $name ) ] : null;
	}

	public function test_filter_by_one_author(): void {
		$ada      = $this->author( 'ada-lovelace' );
		$expected = $this->published( $this->stored_books( $ada ) );
		$this->assertGreaterThanOrEqual( 5, count( $expected ) );

		list( $response, $data ) = $this->get( '/wp/v2/books', array( 'book_author' => $ada, 'per_page' => 100 ), 0, false );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $expected, $this->ids( $data ) );
		$this->assertSame( (string) count( $expected ), $this->header( $response, 'X-WP-Total' ) );
		$this->assertContains( $this->book( 'the-old-catalogue' ), $this->ids( $data ), '1.x-format book' );
		$this->assertNotContains( $this->book( 'unannounced-sequel' ), $this->ids( $data ) );

		// Editors can combine it with status.
		list( $response, $data ) = $this->get( '/wp/v2/books', array( 'book_author' => $ada, 'status' => 'draft' ), $this->user( 'eddie' ), false );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( $this->book( 'unannounced-sequel' ) ), $this->ids( $data ) );
	}

	public function test_filter_by_several_authors_and_formats(): void {
		$ada      = $this->author( 'ada-lovelace' );
		$alan     = $this->author( 'alan-turing' );
		$expected = self::sorted( array_unique( array_merge( $this->published( $this->stored_books( $ada ) ), $this->published( $this->stored_books( $alan ) ) ) ) );

		list( , $data ) = $this->get( '/wp/v2/books', array( 'book_author' => "$ada,$alan", 'per_page' => 100 ), 0, false );
		$this->assertSame( $expected, $this->ids( $data ), 'comma separated' );
		list( , $data ) = $this->get( '/wp/v2/books', array( 'book_author' => array( $alan, $ada ), 'per_page' => 100 ), 0, false );
		$this->assertSame( $expected, $this->ids( $data ), 'array' );
		$this->assertContains( $this->book( 'pamphlet-of-1999' ), $this->ids( $data ) );

		// Pagination.
		list( $response, $page2 ) = $this->get( '/wp/v2/books', array( 'book_author' => "$ada,$alan", 'per_page' => 3, 'page' => 2, 'orderby' => 'id', 'order' => 'asc' ), 0, false );
		$this->assertSame( array_slice( $expected, 3, 3 ), array_column( $page2, 'id' ) );
		$this->assertSame( (string) count( $expected ), $this->header( $response, 'X-WP-Total' ) );
		$this->assertSame( (string) (int) ceil( count( $expected ) / 3 ), $this->header( $response, 'X-WP-TotalPages' ) );

		// Search + include still apply.
		list( , $data ) = $this->get( '/wp/v2/books', array( 'book_author' => $ada, 'search' => 'Catalogue' ), 0, false );
		$this->assertSame( array( $this->book( 'the-old-catalogue' ) ), $this->ids( $data ) );
		list( , $data ) = $this->get( '/wp/v2/books', array( 'book_author' => $ada, 'include' => array( $this->book( 'the-hidden-hand' ), $this->book( 'memoirs-of-a-ghost' ) ) ), 0, false );
		$this->assertSame( array( $this->book( 'the-hidden-hand' ) ), $this->ids( $data ) );
	}

	public function test_hidden_unknown_and_invalid_authors(): void {
		$secret = $this->author( 'secret-pseudonym' );
		$ghost  = $this->author( 'private-ghostwriter' );
		foreach ( array( 0, $this->user( 'sam' ) ) as $user ) {
			foreach ( array( $secret, $ghost, 999999, $this->book( 'the-hidden-hand' ) ) as $id ) {
				list( $response, $data ) = $this->get( '/wp/v2/books', array( 'book_author' => $id ), $user, false );
				$this->assertSame( 200, $response->get_status() );
				$this->assertSame( array(), $data, "user $user, book_author=$id" );
				$this->assertSame( '0', $this->header( $response, 'X-WP-Total' ) );
			}
			// Mixed: only the visible author counts.
			list( , $data ) = $this->get( '/wp/v2/books', array( 'book_author' => array( $secret, $this->author( 'grace-hopper' ) ), 'per_page' => 100 ), $user, false );
			$this->assertSame( $this->published( $this->stored_books( $this->author( 'grace-hopper' ) ) ), $this->ids( $data ) );
		}

		list( , $data ) = $this->get( '/wp/v2/books', array( 'book_author' => $secret ), $this->user( 'eddie' ), false );
		$this->assertSame( self::sorted( array( $this->book( 'the-hidden-hand' ), $this->book( 'pamphlet-of-1999' ) ) ), $this->ids( $data ) );

		list( $response ) = $this->get( '/wp/v2/books', array( 'book_author' => 'ada' ), 0, false );
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] ?? null );
	}

	public function test_core_author_parameter_keeps_filtering_by_user(): void {
		$wanda = $this->user( 'wanda' );
		list( , $data ) = $this->get( '/wp/v2/books', array( 'author' => $wanda ), 0, false );
		$this->assertSame( array( $this->book( 'wandas-first-book' ) ), $this->ids( $data ) );

		list( , $data ) = $this->get( '/wp/v2/books', array( 'author' => 1, 'book_author' => $this->author( 'wanda-writes' ) ), 0, false );
		$this->assertSame( array(), $data );
		list( , $data ) = $this->get( '/wp/v2/books', array( 'author' => $wanda, 'book_author' => $this->author( 'wanda-writes' ) ), 0, false );
		$this->assertSame( array( $this->book( 'wandas-first-book' ) ), $this->ids( $data ) );

		// Author post IDs are not user IDs.
		list( , $data ) = $this->get( '/wp/v2/books', array( 'author' => $this->author( 'ada-lovelace' ) ), 0, false );
		$this->assertSame( array(), $data );
	}

	public function test_parameter_is_discoverable(): void {
		$options = $this->rest( 'OPTIONS', '/wp/v2/books' )->get_data();
		$args    = null;
		foreach ( $options['endpoints'] as $endpoint ) {
			if ( in_array( 'GET', $endpoint['methods'], true ) ) {
				$args = $endpoint['args'];
			}
		}
		$this->assertIsArray( $args );
		$this->assertArrayHasKey( 'book_author', $args );
		$this->assertSame( 'array', $args['book_author']['type'] ?? null );
		$this->assertSame( 'integer', $args['book_author']['items']['type'] ?? null );
		$this->assertSame( 'integer', $args['author']['items']['type'] ?? null, 'core author param unchanged' );
	}
}
