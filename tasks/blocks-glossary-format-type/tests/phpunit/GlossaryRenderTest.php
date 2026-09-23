<?php
/**
 * Front-end rendering of marked glossary terms and legacy shortcodes.
 */

use function WPSB\Glossary\mark;
use function WPSB\Glossary\paragraph;
use function WPSB\Glossary\render_content;
use function WPSB\Glossary\render_slug;
use function WPSB\Glossary\squish;
use function WPSB\Glossary\term_id;
use function WPSB\Glossary\terms;

class GlossaryRenderTest extends WPSB\TestCase {

	private function assertAccessibleTerm( array $t, int $id, string $text, ?string $definition = null ): void {
		$this->assertSame( (string) $id, $t['attrs']['data-term-id'] ?? null, 'data-term-id' );
		$this->assertSame( $text, $t['text'], 'marked text' );
		$this->assertSame( '0', $t['attrs']['tabindex'] ?? null, 'Terms must be focusable (tabindex="0")' );
		$this->assertArrayNotHasKey( 'title', $t['attrs'], 'No title attribute on terms any more' );
		$this->assertNotNull( $t['tooltip'], 'aria-describedby must point to the definition element' );
		$this->assertSame( 'tooltip', $t['tooltip']['attrs']['role'] ?? null );
		$this->assertContains( 'acme-glossary-term__definition', preg_split( '/\s+/', $t['tooltip']['attrs']['class'] ?? '' ) );
		$this->assertSame( 0, $t['tooltip']['children'], 'The definition must be plain text' );
		if ( null === $definition ) {
			$definition = squish( acme_glossary_get_definition( $id ) );
		}
		$this->assertSame( $definition, $t['tooltip']['text'] );
	}

	public function test_marked_terms_get_accessible_tooltips(): void {
		$cache = term_id( 'cache' );
		$cdn   = term_id( 'cdn' );
		$html  = render_content(
			paragraph( 'Use a ' . mark( $cache, 'cache' ) . ' and a ' . mark( $cdn, 'CDN' ) . '.' ) . "\n\n" .
			paragraph( 'Another ' . mark( $cache, 'cache' ) . ' mention.' )
		);
		$all = terms( $html );
		$this->assertCount( 3, $all, $html );
		$this->assertAccessibleTerm( $all[0], $cache, 'cache', 'A store of copies of data, so that future requests are served faster.' );
		$this->assertAccessibleTerm( $all[1], $cdn, 'CDN', 'Content delivery network: servers around the world that deliver files from a location near the visitor.' );
		$this->assertAccessibleTerm( $all[2], $cache, 'cache' );
		$ids = array( $all[0]['attrs']['aria-describedby'], $all[1]['attrs']['aria-describedby'], $all[2]['attrs']['aria-describedby'] );
		$this->assertCount( 3, array_unique( $ids ), 'Tooltip ids must be unique on the page' );
		$this->assertStringContainsString( 'Use a ', $html );
		$this->assertStringContainsString( ' mention.', $html );
	}

	public function test_attribute_order_and_inner_formatting(): void {
		$ttfb = term_id( 'ttfb' );
		$html = render_content( paragraph( 'Measure <span data-term-id="' . $ttfb . '" class="acme-glossary-term"><strong>time</strong> to first byte</span> now.' ) );
		$all  = terms( $html );
		$this->assertCount( 1, $all, $html );
		$this->assertAccessibleTerm( $all[0], $ttfb, 'time to first byte' );
		$this->assertStringContainsString( '<strong>time</strong>', $all[0]['inner_html'] );
	}

