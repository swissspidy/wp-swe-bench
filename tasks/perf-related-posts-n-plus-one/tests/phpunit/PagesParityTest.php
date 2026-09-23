<?php
/**
 * Front-end pages (real requests through the theme) show exactly the lists of 2.3.1.
 */

use function WPSB\Related\fixture;
use function WPSB\Related\sections;

class PagesParityTest extends WPSB\Related\HttpTestCase {

	public function test_pages_show_the_same_lists(): void {
		foreach ( fixture( 'pages' ) as $path => $expected ) {
			$r = $this->http( 'GET', $path );
			$this->assertSame( 200, $r['status'], $path );
			$this->assertSame( $expected, sections( $r['body'] ), "Related lists on $path changed" );
		}
	}
}
