<?php
/**
 * v1 collection: bare array, pagination headers + Link, filters (incl. legacy statuses), ordering.
 */

use function WPSB\Bookings\count_rows;
use function WPSB\Bookings\link_header;
use function WPSB\Bookings\links;
use function WPSB\Bookings\room;
use function WPSB\Bookings\seeded;
use function WPSB\Bookings\user;
use const WPSB\Bookings\NS;

class BookingsCollectionTest extends WPSB\TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->login_as( user( 'mira' ) );
	}

	private function ids( array $query ): array {
		$res = $this->rest( 'GET', NS . '/bookings', $query + array( 'per_page' => 100 ) );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		return array_map( static fn( $b ) => $b['id'], $res->get_data() );
	}

	private function db_ids( string $where, string $order = 'start_date ASC, id ASC' ): array {
		global $wpdb;
		$t = WPSB\Bookings\table();
		return array_map( 'intval', $wpdb->get_col( "SELECT id FROM {$t} WHERE {$where} ORDER BY {$order}" ) );
	}

	public function test_pagination_headers_and_links(): void {
		$total = count_rows();
		$res   = $this->rest( 'GET', NS . '/bookings' );
		$this->assertSame( 200, $res->get_status() );
		$data = $res->get_data();
		$this->assertIsArray( $data );
		$this->assertTrue( array_is_list( $data ), 'the collection is a JSON array' );
		$this->assertCount( 10, $data, 'default per_page is 10' );
		$h = $res->get_headers();
		$this->assertSame( (string) $total, (string) $h['X-WP-Total'] );
		$this->assertSame( (string) (int) ceil( $total / 10 ), (string) $h['X-WP-TotalPages'] );
		$l = links( link_header( $res ) );
		$this->assertArrayHasKey( 'next', $l );
		$this->assertArrayNotHasKey( 'prev', $l );

		$res = $this->rest( 'GET', NS . '/bookings', array( 'per_page' => 4, 'page' => 2, 'room' => room( 'lake-suite' ), 'order' => 'desc' ) );
		$lake = count_rows( 'room_id = ' . room( 'lake-suite' ) );
		$this->assertSame( (string) $lake, (string) $res->get_headers()['X-WP-Total'] );
		$this->assertSame( (string) (int) ceil( $lake / 4 ), (string) $res->get_headers()['X-WP-TotalPages'] );
		$this->assertCount( 4, $res->get_data() );
		$l = links( link_header( $res ) );
		$this->assertArrayHasKey( 'prev', $l );
		$this->assertArrayHasKey( 'next', $l );
		foreach ( array( 'prev' => 1, 'next' => 3 ) as $rel => $page ) {
			$parts = wp_parse_url( $l[ $rel ] );
			$this->assertStringContainsString( 'acme-bookings/v1/bookings', $l[ $rel ] );
			parse_str( $parts['query'] ?? '', $q );
			if ( isset( $q['rest_route'] ) ) {
				unset( $q['rest_route'] );
			}
			$this->assertSame( (string) $page, (string) ( $q['page'] ?? '1' ), "$rel page" );
			$this->assertSame( '4', (string) ( $q['per_page'] ?? '' ), "$rel keeps per_page" );
			$this->assertSame( (string) room( 'lake-suite' ), (string) ( $q['room'] ?? '' ), "$rel keeps room" );
			$this->assertSame( 'desc', (string) ( $q['order'] ?? '' ), "$rel keeps order" );
		}

		// Last page: prev only.
		$pages = (int) ceil( $lake / 4 );
		$res   = $this->rest( 'GET', NS . '/bookings', array( 'per_page' => 4, 'page' => $pages, 'room' => room( 'lake-suite' ) ) );
		$l     = links( link_header( $res ) );
		$this->assertArrayHasKey( 'prev', $l );
		$this->assertArrayNotHasKey( 'next', $l );
		$this->assertCount( $lake - 4 * ( $pages - 1 ), $res->get_data() );

		// Pages don't overlap and cover everything.
		$seen = array();
		for ( $p = 1; $p <= (int) ceil( $total / 7 ); $p++ ) {
			$seen = array_merge( $seen, $this->ids( array( 'per_page' => 7, 'page' => $p ) ) );
		}
		$this->assertCount( $total, array_unique( $seen ) );

		// Past the last page.
		$this->assertSame( 400, $this->rest( 'GET', NS . '/bookings', array( 'page' => 50 ) )->get_status() );
	}

	public function test_status_filter_includes_legacy_spellings(): void {
		$this->assertEqualsCanonicalizing( $this->db_ids( "status IN ('confirmed','approved')" ), $this->ids( array( 'status' => 'confirmed' ) ) );
		$this->assertContains( (int) seeded( 'seed:legacy-approved' )->id, $this->ids( array( 'status' => 'confirmed' ) ) );
		$this->assertEqualsCanonicalizing( $this->db_ids( "status IN ('cancelled','canceled')" ), $this->ids( array( 'status' => 'cancelled' ) ) );
		$this->assertContains( (int) seeded( 'seed:legacy-canceled' )->id, $this->ids( array( 'status' => 'cancelled' ) ) );
		$this->assertEqualsCanonicalizing( $this->db_ids( "status = 'pending'" ), $this->ids( array( 'status' => 'pending' ) ) );
	}

	public function test_room_customer_and_date_filters(): void {
		$garden = room( 'garden-room' );
		$alice  = user( 'alice' );
		$this->assertEqualsCanonicalizing( $this->db_ids( "room_id = $garden" ), $this->ids( array( 'room' => $garden ) ) );
		$this->assertEqualsCanonicalizing( $this->db_ids( "customer_id = $alice" ), $this->ids( array( 'customer' => $alice ) ) );
		$this->assertEqualsCanonicalizing( $this->db_ids( "customer_id = $alice AND room_id = $garden" ), $this->ids( array( 'customer' => $alice, 'room' => $garden ) ) );

		// after = ends after (strictly), before = starts before (strictly).
		$after = $this->ids( array( 'after' => '2026-11-04T11:00:00+00:00' ) );
		$this->assertNotContains( (int) seeded( 'Anniversary trip' )->id, $after );
		$this->assertEqualsCanonicalizing( $this->db_ids( "end_date > '2026-11-04 11:00:00'" ), $after );

		$before = $this->ids( array( 'before' => '2026-11-05T14:00:00+00:00' ) );
		$this->assertEqualsCanonicalizing( array( (int) seeded( 'Anniversary trip' )->id ), $before );

		// A window, given with an offset.
		$window = $this->ids( array( 'after' => '2026-11-05T00:00:00+01:00', 'before' => '2026-12-01T01:00:00+01:00' ) );
		$this->assertEqualsCanonicalizing( $this->db_ids( "end_date > '2026-11-04 23:00:00' AND start_date < '2026-12-01 00:00:00'" ), $window );
		$this->assertContains( (int) seeded( 'Late arrival' )->id, $window );
		$this->assertContains( (int) seeded( 'seed:garden-late-november' )->id, $window );
	}

	public function test_ordering(): void {
		$this->assertSame( $this->db_ids( '1=1' ), $this->ids( array() ), 'default: start ascending' );
		$this->assertSame( $this->db_ids( '1=1', 'start_date DESC, id DESC' ), $this->ids( array( 'order' => 'desc' ) ) );
		$this->assertSame( $this->db_ids( '1=1', 'id DESC' ), $this->ids( array( 'orderby' => 'id', 'order' => 'desc' ) ) );
		$this->assertSame( $this->db_ids( '1=1', 'id ASC' ), $this->ids( array( 'orderby' => 'id' ) ) );
		$by_created = $this->ids( array( 'orderby' => 'created' ) );
		$this->assertSame( $this->db_ids( '1=1', 'created_at ASC, id ASC' ), $by_created );
	}
}
