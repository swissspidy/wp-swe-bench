<?php
/**
 * Served pages: the theme's theme.json block styles and variations apply.
 */

use function WPSB\ContentBlocks\blocks;

class HttpTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	public function test_theme_variations_are_applied_on_served_pages(): void {
		$res = $this->http( 'GET', '/notices-v2/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertMatchesRegularExpression(
			'/\.wp-block-acme-notice-box\.is-style-outlined[^{]*\{[^}]*border-color:\s*var\(--wp--preset--color--umber\)/',
			$res['body'],
			"The theme's Outlined variation styles must be output for outlined notices"
		);
		$this->assertMatchesRegularExpression( '/\.wp-block-acme-notice-box\)?\s*\{[^}]*background-color:\s*var\(--wp--preset--color--sand\)/', $res['body'], "theme.json block defaults missing" );

		$res = $this->http( 'GET', '/acme-in-numbers/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertMatchesRegularExpression(
			'/\.wp-block-acme-stat\.is-style-card[^{]*\{[^}]*background-color:\s*var\(--wp--preset--color--mist\)/',
			$res['body'],
			"The theme's Card variation styles must be output for card statistics"
		);
		$all = blocks( $res['body'], 'wp-block-acme-stat' );
		$this->assertCount( 3, $all );
		$this->assertContains( 'has-x-large-font-size', $all[1]['classes'] );
	}

	public function test_served_1_0_notices_use_standard_markup(): void {
		$res = $this->http( 'GET', '/notices-v1/' );
		$this->assertSame( 200, $res['status'] );
		$all = blocks( $res['body'], 'wp-block-acme-notice-box' );
		$this->assertCount( 3, $all );
		$this->assertArrayNotHasKey( 'background-color', $all[0]['style'] );
		$this->assertContains( 'has-sand-background-color', $all[1]['classes'] );
		$this->assertStringNotContainsString( 'acme-notice--', $res['body'] );
		$this->assertStringNotContainsString( '#fff8e1', strtolower( $res['body'] ) );
	}
}
