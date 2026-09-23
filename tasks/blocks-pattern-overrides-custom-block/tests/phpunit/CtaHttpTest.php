<?php
/**
 * Requests against the real (Playground) web server + WP-CLI inventory.
 */

use function WPSB\CTA\ctas;
use function WPSB\CTA\split_url;

class CtaHttpTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	public function test_served_pages_show_per_instance_overrides(): void {
		$res = $this->http( 'GET', '/pricing/' );
		$this->assertSame( 200, $res['status'] );
		$all = ctas( $res['body'] );
		$this->assertCount( 4, $all );
		$this->assertSame(
			array( 'Get the pricing digest', 'Only the heading changed', 'Join our newsletter', 'Talk to sales' ),
			array_column( $all, 'heading' )
		);
		[ $url ] = split_url( $all[0]['link']['href'] ?? '' );
		$this->assertSame( 'https://news.example.com/pricing', $url );

		$res = $this->http( 'GET', '/webinars/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertSame( array( 'Webinar replay: patterns', 'Live webinar: blocks in depth' ), array_column( ctas( $res['body'] ), 'heading' ) );
	}

	public function test_served_legacy_and_standalone_ctas(): void {
		$res = $this->http( 'GET', '/legacy-cta/' );
		$this->assertSame( 200, $res['status'] );
		$all = ctas( $res['body'], true );
		$this->assertCount( 1, $all );
		$this->assertSame( 'Try Acme free', $all[0]['heading'] );

		$res = $this->http( 'GET', '/standalone-ctas/' );
		$this->assertSame( array( 'Talk to sales', 'Download the report' ), array_column( ctas( $res['body'] ), 'heading' ) );
	}

	public function test_inventory_cli_lists_stored_ctas(): void {
		$out = $this->wp_cli( 'acme-cta list --format=json' );
		$this->assertSame( 0, $out['exit'], $out['stderr'] );
		$rows = json_decode( $out['stdout'], true );
		$this->assertIsArray( $rows, $out['stdout'] . $out['stderr'] );

		$by_heading = array();
		foreach ( $rows as $row ) {
			$by_heading[ $row['heading'] ] = $row;
		}
		$this->assertSame( 'https://news.example.com/subscribe', $by_heading['Join our newsletter']['url'] ?? null );
		$this->assertSame( 'wp_block', $by_heading['Join our newsletter']['post_type'] ?? null );
		$this->assertSame( 'newsletter', $by_heading['Join our newsletter']['campaign'] ?? null );
		$this->assertSame( 'Save my seat', $by_heading['Live webinar: blocks in depth']['button_text'] ?? null );
		$this->assertSame( 'dark', $by_heading['Live webinar: blocks in depth']['variant'] ?? null );
		$this->assertSame( 'https://app.example.net/signup', $by_heading['Try Acme free']['url'] ?? null );
		$this->assertSame( 'secondary', $by_heading['Try Acme free']['variant'] ?? null );
		$this->assertSame( 'https://files.example.org/report.pdf', $by_heading['Download the report']['url'] ?? null );

		$out  = $this->wp_cli( 'acme-cta list --post_type=wp_block --format=json' );
		$rows = json_decode( $out['stdout'], true );
		$this->assertCount( 3, $rows );
	}
}
