<?php
/**
 * v1 permission matrix: guests, customers (own/others), managers (role + administrator).
 */

use function WPSB\Bookings\room;
use function WPSB\Bookings\seeded;
use function WPSB\Bookings\user;
use function WPSB\Bookings\row;
use function WPSB\Bookings\count_rows;
use const WPSB\Bookings\NS;

class BookingsPermissionsTest extends WPSB\TestCase {

	private function valid_body( array $extra = array() ): array {
		return $extra + array(
			'room'   => room( 'attic-single' ),
			'start'  => '2027-08-01T14:00:00+00:00',
			'end'    => '2027-08-03T11:00:00+00:00',
			'guests' => 1,
		);
	}

	public function test_guests_get_401_everywhere(): void {
		wp_set_current_user( 0 );
		$id = seeded( 'Anniversary trip' )->id;
		$this->assertSame( 401, $this->rest( 'GET', NS . '/bookings' )->get_status() );
		$this->assertSame( 401, $this->rest( 'GET', NS . '/bookings/' . $id )->get_status() );
		$this->assertSame( 401, $this->rest( 'POST', NS . '/bookings', array(), $this->valid_body() )->get_status() );
		$this->assertSame( 401, $this->rest( 'PATCH', NS . '/bookings/' . $id, array(), array( 'notes' => 'hacked' ) )->get_status() );
		$this->assertSame( 401, $this->rest( 'DELETE', NS . '/bookings/' . $id )->get_status() );
		$this->assertSame( 'Anniversary trip', row( (int) $id )->notes );
	}

	public function test_customers_only_list_their_own_bookings(): void {
		$alice = $this->login_as( user( 'alice' ) );
		$res   = $this->rest( 'GET', NS . '/bookings', array( 'per_page' => 100 ) );
		$this->assertSame( 200, $res->get_status() );
		$items = $res->get_data();
		$this->assertCount( count_rows( "customer_id = $alice" ), $items );
		$this->assertSame( (string) count_rows( "customer_id = $alice" ), (string) $res->get_headers()['X-WP-Total'] );
		foreach ( $items as $b ) {
			$this->assertSame( $alice, $b['customer'] );
		}
		// Filters can't widen what a customer sees.
		$res = $this->rest( 'GET', NS . '/bookings', array( 'customer' => user( 'bob' ), 'per_page' => 100 ) );
		$this->assertContains( $res->get_status(), array( 200, 403 ) );
		if ( 200 === $res->get_status() ) {
			foreach ( $res->get_data() as $b ) {
				$this->assertSame( $alice, $b['customer'], 'customer filter must not reveal other bookings' );
			}
		}
		$res = $this->rest( 'GET', NS . '/bookings', array( 'room' => room( 'garden-room' ), 'per_page' => 100 ) );
		foreach ( $res->get_data() as $b ) {
			$this->assertSame( $alice, $b['customer'] );
		}
		// A customer without bookings sees an empty list.
		$this->login_as( user( 'carol' ) );
		$res = $this->rest( 'GET', NS . '/bookings' );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( array(), $res->get_data() );
		$this->assertSame( '0', (string) $res->get_headers()['X-WP-Total'] );
	}

	public function test_customers_read_only_their_own_booking_and_no_edit_context(): void {
		$this->login_as( user( 'alice' ) );
		$this->assertSame( 200, $this->rest( 'GET', NS . '/bookings/' . seeded( 'Anniversary trip' )->id )->get_status() );
		$this->assertSame( 403, $this->rest( 'GET', NS . '/bookings/' . seeded( 'Late arrival' )->id )->get_status(), "bob's booking" );
		$this->assertSame( 403, $this->rest( 'GET', NS . '/bookings/' . seeded( 'Anniversary trip' )->id, array( 'context' => 'edit' ) )->get_status() );
		$this->assertSame( 403, $this->rest( 'GET', NS . '/bookings', array( 'context' => 'edit' ) )->get_status() );
		// Editors are customers too: the capability matters, not the role.
		$this->login_as( user( 'eddie' ) );
		$this->assertSame( 403, $this->rest( 'GET', NS . '/bookings/' . seeded( 'Anniversary trip' )->id )->get_status() );
		$this->assertSame( array(), $this->rest( 'GET', NS . '/bookings' )->get_data() );
	}

