<?php
/**
 * Server-rendered state of product grids (served by Playground).
 */

use function WPSB\Catalog\grids;
use function WPSB\Catalog\pressed;
use function WPSB\Catalog\scripts;
use function WPSB\Catalog\titles_in;

class GridServerStateTest extends WPSB\TestCase {

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

	private function assertSameSet( array $expected, array $actual, string $message = '' ): void {
		sort( $expected );
		sort( $actual );
		$this->assertSame( $expected, $actual, $message );
	}

	/** Exactly one filter is pressed, and is-active matches aria-pressed. */
	private function assertFilters( array $grid, string $selected ): void {
		foreach ( $grid['filters'] as $f ) {
			$this->assertSame( 'button', $f['tag'] );
			$this->assertSame( 'button', $f['type'] );
			$this->assertSame( $f['slug'] === $selected ? 'true' : 'false', $f['pressed'], 'aria-pressed of filter "' . $f['text'] . '"' );
			$this->assertSame( $f['slug'] === $selected, $f['active'], 'is-active class of filter "' . $f['text'] . '"' );
		}
	}

	public function test_shop_grid_initial_state(): void {
		$html = $this->get( '/shop/' );
		$all  = grids( $html );
		$this->assertCount( 1, $all );
		$g = $all[0];
		$this->assertSame( 'All products', $g['heading'] );
		$this->assertSame(
			array( array( '', 'All' ), array( 'mugs', 'Mugs' ), array( 'posters', 'Posters' ), array( 'stickers', 'Stickers' ), array( 'shirts', 'T-Shirts' ) ),
			array_map( static fn( $f ) => array( $f['slug'], $f['text'] ), $g['filters'] )
		);
		$this->assertFilters( $g, '' );
		$this->assertNotNull( $g['search'] );
		$this->assertSame( 'Showing 12 of 12 products', $g['count'], 'The count must be rendered by the server' );
		$this->assertCount( 12, $g['items'] );
		$this->assertSameSet( titles_in( '' ), $g['visible'] );
		$this->assertTrue( $g['empty_hidden'], 'The "no products" message must have the hidden attribute' );
		$this->assertStringNotContainsString( 'Secret Prototype', $html, 'Drafts are never listed' );
	}

	public function test_initially_selected_category_is_rendered_by_the_server(): void {
		$g = grids( $this->get( '/featured/' ) )[0];
		$this->assertSame( 'Wall art', $g['heading'] );
		$this->assertFilters( $g, 'posters' );
		$this->assertSame( 'Showing 3 of 12 products', $g['count'] );
		$this->assertSame( array( 'Mountain Poster', 'Ocean Poster', 'Mug & Poster Bundle' ), $g['visible'], 'Only posters visible, ordered by price' );
		$this->assertCount( 12, $g['items'], 'Products that do not match stay in the page (hidden)' );
		$this->assertTrue( $g['empty_hidden'] );
	}

	public function test_several_grids_have_their_own_state(): void {
		$all = grids( $this->get( '/two-grids/' ) );
		$this->assertCount( 2, $all );
		[ $a, $b ] = $all;
		$this->assertSame( array( '', 'mugs', 'posters' ), array_column( $a['filters'], 'slug' ) );
		$this->assertFilters( $a, '' );
		$this->assertSame( 'Showing 7 of 7 products', $a['count'] );
		$this->assertSameSet( array_values( array_unique( array_merge( titles_in( 'mugs' ), titles_in( 'posters' ) ) ) ), $a['visible'] );

		$this->assertNull( $b['search'], 'showSearch:false grids have no search field' );
		$this->assertFilters( $b, 'shirts' );
		$this->assertSame( 'Showing 3 of 12 products', $b['count'] );
		$this->assertSame( array( 'Logo T-Shirt', 'Blue T-Shirt', 'Vintage T-Shirt' ), $b['visible'] );
	}

	public function test_shortcode_grids_are_rendered_the_same_way(): void {
		$all = grids( $this->get( '/classic-shop/' ) );
		$this->assertCount( 2, $all );
		$this->assertSame( 'Mugs', $all[0]['heading'] );
		$this->assertFilters( $all[0], '' );
		$this->assertSame( 'Showing 5 of 5 products', $all[0]['count'] );
		$this->assertSameSet( titles_in( 'mugs' ), $all[0]['visible'] );
		$this->assertTrue( $all[0]['empty_hidden'] );

		$this->assertNull( $all[1]['search'] );
		$this->assertSame( 'Showing 1 of 1 product', $all[1]['count'], 'Singular when the grid has one product' );
		$this->assertSame( array( 'Sticker Pack' ), $all[1]['visible'] );
	}

	public function test_grid_without_products_shows_the_empty_message(): void {
		$path = $this->page_with( '<!-- wp:acme/product-grid {"categories":["does-not-exist"],"heading":"Nothing"} /-->', 'wpsb-empty-grid' );
		$g    = grids( $this->get( $path ) )[0];
		$this->assertSame( 'Showing 0 of 0 products', $g['count'] );
		$this->assertSame( array(), $g['items'] );
		$this->assertFalse( $g['empty_hidden'], 'The "no products" message must be visible when nothing matches' );
		$this->assertSame( array( '' ), array_column( $g['filters'], 'slug' ) );
	}

	public function test_no_jquery_and_view_script_is_a_module(): void {
		foreach ( array( '/shop/', '/two-grids/', '/classic-shop/' ) as $path ) {
			$html   = $this->get( $path );
			$module = false;
			foreach ( scripts( $html ) as $s ) {
				$this->assertDoesNotMatchRegularExpression( '#/jquery(-migrate)?(\.min)?\.js#', (string) $s['src'], "$path loads jQuery" );
				if ( 'module' === $s['type'] && false !== strpos( (string) $s['src'], '/plugins/acme-catalog/' ) ) {
					$module = true;
				}
			}
			$this->assertTrue( $module, "$path: the grid's front-end script must be loaded as a JavaScript module" );
		}
	}

	public function test_product_cards_and_currency_setting(): void {
		$g     = grids( $this->get( '/shop/' ) )[0];
		$items = array_column( $g['items'], null, 'title' );
		$this->assertSame( '12.00 €', $items['Blue Mug']['price'] );
		$this->assertSame( '14.50 €', $items['Café Mug']['price'] );
		$this->assertStringEndsWith( '/product/blue-mug/', $items['Blue Mug']['href'] );

		$saved = get_option( 'acme_catalog_options' );
		update_option( 'acme_catalog_options', array( 'currency' => '$', 'currency_position' => 'before' ) );
		try {
			$items = array_column( grids( $this->get( '/shop/' ) )[0]['items'], null, 'title' );
			$this->assertSame( '$27.00', $items['Mug & Poster Bundle']['price'] );
		} finally {
			update_option( 'acme_catalog_options', $saved );
		}
	}
}
