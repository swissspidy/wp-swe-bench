<?php
/**
 * Importing the full export with "Change all imported URLs…" enabled.
 */

use function WPSB\Importer\cover_background;
use function WPSB\Importer\find_blocks;
use function WPSB\Importer\import_command;
use function WPSB\Importer\imported;
use function WPSB\Importer\imported_row;
use function WPSB\Importer\original;
use function WPSB\Importer\posts;
use function WPSB\Importer\self_closing;
use function WPSB\Importer\shape;
use function WPSB\Importer\style_urls_by_class;
use const WPSB\Importer\FIXTURES;
use const WPSB\Importer\OLD;
use const WPSB\Importer\SITE;

class ImportRewriteTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private static $import = null;

	private function import(): array {
		if ( null === self::$import ) {
			self::$import = $this->wp_cli( import_command( FIXTURES . '/alpine-trails-full.xml', true ) );
			wp_cache_flush();
		}
		return self::$import;
	}

	protected function setUp(): void {
		parent::setUp();
		$this->import();
	}

	public function test_import_completes(): void {
		$this->assertSame( 0, self::$import['exit'], self::$import['stderr'] . self::$import['stdout'] );
		$this->assertStringContainsString( 'All done', self::$import['stdout'] );
		foreach ( array_keys( posts() ) as $slug ) {
			$this->assertNotNull( imported_row( $slug ), "$slug was imported" );
		}
	}

	public function test_block_structure_is_unchanged_by_rewriting(): void {
		foreach ( array_keys( posts() ) as $slug ) {
			$this->assertSame( shape( parse_blocks( original( $slug ) ) ), shape( parse_blocks( imported( $slug ) ) ), "Block structure of $slug" );
		}
	}

	public function test_self_closing_blocks_stay_self_closing(): void {
		foreach ( array_keys( posts() ) as $slug ) {
			$this->assertSame( self_closing( original( $slug ) ), self_closing( imported( $slug ) ), "Self-closing blocks in $slug" );
		}
	}

	public function test_navigation_links_point_to_this_site(): void {
		$blocks = parse_blocks( imported( 'main-menu' ) );
		$links  = array();
		foreach ( array_merge( find_blocks( $blocks, 'core/navigation-link' ), find_blocks( $blocks, 'core/navigation-submenu' ) ) as $link ) {
			$links[ $link['attrs']['label'] ] = $link['attrs']['url'];
		}
		ksort( $links );
		$this->assertSame(
			array(
				'About'          => SITE . '/about/',
				'Blog'           => SITE . '/blog/',
				'Contact'        => SITE . '/contact/',
				'Guides'         => SITE . '/guides/',
				'Maps & routes'  => SITE . '/guides/maps/',
				'Packing list'   => SITE . '/guides/packing-list/',
				'Partner shop'   => 'https://shop.partner.example/?ref=oldblog',
			),
			$links
		);
		$this->assertSame( 2, find_blocks( $blocks, 'core/navigation-link' )[0]['attrs']['id'] );
		$this->assertTrue( find_blocks( $blocks, 'core/navigation-link' )[3]['attrs']['opensInNewTab'] );
	}

	public function test_navigation_menu_keeps_its_hierarchy(): void {
		$blocks = array_values( array_filter( parse_blocks( imported( 'main-menu' ) ), static fn( $b ) => null !== $b['blockName'] ) );
		$this->assertSame(
			array( 'core/navigation-link', 'core/navigation-link', 'core/navigation-submenu', 'core/navigation-link' ),
			array_column( $blocks, 'blockName' )
		);
		$this->assertSame( 'Contact', $blocks[3]['attrs']['label'] );
		$children = array_values( array_filter( $blocks[2]['innerBlocks'], static fn( $b ) => null !== $b['blockName'] ) );
		$this->assertSame( array( 'Packing list', 'Partner shop', 'Maps & routes' ), array_map( static fn( $b ) => $b['attrs']['label'], $children ) );
		$this->assertSame( array(), $blocks[0]['innerBlocks'] );
	}

	public function test_social_links_are_rewritten_and_intact(): void {
		$blocks = parse_blocks( imported( 'follow-us' ) );
		$social = find_blocks( $blocks, 'core/social-links' );
		$this->assertCount( 1, $social );
		$this->assertSame(
			array( SITE . '/feed/', 'https://mastodon.example/@alpinetrails', SITE . '/newsletter/' ),
			array_map( static fn( $b ) => $b['attrs']['url'], find_blocks( $social[0]['innerBlocks'], 'core/social-link' ) )
		);
		$this->assertSame( 'News & offers', find_blocks( $blocks, 'core/social-link' )[2]['attrs']['label'] );
		$top = array_values( array_filter( array_column( $blocks, 'blockName' ) ) );
		$this->assertSame( array( 'core/paragraph', 'core/social-links', 'core/latest-posts', 'core/paragraph' ), $top );
	}

	public function test_social_links_render_on_the_front_end(): void {
		$res = $this->http( 'GET', '/follow-us/' );
		$this->assertSame( 200, $res['status'] );
		$xp    = WPSB\Importer\dom( $res['body'] );
		$icons = $xp->query( "//ul[contains(@class,'wp-block-social-links')]/li[contains(@class,'wp-social-link')]" );
		$this->assertSame( 3, $icons->length );
		$latest = "contains(concat(' ', normalize-space(@class), ' '), ' wp-block-latest-posts ')";
		$this->assertSame( 1, $xp->query( "//*[$latest]" )->length );
		$this->assertSame( 0, $xp->query( "//ul[contains(@class,'wp-block-social-links')]//*[$latest]" )->length );
		$this->assertStringNotContainsString( 'oldblog.example/feed', $res['body'] );
	}

	public function test_parallax_cover_background_is_rewritten_consistently(): void {
		$covers = find_blocks( parse_blocks( imported( 'summer-in-the-alps' ) ), 'core/cover' );
		$this->assertCount( 1, $covers );
		$url = SITE . '/wp-content/uploads/2024/05/alps-hero.jpg';
		$this->assertSame( $url, $covers[0]['attrs']['url'] );
		$this->assertSame( $url, cover_background( $covers[0] ) );
		$this->assertStringNotContainsString( 'oldblog.example', imported( 'summer-in-the-alps' ) );
	}

	public function test_repeated_cover_backgrounds(): void {
		$covers = find_blocks( parse_blocks( imported( 'granite-pattern' ) ), 'core/cover' );
		$this->assertCount( 2, $covers );
		$url = SITE . '/wp-content/uploads/2024/05/granite.png';
		$this->assertSame( $url, $covers[0]['attrs']['url'] );
		$this->assertSame( $url, cover_background( $covers[0] ) );
		// A texture from another site is left alone.
		$this->assertSame( 'https://cdn.partner.example/textures/moss.png', $covers[1]['attrs']['url'] );
		$this->assertSame( 'https://cdn.partner.example/textures/moss.png', cover_background( $covers[1] ) );
	}

	public function test_css_urls_in_style_attributes(): void {
		$html = find_blocks( parse_blocks( imported( 'old-landing-page' ) ), 'core/html' );
		$this->assertCount( 1, $html );
		$urls = style_urls_by_class( $html[0]['innerHTML'] );
		$this->assertSame( array( SITE . '/wp-content/uploads/2023/10/pattern.png' ), $urls['promo'] );
		$this->assertSame( array( SITE . '/wp-content/uploads/2023/10/badge.svg' ), $urls['badge'] );
		$this->assertSame( array( SITE . '/wp-content/uploads/2023/10/photo.jpg', 'https://cdn.partner.example/overlay.png' ), $urls['photo'] );
		$this->assertSame( array( 'data:image/gif;base64,R0lGODlhAQABAAAAACw=' ), $urls['dot'] );
		$this->assertContains( $urls['rel'][0], array( '/wp-content/uploads/2023/10/relative.png', SITE . '/wp-content/uploads/2023/10/relative.png' ) );
		$this->assertStringNotContainsString( 'oldblog.example', imported( 'old-landing-page' ) );

		// The rest of the declarations and the markup are kept.
		$xp = WPSB\Importer\dom( $html[0]['innerHTML'] );
		$this->assertStringContainsString( 'repeat', $xp->query( "//*[@class='promo']" )->item( 0 )->getAttribute( 'style' ) );
		$this->assertStringContainsString( 'padding:2em', $xp->query( "//*[@class='promo']" )->item( 0 )->getAttribute( 'style' ) );
		$this->assertStringContainsString( 'width: 40px', $xp->query( "//*[@class='badge']" )->item( 0 )->getAttribute( 'style' ) );
		$this->assertSame( 'New', trim( $xp->query( "//*[@class='badge']" )->item( 0 )->textContent ) );
	}

	public function test_links_images_and_text_are_still_rewritten(): void {
		$content = imported( 'packing-tips' );
		$this->assertStringContainsString( 'href="' . SITE . '/guides/packing-list/"', $content );
		$this->assertStringContainsString( 'href="https://www.example.org/hiking/"', $content );
		$this->assertStringContainsString( 'src="' . SITE . '/wp-content/uploads/2024/06/lake.jpg"', $content );
		$this->assertStringContainsString( 'href="' . SITE . '/2024/06/lake/"', $content );
		$this->assertStringContainsString( 'href="' . SITE . '/shop/"', $content );
		$this->assertStringContainsString( SITE . '/start/', $content );
		$this->assertStringNotContainsString( OLD, $content );
		$this->assertSame( 'All our tips: ' . SITE . '/guides/packing-list/', imported_row( 'packing-tips' )['post_excerpt'] );
		$this->assertStringContainsString( 'href="' . SITE . '/photographers/lea/"', imported( 'summer-in-the-alps' ) );
		$this->assertStringContainsString( 'href="' . SITE . '/blog/"', imported( 'follow-us' ) );
	}

	public function test_blocks_without_urls_to_rewrite_are_kept(): void {
		$content = imported( 'newsletter' );
		foreach ( array( '<h2 class="wp-block-heading">Stay in the loop</h2>', '<p>We send one email a month.</p>', '"action":"https://lists.example.org/subscribe"', '"zoom":11' ) as $fragment ) {
			$this->assertStringContainsString( $fragment, $content );
		}
		$this->assertStringContainsString( '<!-- wp:latest-posts {"postsToShow":3} /-->', imported( 'follow-us' ) );
	}
}