	public function test_customer_create_rules(): void {
		$alice = $this->login_as( user( 'alice' ) );

		$res = $this->rest( 'POST', NS . '/bookings', array(), $this->valid_body( array( 'customer' => user( 'bob' ) ) ) );
		$this->assertSame( 403, $res->get_status(), 'booking for someone else' );
		$res = $this->rest( 'POST', NS . '/bookings', array(), $this->valid_body( array( 'status' => 'confirmed' ) ) );
		$this->assertSame( 403, $res->get_status(), 'self-confirmed booking' );
		$res = $this->rest( 'POST', NS . '/bookings', array(), $this->valid_body( array( 'admin_notes' => 'free upgrade' ) ) );
		$this->assertSame( 403, $res->get_status(), 'admin notes' );
		$this->assertSame( 0, count_rows( "start_date = '2027-08-01 14:00:00'" ) );

		$res = $this->rest( 'POST', NS . '/bookings', array(), $this->valid_body( array( 'notes' => 'Quiet room please', 'customer' => $alice, 'status' => 'pending' ) ) );
		$this->assertSame( 201, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$b = $res->get_data();
		$this->assertSame( $alice, $b['customer'] );
		$this->assertSame( 'pending', $b['status'] );
		$this->assertArrayNotHasKey( 'admin_notes', $b );
		$stored = row( $b['id'] );
		$this->assertSame( '2027-08-01 14:00:00', $stored->start_date );
		$this->assertSame( '2027-08-03 11:00:00', $stored->end_date );
		$this->assertSame( 'pending', $stored->status );
		$this->assertEquals( 158.0, (float) $stored->total, '2 nights x 79' );
		$this->assertEquals( 158, $b['total'] );

		// Defaults: customer = current user, status pending.
		$res = $this->rest( 'POST', NS . '/bookings', array(), $this->valid_body( array( 'start' => '2027-09-01T14:00:00Z', 'end' => '2027-09-02T11:00:00Z' ) ) );
		$this->assertSame( 201, $res->get_status() );
		$this->assertSame( $alice, row( $res->get_data()['id'] )->customer_id + 0 );
	}

	public function test_customer_update_rules(): void {
		$this->login_as( user( 'bob' ) );
		$own   = (int) seeded( 'seed:garden-late-november' )->id;
		$other = (int) seeded( 'Anniversary trip' )->id;

		$this->assertSame( 403, $this->rest( 'PATCH', NS . '/bookings/' . $other, array(), array( 'notes' => 'mine now' ) )->get_status() );
		$this->assertSame( 403, $this->rest( 'PATCH', NS . '/bookings/' . $other, array(), array( 'status' => 'cancelled' ) )->get_status() );
		$this->assertSame( 'confirmed', row( $other )->status );

		foreach ( array(
			array( 'status' => 'confirmed' ),
			array( 'start' => '2026-11-19T14:00:00+00:00' ),
			array( 'room' => room( 'lake-suite' ) ),
			array( 'admin_notes' => 'x' ),
			array( 'customer' => user( 'alice' ) ),
		) as $body ) {
			$res = $this->rest( 'PATCH', NS . '/bookings/' . $own, array(), $body );
			$this->assertSame( 403, $res->get_status(), 'customer must not change ' . wp_json_encode( $body ) );
		}
		$this->assertSame( 'pending', row( $own )->status );
		$this->assertSame( '2026-11-20 14:00:00', row( $own )->start_date );

		$res = $this->rest( 'PATCH', NS . '/bookings/' . $own, array(), array( 'notes' => 'Arriving by train', 'guests' => 1 ) );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$this->assertSame( 'Arriving by train', row( $own )->notes );
		$this->assertSame( 1, (int) row( $own )->guests );

		$res = $this->rest( 'POST', NS . '/bookings/' . $own, array(), array( 'status' => 'cancelled' ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( 'cancelled', $res->get_data()['status'] );
		$this->assertSame( 'cancelled', row( $own )->status );

		// Customers never delete.
		$this->assertSame( 403, $this->rest( 'DELETE', NS . '/bookings/' . $own )->get_status() );
		$this->assertNotNull( row( $own ) );
	}

	public function test_managers_can_do_everything(): void {
		foreach ( array( 'mira', 'admin' ) as $login ) {
			$this->login_as( user( $login ) );
			$all = $this->rest( 'GET', NS . '/bookings', array( 'per_page' => 100 ) );
			$this->assertSame( 200, $all->get_status() );
			$this->assertCount( count_rows(), $all->get_data(), "$login sees every booking" );

			$res = $this->rest(
				'POST',
				NS . '/bookings',
				array( 'context' => 'edit' ),
				array(
					'room'        => room( 'lake-suite' ),
					'customer'    => user( 'carol' ),
					'start'       => '2027-10-01T14:00:00+00:00',
					'end'         => '2027-10-04T11:00:00+00:00',
					'status'      => 'confirmed',
					'guests'      => 4,
					'admin_notes' => 'Phone booking',
				)
			);
			$this->assertSame( 201, $res->get_status(), wp_json_encode( $res->get_data() ) );
			$id = $res->get_data()['id'];
			$this->assertSame( user( 'carol' ), (int) row( $id )->customer_id );
			$this->assertSame( 'confirmed', row( $id )->status );
			$this->assertSame( 'Phone booking', row( $id )->admin_notes );

			$res = $this->rest( 'PUT', NS . '/bookings/' . $id, array( 'context' => 'edit' ), array( 'start' => '2027-10-02T14:00:00+00:00', 'admin_notes' => 'moved' ) );
			$this->assertSame( 200, $res->get_status() );
			$this->assertSame( '2027-10-02 14:00:00', row( $id )->start_date );
			$this->assertSame( 'moved', $res->get_data()['admin_notes'] );

			$res = $this->rest( 'DELETE', NS . '/bookings/' . $id );
			$this->assertSame( 200, $res->get_status() );
			$this->assertTrue( $res->get_data()['deleted'] );
			$this->assertSame( $id, $res->get_data()['previous']['id'] );
			$this->assertNull( row( $id ) );
		}
	}

	public function test_manager_capability_not_role_name(): void {
		// A user who got the capability directly is a manager.
		$uid  = $this->create_user( 'subscriber' );
		$user = new WP_User( $uid );
		$user->add_cap( 'manage_acme_bookings' );
		$this->login_as( $uid );
		$this->assertCount( count_rows(), $this->rest( 'GET', NS . '/bookings', array( 'per_page' => 100 ) )->get_data() );
		$this->assertSame( 200, $this->rest( 'GET', NS . '/bookings/' . seeded( 'Anniversary trip' )->id, array( 'context' => 'edit' ) )->get_status() );
	}
}