	public function test_definitions_from_excerpt_content_and_filter(): void {
		$ids  = array( term_id( 'edge-function' ), term_id( 'origin-server' ), term_id( 'cache-invalidation' ), term_id( 'stale-while-revalidate' ) );
		$html = render_content( paragraph( mark( $ids[0], 'edge' ) . ' ' . mark( $ids[1], 'origin' ) . ' ' . mark( $ids[2], 'invalidation' ) . ' ' . mark( $ids[3], 'SWR' ) ) );
		$all  = terms( $html );
		$this->assertCount( 4, $all, $html );
		$this->assertAccessibleTerm( $all[0], $ids[0], 'edge', 'Code that runs on CDN servers close to the visitor.' );
		$this->assertAccessibleTerm( $all[1], $ids[1], 'origin' );
		$this->assertStringStartsWith( 'The origin server is the machine', $all[1]['tooltip']['text'] );
		$this->assertAccessibleTerm( $all[2], $ids[2], 'invalidation', 'Removing or refreshing cached data when the original changes. (Deprecated term.)' );
		$this->assertAccessibleTerm( $all[3], $ids[3], 'SWR' );
		$this->assertStringContainsString( '& refresh it "in the background"', $all[3]['tooltip']['text'] );
	}

	public function test_definitions_cannot_inject_markup(): void {
		$cdn    = term_id( 'cdn' );
		$filter = static function ( $definition, $term ) use ( $cdn ) {
			if ( (int) $term->ID === $cdn ) {
				return '</span><img src=x onerror=alert(1)> "quoted" & <b>bold</b> <script>alert(2)</script>';
			}
			return $definition;
		};
		add_filter( 'acme_glossary_definition', $filter, 20, 2 );
		try {
			$html = render_content( paragraph( 'A ' . mark( $cdn, 'CDN' ) . ' here.' ) );
			$sc   = render_content( '<p>Old: [glossary term="cdn"]CDN[/glossary]</p>' );
		} finally {
			remove_filter( 'acme_glossary_definition', $filter, 20 );
		}
		foreach ( array( $html, $sc ) as $out ) {
			$this->assertStringNotContainsString( '<img', $out );
			$this->assertStringNotContainsString( '<script', $out );
			$this->assertStringNotContainsString( '<b>', $out );
			$this->assertDoesNotMatchRegularExpression( '/<[^>]+\sonerror=/i', $out );
			$all = terms( $out );
			$this->assertCount( 1, $all, $out );
			$this->assertNotNull( $all[0]['tooltip'] );
			$this->assertSame( 0, $all[0]['tooltip']['children'] );
			$this->assertStringContainsString( '"quoted" &', $all[0]['tooltip']['text'] );
			$this->assertSame( 'CDN', $all[0]['text'] );
		}
		$this->assertStringContainsString( ' here.', $html );
	}

	public function test_marks_of_missing_or_unpublished_terms_are_plain_text(): void {
		$page_id = (int) get_page_by_path( 'glossary-terms' )->ID;
		$content = paragraph(
			'Draft: ' . mark( term_id( 'cache-stampede' ), 'stampede' ) .
			' Private: ' . mark( term_id( 'internal-cdn' ), '<em>internal</em> CDN' ) .
			' Gone: ' . mark( 999999, 'ghost' ) .
			' Page: ' . mark( $page_id, 'not a term' ) .
			' Bad: <span class="acme-glossary-term" data-term-id="abc">bad id</span>' .
			' Fine: ' . mark( term_id( 'cache' ), 'cache' )
		);
		$html = render_content( $content );
		$all  = terms( $html );
		$this->assertCount( 1, $all, 'Only the published term may be rendered as a term: ' . $html );
		$this->assertSame( 'cache', $all[0]['text'] );
		foreach ( array( 'Draft: stampede', 'Gone: ghost', 'Page: not a term', 'Bad: bad id' ) as $text ) {
			$this->assertStringContainsString( $text, squish( wp_strip_all_tags( $html ) ) );
		}
		$this->assertStringContainsString( '<em>internal</em> CDN', $html );
		$this->assertStringNotContainsString( 'Many requests regenerating', $html );
		$this->assertStringNotContainsString( 'Our private CDN', $html );
		$this->assertStringNotContainsString( 'data-term-id="' . term_id( 'cache-stampede' ) . '"', $html );
	}

