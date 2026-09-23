<?php
/**
 * Step 1: the classic widget keeps working (pass-to-pass).
 */

use function WPSB\Events\dom;
use function WPSB\Events\listings;

class ClassicWidgetTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	public function test_widgets_render_in_the_sidebars(): void {
		$res = $this->http( 'GET', '/no-events/' );
		$this->assertSame( 200, $res['status'] );
		$xpath = dom( $res['body'] );

		$sidebar = $xpath->query( '//*[@id="acme_upcoming_events-2"]' )->item( 0 );
		$this->assertNotNull( $sidebar, 'Sidebar widget missing' );
		$this->assertSame( 'Workshops', trim( $xpath->query( './/h2[@class="widget-title"]', $sidebar )->item( 0 )->textContent ) );
		$l = listings( '', $xpath, $sidebar );
		$this->assertCount( 1, $l );
		$this->assertSame( array( 'Intro to Gutenberg', 'Block Themes Deep Dive', 'Performance Clinic' ), $l[0]['titles'] );

		$footer = $xpath->query( '//*[@id="acme_upcoming_events-3"]' )->item( 0 );
		$this->assertNotNull( $footer, 'Footer widget missing' );
		$l = listings( '', $xpath, $footer );
		$this->assertSame( 7, $l[0]['items'] );
		$this->assertStringNotContainsString( 'acme-event__venue', $l[0]['inner'] );

		$this->assertNotNull( $xpath->query( '//*[@id="block-2"]' )->item( 0 ), 'Existing block widgets render' );
	}

	public function test_widget_type_is_registered(): void {
		$ids = array_map( static fn( $w ) => $w->id_base, array_values( $GLOBALS['wp_widget_factory']->widgets ) );
		$this->assertContains( 'acme_upcoming_events', $ids );
	}
}
