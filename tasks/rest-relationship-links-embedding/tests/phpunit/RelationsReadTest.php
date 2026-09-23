<?php
/**
 * `authors` / `books` fields, links and embeds on the core endpoints (read-only, in-process).
 */

class RelationsReadTest extends AcmeLibraryCase {

	public function test_book_authors_field_links_and_embeds_in_order(): void {
		$book     = $this->book( 'the-silent-compiler' );
		$expected = array( $this->author( 'ada-lovelace' ), $this->author( 'barbara-liskov' ), $this->author( 'edsger-dijkstra' ) );
		$this->assertSame( $expected, $this->stored_authors( $book ), 'fixture sanity' );

		list( $response, $data ) = $this->get( '/wp/v2/books/' . $book );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $expected, $data['authors'] ?? null, 'authors field' );
		$this->assertLinksAndEmbeds( $data, self::REL_AUTHOR, $expected, 'author', 'the-silent-compiler' );
		$this->assertSame( 'Ada Lovelace', self::embedded( $data, self::REL_AUTHOR )[0]['title']['rendered'] );
		$this->assertStringContainsString( '/writers/ada-lovelace/', self::embedded( $data, self::REL_AUTHOR )[0]['link'] );

		// Core's own `author` (WordPress user) link/embed is untouched.
		$this->assertSame( 1, (int) ( self::embedded( $data, 'author' )[0]['id'] ?? 0 ) );
		$this->assertSame( 1, $data['author'] );

