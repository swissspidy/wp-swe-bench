<?php
/**
 * Writing relations through the core endpoints (real HTTP requests, committed data).
 */

class WritesTest extends AcmeLibraryCase {

	protected bool $use_transactions = false;

	/** @var array<int, array> */
	private array $logins = array();

	private function as_user( ?int $user ): array {
		if ( null === $user ) {
			return array();
		}
		if ( ! isset( $this->logins[ $user ] ) ) {
			$this->logins[ $user ] = $this->http_login( $user );
		}
		return array(
			'login'      => $this->logins[ $user ],
			'rest_nonce' => true,
		);
	}

	private function api( ?int $user, string $method, string $path, ?array $body = null ): array {
		$opts = $this->as_user( $user );
		if ( null !== $body ) {
			$opts['body'] = $body;
			$opts['json'] = true;
		}
		return $this->http( $method, '/wp-json' . $path, $opts );
	}

	private function count_meta( int $author_id ): int {
		wp_cache_flush();
		return (int) get_post_meta( $author_id, '_acme_book_count', true );
	}

	private function new_book( string $title, array $authors = array() ): int {
		$res = $this->api( $this->user( 'eddie' ), 'POST', '/wp/v2/books', array( 'title' => $title, 'status' => 'publish', 'authors' => $authors ) );
		$this->assertSame( 201, $res['status'], $res['body'] );
		$this->assertSame( $authors, $res['json']['authors'] ?? null );
		return (int) $res['json']['id'];
	}

