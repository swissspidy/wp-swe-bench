<?php
/**
 * v2 and v1 deprecation over real HTTP (application passwords, cookie + nonce).
 */

use function WPSB\Bookings\links;
use function WPSB\Bookings\room;
use function WPSB\Bookings\row;
use function WPSB\Bookings\seeded;
use function WPSB\Bookings\user;

class BookingsV2HttpTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private array $created  = array();
	private array $app_pass = array();

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->created as $id ) {
			$wpdb->delete( WPSB\Bookings\table(), array( 'id' => $id ) );
		}
		foreach ( $this->app_pass as $pair ) {
			WP_Application_Passwords::delete_application_password( $pair[0], $pair[1] );
		}
		parent::tearDown();
	}

	private function basic_auth( string $login ): array {
		$uid     = user( $login );
		$created = WP_Application_Passwords::create_new_application_password( $uid, array( 'name' => 'Partner ' . wp_generate_password( 6, false ) ) );
		$this->assertIsArray( $created );
		$this->app_pass[] = array( $uid, $created[1]['uuid'] );
		return array( 'Authorization' => 'Basic ' . base64_encode( $login . ':' . $created[0] ) );
	}

	public function test_v1_responses_announce_the_deprecation(): void {
		$auth = $this->basic_auth( 'mira' );
		$id   = (int) seeded( 'Anniversary trip' )->id;
		foreach ( array(
			'/wp-json/acme-bookings/v1/bookings?per_page=5' => '/acme-bookings/v2/bookings',
			"/wp-json/acme-bookings/v1/bookings/$id"         => "/acme-bookings/v2/bookings/$id",
		) as $v1 => $v2 ) {
			$res = $this->http( 'GET', $v1, array( 'headers' => $auth ) );
			$this->assertSame( 200, $res['status'], $v1 );
			$this->assertSame( '@1788220800', $res['headers']['deprecation'] ?? null, $v1 );
			$l = links( $res['headers']['link'] ?? '' );
			$this->assertArrayHasKey( 'successor-version', $l, $v1 . ': ' . ( $res['headers']['link'] ?? '' ) );
			$this->assertStringEndsWith( $v2, rtrim( strtok( $l['successor-version'], '?' ), '/' ) );
			$first = str_contains( $v1, '?' ) ? $res['json'][0] : $res['json'];
			$this->assertArrayHasKey( 'start', $first, 'v1 shape unchanged' );
			$this->assertArrayNotHasKey( 'period', $first );
		}
		// Pagination links survive next to the successor link.
		$res = $this->http( 'GET', '/wp-json/acme-bookings/v1/bookings?per_page=5', array( 'headers' => $auth ) );
		$this->assertArrayHasKey( 'next', links( $res['headers']['link'] ?? '' ) );

		$res = $this->http(
			'POST',
			'/wp-json/acme-bookings/v1/bookings',
			array(
				'headers' => $auth,
				'json'    => true,
				'body'    => array( 'room' => room( 'attic-single' ), 'start' => '2027-06-01T14:00:00Z', 'end' => '2027-06-02T11:00:00Z' ),
			)
		);
		$this->assertSame( 201, $res['status'], substr( $res['body'], 0, 400 ) );
		$this->created[] = $res['json']['id'];
		$this->assertSame( '@1788220800', $res['headers']['deprecation'] ?? null );
		$this->assertSame( '2027-06-01T14:00:00+00:00', $res['json']['start'] );

		$res = $this->http( 'GET', "/wp-json/acme-bookings/v2/bookings/$id", array( 'headers' => $auth ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertArrayNotHasKey( 'deprecation', $res['headers'] );
		$res = $this->http( 'GET', '/wp-json/acme-bookings/v1/availability?room=' . room( 'garden-room' ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertArrayNotHasKey( 'deprecation', $res['headers'] );
	}

	public function test_v2_over_http_with_cookie_and_application_password(): void {
		$alice = user( 'alice' );
		$login = $this->http_login( $alice );
		$res   = $this->http(
			'POST',
			'/wp-json/acme-bookings/v2/bookings',
			array(
				'login'      => $login,
				'rest_nonce' => true,
				'json'       => true,
				'body'       => array(
					'room'   => room( 'lake-suite' ),
					'guests' => 3,
					'period' => array( 'start' => '2027-07-10T15:00:00', 'end' => '2027-07-12T10:00:00', 'timezone' => 'Europe/Zurich' ),
				),
			)
		);
		$this->assertSame( 201, $res['status'], substr( $res['body'], 0, 500 ) );
		$id              = $res['json']['id'];
		$this->created[] = $id;
		$this->assertSame( array( 'start' => '2027-07-10T15:00:00+02:00', 'end' => '2027-07-12T10:00:00+02:00', 'timezone' => 'Europe/Zurich' ), $res['json']['period'] );
		$this->assertSame( array( 'amount' => 49100, 'currency' => 'EUR' ), $res['json']['total'] );
		$this->assertSame( '2027-07-10 13:00:00', row( $id )->start_date );
		$this->assertSame( 'pending', row( $id )->status );

		$bob = $this->basic_auth( 'bob' );
		$this->assertSame( 403, $this->http( 'GET', "/wp-json/acme-bookings/v2/bookings/$id", array( 'headers' => $bob ) )['status'] );
		$this->assertSame( 401, $this->http( 'GET', "/wp-json/acme-bookings/v2/bookings/$id" )['status'] );

		$mira = $this->basic_auth( 'mira' );
		$res  = $this->http( 'GET', "/wp-json/acme-bookings/v1/bookings/$id", array( 'headers' => $mira ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertSame( '2027-07-10T13:00:00+00:00', $res['json']['start'] );
		$this->assertEquals( 491, $res['json']['total'] );

		$res = $this->http( 'OPTIONS', '/wp-json/acme-bookings/v2/bookings', array( 'headers' => $mira ) );
		$this->assertSame( 'object', $res['json']['schema']['properties']['period']['type'] ?? null );
	}
}
