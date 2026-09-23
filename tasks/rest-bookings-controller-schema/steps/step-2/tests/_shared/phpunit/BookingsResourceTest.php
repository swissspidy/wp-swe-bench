<?php
/**
 * v1 booking resource: schema (OPTIONS), field shapes, contexts, legacy rows, links/_embed.
 */

use function WPSB\Bookings\room;
use function WPSB\Bookings\seeded;
use function WPSB\Bookings\user;
use const WPSB\Bookings\NS;

class BookingsResourceTest extends WPSB\TestCase {

	private function manager(): int {
		return $this->login_as( user( 'mira' ) );
	}

	public function test_options_describes_the_booking_schema(): void {
		$this->manager();
		$res = $this->rest( 'OPTIONS', NS . '/bookings' );
		$this->assertSame( 200, $res->get_status() );
		$data = $res->get_data();
		$this->assertArrayHasKey( 'schema', $data, 'OPTIONS must include the resource schema' );
		$schema = $data['schema'];
		$this->assertSame( 'booking', $schema['title'] );
		$props = $schema['properties'];
		$this->assertEqualsCanonicalizing(
			array( 'id', 'room', 'customer', 'start', 'end', 'status', 'guests', 'notes', 'admin_notes', 'total', 'currency', 'created' ),
			array_keys( $props )
		);
		$this->assertSame( 'integer', $props['id']['type'] );
		$this->assertTrue( $props['id']['readonly'] ?? false );
		$this->assertSame( 'integer', $props['room']['type'] );
		$this->assertSame( 'integer', $props['customer']['type'] );
		foreach ( array( 'start', 'end', 'created' ) as $f ) {
			$this->assertSame( 'string', $props[ $f ]['type'], $f );
			$this->assertSame( 'date-time', $props[ $f ]['format'] ?? null, "$f format" );
		}
		$this->assertEqualsCanonicalizing( array( 'pending', 'confirmed', 'cancelled' ), $props['status']['enum'] );
		$this->assertSame( 'pending', $props['status']['default'] ?? null );
		$this->assertSame( 'integer', $props['guests']['type'] );
		$this->assertSame( 1, $props['guests']['minimum'] ?? null );
		$this->assertSame( 'number', $props['total']['type'] );
		foreach ( array( 'total', 'currency', 'created' ) as $f ) {
			$this->assertTrue( $props[ $f ]['readonly'] ?? false, "$f must be read-only" );
		}
		$this->assertSame( array( 'edit' ), array_values( $props['admin_notes']['context'] ) );
		$this->assertEqualsCanonicalizing( array( 'view', 'edit', 'embed' ), $props['start']['context'] );
		$this->assertNotContains( 'embed', $props['guests']['context'] );

		// Arguments per method.
		$post = null;
		$get  = null;
		foreach ( $data['endpoints'] as $ep ) {
			if ( in_array( 'POST', $ep['methods'], true ) ) {
				$post = $ep['args'];
			}
			if ( in_array( 'GET', $ep['methods'], true ) ) {
				$get = $ep['args'];
			}
		}
		$this->assertNotNull( $post );
		foreach ( array( 'room', 'start', 'end' ) as $f ) {
			$this->assertTrue( $post[ $f ]['required'] ?? false, "POST $f must be required" );
		}
		$this->assertFalse( $post['notes']['required'] ?? false );
		$this->assertArrayNotHasKey( 'id', $post, 'read-only fields are not arguments' );
		foreach ( array( 'page', 'per_page', 'room', 'customer', 'status', 'after', 'before', 'orderby', 'order' ) as $arg ) {
			$this->assertArrayHasKey( $arg, $get, "GET arg $arg" );
		}

		// Item route too.
		$item = $this->rest( 'OPTIONS', NS . '/bookings/' . seeded( 'Anniversary trip' )->id );
		$this->assertSame( 'booking', $item->get_data()['schema']['title'] ?? null );
	}

