<?php
/**
 * The /acme-library/v1 routes, the byline and the counters (HTTP, seeded data).
 */

class LegacyRoutesTest extends AcmeLibraryCase {

	protected bool $use_transactions = false;

	private function get_json( string $path, ?int $user = null ): array {
		$opts = array();
		if ( null !== $user ) {
			$opts = array(
				'login'      => $this->http_login( $user ),
				'rest_nonce' => true,
			);
		}
		return $this->http( 'GET', '/wp-json' . $path, $opts );
	}

	public function test_book_authors_route_shape(): void {
		$book = $this->book( 'the-silent-compiler' );
		$res  = $this->get_json( "/acme-library/v1/books/$book/authors" );
		$this->assertSame( 200, $res['status'] );
		$this->assertSame( array( 'Ada Lovelace', 'Barbara Liskov', 'Edsger Dijkstra' ), array_column( $res['json'], 'name' ) );
		$this->assertSame( array( 0, 1, 2 ), array_column( $res['json'], 'position' ) );
		$this->assertSame( array( 'id', 'name', 'slug', 'link', 'position' ), array_keys( $res['json'][0] ) );
		$this->assertSame( 'ada-lovelace', $res['json'][0]['slug'] );
		$this->assertStringEndsWith( '/writers/ada-lovelace/', $res['json'][0]['link'] );

		$this->assertSame( 404, $this->get_json( '/acme-library/v1/books/999999/authors' )['status'] );
		$this->assertSame( 404, $this->get_json( '/acme-library/v1/books/' . $this->author( 'ada-lovelace' ) . '/authors' )['status'] );

		$old = $this->get_json( '/acme-library/v1/books/' . $this->book( 'the-old-catalogue' ) . '/authors' );
		$this->assertSame( array( 'Grace Hopper', 'Ada Lovelace' ), array_column( $old['json'], 'name' ) );
	}

	public function test_author_books_route_shape(): void {
		$ada = $this->author( 'ada-lovelace' );
		$res = $this->get_json( "/acme-library/v1/authors/$ada/books" );
		$this->assertSame( 200, $res['status'] );
		$this->assertSame( array( 'id', 'title', 'link', 'year' ), array_keys( $res['json'][0] ) );
		$titles = array_column( $res['json'], 'title' );
		$this->assertContains( 'The Silent Compiler', $titles );
		$this->assertContains( 'The Old Catalogue', $titles );
		$this->assertContains( 'The Hidden Hand', $titles );
		$old = array_values( array_filter( $res['json'], static fn( $b ) => 'The Old Catalogue' === $b['title'] ) );
		$this->assertSame( 1998, $old[0]['year'] );
	}

	public function test_relation_routes_do_not_leak_unpublished_posts(): void {
		$hidden = $this->book( 'the-hidden-hand' );
		$res    = $this->get_json( "/acme-library/v1/books/$hidden/authors" );
		$this->assertSame( 200, $res['status'] );
		$this->assertSame( array( 'Ada Lovelace', 'Grace Hopper' ), array_column( $res['json'], 'name' ) );
		$this->assertSame( array( 0, 1 ), array_column( $res['json'], 'position' ) );
		$this->assertStringNotContainsString( 'Pseudonym', $res['body'] );
		$this->assertStringNotContainsString( 'Ghostwriter', $res['body'] );

		foreach ( array( 'unannounced-sequel', 'internal-style-guide', 'unsorted-notes' ) as $slug ) {
			$res = $this->get_json( '/acme-library/v1/books/' . $this->book( $slug ) . '/authors' );
			$this->assertSame( 404, $res['status'], "$slug: " . $res['body'] );
		}
		foreach ( array( 'secret-pseudonym', 'private-ghostwriter', 'pending-reviewer' ) as $slug ) {
			$res = $this->get_json( '/acme-library/v1/authors/' . $this->author( $slug ) . '/books' );
			$this->assertSame( 404, $res['status'], "$slug: " . $res['body'] );
		}

		$ada    = $this->author( 'ada-lovelace' );
		$titles = array_column( $this->get_json( "/acme-library/v1/authors/$ada/books" )['json'], 'title' );
		$this->assertNotContains( 'Unannounced Sequel', $titles );
		$grace  = $this->author( 'grace-hopper' );
		$titles = array_column( $this->get_json( "/acme-library/v1/authors/$grace/books", $this->user( 'sam' ) )['json'], 'title' );
		$this->assertNotContains( 'Internal Style Guide', $titles );

		// Editors still see everything.
		$eddie = $this->user( 'eddie' );
		$res   = $this->get_json( "/acme-library/v1/books/$hidden/authors", $eddie );
		$this->assertSame( array( 'secret-pseudonym', 'ada-lovelace', 'private-ghostwriter', 'grace-hopper' ), array_column( $res['json'], 'slug' ) );
		$this->assertSame( array( 0, 1, 2, 3 ), array_column( $res['json'], 'position' ) );
		$this->assertSame( 200, $this->get_json( '/acme-library/v1/books/' . $this->book( 'unannounced-sequel' ) . '/authors', $eddie )['status'] );
		$res = $this->get_json( '/acme-library/v1/authors/' . $this->author( 'secret-pseudonym' ) . '/books', $eddie );
		$this->assertSame( 200, $res['status'] );
		$this->assertSame( array( 'Pamphlet of 1999', 'The Hidden Hand' ), self::sorted_titles( $res['json'] ) );
		$titles = array_column( $this->get_json( "/acme-library/v1/authors/$ada/books", $eddie )['json'], 'title' );
		$this->assertContains( 'Unannounced Sequel', $titles );
	}

	private static function sorted_titles( array $items ): array {
		$titles = array_column( $items, 'title' );
		sort( $titles );
		return $titles;
	}

	public function test_byline_and_counters(): void {
		$page = $this->http( 'GET', '/books/the-hidden-hand/' );
		$this->assertSame( 200, $page['status'] );
		$this->assertMatchesRegularExpression( '#<p class="acme-book-byline">By <a class="acme-book-author" href="[^"]+/writers/ada-lovelace/">Ada Lovelace</a> and <a class="acme-book-author" href="[^"]+/writers/grace-hopper/">Grace Hopper</a></p>#', $page['body'] );
		$this->assertStringNotContainsString( 'Secret Pseudonym', $page['body'] );

		$page = $this->http( 'GET', '/books/the-old-catalogue/' );
		$this->assertStringContainsString( '>Grace Hopper</a> and <a class="acme-book-author"', $page['body'] );

		wp_cache_flush();
		$ada = $this->author( 'ada-lovelace' );
		$this->assertSame( count( $this->published( $this->stored_books( $ada ) ) ), (int) get_post_meta( $ada, '_acme_book_count', true ) );

		$res = $this->wp_cli( 'acme-library recount' );
		$this->assertSame( 0, $res['exit'], $res['stderr'] );
		wp_cache_flush();
		foreach ( array( 'ada-lovelace', 'grace-hopper', 'alan-turing', 'private-ghostwriter' ) as $slug ) {
			$id = $this->author( $slug );
			$this->assertSame( count( $this->published( $this->stored_books( $id ) ) ), (int) get_post_meta( $id, '_acme_book_count', true ), $slug );
		}
	}
}
