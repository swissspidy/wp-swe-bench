<?php
/**
 * Step 2: state from the URL (first grid of a page) and pagination, rendered by the server.
 */

use function WPSB\Catalog\grids;
use function WPSB\Catalog\titles_in;

class GridUrlStateTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	/** @var int[] */
	private array $cleanup = array();

	protected function tearDown(): void {
		foreach ( $this->cleanup as $id ) {
			wp_delete_post( $id, true );
		}
		parent::tearDown();
	}

	private function get( string $path ): string {
		$res = $this->http( 'GET', $path );
		$this->assertSame( 200, $res['status'], "GET $path" );
		return $res['body'];
	}

	private function page_with( string $content, string $slug ): string {
		$this->login_as( 'administrator' );
		$id = $this->create_post( array( 'post_type' => 'page', 'post_name' => $slug, 'post_content' => wp_slash( $content ) ) );
		wp_set_current_user( 0 );
		$this->cleanup[] = $id;
		return wp_make_link_relative( get_permalink( $id ) );
	}

	private function selected( array $grid ): array {
		return array_values( array_map( static fn( $f ) => $f['slug'], array_filter( $grid['filters'], static fn( $f ) => 'true' === $f['pressed'] ) ) );
	}

	private function assertSameSet( array $expected, array $actual, string $message = '' ): void {
		sort( $expected );
		sort( $actual );
		$this->assertSame( $expected, $actual, $message );
	}

	public function test_category_from_url(): void {
		$g = grids( $this->get( '/shop/?acme_cat=mugs' ) )[0];
		$this->assertSame( array( 'mugs' ), $this->selected( $g ) );
		$this->assertSame( 'Showing 5 of 12 products', $g['count'] );
		$this->assertSameSet( titles_in( 'mugs' ), $g['visible'] );

		$g = grids( $this->get( '/shop/?acme_cat=not-a-category' ) )[0];
		$this->assertSame( array( '' ), $this->selected( $g ), 'Unknown categories fall back to the default selection' );
		$this->assertSame( 'Showing 12 of 12 products', $g['count'] );
	}

	public function test_search_from_url(): void {
		$g = grids( $this->get( '/shop/?acme_q=blue' ) )[0];
		$this->assertSame( 'blue', $g['search']['value'] );
		$this->assertSame( array( 'Blue Mug', 'Blue T-Shirt' ), $g['visible'] );
		$this->assertSame( 'Showing 2 of 12 products', $g['count'] );

		$g = grids( $this->get( '/shop/?acme_cat=shirts&acme_q=' . rawurlencode( 'TEE vin' ) ) )[0];
		$this->assertSame( array( 'Vintage T-Shirt' ), $g['visible'] );

		$g = grids( $this->get( '/shop/?acme_cat=mugs&acme_q=zzz' ) )[0];
		$this->assertSame( array(), $g['visible'] );
		$this->assertSame( 'Showing 0 of 12 products', $g['count'] );
		$this->assertFalse( $g['empty_hidden'] );
	}

	public function test_search_text_from_url_is_escaped(): void {
		$evil = '"><script>alert(1)</script><img src=x onerror=alert(2)>';
		$html = $this->get( '/shop/?acme_q=' . rawurlencode( $evil ) );
		$this->assertStringNotContainsString( '<script>alert(1)', $html );
		$this->assertStringNotContainsString( '<img src=x', $html );
		$g = grids( $html )[0];
		$this->assertSame( $evil, $g['search']['value'], 'The search field shows the text from the URL' );
		$this->assertSame( 'Showing 0 of 12 products', $g['count'] );
	}

	public function test_all_on_a_grid_with_a_default_category(): void {
		$g = grids( $this->get( '/featured/?acme_cat=all' ) )[0];
		$this->assertSame( array( '' ), $this->selected( $g ) );
		$this->assertSame( 'Showing 12 of 12 products', $g['count'] );

		$g = grids( $this->get( '/featured/' ) )[0];
		$this->assertSame( array( 'posters' ), $this->selected( $g ) );
	}

	public function test_only_the_first_grid_reads_the_url(): void {
		[ $a, $b ] = grids( $this->get( '/two-grids/?acme_cat=posters&acme_q=ocean' ) );
		$this->assertSame( array( 'posters' ), $this->selected( $a ) );
		$this->assertSame( array( 'Ocean Poster' ), $a['visible'] );
		$this->assertSame( 'ocean', $a['search']['value'] );
		$this->assertSame( array( 'shirts' ), $this->selected( $b ), 'Other grids keep their own default state' );
		$this->assertSame( 'Showing 3 of 12 products', $b['count'] );

		[ $a, $b ] = grids( $this->get( '/two-grids/?acme_cat=shirts' ) );
		$this->assertSame( array( '' ), $this->selected( $a ), 'shirts is not a filter of the first grid' );
		$this->assertSame( array( 'shirts' ), $this->selected( $b ) );

		[ $a, $b ] = grids( $this->get( '/classic-shop/?acme_q=travel' ) );
		$this->assertSame( array( 'Travel Mug' ), $a['visible'], 'Shortcode grids read the URL too' );
		$this->assertSame( array( 'Sticker Pack' ), $b['visible'] );
	}

	public function test_pagination(): void {
		$path = $this->page_with( '<!-- wp:acme/product-grid {"perPage":4} /-->', 'wpsb-paged-grid' );
		$all  = titles_in( '' );

		$g = grids( $this->get( $path ) )[0];
		$this->assertCount( 12, $g['items'] );
		$order = array_column( $g['items'], 'title' );
		$this->assertSameSet( $all, $order );
		$this->assertSame( array_slice( $order, 0, 4 ), $g['visible'] );
		$this->assertSame( 'Showing 12 of 12 products', $g['count'], 'The count is about all matching products, not the page' );
		$this->assertNotNull( $g['nav'], 'Pagination (.acme-grid__pagination) expected' );
		$this->assertFalse( $g['nav']['hidden'] );
		$this->assertSame( 'Page 1 of 3', $g['nav']['text'] );
		$this->assertTrue( $g['nav']['prev_disabled'] );
		$this->assertFalse( $g['nav']['next_disabled'] );

		$g = grids( $this->get( $path . '?acme_page=2' ) )[0];
		$this->assertSame( array_slice( $order, 4, 4 ), $g['visible'] );
		$this->assertSame( 'Page 2 of 3', $g['nav']['text'] );
		$this->assertFalse( $g['nav']['prev_disabled'] );
		$this->assertFalse( $g['nav']['next_disabled'] );

		$g = grids( $this->get( $path . '?acme_page=3' ) )[0];
		$this->assertSame( array_slice( $order, 8, 4 ), $g['visible'] );
		$this->assertTrue( $g['nav']['next_disabled'] );

		foreach ( array( '4', '0', 'abc', '-1' ) as $invalid ) {
			$g = grids( $this->get( $path . '?acme_page=' . $invalid ) )[0];
			$this->assertSame( 'Page 1 of 3', $g['nav']['text'], "acme_page=$invalid falls back to page 1" );
			$this->assertSame( array_slice( $order, 0, 4 ), $g['visible'] );
		}

		$mugs = array_values( array_filter( $order, static fn( $t ) => in_array( $t, titles_in( 'mugs' ), true ) ) );
		$g    = grids( $this->get( $path . '?acme_cat=mugs&acme_page=2' ) )[0];
		$this->assertSame( array_slice( $mugs, 4 ), $g['visible'] );
		$this->assertSame( 'Page 2 of 2', $g['nav']['text'] );
		$this->assertSame( 'Showing 5 of 12 products', $g['count'] );

		$g = grids( $this->get( $path . '?acme_cat=stickers' ) )[0];
		$this->assertTrue( $g['nav']['hidden'], 'Pagination is hidden when there is only one page' );
	}

	public function test_grids_without_page_size_show_everything(): void {
		$g = grids( $this->get( '/shop/?acme_page=2' ) )[0];
		$this->assertCount( 12, $g['visible'] );
		$this->assertTrue( null === $g['nav'] || $g['nav']['hidden'], 'No visible pagination without a page size' );
	}

	public function test_shortcode_per_page(): void {
		$path = $this->page_with( '[acme_products per_page="5" search="no"]', 'wpsb-paged-shortcode' );
		$g    = grids( $this->get( $path . '?acme_page=3' ) )[0];
		$this->assertSame( 'Page 3 of 3', $g['nav']['text'] );
		$this->assertCount( 2, $g['visible'] );
	}
}
