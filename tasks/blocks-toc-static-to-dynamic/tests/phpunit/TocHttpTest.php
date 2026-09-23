<?php
/**
 * Requests against the real (Playground) web server: pagination and served pages.
 */

use function WPSB\Toc\fragment;
use function WPSB\Toc\headings;
use function WPSB\Toc\ids;
use function WPSB\Toc\resolve;
use function WPSB\Toc\tocs;

class TocHttpTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private function get( string $path ): array {
		$res = $this->http( 'GET', $path );
		$this->assertSame( 200, $res['status'], "GET $path" );
		return $res;
	}

	public function test_paginated_post_links_point_at_the_right_page(): void {
		$base = rtrim( WP_HOME, '/' );
		$page = $this->get( '/paged-guide/' );
		$all  = tocs( $page['body'] );
		$this->assertCount( 1, $all );
		$toc  = $all[0];
		$urls = array_map( static fn( $i ) => resolve( $i['href'], $base . '/paged-guide/' ), $toc['items'] );
		$this->assertSame(
			array(
				$base . '/paged-guide/#introduction',
				$base . '/paged-guide/#requirements',
				$base . '/paged-guide/2/#installation',
				$base . '/paged-guide/2/#introduction-2',
				$base . '/paged-guide/2/#verifying',
				$base . '/paged-guide/3/#wrap-up',
			),
			$urls
		);
		$this->assertSame( '#introduction', $toc['items'][0]['href'], 'Links to the current page are fragment-only' );
		$this->assertSame( 3, $toc['items'][4]['level'] );

		// The headings on the first page carry the ids.
		$ids = ids( $page['body'] );
		$this->assertContains( 'introduction', $ids );
		$this->assertContains( 'requirements', $ids );
	}

	public function test_headings_on_later_pages_get_the_same_anchors(): void {
		$page2 = $this->get( '/paged-guide/2/' );
		$h     = array_values( array_filter( headings( $page2['body'] ), static fn( $x ) => in_array( $x['text'], array( 'Installation', 'Introduction', 'Verifying' ), true ) ) );
		$this->assertSame(
			array( array( 'Installation', 'installation' ), array( 'Introduction', 'introduction-2' ), array( 'Verifying', 'verifying' ) ),
			array_map( static fn( $x ) => array( $x['text'], $x['id'] ), $h ),
			'Page 2 must use the anchors the TOC links to (unique across the whole post)'
		);

		$page3 = $this->get( '/paged-guide/3/' );
		$h     = array_values( array_filter( headings( $page3['body'] ), static fn( $x ) => 'Wrap-up' === $x['text'] ) );
		$this->assertSame( 'wrap-up', $h[0]['id'] ?? null );
	}

	public function test_served_pages_link_to_existing_ids(): void {
		foreach ( array( '/stale-toc/', '/duplicate-headings/', '/special-chars/', '/legacy-contents/', '/no-title-toc/' ) as $path ) {
			$res = $this->get( $path );
			$all = tocs( $res['body'] );
			$this->assertCount( 1, $all, "One TOC on $path" );
			$ids = ids( $res['body'] );
			$this->assertNotEmpty( $all[0]['items'] );
			foreach ( $all[0]['items'] as $item ) {
				$frag = fragment( $item['href'] );
				$this->assertSame( 1, count( array_keys( $ids, $frag, true ) ), "$path: #$frag must exist exactly once" );
			}
			$this->assertSame( 'nav', $all[0]['tag'] );
		}
	}

	public function test_smooth_scroll_setting_still_works(): void {
		$res = $this->get( '/stale-toc/' );
		$this->assertStringContainsString( 'scroll-behavior:smooth', $res['body'] );

		$saved = get_option( 'acme_toc_options' );
		update_option( 'acme_toc_options', array( 'max_level' => 3, 'smooth_scroll' => false ) );
		try {
			$res = $this->get( '/stale-toc/' );
			$this->assertStringNotContainsString( 'scroll-behavior:smooth', $res['body'] );
		} finally {
			update_option( 'acme_toc_options', $saved );
		}
	}

	public function test_toc_markup_keeps_theme_classes(): void {
		$res = $this->get( '/duplicate-headings/' );
		$all = tocs( $res['body'] );
		$this->assertCount( 1, $all );
		$this->assertContains( 'wp-block-acme-toc', $all[0]['classes'] );
		$this->assertSame( 'In this guide', $all[0]['title'] );
		$this->assertSame( 'In this guide', $all[0]['label'] );
		foreach ( $all[0]['items'] as $item ) {
			$this->assertContains( 'acme-toc__item--h2', $item['classes'] );
		}
	}
}
