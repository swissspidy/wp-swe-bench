<?php
/**
 * Displayed dates/times (template tag + REST API) in the event's timezone.
 */

use function WPSB\Events\parse_when;
use function WPSB\Events\set_site_timezone;
use function WPSB\Events\when_html;

class DisplayTest extends WPSB\Events\EventsTestCase {

	private function assertWhen( int $id, array $start, ?array $end, bool $all_day = false ): void {
		$when = parse_when( when_html( $id ) );
		$this->assertSame( array( 'datetime' => $start[0], 'text' => $start[1] ), $when['start'], 'start' );
		if ( null === $end ) {
			$this->assertNull( $when['end'], 'no end expected' );
		} else {
			$this->assertSame( array( 'datetime' => $end[0], 'text' => $end[1] ), $when['end'], 'end' );
		}
		$this->assertSame( $all_day, $when['all_day'] );
	}

	public function test_new_york_times_around_dst(): void {
		$spring = $this->event( 'America/New_York', 'Spring', '2026-03-08 01:30', '2026-03-08 03:30' );
		$winter = $this->event( 'America/New_York', 'Winter', '2026-01-15 19:00', '2026-01-15 21:00' );
		$summer = $this->event( 'America/New_York', 'Summer', '2026-07-15 19:00', '2026-07-15 21:00' );
		$night  = $this->event( 'America/New_York', 'Night', '2026-03-07 22:00', '2026-03-08 04:00' );

		$this->assertWhen( $spring, array( '2026-03-08T01:30:00-05:00', 'March 8, 2026 1:30 am' ), array( '2026-03-08T03:30:00-04:00', '3:30 am' ) );
		$this->assertWhen( $winter, array( '2026-01-15T19:00:00-05:00', 'January 15, 2026 7:00 pm' ), array( '2026-01-15T21:00:00-05:00', '9:00 pm' ) );
		$this->assertWhen( $summer, array( '2026-07-15T19:00:00-04:00', 'July 15, 2026 7:00 pm' ), array( '2026-07-15T21:00:00-04:00', '9:00 pm' ) );
		$this->assertWhen( $night, array( '2026-03-07T22:00:00-05:00', 'March 7, 2026 10:00 pm' ), array( '2026-03-08T04:00:00-04:00', 'March 8, 2026 4:00 am' ) );
	}

	public function test_berlin_auckland_and_offset_times(): void {
		$berlin = $this->event( 'Europe/Berlin', 'Berlin', '2026-10-25 01:30', '2026-10-25 04:00' );
		$akl    = $this->event( 'Pacific/Auckland', 'Auckland', '2026-04-05 01:00', '2026-04-05 04:00' );
		$india  = $this->event( '+05:30', 'India', '2026-03-08 10:00', '2026-03-08 11:00' );

		// Displayed while the site is on yet another timezone: each event keeps its own.
		set_site_timezone( 'America/New_York' );
		$this->assertWhen( $berlin, array( '2026-10-25T01:30:00+02:00', 'October 25, 2026 1:30 am' ), array( '2026-10-25T04:00:00+01:00', '4:00 am' ) );
		$this->assertWhen( $akl, array( '2026-04-05T01:00:00+13:00', 'April 5, 2026 1:00 am' ), array( '2026-04-05T04:00:00+12:00', '4:00 am' ) );
		$this->assertWhen( $india, array( '2026-03-08T10:00:00+05:30', 'March 8, 2026 10:00 am' ), array( '2026-03-08T11:00:00+05:30', '11:00 am' ) );
	}

	public function test_all_day_events_west_of_utc_keep_their_date(): void {
		$picnic = $this->event( 'America/New_York', 'Picnic', '2026-07-04', '', true );
		$la     = $this->event( 'America/Los_Angeles', 'Holidays', '2026-12-24', '2026-12-26', true );
		$this->assertWhen( $picnic, array( '2026-07-04', 'July 4, 2026' ), null, true );
		$this->assertWhen( $la, array( '2026-12-24', 'December 24, 2026' ), array( '2026-12-26', 'December 26, 2026' ), true );

		$xmas = $this->event( 'Pacific/Auckland', 'Christmas', '2026-12-25', '', true );
		$this->assertWhen( $xmas, array( '2026-12-25', 'December 25, 2026' ), null, true );
	}

	public function test_events_keep_their_timezone_after_the_site_timezone_changes(): void {
		$id = $this->event( 'America/New_York', 'Board', '2026-01-15 19:00', '2026-01-15 21:00' );
		set_site_timezone( 'Europe/Berlin' );
		$this->assertWhen( $id, array( '2026-01-15T19:00:00-05:00', 'January 15, 2026 7:00 pm' ), array( '2026-01-15T21:00:00-05:00', '9:00 pm' ) );

		$items = $this->rest_events( array( 'upcoming' => false ) );
		$this->assertSame( '2026-01-15T19:00:00-05:00', $items[ $id ]['start'] );
		$this->assertSame( 'America/New_York', $items[ $id ]['timezone'] );
	}

	public function test_rest_items_use_the_event_offset_on_that_date(): void {
		$spring = $this->event( 'America/New_York', 'Spring', '2026-03-08 01:30', '2026-03-08 03:30' );
		$india  = $this->event( '+05:30', 'India', '2026-03-08 10:00', '2026-03-08 11:00' );
		$akl    = $this->event( 'Pacific/Auckland', 'Auckland', '2026-09-27 01:00', '2026-09-27 04:00' );
		$xmas   = $this->event( 'Pacific/Auckland', 'Christmas', '2026-12-25', '2026-12-26', true );

		$items = $this->rest_events( array( 'upcoming' => false ) );
		$this->assertSame( array( '2026-03-08T01:30:00-05:00', '2026-03-08T03:30:00-04:00', 'America/New_York', false ), array( $items[ $spring ]['start'], $items[ $spring ]['end'], $items[ $spring ]['timezone'], $items[ $spring ]['all_day'] ) );
		$this->assertSame( array( '2026-03-08T10:00:00+05:30', '2026-03-08T11:00:00+05:30', '+05:30' ), array( $items[ $india ]['start'], $items[ $india ]['end'], $items[ $india ]['timezone'] ) );
		$this->assertSame( array( '2026-09-27T01:00:00+12:00', '2026-09-27T04:00:00+13:00' ), array( $items[ $akl ]['start'], $items[ $akl ]['end'] ) );
		$this->assertSame( array( '2026-12-25', '2026-12-26', true, 'Pacific/Auckland' ), array( $items[ $xmas ]['start'], $items[ $xmas ]['end'], $items[ $xmas ]['all_day'], $items[ $xmas ]['timezone'] ) );
		$this->assertSame( 'Christmas', $items[ $xmas ]['title'] );
		$this->assertStringContainsString( '/events/', $items[ $xmas ]['link'] );
	}
}
