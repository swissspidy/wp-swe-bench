<?php
/**
 * Existing per-site behaviour that must keep working (pass-to-pass).
 */

use WPSB\Directory\NetworkTestCase;
use function WPSB\Directory\listings_table;
use function WPSB\Directory\rows;

class SiteBehaviourTest extends NetworkTestCase {

	private function names_in( string $html ): array {
		preg_match_all( '#<h3 class="acme-directory__name">(?:<a [^>]*>)?([^<]+)#', $html, $m );
		return array_map( 'html_entity_decode', $m[1] );
	}

	public function test_directory_pages_render_each_sites_own_listings(): void {
		$r = $this->http( 'GET', '/directory/' );
		$this->assertSame( 200, $r['status'] );
		$this->assertSame( array( 'Bakery Brot', 'Cafe Central', 'Corner Books', 'Garden Centre Green', 'Harbour Fish Bar', 'Plumbing Pros' ), $this->names_in( $r['body'] ) );

		$r = $this->http( 'GET', '/south/directory/' );
		$this->assertSame( 200, $r['status'] );
		$this->assertSame( array( 'South Beach Surf School', 'Dune Kiosk', 'Lighthouse Diner', 'Tidepool Tours' ), $this->names_in( $r['body'] ) );
	}

	public function test_rest_api_is_per_site(): void {
		$r = $this->http( 'GET', '/wp-json/acme-directory/v1/listings?category=food' );
		$this->assertSame( 200, $r['status'] );
		$this->assertSame( array( 'Bakery Brot', 'Cafe Central', 'Harbour Fish Bar' ), array_column( $r['json'], 'name' ) );

		$r = $this->http( 'GET', '/south/wp-json/acme-directory/v1/categories' );
		$this->assertSame( 200, $r['status'] );
		$counts = array_column( $r['json'], 'count', 'slug' );
		$this->assertSame( array( 'beaches' => 1, 'food' => 2, 'general' => 0, 'network-partners' => 1 ), $counts );
	}

	public function test_members_can_suggest_listings_on_their_site(): void {
		$login = $this->http_login( 1 );
		$r     = $this->http(
			'POST',
			'/south/wp-json/acme-directory/v1/listings',
			array(
				'login'      => $login,
				'rest_nonce' => true,
				'json'       => true,
				'body'       => array(
					'name'     => 'Rockpool Cafe',
					'category' => 'food',
				),
			)
		);
		$this->assertSame( 201, $r['status'], $r['body'] );
		$this->assertSame( 'food', $r['json']['category'] );
		$this->assertContains( 'Rockpool Cafe', array_column( rows( listings_table( 3 ) ), 'name' ) );
		$this->assertNotContains( 'Rockpool Cafe', array_column( rows( listings_table( 1 ) ), 'name' ) );
	}

	public function test_cleanup_command_runs_per_site(): void {
		global $wpdb;
		$wpdb->update( listings_table( 3 ), array( 'expires_at' => '2020-01-01 00:00:00' ), array( 'name' => 'Dune Kiosk' ) );
		$r = $this->cli( 'acme-directory cleanup --url=http://127.0.0.1:9400/south/' );
		$this->assertStringContainsString( 'Expired 1 listing(s)', $r['stdout'] );
		$status = array_column( rows( listings_table( 3 ) ), 'status', 'name' );
		$this->assertSame( 'expired', $status['Dune Kiosk'] );
		$this->assertSame( 'published', array_column( rows( listings_table( 1 ) ), 'status', 'name' )['Cafe Central'] );
	}
}