		// The CURIE is announced.
		$names = array_column( $data['_links']['curies'] ?? array(), 'href', 'name' );
		$this->assertSame( 'https://api.acme-publishing.example/rels/{rel}', $names['acme'] ?? null );
	}

	public function test_author_books_field_links_and_embeds(): void {
		$ada      = $this->author( 'ada-lovelace' );
		$all      = $this->stored_books( $ada );
		$visible  = $this->published( $all );
		$sequel   = $this->book( 'unannounced-sequel' );
		$this->assertContains( $sequel, $all, 'fixture sanity' );
		$this->assertContains( $this->book( 'the-old-catalogue' ), $visible, 'fixture sanity' );

		list( $response, $data ) = $this->get( '/wp/v2/authors/' . $ada );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $visible, self::sorted( $data['books'] ?? array() ), 'books field (anonymous)' );
		$this->assertSame( $data['books'], self::link_ids( $data, self::REL_BOOK ), 'links in the order of the field' );
		$this->assertLinksAndEmbeds( $data, self::REL_BOOK, array_map( 'intval', $data['books'] ), 'book', 'ada' );

		list( , $data ) = $this->get( '/wp/v2/authors/' . $ada, array(), $this->user( 'eddie' ) );
		$this->assertSame( $all, self::sorted( $data['books'] ), 'books field (editor)' );
		$this->assertContains( $sequel, self::link_ids( $data, self::REL_BOOK ) );
		$this->assertLinksAndEmbeds( $data, self::REL_BOOK, array_map( 'intval', $data['books'] ), 'book', 'ada (editor)' );
	}

	public function test_unpublished_related_posts_do_not_leak(): void {
		$book    = $this->book( 'the-hidden-hand' );
		$secret  = $this->author( 'secret-pseudonym' );
		$ghost   = $this->author( 'private-ghostwriter' );
		$ada     = $this->author( 'ada-lovelace' );
		$grace   = $this->author( 'grace-hopper' );
		$this->assertSame( array( $secret, $ada, $ghost, $grace ), $this->stored_authors( $book ), 'fixture sanity' );

		foreach ( array( 'anonymous' => 0, 'subscriber' => $this->user( 'sam' ), 'contributor' => $this->user( 'cora' ) ) as $who => $user ) {
			list( $response, $data ) = $this->get( '/wp/v2/books/' . $book, array(), $user );
			$this->assertSame( 200, $response->get_status() );
			$this->assertSame( array( $ada, $grace ), $data['authors'], "$who: authors" );
			$this->assertLinksAndEmbeds( $data, self::REL_AUTHOR, array( $ada, $grace ), 'author', $who );
			$json = wp_json_encode( $data );
			foreach ( array( 'Secret Pseudonym', 'secret-pseudonym', 'Ghostwriter', "/authors/$secret", "/authors/$ghost" ) as $needle ) {
				$this->assertStringNotContainsString( $needle, $json, "$who sees $needle" );
			}

			list( , $memoirs ) = $this->get( '/wp/v2/books/' . $this->book( 'memoirs-of-a-ghost' ), array(), $user );
			$this->assertSame( array(), $memoirs['authors'], "$who: memoirs" );
			$this->assertArrayNotHasKey( self::REL_AUTHOR, $memoirs['_links'] );
			$this->assertArrayNotHasKey( self::REL_AUTHOR, $memoirs['_embedded'] ?? array() );

			// Books of a published author: no draft or private books.
			list( , $grace_data ) = $this->get( '/wp/v2/authors/' . $grace, array(), $user );
			$this->assertNotContains( $this->book( 'internal-style-guide' ), $grace_data['books'] );
			$this->assertNotContains( $this->book( 'internal-style-guide' ), self::link_ids( $grace_data, self::REL_BOOK ) );
			$this->assertStringNotContainsString( 'Internal Style Guide', wp_json_encode( $grace_data ) );
		}

		// Editors see (and embed) everything, in order.
		list( , $data ) = $this->get( '/wp/v2/books/' . $book, array(), $this->user( 'eddie' ) );
		$this->assertSame( array( $secret, $ada, $ghost, $grace ), $data['authors'] );
		$this->assertLinksAndEmbeds( $data, self::REL_AUTHOR, array( $secret, $ada, $ghost, $grace ), 'author', 'editor' );
	}

	public function test_books_in_the_1x_format(): void {
		$old   = $this->book( 'the-old-catalogue' );
		$ada   = $this->author( 'ada-lovelace' );
		$grace = $this->author( 'grace-hopper' );
		$alan  = $this->author( 'alan-turing' );

		list( , $data ) = $this->get( '/wp/v2/books/' . $old );
		$this->assertSame( array( $grace, $ada ), $data['authors'] );
		$this->assertLinksAndEmbeds( $data, self::REL_AUTHOR, array( $grace, $ada ), 'author', 'the-old-catalogue' );

		$pamphlet       = $this->book( 'pamphlet-of-1999' );
		list( , $data ) = $this->get( '/wp/v2/books/' . $pamphlet );
		$this->assertSame( array( $alan ), $data['authors'], 'draft author of a legacy book hidden' );
		list( , $data ) = $this->get( '/wp/v2/books/' . $pamphlet, array(), $this->user( 'eddie' ) );
		$this->assertSame( array( $this->author( 'secret-pseudonym' ), $alan ), $data['authors'] );

		list( , $data ) = $this->get( '/wp/v2/authors/' . $alan );
		$this->assertContains( $pamphlet, $data['books'] );
		$this->assertNotContains( $this->book( 'unsorted-notes' ), $data['books'], 'draft legacy book hidden' );
		list( , $data ) = $this->get( '/wp/v2/authors/' . $alan, array(), $this->user( 'eddie' ) );
		$this->assertContains( $this->book( 'unsorted-notes' ), $data['books'] );
	}

	public function test_collection_items_have_correct_relations(): void {
		list( $response, $data ) = $this->get( '/wp/v2/books', array( 'per_page' => 100 ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 100, $data );
		$with_several = 0;
		foreach ( $data as $item ) {
			$expected = $this->published( $this->stored_authors( $item['id'] ) );
			$this->assertSame( $expected, $item['authors'] ?? null, 'book ' . $item['slug'] );
			$this->assertLinksAndEmbeds( $item, self::REL_AUTHOR, $expected, 'author', $item['slug'] );
			$with_several += count( $expected ) > 1 ? 1 : 0;
		}
		$this->assertGreaterThan( 20, $with_several );

		list( , $data ) = $this->get(
			'/wp/v2/authors',
			array(
				'per_page' => 100,
				'status'   => 'publish,draft,pending,private',
			),
			$this->user( 'eddie' )
		);
		$this->assertGreaterThanOrEqual( 40, count( $data ) );
		foreach ( $data as $item ) {
			$this->assertSame( $this->stored_books( $item['id'] ), self::sorted( $item['books'] ?? array() ), 'author ' . $item['slug'] );
		}
	}

	public function test_fields_are_in_the_schema_and_edit_context(): void {
		$options = $this->rest( 'OPTIONS', '/wp/v2/books' );
		$schema           = $options->get_data()['schema']['properties'] ?? array();
		$this->assertSame( 'array', $schema['authors']['type'] ?? null );
		$this->assertSame( 'integer', $schema['authors']['items']['type'] ?? null );
		$this->assertContains( 'view', $schema['authors']['context'] );
		$this->assertContains( 'edit', $schema['authors']['context'] );

		$schema = $this->rest( 'OPTIONS', '/wp/v2/authors' )->get_data()['schema']['properties'] ?? array();
		$this->assertSame( 'array', $schema['books']['type'] ?? null );
		$this->assertSame( 'integer', $schema['books']['items']['type'] ?? null );

		$book = $this->book( 'the-hidden-hand' );
		list( $response, $data ) = $this->get( '/wp/v2/books/' . $book, array( 'context' => 'edit' ), $this->user( 'eddie' ), false );
		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 4, $data['authors'] );
	}
}