	public function test_legacy_shortcodes_render_the_new_markup(): void {
		$html = render_slug( 'caching-basics' );
		$all  = terms( $html );
		$this->assertCount( 4, $all, $html );
		$this->assertAccessibleTerm( $all[0], term_id( 'cache' ), 'Cache' );
		$this->assertAccessibleTerm( $all[1], term_id( 'cdn' ), 'content delivery network' );
		$this->assertAccessibleTerm( $all[2], term_id( 'edge-function' ), 'edge functions' );
		$this->assertAccessibleTerm( $all[3], term_id( 'ttfb' ), 'TTFB' );
		$text = squish( wp_strip_all_tags( $html ) );
		$this->assertStringContainsString( 'Unknown terms stay plain: mystery meat.', $text );
		$this->assertStringContainsString( 'Drafts are never explained: stampedes.', $text );
		$this->assertStringContainsString( 'our internal CDN.', $text );
		$this->assertStringNotContainsString( '[glossary', $html );
		$this->assertDoesNotMatchRegularExpression( '/class="acme-glossary-term"[^>]*\stitle=/', $html );

		$block = terms( render_slug( 'block-shortcode' ) );
		$this->assertCount( 1, $block );
		$this->assertAccessibleTerm( $block[0], term_id( 'cdn' ), 'CDN' );
	}

	public function test_changed_definitions_show_up_immediately(): void {
		$cdn     = term_id( 'cdn' );
		$edge    = term_id( 'edge-function' );
		$content = paragraph( mark( $cdn, 'CDN' ) . ' and ' . mark( $edge, 'edge' ) );
		// Warm every cache first, like visitors would.
		render_content( $content );
		render_slug( 'caching-basics' );

		// Changed through code / WP-CLI (no post save).
		update_post_meta( $cdn, '_acme_glossary_short', 'Updated CDN definition.' );
		$all = terms( render_content( $content ) );
		$this->assertSame( 'Updated CDN definition.', $all[0]['tooltip']['text'] ?? null );
		$legacy = terms( render_slug( 'caching-basics' ) );
		$this->assertSame( 'Updated CDN definition.', $legacy[1]['tooltip']['text'] ?? null, 'Shortcodes must show the current definition too' );

		// Changed through the post itself.
		wp_update_post( array( 'ID' => $edge, 'post_excerpt' => 'Updated edge excerpt.' ) );
		$all = terms( render_content( $content ) );
		$this->assertSame( 'Updated edge excerpt.', $all[1]['tooltip']['text'] ?? null );
		$legacy = terms( render_slug( 'caching-basics' ) );
		$this->assertSame( 'Updated edge excerpt.', $legacy[2]['tooltip']['text'] ?? null );

		// Short definition removed: falls back to the excerpt (the term has none) / content.
		delete_post_meta( $cdn, '_acme_glossary_short' );
		$all = terms( render_content( $content ) );
		$this->assertSame( 'See our CDN guide.', $all[0]['tooltip']['text'] ?? null );
	}

