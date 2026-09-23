<?php
/**
 * v1 validation (400 with field details), date handling, overlap detection (409), notifications.
 */

use function WPSB\Bookings\error_mentions;
use function WPSB\Bookings\room;
use function WPSB\Bookings\seeded;
use function WPSB\Bookings\user;
use function WPSB\Bookings\row;
use function WPSB\Bookings\count_rows;
use const WPSB\Bookings\NS;

class BookingsValidationTest extends WPSB\TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->login_as( user( 'mira' ) );
	}

	private function body( array $extra = array() ): array {
		return array_merge(
			array(
				'room'     => room( 'garden-room' ),
				'customer' => user( 'carol' ),
				'start'    => '2027-06-10T14:00:00+00:00',
				'end'      => '2027-06-12T11:00:00+00:00',
				'guests'   => 2,
			),
			$extra
		);
	}

	/**
	 * @param string|string[] $field Field the error must name (any of them, if several are defensible).
	 */
	private function assert_invalid( array $body, $field, string $method = 'POST', string $path = '/bookings' ): void {
		$fields = (array) $field;
		$field  = implode( '|', $fields );
		$before = count_rows();
		$res    = $this->rest( $method, NS . $path, array(), $body );
		$data   = $this->rest_data( $res );
		$this->assertSame( 400, $res->get_status(), "expected 400 for $field: " . wp_json_encode( $data ) );
		$this->assertContains( $data['code'] ?? '', array( 'rest_invalid_param', 'rest_missing_callback_param' ), wp_json_encode( $data ) );
		$named = array_filter( $fields, static fn( $f ) => error_mentions( $data, $f ) );
		$this->assertNotEmpty( $named, "error must name '$field': " . wp_json_encode( $data ) );
		$this->assertSame( $before, count_rows(), 'nothing may be written' );
	}

	public function test_required_fields(): void {
		foreach ( array( 'room', 'start', 'end' ) as $field ) {
			$body = $this->body();
			unset( $body[ $field ] );
			$this->assert_invalid( $body, $field );
		}
	}

	public function test_invalid_values_are_400_with_field_details(): void {
		$this->assert_invalid( $this->body( array( 'start' => 'next tuesday' ) ), 'start' );
		$this->assert_invalid( $this->body( array( 'end' => '2027-13-45T99:00:00' ) ), 'end' );
		$this->assert_invalid( $this->body( array( 'end' => '2027-06-10T14:00:00+00:00' ) ), array( 'end', 'start' ) );
		$this->assert_invalid( $this->body( array( 'end' => '2027-06-09T11:00:00+00:00' ) ), array( 'end', 'start' ) );
		$this->assert_invalid( $this->body( array( 'room' => room( 'tower-room' ) ) ), 'room' );
		$this->assert_invalid( $this->body( array( 'room' => get_page_by_path( 'book-garden-room' )->ID ) ), 'room' );
		$this->assert_invalid( $this->body( array( 'room' => 99999 ) ), 'room' );
		$this->assert_invalid( $this->body( array( 'customer' => 99999 ) ), 'customer' );
		$this->assert_invalid( $this->body( array( 'status' => 'approved' ) ), 'status' );
		$this->assert_invalid( $this->body( array( 'guests' => 0 ) ), 'guests' );
		$this->assert_invalid( $this->body( array( 'guests' => 3 ) ), 'guests' );
		$this->assert_invalid( $this->body( array( 'guests' => 'two' ) ), 'guests' );
		$this->assert_invalid( $this->body( array( 'room' => room( 'attic-single' ) ) ), array( 'guests', 'room' ) );
	}

	public function test_updates_are_validated_too(): void {
		$id = (int) seeded( 'Anniversary trip' )->id; // Garden Room, 2 guests.
		$this->assert_invalid( array( 'guests' => 5 ), 'guests', 'PATCH', "/bookings/$id" );
		$this->assert_invalid( array( 'room' => room( 'attic-single' ) ), array( 'guests', 'room' ), 'PATCH', "/bookings/$id" );
		$this->assert_invalid( array( 'end' => '2026-11-01T11:00:00+00:00' ), array( 'end', 'start' ), 'PATCH', "/bookings/$id" );
		$this->assert_invalid( array( 'status' => 'approved' ), 'status', 'PATCH', "/bookings/$id" );
		$this->assertSame( '2026-11-04 11:00:00', row( $id )->end_date );
		$this->assertSame( (string) room( 'garden-room' ), (string) row( $id )->room_id );

		// Moving to the (bigger) Lake Suite with 4 guests is fine.
		$res = $this->rest( 'PATCH', NS . "/bookings/$id", array(), array( 'room' => room( 'lake-suite' ), 'guests' => 4, 'start' => '2027-07-01T14:00:00+00:00', 'end' => '2027-07-02T11:00:00+00:00' ) );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$this->assertSame( 4, $res->get_data()['guests'] );
	}

	public function test_dates_with_offsets_are_stored_in_utc(): void {
		$res = $this->rest( 'POST', NS . '/bookings', array(), $this->body( array( 'start' => '2027-06-10T16:00:00+02:00', 'end' => '2027-06-12T06:00:00-05:00' ) ) );
		$this->assertSame( 201, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$b = $res->get_data();
		$this->assertSame( '2027-06-10T14:00:00+00:00', $b['start'] );
		$this->assertSame( '2027-06-12T11:00:00+00:00', $b['end'] );
		$this->assertSame( '2027-06-10 14:00:00', row( $b['id'] )->start_date );
		$this->assertEquals( 258, $b['total'] );
		$this->assertStringEndsWith( '/acme-bookings/v1/bookings/' . $b['id'], $res->get_headers()['Location'] ?? '' );

		// Without an offset: UTC.
		$res = $this->rest( 'POST', NS . '/bookings', array(), $this->body( array( 'start' => '2027-06-20T14:00:00', 'end' => '2027-06-21T11:00:00' ) ) );
		$this->assertSame( 201, $res->get_status() );
		$this->assertSame( '2027-06-20 14:00:00', row( $res->get_data()['id'] )->start_date );
	}

	public function test_overlapping_bookings_conflict(): void {
		// Garden Room is booked 2026-11-20 14:00 → 2026-11-23 11:00 (pending).
		$res = $this->rest( 'POST', NS . '/bookings', array(), $this->body( array( 'start' => '2026-11-21T14:00:00Z', 'end' => '2026-11-22T11:00:00Z' ) ) );
		$this->assertSame( 409, $res->get_status() );
		$this->assertSame( 'acme_bookings_conflict', $this->rest_data( $res )['code'] ?? null );

		$res = $this->rest( 'POST', NS . '/bookings', array(), $this->body( array( 'start' => '2026-11-18T14:00:00Z', 'end' => '2026-11-25T11:00:00Z' ) ) );
		$this->assertSame( 409, $res->get_status(), 'enclosing range' );

		// Touching ranges are fine on both sides.
		$res = $this->rest( 'POST', NS . '/bookings', array(), $this->body( array( 'start' => '2026-11-23T11:00:00Z', 'end' => '2026-11-24T11:00:00Z' ) ) );
		$this->assertSame( 201, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$res = $this->rest( 'POST', NS . '/bookings', array(), $this->body( array( 'start' => '2026-11-19T14:00:00Z', 'end' => '2026-11-20T14:00:00Z' ) ) );
		$this->assertSame( 201, $res->get_status(), wp_json_encode( $res->get_data() ) );

		// Other rooms are independent.
		$res = $this->rest( 'POST', NS . '/bookings', array(), $this->body( array( 'room' => room( 'lake-suite' ), 'start' => '2026-11-21T14:00:00Z', 'end' => '2026-11-22T11:00:00Z' ) ) );
		$this->assertSame( 201, $res->get_status() );
	}

	public function test_cancelled_bookings_never_block_including_legacy_rows(): void {
		// Garden Room 2026-11-10 → 11-12 was cancelled in 1.0 (stored as "canceled").
		$res = $this->rest( 'POST', NS . '/bookings', array(), $this->body( array( 'start' => '2026-11-10T14:00:00Z', 'end' => '2026-11-12T11:00:00Z' ) ) );
		$this->assertSame( 201, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$new = $res->get_data()['id'];

		// A cancelled booking may overlap anything …
		$res = $this->rest( 'POST', NS . '/bookings', array(), $this->body( array( 'start' => '2026-11-10T14:00:00Z', 'end' => '2026-11-11T11:00:00Z', 'status' => 'cancelled' ) ) );
		$this->assertSame( 201, $res->get_status() );
		$cancelled = $res->get_data()['id'];
		// … but re-activating it is checked.
		$res = $this->rest( 'PATCH', NS . "/bookings/$cancelled", array(), array( 'status' => 'confirmed' ) );
		$this->assertSame( 409, $res->get_status() );
		$this->assertSame( 'cancelled', row( $cancelled )->status );

		// The legacy cancelled row itself can't be re-activated either (slot now taken).
		$legacy = (int) seeded( 'seed:legacy-canceled' )->id;
		$this->assertSame( 409, $this->rest( 'PATCH', NS . "/bookings/$legacy", array(), array( 'status' => 'pending' ) )->get_status() );

		// A booking never conflicts with itself.
		$res = $this->rest( 'PATCH', NS . "/bookings/$new", array(), array( 'end' => '2026-11-12T10:00:00Z', 'status' => 'confirmed' ) );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );

		// Moving onto another booking conflicts.
		$res = $this->rest( 'PATCH', NS . "/bookings/$new", array(), array( 'start' => '2026-11-03T14:00:00Z', 'end' => '2026-11-05T11:00:00Z' ) );
		$this->assertSame( 409, $res->get_status() );
		$this->assertSame( '2026-11-10 14:00:00', row( $new )->start_date );
	}

	public function test_notifications_and_actions_still_fire(): void {
		$this->clear_mails();
		$created = array();
		$changed = array();
		add_action( 'acme_bookings_booking_created', $c = static function ( $id ) use ( &$created ) { $created[] = (int) $id; } );
		add_action( 'acme_bookings_status_changed', $s = static function ( $id, $new, $old ) use ( &$changed ) { $changed[] = array( (int) $id, $new, $old ); }, 10, 3 );

		$this->login_as( user( 'alice' ) );
		$res = $this->rest( 'POST', NS . '/bookings', array(), array( 'room' => room( 'lake-suite' ), 'start' => '2027-11-01T14:00:00Z', 'end' => '2027-11-03T11:00:00Z' ) );
		$this->assertSame( 201, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$id = $res->get_data()['id'];
		$this->assertSame( array( $id ), $created );

		$this->login_as( user( 'mira' ) );
		$this->assertSame( 200, $this->rest( 'PATCH', NS . "/bookings/$id", array(), array( 'status' => 'confirmed' ) )->get_status() );
		$this->login_as( user( 'alice' ) );
		$this->assertSame( 200, $this->rest( 'PATCH', NS . "/bookings/$id", array(), array( 'status' => 'cancelled' ) )->get_status() );
		$this->assertSame( array( array( $id, 'confirmed', 'pending' ), array( $id, 'cancelled', 'confirmed' ) ), $changed );

		remove_action( 'acme_bookings_booking_created', $c );
		remove_action( 'acme_bookings_status_changed', $s, 10 );

		$subjects = array_column( $this->mails(), 'subject', null );
		$to       = array_map( static fn( $m ) => is_array( $m['to'] ) ? implode( ',', $m['to'] ) : $m['to'], $this->mails() );
		$this->assertContains( "[Acme Bookings] New booking request #$id", $subjects );
		$this->assertContains( "Your booking #$id is confirmed", $subjects );
		$this->assertContains( "Your booking #$id was cancelled", $subjects );
		$this->assertContains( 'office@example.org', $to );
		$this->assertContains( 'alice@example.org', $to );
	}

	public function test_collection_parameters_are_validated(): void {
		foreach ( array(
			array( 'per_page' => 0 ),
			array( 'per_page' => 101 ),
			array( 'status' => 'approved' ),
			array( 'orderby' => 'price' ),
			array( 'order' => 'sideways' ),
			array( 'after' => 'yesterday' ),
			array( 'room' => 'garden' ),
			array( 'page' => 999 ),
		) as $query ) {
			$res = $this->rest( 'GET', NS . '/bookings', $query );
			$this->assertSame( 400, $res->get_status(), 'expected 400 for ' . wp_json_encode( $query ) );
		}
	}
}
