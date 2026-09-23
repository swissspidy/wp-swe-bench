<?php
/**
 * v2: period with time zones, money in minor units, shared storage with v1, schema, permissions.
 */

use function WPSB\Bookings\count_rows;
use function WPSB\Bookings\error_mentions;
use function WPSB\Bookings\link_header;
use function WPSB\Bookings\links;
use function WPSB\Bookings\room;
use function WPSB\Bookings\row;
use function WPSB\Bookings\seeded;
use function WPSB\Bookings\user;
use const WPSB\Bookings\NS;
use const WPSB\Bookings\NS2;

class BookingsV2Test extends WPSB\TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->login_as( user( 'mira' ) );
	}

	private function create_v2( array $body, array $query = array() ): WP_REST_Response {
		return $this->rest(
			'POST',
			NS2 . '/bookings',
			$query,
			array_merge(
				array(
					'room'     => room( 'garden-room' ),
					'customer' => user( 'carol' ),
					'guests'   => 2,
				),
				$body
			)
		);
	}

	private function assert_period_invalid( WP_REST_Response $res ): void {
		$data = $this->rest_data( $res );
		$this->assertSame( 400, $res->get_status(), wp_json_encode( $data ) );
		$this->assertTrue( error_mentions( $data, 'period' ), 'error must name period: ' . wp_json_encode( $data ) );
	}

	public function test_v2_shape_for_existing_bookings(): void {
		$row = seeded( 'Anniversary trip' );
		$res = $this->rest( 'GET', NS2 . '/bookings/' . $row->id );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$b = $this->rest_data( $res );
		$this->assertEqualsCanonicalizing(
			array( 'id', 'room', 'customer', 'period', 'status', 'guests', 'notes', 'total', 'created', '_links' ),
			array_keys( $b )
		);
		$this->assertSame(
			array(
				'start'    => '2026-11-02T14:00:00+00:00',
				'end'      => '2026-11-04T11:00:00+00:00',
				'timezone' => 'UTC',
			),
			$b['period']
		);
		$this->assertSame( array( 'amount' => 25800, 'currency' => 'EUR' ), $b['total'] );
		$this->assertSame( 'confirmed', $b['status'] );
		$this->assertMatchesRegularExpression( '/\+00:00$/', $b['created'] );
		$this->assertStringEndsWith( '/acme-bookings/v2/bookings/' . $row->id, $b['_links']['self'][0]['href'] );
		$this->assertStringEndsWith( '/acme-bookings/v2/bookings', $b['_links']['collection'][0]['href'] );
		$this->assertStringEndsWith( '/wp/v2/rooms/' . room( 'garden-room' ), $b['_links']['room'][0]['href'] );

		// Legacy row without a stored total: 1718.50 EUR.
		$legacy = $this->rest_data( $this->rest( 'GET', NS2 . '/bookings/' . seeded( 'seed:legacy-approved' )->id ) );
		$this->assertSame( array( 'amount' => 171850, 'currency' => 'EUR' ), $legacy['total'] );
		$this->assertSame( 'confirmed', $legacy['status'] );

		$edit = $this->rest_data( $this->rest( 'GET', NS2 . '/bookings/' . $row->id, array( 'context' => 'edit' ) ) );
		$this->assertSame( 'VIP - upgrade if possible', $edit['admin_notes'] ?? null );
		$embed = $this->rest_data( $this->rest( 'GET', NS2 . '/bookings/' . $row->id, array( 'context' => 'embed' ) ) );
		unset( $embed['_links'] );
		$this->assertEqualsCanonicalizing( array( 'id', 'room', 'customer', 'period', 'status' ), array_keys( $embed ) );

		$embedded = $this->rest_data( $res, true );
		$this->assertSame( 'Garden Room', $embedded['_embedded']['room'][0]['title']['rendered'] ?? null );
		$this->assertSame( 'Alice Traveller', $embedded['_embedded']['customer'][0]['name'] ?? null );
	}

	public function test_bookings_without_own_timezone_follow_the_site_timezone(): void {
		update_option( 'timezone_string', 'America/New_York' );
		update_option( 'gmt_offset', '' );
		$b = $this->rest_data( $this->rest( 'GET', NS2 . '/bookings/' . seeded( 'Anniversary trip' )->id ) );
		$this->assertSame(
			array(
				'start'    => '2026-11-02T09:00:00-05:00',
				'end'      => '2026-11-04T06:00:00-05:00',
				'timezone' => 'America/New_York',
			),
			$b['period']
		);
		// v1 stays UTC.
		$v1 = $this->rest_data( $this->rest( 'GET', NS . '/bookings/' . seeded( 'Anniversary trip' )->id ) );
		$this->assertSame( '2026-11-02T14:00:00+00:00', $v1['start'] );

		// Created through v1: no timezone of its own either.
		$res = $this->rest( 'POST', NS . '/bookings', array(), array( 'room' => room( 'attic-single' ), 'start' => '2027-06-01T14:00:00Z', 'end' => '2027-06-02T11:00:00Z' ) );
		$this->assertSame( 201, $res->get_status() );
		$id = $res->get_data()['id'];
		update_option( 'timezone_string', 'Asia/Tokyo' );
		$v2 = $this->rest_data( $this->rest( 'GET', NS2 . "/bookings/$id" ) );
		$this->assertSame( array( 'start' => '2027-06-01T23:00:00+09:00', 'end' => '2027-06-02T20:00:00+09:00', 'timezone' => 'Asia/Tokyo' ), $v2['period'] );
	}

	public function test_create_with_timezone_and_dst(): void {
		$res = $this->create_v2(
			array(
				'period' => array(
					'start'    => '2027-03-01T15:00:00',
					'end'      => '2027-03-03T10:00:00',
					'timezone' => 'Europe/Zurich',
				),
			)
		);
		$this->assertSame( 201, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$b = $res->get_data();
		$this->assertSame( array( 'start' => '2027-03-01T15:00:00+01:00', 'end' => '2027-03-03T10:00:00+01:00', 'timezone' => 'Europe/Zurich' ), $b['period'] );
		$this->assertSame( array( 'amount' => 25800, 'currency' => 'EUR' ), $b['total'] );
		$this->assertStringEndsWith( '/acme-bookings/v2/bookings/' . $b['id'], $res->get_headers()['Location'] ?? '' );
		$stored = row( $b['id'] );
		$this->assertSame( '2027-03-01 14:00:00', $stored->start_date, 'stored in UTC like every other booking' );
		$this->assertSame( '2027-03-03 09:00:00', $stored->end_date );

		// Same booking through v1: UTC, same moments.
		$v1 = $this->rest_data( $this->rest( 'GET', NS . '/bookings/' . $b['id'] ) );
		$this->assertSame( '2027-03-01T14:00:00+00:00', $v1['start'] );
		$this->assertSame( '2027-03-03T09:00:00+00:00', $v1['end'] );
		$this->assertEquals( 258, $v1['total'] );

		// Summer time, offsets given explicitly (in another zone than the booking's).
		$res = $this->create_v2(
			array(
				'period' => array(
					'start'    => '2027-07-01T13:00:00Z',
					'end'      => '2027-07-02T04:00:00-05:00',
					'timezone' => 'Europe/Zurich',
				),
			)
		);
		$this->assertSame( 201, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$this->assertSame( array( 'start' => '2027-07-01T15:00:00+02:00', 'end' => '2027-07-02T11:00:00+02:00', 'timezone' => 'Europe/Zurich' ), $res->get_data()['period'] );

		// No timezone sent: the site's.
		update_option( 'timezone_string', 'Europe/Lisbon' );
		$res = $this->create_v2( array( 'period' => array( 'start' => '2027-08-01T15:00:00', 'end' => '2027-08-02T10:00:00' ) ) );
		$this->assertSame( 201, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$this->assertSame( array( 'start' => '2027-08-01T15:00:00+01:00', 'end' => '2027-08-02T10:00:00+01:00', 'timezone' => 'Europe/Lisbon' ), $res->get_data()['period'] );
		$this->assertSame( '2027-08-01 14:00:00', row( $res->get_data()['id'] )->start_date );
	}

	public function test_partial_period_updates_and_v1_edits_keep_the_timezone(): void {
		$res = $this->create_v2( array( 'period' => array( 'start' => '2027-03-01T15:00:00', 'end' => '2027-03-03T10:00:00', 'timezone' => 'Europe/Zurich' ) ) );
		$this->assertSame( 201, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$id = $res->get_data()['id'];

		// Only the timezone: same moments, other presentation.
		$res = $this->rest( 'PATCH', NS2 . "/bookings/$id", array(), array( 'period' => array( 'timezone' => 'Asia/Tokyo' ) ) );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$this->assertSame( array( 'start' => '2027-03-01T23:00:00+09:00', 'end' => '2027-03-03T18:00:00+09:00', 'timezone' => 'Asia/Tokyo' ), $res->get_data()['period'] );
		$this->assertSame( '2027-03-01 14:00:00', row( $id )->start_date );

		// Only the start, as local time: interpreted in the booking's timezone.
		$res = $this->rest( 'PATCH', NS2 . "/bookings/$id", array(), array( 'period' => array( 'start' => '2027-03-02T09:00:00' ) ) );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$this->assertSame( '2027-03-02 00:00:00', row( $id )->start_date );
		$this->assertSame( '2027-03-03 09:00:00', row( $id )->end_date );
		$this->assertSame( 'Asia/Tokyo', $res->get_data()['period']['timezone'] );

		// Editing through v1 keeps the timezone.
		$res = $this->rest( 'PATCH', NS . "/bookings/$id", array(), array( 'notes' => 'late check-in', 'end' => '2027-03-03T10:00:00Z' ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( '2027-03-03T10:00:00+00:00', $res->get_data()['end'] );
		$v2 = $this->rest_data( $this->rest( 'GET', NS2 . "/bookings/$id" ) );
		$this->assertSame( array( 'start' => '2027-03-02T09:00:00+09:00', 'end' => '2027-03-03T19:00:00+09:00', 'timezone' => 'Asia/Tokyo' ), $v2['period'] );
		$this->assertSame( 'late check-in', $v2['notes'] );
	}

	public function test_money_uses_the_currency_minor_unit(): void {
		$id = seeded( 'Anniversary trip' )->id; // 258.00
		update_option( 'acme_bookings_currency', 'JPY' );
		$b = $this->rest_data( $this->rest( 'GET', NS2 . "/bookings/$id" ) );
		$this->assertSame( array( 'amount' => 258, 'currency' => 'JPY' ), $b['total'] );
		update_option( 'acme_bookings_currency', 'KWD' );
		$b = $this->rest_data( $this->rest( 'GET', NS2 . "/bookings/$id" ) );
		$this->assertSame( array( 'amount' => 258000, 'currency' => 'KWD' ), $b['total'] );
		update_option( 'acme_bookings_currency', 'CHF' );
		$b = $this->rest_data( $this->rest( 'GET', NS2 . '/bookings/' . seeded( 'seed:legacy-approved' )->id ) );
		$this->assertSame( array( 'amount' => 171850, 'currency' => 'CHF' ), $b['total'] );
		$this->assertIsInt( $b['total']['amount'] );
		// v1 unchanged: a number in major units.
		$v1 = $this->rest_data( $this->rest( 'GET', NS . '/bookings/' . seeded( 'seed:legacy-approved' )->id ) );
		$this->assertEquals( 1718.5, $v1['total'] );
		$this->assertSame( 'CHF', $v1['currency'] );
	}

	public function test_period_validation(): void {
		$before = count_rows();
		$this->assert_period_invalid( $this->create_v2( array() ) );
		$this->assert_period_invalid( $this->create_v2( array( 'period' => array( 'start' => '2027-03-01T15:00:00' ) ) ) );
		$this->assert_period_invalid( $this->create_v2( array( 'period' => array( 'start' => 'soon', 'end' => '2027-03-03T10:00:00' ) ) ) );
		$this->assert_period_invalid( $this->create_v2( array( 'period' => array( 'start' => '2027-03-03T15:00:00', 'end' => '2027-03-03T10:00:00' ) ) ) );
		$this->assert_period_invalid( $this->create_v2( array( 'period' => array( 'start' => '2027-03-01T15:00:00', 'end' => '2027-03-03T10:00:00', 'timezone' => 'Mars/Olympus_Mons' ) ) ) );
		$this->assert_period_invalid( $this->create_v2( array( 'period' => array( 'start' => '2027-03-01T15:00:00', 'end' => '2027-03-03T10:00:00', 'timezone' => 42 ) ) ) );
		$this->assertSame( $before, count_rows() );

		$id = (int) seeded( 'Anniversary trip' )->id;
		$this->assert_period_invalid( $this->rest( 'PATCH', NS2 . "/bookings/$id", array(), array( 'period' => array( 'end' => '2026-11-01T10:00:00Z' ) ) ) );
		$this->assert_period_invalid( $this->rest( 'PATCH', NS2 . "/bookings/$id", array(), array( 'period' => array( 'timezone' => 'Europe/Nowhere' ) ) ) );
		$this->assertSame( '2026-11-04 11:00:00', row( $id )->end_date );

		// Other fields are validated as in v1.
		$res  = $this->create_v2( array( 'guests' => 3, 'period' => array( 'start' => '2027-03-01T15:00:00', 'end' => '2027-03-03T10:00:00' ) ) );
		$this->assertSame( 400, $res->get_status() );
		$this->assertTrue( error_mentions( $this->rest_data( $res ), 'guests' ) );
	}

	public function test_overlaps_across_versions(): void {
		// Garden Room 2026-11-20 14:00Z → 11-23 11:00Z (created in 1.x).
		$res = $this->create_v2( array( 'period' => array( 'start' => '2026-11-22T15:00:00', 'end' => '2026-11-24T10:00:00', 'timezone' => 'Europe/Zurich' ) ) );
		$this->assertSame( 409, $res->get_status() );
		$this->assertSame( 'acme_bookings_conflict', $this->rest_data( $res )['code'] ?? null );
		// Touching in local time: 12:00+01:00 == 11:00Z.
		$res = $this->create_v2( array( 'period' => array( 'start' => '2026-11-23T12:00:00', 'end' => '2026-11-24T10:00:00', 'timezone' => 'Europe/Zurich' ) ) );
		$this->assertSame( 201, $res->get_status(), wp_json_encode( $res->get_data() ) );
		// And v1 sees the v2 booking.
		$res = $this->rest( 'POST', NS . '/bookings', array(), array( 'room' => room( 'garden-room' ), 'start' => '2026-11-23T20:00:00Z', 'end' => '2026-11-25T11:00:00Z' ) );
		$this->assertSame( 409, $res->get_status() );
	}

	public function test_permissions_and_collection_match_v1(): void {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->rest( 'GET', NS2 . '/bookings' )->get_status() );
		$this->assertSame( 401, $this->rest( 'GET', NS2 . '/bookings/' . seeded( 'Anniversary trip' )->id )->get_status() );

		$alice = $this->login_as( user( 'alice' ) );
		$list  = $this->rest( 'GET', NS2 . '/bookings', array( 'per_page' => 100 ) );
		$this->assertSame( 200, $list->get_status() );
		$this->assertCount( count_rows( "customer_id = $alice" ), $list->get_data() );
		$this->assertSame( 403, $this->rest( 'GET', NS2 . '/bookings/' . seeded( 'Late arrival' )->id )->get_status() );
		$this->assertSame( 403, $this->rest( 'GET', NS2 . '/bookings', array( 'context' => 'edit' ) )->get_status() );
		$own = (int) seeded( 'Anniversary trip' )->id;
		$this->assertSame( 403, $this->rest( 'PATCH', NS2 . "/bookings/$own", array(), array( 'period' => array( 'timezone' => 'Europe/Zurich' ) ) )->get_status() );
		$this->assertSame( 403, $this->rest( 'DELETE', NS2 . "/bookings/$own" )->get_status() );
		$res = $this->rest( 'PATCH', NS2 . "/bookings/$own", array(), array( 'status' => 'cancelled' ) );
		$this->assertSame( 200, $res->get_status() );
		$res = $this->rest( 'POST', NS2 . '/bookings', array(), array( 'room' => room( 'lake-suite' ), 'period' => array( 'start' => '2027-09-01T15:00:00', 'end' => '2027-09-02T10:00:00' ) ) );
		$this->assertSame( 201, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$this->assertSame( $alice, $res->get_data()['customer'] );
		$this->assertSame( 'pending', $res->get_data()['status'] );
		$this->assertSame( 403, $this->rest( 'POST', NS2 . '/bookings', array(), array( 'room' => room( 'lake-suite' ), 'status' => 'confirmed', 'period' => array( 'start' => '2027-09-05T15:00:00', 'end' => '2027-09-06T10:00:00' ) ) )->get_status() );

		$this->login_as( user( 'mira' ) );
		$res = $this->rest( 'GET', NS2 . '/bookings', array( 'per_page' => 5, 'page' => 2, 'status' => 'cancelled' ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( (string) count_rows( "status IN ('cancelled','canceled')" ), (string) $res->get_headers()['X-WP-Total'] );
		$l = links( link_header( $res ) );
		$this->assertStringContainsString( 'acme-bookings/v2/bookings', $l['prev'] ?? '' );
		$this->assertStringContainsString( 'status=cancelled', $l['prev'] ?? '' );
		$ids = array_map( static fn( $b ) => $b['id'], $this->rest( 'GET', NS2 . '/bookings', array( 'per_page' => 100, 'after' => '2026-11-04T12:00:00+01:00', 'before' => '2026-11-30T00:00:00Z' ) )->get_data() );
		$this->assertNotContains( (int) seeded( 'Anniversary trip' )->id, $ids );
		$this->assertContains( (int) seeded( 'Late arrival' )->id, $ids );

		$res = $this->rest( 'DELETE', NS2 . '/bookings/' . seeded( 'Late arrival' )->id );
		$this->assertSame( 200, $res->get_status() );
		$this->assertTrue( $res->get_data()['deleted'] );
		$this->assertArrayHasKey( 'period', $res->get_data()['previous'] );
	}

	public function test_options_describe_each_version(): void {
		$schema = $this->rest( 'OPTIONS', NS2 . '/bookings' )->get_data()['schema'] ?? array();
		$this->assertSame( 'booking', $schema['title'] ?? null );
		$props = $schema['properties'];
		$this->assertEqualsCanonicalizing( array( 'id', 'room', 'customer', 'period', 'status', 'guests', 'notes', 'admin_notes', 'total', 'created' ), array_keys( $props ) );
		$this->assertSame( 'object', $props['period']['type'] );
		$this->assertSame( 'string', $props['period']['properties']['start']['type'] );
		$this->assertSame( 'date-time', $props['period']['properties']['start']['format'] ?? null );
		$this->assertSame( 'date-time', $props['period']['properties']['end']['format'] ?? null );
		$this->assertSame( 'string', $props['period']['properties']['timezone']['type'] );
		$this->assertSame( 'object', $props['total']['type'] );
		$this->assertTrue( $props['total']['readonly'] ?? false );
		$this->assertSame( 'integer', $props['total']['properties']['amount']['type'] );
		$this->assertSame( 'string', $props['total']['properties']['currency']['type'] );
		$this->assertContains( 'embed', $props['period']['context'] );

		$v1 = $this->rest( 'OPTIONS', NS . '/bookings' )->get_data()['schema']['properties'];
		$this->assertArrayHasKey( 'start', $v1 );
		$this->assertArrayNotHasKey( 'period', $v1 );
		$this->assertSame( 'number', $v1['total']['type'] );
	}
}