	public function test_trashed_deleted_and_unpublished_terms_fall_back_to_text(): void {
		$cdn     = term_id( 'cdn' );
		$ttfb    = term_id( 'ttfb' );
		$cache   = term_id( 'cache' );
		$edge    = term_id( 'edge-function' );
		$content = paragraph( mark( $cdn, 'CDN' ) . ' / ' . mark( $ttfb, 'TTFB' ) . ' / ' . mark( $cache, 'cache' ) . ' / ' . mark( $edge, 'edge' ) );
		$this->assertCount( 4, terms( render_content( $content ) ) );
		$this->assertCount( 4, terms( render_slug( 'caching-basics' ) ) );

		// Deleted for good (no save involved).
		wp_delete_post( $cache, true );
		$this->assertSame( array( 'CDN', 'TTFB', 'edge' ), array_column( terms( render_content( $content ) ), 'text' ) );
		$this->assertSame( array( 'content delivery network', 'edge functions', 'TTFB' ), array_column( terms( render_slug( 'caching-basics' ) ), 'text' ) );

		// Status changed directly in the database (e.g. by an import tool), caches cleaned the WordPress way.
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'private' ), array( 'ID' => $ttfb ) );
		clean_post_cache( $ttfb );
		$this->assertSame( array( 'CDN', 'edge' ), array_column( terms( render_content( $content ) ), 'text' ) );

		wp_trash_post( $cdn );
		$html = render_content( $content );
		$all  = terms( $html );
		$this->assertCount( 1, $all, $html );
		$this->assertSame( 'edge', $all[0]['text'] );
		$this->assertStringContainsString( 'CDN / TTFB / cache /', squish( wp_strip_all_tags( $html ) ) );

		$legacy = terms( render_slug( 'caching-basics' ) );
		$this->assertCount( 1, $legacy, 'Only the edge function shortcode still has a published term' );
		$this->assertSame( 'edge functions', $legacy[0]['text'] );
	}

	public function test_queries_do_not_grow_with_the_number_of_marks(): void {
		$ids   = array( term_id( 'cache' ), term_id( 'cdn' ), term_id( 'ttfb' ) );
		$build = static function ( int $n ) use ( $ids ) {
			$parts = array();
			for ( $i = 0; $i < $n; $i++ ) {
				$parts[] = paragraph( 'Item ' . $i . ': ' . mark( $ids[ $i % 3 ], 'term ' . $i ) );
			}
			return implode( "\n\n", $parts );
		};
		$small = $build( 3 );
		$large = $build( 60 );
		render_content( $small );

		wp_cache_flush();
		$few = $this->count_queries( fn() => render_content( $small ) );
		wp_cache_flush();
		$many = $this->count_queries( fn() => render_content( $large ) );

		$this->assertCount( 60, terms( $many['result'] ) );
		$this->assertLessThanOrEqual( $few['count'] + 3, $many['count'], "60 marks: {$many['count']} queries, 3 marks: {$few['count']}\n" . implode( "\n", $many['queries'] ) );
	}

	public function test_index_rest_and_helpers_keep_working(): void {
		$html = render_slug( 'glossary-terms', 'page' );
		$this->assertStringContainsString( 'acme-glossary-index', $html );
		$this->assertStringContainsString( 'Time to first byte', $html );
		$this->assertStringContainsString( 'How long the browser waits for the first byte of the response.', $html );
		$this->assertStringNotContainsString( 'Cache stampede', $html );
		$this->assertStringContainsString( '<dt id="glossary-cdn">', do_shortcode( '[glossary_index letters="no"]' ) );

		$res = $this->rest( 'GET', '/acme-glossary/v1/terms', array( 'search' => 'cach' ) );
		$this->assertSame( 200, $res->get_status() );
		$titles = array_column( $res->get_data(), 'title' );
		$this->assertSame( array( 'Cache', 'Cache invalidation' ), $titles );

		$this->assertSame( term_id( 'cdn' ), acme_glossary_find_term( 'CDN' )->ID );
		$this->assertSame( term_id( 'ttfb' ), acme_glossary_find_term( 'time to first byte' )->ID );
		$this->assertNull( acme_glossary_find_term( 'cache-stampede' ) );
		$this->assertSame( 'A store of copies of data, so that future requests are served faster.', acme_glossary_get_definition( term_id( 'cache' ) ) );
	}

	public function test_no_php_warnings(): void {
		$errors = array();
		set_error_handler(
			static function ( $no, $str, $file, $line ) use ( &$errors ) {
				if ( false !== strpos( $file, 'acme-glossary' ) ) {
					$errors[] = "$str in $file:$line";
				}
				return false;
			}
		);
		try {
			render_slug( 'caching-basics' );
			render_slug( 'cdn-setup' );
			render_slug( 'block-shortcode' );
			render_content( paragraph( '<span class="acme-glossary-term">no id</span> <span class="acme-glossary-term" data-term-id="">empty</span>' ) );
		} finally {
			restore_error_handler();
		}
		$this->assertSame( array(), $errors );
	}
}
