<?php
/**
 * Pages served by the real (Playground) server.
 */

use function WPSB\Glossary\term_id;
use function WPSB\Glossary\terms;

class GlossaryHttpTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	public function test_served_classic_post_has_accessible_tooltips(): void {
		$res = $this->http( 'GET', '/caching-basics/' );
		$this->assertSame( 200, $res['status'] );
		$all = terms( $res['body'] );
		$this->assertCount( 4, $all );
		$this->assertSame( (string) term_id( 'cdn' ), $all[1]['attrs']['data-term-id'] ?? null );
		$this->assertSame( 'Content delivery network: servers around the world that deliver files from a location near the visitor.', $all[1]['tooltip']['text'] ?? null );
		$this->assertStringNotContainsString( '[glossary', $res['body'] );
	}

	public function test_served_glossary_pages_still_work(): void {
		$res = $this->http( 'GET', '/glossary-terms/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringContainsString( 'acme-glossary-index', $res['body'] );
		$this->assertStringContainsString( 'Stale-while-revalidate', $res['body'] );

		$res = $this->http( 'GET', '/glossary/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringContainsString( 'Origin server', $res['body'] );

		$res = $this->http( 'GET', '/wp-json/acme-glossary/v1/terms?search=cdn' );
		$this->assertSame( 200, $res['status'] );
		$this->assertContains( 'CDN', array_column( (array) $res['json'], 'title' ) );
		$this->assertNotContains( 'Internal CDN', array_column( (array) $res['json'], 'title' ) );
	}
}
