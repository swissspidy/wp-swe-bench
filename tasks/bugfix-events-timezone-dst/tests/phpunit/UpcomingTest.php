<?php
/**
 * "Upcoming" (events that have not ended yet), with the plugin clock pinned via `acme_events_now`.
 */

use function WPSB\Events\dom;
use function WPSB\Events\seeded;

class UpcomingTest extends WPSB\Events\EventsTestCase {

	public function test_new_york_events_end_exactly_on_time(): void {
		$fall   = $this->event( 'America/New_York', 'Fall', '2026-11-01 00:30', '2026-11-01 03:00' );   // Ends 08:00Z.
		$winter = $this->event( 'America/New_York', 'Winter', '2026-01-15 19:00', '2026-01-15 21:00' ); // Ends 02:00Z Jan 16.
		$summer = $this->event( 'America/New_York', 'Summer', '2026-07-15 19:00', '2026-07-15 21:00' ); // Ends 01:00Z Jul 16.
		$all    = array( $fall, $winter, $summer );

		$this->set_now( '2026-01-16 01:59:00' );
		$this->assertSame( array( $winter, $summer, $fall ), $this->upcoming_ids( $all ) );
		$this->set_now( '2026-01-16 02:01:00' );
		$this->assertSame( array( $summer, $fall ), $this->upcoming_ids( $all ) );

		$this->set_now( '2026-07-16 00:59:00' );
		$this->assertSame( array( $summer, $fall ), $this->upcoming_ids( $all ) );
		$this->set_now( '2026-07-16 01:01:00' );
		$this->assertSame( array( $fall ), $this->upcoming_ids( $all ) );

		$this->set_now( '2026-11-01 07:59:00' );
		$this->assertSame( array( $fall ), $this->upcoming_ids( $all ) );
		$this->set_now( '2026-11-01 08:01:00' );
		$this->assertSame( array(), $this->upcoming_ids( $all ) );
	}

	public function test_berlin_auckland_and_offset_events_end_on_time(): void {
		$berlin_w = $this->event( 'Europe/Berlin', 'Berlin winter', '2026-02-10 18:00', '2026-02-10 20:00' ); // Ends 19:00Z.
		$berlin_s = $this->event( 'Europe/Berlin', 'Berlin summer', '2026-06-10 18:00', '2026-06-10 20:00' ); // Ends 18:00Z.
		$akl_s    = $this->event( 'Pacific/Auckland', 'AKL summer', '2026-02-10 18:00', '2026-02-10 20:00' ); // NZDT: ends 07:00Z.
		$akl_w    = $this->event( 'Pacific/Auckland', 'AKL winter', '2026-06-10 18:00', '2026-06-10 20:00' ); // NZST: ends 08:00Z.
		$india    = $this->event( '+05:30', 'India', '2026-06-10 18:00', '2026-06-10 20:00' );                // Ends 14:30Z.
		$all      = array( $berlin_w, $berlin_s, $akl_s, $akl_w, $india );

		$check = function ( string $now, int $id, bool $expected ) use ( $all ) {
			$this->set_now( $now );
			$this->assertSame( $expected, in_array( $id, $this->upcoming_ids( $all ), true ), "event $id at $now" );
		};
		$check( '2026-02-10 18:59:00', $berlin_w, true );
		$check( '2026-02-10 19:01:00', $berlin_w, false );
		$check( '2026-06-10 17:59:00', $berlin_s, true );
		$check( '2026-06-10 18:01:00', $berlin_s, false );
		$check( '2026-02-10 06:59:00', $akl_s, true );
		$check( '2026-02-10 07:01:00', $akl_s, false );
		$check( '2026-06-10 07:59:00', $akl_w, true );
		$check( '2026-06-10 08:01:00', $akl_w, false );
		$check( '2026-06-10 14:29:00', $india, true );
		$check( '2026-06-10 14:31:00', $india, false );
	}