	public function test_editor_replaces_the_authors_of_a_book(): void {
		$eddie = $this->user( 'eddie' );
		$book  = $this->new_book( 'Relations 101', array( $this->author( 'ada-lovelace' ) ) );
		$ada   = $this->author( 'ada-lovelace' );
		$grace = $this->author( 'grace-hopper' );
		$linus = $this->author( 'linus-torvalds' );
		$ada_count   = $this->count_meta( $ada );
		$grace_count = $this->count_meta( $grace );
		$linus_count = $this->count_meta( $linus );

		$res = $this->api( $eddie, 'POST', "/wp/v2/books/$book", array( 'authors' => array( $linus, $grace, $linus ) ) );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertSame( array( $linus, $grace ), $res['json']['authors'] );
		$this->assertSame( array( $linus, $grace ), $this->stored_authors( $book ) );

		$get = $this->api( null, 'GET', "/wp/v2/books/$book?_embed=acme:author" );
		$this->assertSame( array( $linus, $grace ), $get['json']['authors'] );
		$this->assertSame( array( $linus, $grace ), array_column( $get['json']['_embedded']['acme:author'] ?? array(), 'id' ) );
		$this->assertArrayNotHasKey( 'author', $get['json']['_embedded'], '_embed=acme:author embeds only that relation' );

		// Counters maintained through the existing action.
		$this->assertSame( $ada_count - 1, $this->count_meta( $ada ) );
		$this->assertSame( $grace_count + 1, $this->count_meta( $grace ) );
		$this->assertSame( $linus_count + 1, $this->count_meta( $linus ) );

		// Old route agrees.
		$old = $this->api( null, 'GET', "/acme-library/v1/books/$book/authors" );
		$this->assertSame( array( $linus, $grace ), array_column( $old['json'], 'id' ) );
		$this->assertSame( array( 0, 1 ), array_column( $old['json'], 'position' ) );

		// Other fields in the same request are saved too; [] clears.
		$res = $this->api( $eddie, 'PUT', "/wp/v2/books/$book", array( 'title' => 'Relations 102', 'authors' => array() ) );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertSame( array(), $res['json']['authors'] );
		$this->assertSame( 'Relations 102', $res['json']['title']['raw'] ?? $res['json']['title']['rendered'] );
		$this->assertSame( array(), $this->stored_authors( $book ) );
		$this->assertSame( $grace_count, $this->count_meta( $grace ) );

		// A request that doesn't send `authors` leaves them alone.
		$this->api( $eddie, 'POST', "/wp/v2/books/$book", array( 'authors' => array( $ada ) ) );
		$res = $this->api( $eddie, 'POST', "/wp/v2/books/$book", array( 'excerpt' => 'Short.' ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertSame( array( $ada ), $this->stored_authors( $book ) );
	}

	public function test_invalid_author_lists_reject_the_whole_request(): void {
		$eddie   = $this->user( 'eddie' );
		$ada     = $this->author( 'ada-lovelace' );
		$book    = $this->new_book( 'Validation Tales', array( $ada ) );
		$trashed = (int) wp_insert_post( array( 'post_type' => 'author', 'post_title' => 'Trashed Author', 'post_status' => 'publish' ) );
		wp_trash_post( $trashed );

		$invalid = array(
			'a book ID'        => array( $ada, $this->book( 'the-hidden-hand' ) ),
			'unknown ID'       => array( 999999 ),
			'trashed author'   => array( $trashed ),
			'a page'           => array( (int) wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Not an author', 'post_status' => 'publish' ) ) ),
			'not integers'     => array( 'ada' ),
			'not a list'       => 'ada',
		);
		foreach ( $invalid as $label => $authors ) {
			$res = $this->api( $eddie, 'POST', "/wp/v2/books/$book", array( 'title' => 'Changed', 'authors' => $authors ) );
			$this->assertSame( 400, $res['status'], "$label: " . $res['body'] );
			$this->assertSame( 'rest_invalid_param', $res['json']['code'] ?? null, $label );
			wp_cache_flush();
			$this->assertSame( 'Validation Tales', get_post( $book )->post_title, "$label: nothing may be saved" );
			$this->assertSame( array( $ada ), $this->stored_authors( $book ), $label );
		}

		// Creating with an invalid list creates nothing.
		$res = $this->api( $eddie, 'POST', '/wp/v2/books', array( 'title' => 'Never Created', 'status' => 'publish', 'authors' => array( 999999 ) ) );
		$this->assertSame( 400, $res['status'] );
		global $wpdb;
		$this->assertSame( '0', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title = 'Never Created'" ) );
	}

	public function test_only_authors_the_user_can_see_can_be_referenced(): void {
		$wanda  = $this->user( 'wanda' );
		$book   = $this->book( 'wandas-first-book' );
		$mine   = $this->author( 'wanda-writes' );
		$secret = $this->author( 'secret-pseudonym' );
		$ghost  = $this->author( 'private-ghostwriter' );

		foreach ( array( $secret, $ghost ) as $hidden ) {
			$res = $this->api( $wanda, 'POST', "/wp/v2/books/$book", array( 'authors' => array( $mine, $hidden ) ) );
			$this->assertSame( 400, $res['status'], $res['body'] );
			$this->assertSame( array( $mine ), $this->stored_authors( $book ) );
			$old = $this->api( $wanda, 'POST', "/acme-library/v1/books/$book/authors", array( 'authors' => array( $hidden ) ) );
			$this->assertSame( 400, $old['status'], $old['body'] );
			$this->assertSame( 'acme_library_invalid_author', $old['json']['code'] ?? null );
			$this->assertSame( array( $mine ), $this->stored_authors( $book ) );
		}

		$res = $this->api( $wanda, 'POST', "/wp/v2/books/$book", array( 'authors' => array( $mine, $this->author( 'ada-lovelace' ) ) ) );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertSame( array( $mine, $this->author( 'ada-lovelace' ) ), $this->stored_authors( $book ) );

		// Editors can attach embargoed authors.
		$eddie = $this->user( 'eddie' );
		$res   = $this->api( $eddie, 'POST', "/wp/v2/books/$book", array( 'authors' => array( $secret, $mine ) ) );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertSame( array( $secret, $mine ), $res['json']['authors'] );
		$pub = $this->api( null, 'GET', "/wp/v2/books/$book?_embed" );
		$this->assertSame( array( $mine ), $pub['json']['authors'] );
		$this->assertSame( array( $mine ), array_column( $pub['json']['_embedded']['acme:author'], 'id' ) );
		$old = $this->api( $eddie, 'POST', "/acme-library/v1/books/$book/authors", array( 'authors' => array( $mine, $ghost ) ) );
		$this->assertSame( 200, $old['status'], $old['body'] );
		$this->assertSame( array( $mine, $ghost ), $this->stored_authors( $book ) );

		// Not allowed to edit the book at all.
		$this->assertSame( 401, $this->api( null, 'POST', "/wp/v2/books/$book", array( 'authors' => array() ) )['status'] );
		$this->assertSame( 403, $this->api( $this->user( 'cora' ), 'POST', "/wp/v2/books/$book", array( 'authors' => array() ) )['status'] );
		$this->assertSame( 401, $this->api( null, 'POST', "/acme-library/v1/books/$book/authors", array( 'authors' => array() ) )['status'] );
		$this->assertSame( array( $mine, $ghost ), $this->stored_authors( $book ) );
	}

	public function test_author_books_field_is_writable(): void {
		$eddie = $this->user( 'eddie' );
		$ada   = $this->author( 'ada-lovelace' );
		$alan  = $this->author( 'alan-turing' );
		$grace = $this->author( 'grace-hopper' );
		$b1    = $this->new_book( 'Two Authors', array( $ada, $alan ) );
		$b2    = $this->new_book( 'Solo', array( $alan ) );
		$b3    = $this->new_book( 'Three Authors', array( $grace, $ada, $alan ) );
		$grace_before = $this->stored_books( $grace );
		$count_before = $this->count_meta( $grace );

		$new = array_merge( array_diff( $grace_before, array( $b3 ) ), array( $b1, $b2 ) );
		$res = $this->api( $eddie, 'POST', "/wp/v2/authors/$grace", array( 'books' => $new ) );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertSame( self::sorted( $new ), self::sorted( $res['json']['books'] ) );

		$this->assertSame( array( $ada, $alan, $grace ), $this->stored_authors( $b1 ), 'appended at the end' );
		$this->assertSame( array( $alan, $grace ), $this->stored_authors( $b2 ) );
		$this->assertSame( array( $ada, $alan ), $this->stored_authors( $b3 ), 'removed, others keep their order' );
		$this->assertSame( $count_before + 1, $this->count_meta( $grace ) );
		foreach ( array_diff( $grace_before, array( $b3 ) ) as $untouched ) {
			$this->assertContains( $grace, $this->stored_authors( $untouched ) );
		}

		// Readable back through the book endpoint.
		$this->assertSame( array( $ada, $alan, $grace ), $this->api( null, 'GET', "/wp/v2/books/$b1" )['json']['authors'] );

		// Invalid: not a book / someone else's book for an author-role user.
		$res = $this->api( $eddie, 'POST', "/wp/v2/authors/$grace", array( 'title' => 'Grace H.', 'books' => array( $ada ) ) );
		$this->assertSame( 400, $res['status'] );
		wp_cache_flush();
		$this->assertSame( 'Grace Hopper', get_post( $grace )->post_title );

		$wanda = $this->user( 'wanda' );
		$mine  = $this->author( 'wanda-writes' );
		$res   = $this->api( $wanda, 'POST', "/wp/v2/authors/$mine", array( 'books' => array( $this->book( 'wandas-first-book' ), $b2 ) ) );
		$this->assertSame( 400, $res['status'], 'wanda cannot edit Solo' );
		$this->assertNotContains( $mine, $this->stored_authors( $b2 ) );

		// Creating an author with books.
		$res = $this->api( $eddie, 'POST', '/wp/v2/authors', array( 'title' => 'New Voice', 'status' => 'publish', 'books' => array( $b2 ) ) );
		$this->assertSame( 201, $res['status'], $res['body'] );
		$this->assertSame( array( $alan, $grace, (int) $res['json']['id'] ), $this->stored_authors( $b2 ) );
		$this->assertSame( 1, $this->count_meta( (int) $res['json']['id'] ) );
	}
}
