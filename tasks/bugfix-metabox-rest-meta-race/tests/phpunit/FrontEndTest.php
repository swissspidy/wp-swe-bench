<?php
/**
 * Product summary on the front end (unchanged behaviour).
 */

use WPSB\ProductFields\Base;

class FrontEndTest extends Base {

	private function summary( string $slug ): string {
		$res = $this->http( 'GET', "/product/$slug/" );
		$this->assertSame( 200, $res['status'] );
		$xp = WPSB\ProductFields\dom( $res['body'] );
		$el = $xp->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' acme-product-summary ')]" )->item( 0 );
		$this->assertNotNull( $el, "No product summary on /product/$slug/" );
		$this->assertStringNotContainsString( 'Supplier contact', $res['body'] );
		return WPSB\ProductFields\text( $el );
	}

	public function test_summary_shows_price_badge_and_stock(): void {
		$this->assertSame( '$129.00 New "Pro" model In stock', $this->summary( 'trail-runner-pro' ) );
		$this->assertSame( '$59.90 Out of stock', $this->summary( 'camp-stove' ) );
		$this->assertSame( '$24.00 Bestseller In stock', $this->summary( 'wool-beanie' ) );
		$this->assertSame( '$189.00 15% off Out of stock', $this->summary( 'rain-jacket' ) );
	}
}
