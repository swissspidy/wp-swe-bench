<?php
/**
 * The block on real pages served by the web server.
 */

use function WPSB\Events\listings;

class FrontEndHttpTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private array $created = array();

	protected function tearDown(): void {
		foreach ( $this->created as $id ) {
			wp_delete_post( $id, true );
		}
		parent::tearDown();
	}

	private function page( string $content ): int {
		$id              = $this->create_post( array( 'post_type' => 'page', 'post_content' => $content ) );
		$this->created[] = $id;
		return $id;
	}

	public function test_block_on_a_page_matches_the_shortcode_on_a_page(): void {
		$block = $this->page( '<!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph -->' . "\n\n" . '<!-- wp:acme/upcoming-events {"limit":4,"layout":"grid","title":"Coming up","showVenue":false,"align":"wide"} /-->' );
		$short = $this->page( '<!-- wp:shortcode -->' . "\n" . '[acme_events limit="4" layout="grid" title="Coming up" show_venue="no"]' . "\n" . '<!-- /wp:shortcode -->' );

		$b = $this->http( 'GET', get_permalink( $block ) );
		$s = $this->http( 'GET', get_permalink( $short ) );
		$this->assertSame( 200, $b['status'] );
		$this->assertSame( 200, $s['status'] );

		$main_b = $this->main_listings( $b['body'] );
		$main_s = $this->main_listings( $s['body'] );
		$this->assertCount( 1, $main_b, 'The block must render one listing on the page' );
		$this->assertCount( 1, $main_s );
		$this->assertSame( $main_s[0]['inner'], $main_b[0]['inner'] );
		$this->assertSame( 'Coming up', $main_b[0]['heading'] );
		$this->assertSame( array( 'Intro to Gutenberg', 'WordPress Meetup Zürich', 'Block Themes Deep Dive', 'WordCamp Acme 2031' ), $main_b[0]['titles'] );
		$this->assertContains( 'acme-events--grid', $main_b[0]['classes'] );
		$this->assertContains( 'alignwide', $main_b[0]['classes'], 'Block wrapper classes (alignment) belong on the listing root' );
	}

	/** Listings inside the main content area only (not the widgets). */
	private function main_listings( string $html ): array {
		$xpath = WPSB\Events\dom( $html );
		$main  = $xpath->query( '//main' )->item( 0 );
		$this->assertNotNull( $main, 'no <main> on the page' );
		return listings( '', $xpath, $main );
	}
}
