<?php
/**
 * Real HTTP requests against the site: logged-out access, cookie + nonce auth, application
 * passwords, and the public availability endpoint the widget uses.
 */

use function WPSB\Bookings\room;
use function WPSB\Bookings\seeded;
use function WPSB\Bookings\user;
use function WPSB\Bookings\row;
use function WPSB\Bookings\count_rows;

class BookingsHttpTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private array $created   = array();
	private array $app_pass  = array();

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
		$created = WP_Application_Passwords::create_new_application_password( $uid, array( 'name' => 'TravelDesk ' . wp_generate_password( 6, false ) ) );
		$this->assertIsArray( $created, is_wp_error( $created ) ? $created->get_error_message() : 'app password' );
		$this->app_pass[] = array( $uid, $created[1]['uuid'] );
		return array( 'Authorization' => 'Basic ' . base64_encode( $login . ':' . $created[0] ) );
	}

	public function test_logged_out_visitors_see_no_bookings(): void {
		$res = $this->http( 'GET', '/wp-json/acme-bookings/v1/bookings' );
		$this->assertSame( 401, $res['status'], substr( $res['body'], 0, 500 ) );
		$this->assertStringNotContainsString( 'Anniversary', $res['body'] );
		$this->assertStringNotContainsString( '"customer', $res['body'] );

		$res = $this->http( 'GET', '/wp-json/acme-bookings/v1/bookings/' . seeded( 'Anniversary trip' )->id );
		$this->assertSame( 401, $res['status'] );
		$this->assertStringNotContainsString( 'Anniversary', $res['body'] );

		$before = count_rows();
		$res    = $this->http(
			'POST',
			'/wp-json/acme-bookings/v1/bookings',
			array(
				'json' => true,
				'body' => array( 'room' => room( 'lake-suite' ), 'start' => '2027-12-01T14:00:00Z', 'end' => '2027-12-02T11:00:00Z' ),
			)
		);
		$this->assertSame( 401, $res['status'] );
		$this->assertSame( $before, count_rows() );
	}

	public function test_cookie_auth_requires_the_rest_nonce(): void {
		$alice = user( 'alice' );
		$login = $this->http_login( $alice );

		// Cookies without the nonce: treated as logged out.
		$res = $this->http( 'GET', '/wp-json/acme-bookings/v1/bookings', array( 'login' => $login ) );
		$this->assertSame( 401, $res['status'] );

		$res = $this->http( 'GET', '/wp-json/acme-bookings/v1/bookings?per_page=50', array( 'login' => $login, 'rest_nonce' => true ) );
		$this->assertSame( 200, $res['status'], substr( $res['body'], 0, 500 ) );
		$this->assertSame( (string) count_rows( "customer_id = $alice" ), $res['headers']['x-wp-total'] ?? null );
		foreach ( $res['json'] as $b ) {
			$this->assertSame( $alice, $b['customer'] );
		}

		$res = $this->http(
			'POST',
			'/wp-json/acme-bookings/v1/bookings',
			array(
				'login'      => $login,
				'rest_nonce' => true,
				'json'       => true,
				'body'       => array( 'room' => room( 'lake-suite' ), 'start' => '2027-12-01T14:00:00Z', 'end' => '2027-12-03T11:00:00Z', 'guests' => 2 ),
			)
		);
		$this->assertSame( 201, $res['status'], substr( $res['body'], 0, 500 ) );
		$this->created[] = $res['json']['id'];
		$this->assertSame( $alice, (int) row( $res['json']['id'] )->customer_id );
		$this->assertSame( 'pending', row( $res['json']['id'] )->status );
		$this->assertEquals( 491, $res['json']['total'] );

		// Same slot again: conflict.
		$res = $this->http(
			'POST',
			'/wp-json/acme-bookings/v1/bookings',
			array(
				'login'      => $login,
				'rest_nonce' => true,
				'json'       => true,
				'body'       => array( 'room' => room( 'lake-suite' ), 'start' => '2027-12-02T14:00:00Z', 'end' => '2027-12-04T11:00:00Z' ),
			)
		);
		$this->assertSame( 409, $res['status'] );
		$this->assertSame( 'acme_bookings_conflict', $res['json']['code'] ?? null );

		// Validation errors over HTTP.
		$res = $this->http(
			'POST',
			'/wp-json/acme-bookings/v1/bookings',
			array(
				'login'      => $login,
				'rest_nonce' => true,
				'json'       => true,
				'body'       => array( 'room' => room( 'lake-suite' ), 'start' => '2027-12-10T14:00:00Z', 'end' => '2027-12-12T11:00:00Z', 'guests' => 9 ),
			)
		);
		$this->assertSame( 400, $res['status'] );
		$this->assertSame( 'rest_invalid_param', $res['json']['code'] ?? null );
		$this->assertArrayHasKey( 'guests', $res['json']['data']['params'] ?? array() );
	}

	public function test_application_passwords(): void {
		$bob = user( 'bob' );
		$auth = $this->basic_auth( 'bob' );
		$res  = $this->http( 'GET', '/wp-json/acme-bookings/v1/bookings?per_page=100', array( 'headers' => $auth ) );
		$this->assertSame( 200, $res['status'], substr( $res['body'], 0, 500 ) );
		$this->assertCount( count_rows( "customer_id = $bob" ), $res['json'] );
		$this->assertSame( 403, $this->http( 'GET', '/wp-json/acme-bookings/v1/bookings/' . seeded( 'Anniversary trip' )->id, array( 'headers' => $auth ) )['status'] );
		$this->assertSame( 403, $this->http( 'DELETE', '/wp-json/acme-bookings/v1/bookings/' . seeded( 'Late arrival' )->id, array( 'headers' => $auth ) )['status'] );

		$mira = $this->basic_auth( 'mira' );
		$res  = $this->http( 'GET', '/wp-json/acme-bookings/v1/bookings?per_page=5&page=2&status=confirmed', array( 'headers' => $mira ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertSame( (string) count_rows( "status IN ('confirmed','approved')" ), $res['headers']['x-wp-total'] ?? null );
		$this->assertMatchesRegularExpression( '/rel="?next"?/', $res['headers']['link'] ?? '' );
		$this->assertMatchesRegularExpression( '/rel="?prev"?/', $res['headers']['link'] ?? '' );
		$this->assertStringContainsString( 'status=confirmed', $res['headers']['link'] ?? '' );

		$res = $this->http( 'GET', '/wp-json/acme-bookings/v1/bookings/' . seeded( 'Anniversary trip' )->id . '?context=edit&_embed=1', array( 'headers' => $mira ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertSame( 'VIP - upgrade if possible', $res['json']['admin_notes'] ?? null );
		$this->assertSame( 'Garden Room', $res['json']['_embedded']['room'][0]['title']['rendered'] ?? null );
		$this->assertSame( 'Alice Traveller', $res['json']['_embedded']['customer'][0]['name'] ?? null );

		// Wrong password: rejected.
		$res = $this->http( 'GET', '/wp-json/acme-bookings/v1/bookings', array( 'headers' => array( 'Authorization' => 'Basic ' . base64_encode( 'mira:not-the-password' ) ) ) );
		$this->assertContains( $res['status'], array( 401, 403 ) );
	}

	public function test_public_availability_endpoint_is_unchanged(): void {
		$garden = room( 'garden-room' );
		$res    = $this->http( 'GET', "/wp-json/acme-bookings/v1/availability?room={$garden}&from=2026-11-01&to=2026-11-30" );
		$this->assertSame( 200, $res['status'], substr( $res['body'], 0, 500 ) );
		$this->assertSame(
			array(
				'room'     => $garden,
				'from'     => '2026-11-01',
				'to'       => '2026-11-30',
				'capacity' => 2,
				'booked'   => array(
					array( 'start' => '2026-11-02', 'end' => '2026-11-04' ),
					array( 'start' => '2026-11-20', 'end' => '2026-11-23' ),
				),
			),
			$res['json']
		);
		$this->assertStringNotContainsString( 'alice', strtolower( $res['body'] ) );
		$this->assertSame( 404, $this->http( 'GET', '/wp-json/acme-bookings/v1/availability?room=' . room( 'tower-room' ) )['status'] );
	}
}
