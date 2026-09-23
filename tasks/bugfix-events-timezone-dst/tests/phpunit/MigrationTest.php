<?php
/**
 * Existing (1.0 / 1.6) events after deploying the fix.
 */

use function WPSB\Events\parse_when;
use function WPSB\Events\raw_meta;
use function WPSB\Events\seeded;
use function WPSB\Events\set_site_timezone;
use function WPSB\Events\stored;
use function WPSB\Events\when_html;

class MigrationTest extends WPSB\Events\EventsTestCase {

	/** Seeded on a New York site. */
	const EXPECTED = array(
		'board-meeting'           => array( '2026-01-20 23:00:00', '2026-01-21 01:00:00' ),
		'spring-hack-night'       => array( '2026-03-08 03:00:00', '2026-03-08 08:00:00' ),
		'summer-concert'          => array( '2026-07-18 23:30:00', '2026-07-19 02:00:00' ),
		'independence-day-picnic' => array( '2026-07-04 04:00:00', '2026-07-05 04:00:00' ),
		'fall-back-social'        => array( '2026-11-01 04:30:00', '2026-11-01 08:00:00' ),
		'winter-retreat'          => array( '2026-12-28 05:00:00', '2026-12-31 05:00:00' ),
		'kickoff-2025'            => array( '2025-02-01 15:00:00', '2025-02-01 17:00:00' ),
		'nye-party'               => array( '2027-01-01 03:00:00', '2027-01-01 07:00:00' ),
		'founders-day-2019'       => array( '2019-05-10 04:00:00', '2019-05-11 04:00:00' ),
	);

	public function test_existing_events_were_converted(): void {
		foreach ( self::EXPECTED as $slug => $utc ) {
			$this->assertSame(
				array( 'timezone' => 'America/New_York', 'start_utc' => $utc[0], 'end_utc' => $utc[1] ),
				stored( seeded( $slug ) ),
				"converted $slug"
			);
		}
		$this->assertSame( '2.0', get_option( 'acme_events_db_version' ) );
	}

	public function test_wall_clock_fields_are_kept_or_filled_in(): void {
		$concert = seeded( 'summer-concert' );
		$this->assertSame( '2026-07-18 19:30:00', raw_meta( $concert, '_acme_event_start' ) );
		$this->assertSame( '2026-07-18 22:00:00', raw_meta( $concert, '_acme_event_end' ) );
		$this->assertSame( 'Riverside park', raw_meta( $concert, '_acme_event_location' ) );

		$nye = seeded( 'nye-party' );
		$this->assertSame( '2026-12-31 22:00:00', raw_meta( $nye, '_acme_event_start' ) );
		$this->assertSame( '2027-01-01 02:00:00', raw_meta( $nye, '_acme_event_end' ) );
		$this->assertSame( 'Rooftop', raw_meta( $nye, '_acme_event_location' ) );

		$founders = seeded( 'founders-day-2019' );
		$this->assertSame( '2019-05-10 00:00:00', raw_meta( $founders, '_acme_event_start' ) );
		$this->assertSame( '2019-05-10 23:59:59', raw_meta( $founders, '_acme_event_end' ) );
		$this->assertSame( '1', raw_meta( $founders, '_acme_event_all_day' ) );

		// The event without dates stays without dates.
		$tba = seeded( 'tba-meetup' );
		$this->assertNull( raw_meta( $tba, '_acme_event_start_utc' ) );
		$this->assertSame( '', when_html( $tba ) );
	}

	public function test_converted_events_display_their_original_times(): void {
		set_site_timezone( 'Europe/Berlin' );
		$when = parse_when( when_html( seeded( 'summer-concert' ) ) );
		$this->assertSame( array( 'datetime' => '2026-07-18T19:30:00-04:00', 'text' => 'July 18, 2026 7:30 pm' ), $when['start'] );
		$this->assertSame( array( 'datetime' => '2026-07-18T22:00:00-04:00', 'text' => '10:00 pm' ), $when['end'] );

		$when = parse_when( when_html( seeded( 'board-meeting' ) ) );
		$this->assertSame( array( 'datetime' => '2026-01-20T18:00:00-05:00', 'text' => 'January 20, 2026 6:00 pm' ), $when['start'] );

		$when = parse_when( when_html( seeded( 'winter-retreat' ) ) );
		$this->assertSame( array( 'datetime' => '2026-12-28', 'text' => 'December 28, 2026' ), $when['start'] );
		$this->assertSame( array( 'datetime' => '2026-12-30', 'text' => 'December 30, 2026' ), $when['end'] );
		$this->assertTrue( $when['all_day'] );

		$when = parse_when( when_html( seeded( 'nye-party' ) ) );
		$this->assertSame( array( 'datetime' => '2026-12-31T22:00:00-05:00', 'text' => 'December 31, 2026 10:00 pm' ), $when['start'] );
		$this->assertSame( array( 'datetime' => '2027-01-01T02:00:00-05:00', 'text' => 'January 1, 2027 2:00 am' ), $when['end'] );
	}
}