	public function test_sorted_by_actual_start_across_timezones(): void {
		$ny     = $this->event( 'America/New_York', 'NY', '2026-04-30 16:45', '2026-04-30 18:00' );   // 20:45Z.
		$akl    = $this->event( 'Pacific/Auckland', 'AKL', '2026-05-01 09:00', '2026-05-01 10:00' );  // 21:00Z Apr 30.
		$berlin = $this->event( 'Europe/Berlin', 'Berlin', '2026-04-30 23:30', '2026-05-01 00:30' );  // 21:30Z.
		$india  = $this->event( '+05:30', 'India', '2026-05-01 02:30', '2026-05-01 03:00' );          // 21:00Z Apr 30.
		$this->set_now( '2026-04-01 00:00:00' );
		$ids = $this->upcoming_ids( array( $ny, $akl, $berlin, $india ) );
		$this->assertSame( $ny, $ids[0] );
		$this->assertSame( $berlin, $ids[3] );
		$this->assertEqualsCanonicalizing( array( $akl, $india ), array( $ids[1], $ids[2] ) );

		$rest = array_values( array_intersect( array_keys( $this->rest_events() ), array( $ny, $akl, $berlin, $india ) ) );
		$this->assertSame( $ids, $rest, 'REST upcoming uses the same order' );
	}

	public function test_all_day_events_last_until_midnight_in_their_timezone(): void {
		$xmas   = $this->event( 'Pacific/Auckland', 'Christmas', '2026-12-25', '', true ); // Ends 11:00Z Dec 25.
		$picnic = $this->event( 'America/New_York', 'Picnic', '2026-07-04', '', true );   // Ends 04:00Z Jul 5.
		$all    = array( $xmas, $picnic );

		$this->set_now( '2026-12-25 10:59:00' );
		$this->assertSame( array( $xmas ), $this->upcoming_ids( $all ) );
		$this->set_now( '2026-12-25 11:01:00' );
		$this->assertSame( array(), $this->upcoming_ids( $all ) );
		$this->set_now( '2026-07-05 03:59:00' );
		$this->assertSame( array( $picnic, $xmas ), $this->upcoming_ids( $all ) );
		$this->set_now( '2026-07-05 04:01:00' );
		$this->assertSame( array( $xmas ), $this->upcoming_ids( $all ) );
	}

	public function test_seeded_events_including_the_oldest_ones(): void {
		$nye      = seeded( 'nye-party' );
		$retreat  = seeded( 'winter-retreat' );   // All day Dec 28–30 (New York): ends 05:00Z Dec 31.
		$fall     = seeded( 'fall-back-social' ); // Ends 08:00Z Nov 1.
		$concert  = seeded( 'summer-concert' );   // Ends 02:00Z Jul 19.
		$seeded   = array( $nye, $retreat, $fall, $concert, seeded( 'kickoff-2025' ), seeded( 'founders-day-2019' ) );

		$this->set_now( '2026-07-19 01:59:00' );
		$this->assertSame( array( $concert, $fall, $retreat, $nye ), $this->upcoming_ids( $seeded ) );
		$this->set_now( '2026-07-19 02:01:00' );
		$this->assertSame( array( $fall, $retreat, $nye ), $this->upcoming_ids( $seeded ) );
		$this->set_now( '2026-12-31 04:59:00' );
		$this->assertSame( array( $retreat, $nye ), $this->upcoming_ids( $seeded ) );
		$this->set_now( '2026-12-31 05:01:00' );
		$this->assertSame( array( $nye ), $this->upcoming_ids( $seeded ) );
		$this->set_now( '2027-01-01 06:59:00' );
		$this->assertSame( array( $nye ), $this->upcoming_ids( $seeded ) );
		$this->set_now( '2027-01-01 07:01:00' );
		$this->assertSame( array(), $this->upcoming_ids( $seeded ) );
	}

	public function test_shortcode_lists_upcoming_events_in_order(): void {
		$a = $this->event( 'Europe/Berlin', 'Berlin talk', '2030-03-31 00:30', '2030-03-31 04:00' );
		$b = $this->event( 'America/New_York', 'NY talk', '2030-03-30 20:00', '2030-03-30 21:00' );
		$this->set_now( '2030-03-29 00:00:00' );
		$html = do_shortcode( '[acme_upcoming_events limit="2"]' );
		$x    = dom( $html );
		$ids  = array();
		foreach ( $x->query( "//ul[contains(@class,'acme-upcoming-events')]/li" ) as $li ) {
			$ids[] = (int) $li->getAttribute( 'data-event-id' );
		}
		$this->assertSame( array( $a, $b ), $ids, 'Berlin 23:30Z Mar 30 before New York 00:00Z Mar 31' );
		$this->assertStringContainsString( 'March 31, 2030 12:30 am', $html );
		$this->assertStringContainsString( 'March 30, 2030 8:00 pm', $html );

		$this->set_now( '2031-01-01 00:00:00' );
		$this->assertStringContainsString( 'acme-upcoming-events-empty', do_shortcode( '[acme_upcoming_events]' ) );
	}
}