	public function test_booking_shape_in_view_context(): void {
		$this->manager();
		$row = seeded( 'Anniversary trip' );
		$res = $this->rest( 'GET', NS . '/bookings/' . $row->id );
		$this->assertSame( 200, $res->get_status() );
		$b = $this->rest_data( $res );
		$this->assertSame( (int) $row->id, $b['id'] );
		$this->assertSame( room( 'garden-room' ), $b['room'] );
		$this->assertSame( user( 'alice' ), $b['customer'] );
		$this->assertSame( '2026-11-02T14:00:00+00:00', $b['start'] );
		$this->assertSame( '2026-11-04T11:00:00+00:00', $b['end'] );
		$this->assertSame( 'confirmed', $b['status'] );
		$this->assertSame( 2, $b['guests'] );
		$this->assertSame( 'Anniversary trip', $b['notes'] );
		$this->assertEquals( 258, $b['total'] );
		$this->assertIsNotString( $b['total'] );
		$this->assertSame( 'EUR', $b['currency'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $b['created'] );
		$this->assertArrayNotHasKey( 'admin_notes', $b, 'admin_notes is edit-context only' );
		$this->assertEqualsCanonicalizing(
			array( 'id', 'room', 'customer', 'start', 'end', 'status', 'guests', 'notes', 'total', 'currency', 'created', '_links' ),
			array_keys( $b )
		);
	}

	public function test_edit_and_embed_contexts(): void {
		$this->manager();
		$row  = seeded( 'Anniversary trip' );
		$edit = $this->rest_data( $this->rest( 'GET', NS . '/bookings/' . $row->id, array( 'context' => 'edit' ) ) );
		$this->assertSame( 'VIP - upgrade if possible', $edit['admin_notes'] ?? null );
		$this->assertSame( 'Anniversary trip', $edit['notes'] );

		$embed = $this->rest_data( $this->rest( 'GET', NS . '/bookings/' . $row->id, array( 'context' => 'embed' ) ) );
		unset( $embed['_links'] );
		$this->assertEqualsCanonicalizing( array( 'id', 'room', 'customer', 'start', 'end', 'status' ), array_keys( $embed ) );

		$bad = $this->rest( 'GET', NS . '/bookings/' . $row->id, array( 'context' => 'nonsense' ) );
		$this->assertSame( 400, $bad->get_status() );
	}

	public function test_legacy_rows_are_normalized(): void {
		$this->manager();
		// 1.0: status "approved", no stored total (priced with the room's rate: 7 nights x 245.50).
		$approved = $this->rest_data( $this->rest( 'GET', NS . '/bookings/' . seeded( 'seed:legacy-approved' )->id ) );
		$this->assertSame( 'confirmed', $approved['status'] );
		$this->assertEquals( 1718.5, $approved['total'] );
		$this->assertSame( 3, $approved['guests'] );
		$this->assertSame( room( 'lake-suite' ), $approved['room'] );

		$canceled = $this->rest_data( $this->rest( 'GET', NS . '/bookings/' . seeded( 'seed:legacy-canceled' )->id ) );
		$this->assertSame( 'cancelled', $canceled['status'] );

		// Every stored booking only exposes documented statuses.
		$all = $this->rest_data( $this->rest( 'GET', NS . '/bookings', array( 'per_page' => 100 ) ) );
		$this->assertCount( WPSB\Bookings\count_rows(), $all );
		foreach ( $all as $b ) {
			$this->assertContains( $b['status'], array( 'pending', 'confirmed', 'cancelled' ) );
			$this->assertIsInt( $b['id'] );
			$this->assertIsInt( $b['room'] );
			$this->assertIsInt( $b['guests'] );
		}
	}

	public function test_links_and_embedding(): void {
		$this->manager();
		$row = seeded( 'Anniversary trip' );
		$res = $this->rest( 'GET', NS . '/bookings/' . $row->id );
		$b   = $this->rest_data( $res );
		$this->assertStringEndsWith( '/acme-bookings/v1/bookings/' . $row->id, $b['_links']['self'][0]['href'] );
		$this->assertStringEndsWith( '/acme-bookings/v1/bookings', $b['_links']['collection'][0]['href'] );
		$this->assertStringEndsWith( '/wp/v2/rooms/' . room( 'garden-room' ), $b['_links']['room'][0]['href'] );
		$this->assertTrue( $b['_links']['room'][0]['embeddable'] ?? false );
		$this->assertStringEndsWith( '/wp/v2/users/' . user( 'alice' ), $b['_links']['customer'][0]['href'] );
		$this->assertTrue( $b['_links']['customer'][0]['embeddable'] ?? false );

		$embedded = $this->rest_data( $res, true );
		$this->assertSame( 'Garden Room', $embedded['_embedded']['room'][0]['title']['rendered'] ?? null );
		$this->assertSame( 'Alice Traveller', $embedded['_embedded']['customer'][0]['name'] ?? null );

		// A customer embedding their own booking gets their own user and the room.
		$this->login_as( user( 'alice' ) );
		$own = $this->rest_data( $this->rest( 'GET', NS . '/bookings/' . $row->id ), true );
		$this->assertSame( 'Garden Room', $own['_embedded']['room'][0]['title']['rendered'] ?? null );
		$this->assertSame( 'Alice Traveller', $own['_embedded']['customer'][0]['name'] ?? null );

		// Collections embed too.
		$this->manager();
		$list = $this->rest_data( $this->rest( 'GET', NS . '/bookings', array( 'room' => room( 'lake-suite' ), 'per_page' => 3 ) ), true );
		$this->assertCount( 3, $list );
		foreach ( $list as $item ) {
			$this->assertSame( 'Lake Suite', $item['_embedded']['room'][0]['title']['rendered'] ?? null );
		}
	}

	public function test_missing_booking_is_404(): void {
		$this->manager();
		$this->assertSame( 404, $this->rest( 'GET', NS . '/bookings/987654' )->get_status() );
		$this->assertSame( 404, $this->rest( 'PATCH', NS . '/bookings/987654', array(), array( 'notes' => 'x' ) )->get_status() );
		$this->assertSame( 404, $this->rest( 'DELETE', NS . '/bookings/987654' )->get_status() );
		$this->login_as( user( 'alice' ) );
		$this->assertSame( 404, $this->rest( 'GET', NS . '/bookings/987654' )->get_status() );
	}
}
