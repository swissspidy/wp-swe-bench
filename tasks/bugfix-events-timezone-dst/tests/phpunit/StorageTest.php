<?php
/**
 * What gets stored for events saved in different site timezones.
 */

use function WPSB\Events\raw_meta;
use function WPSB\Events\set_site_timezone;
use function WPSB\Events\stored;

class StorageTest extends WPSB\Events\EventsTestCase {

	public function test_new_york_events_around_dst_transitions(): void {
		$spring = $this->event( 'America/New_York', 'Spring', '2026-03-08 01:30', '2026-03-08 03:30' );
		$fall   = $this->event( 'America/New_York', 'Fall', '2026-11-01 00:30', '2026-11-01 03:00' );
		$winter = $this->event( 'America/New_York', 'Winter', '2026-01-15 19:00', '2026-01-15 21:00' );
		$summer = $this->event( 'America/New_York', 'Summer', '2026-07-15 19:00', '2026-07-15 21:00' );

		$this->assertSame( array( 'timezone' => 'America/New_York', 'start_utc' => '2026-03-08 06:30:00', 'end_utc' => '2026-03-08 07:30:00' ), stored( $spring ) );
		$this->assertSame( array( 'timezone' => 'America/New_York', 'start_utc' => '2026-11-01 04:30:00', 'end_utc' => '2026-11-01 08:00:00' ), stored( $fall ) );
		$this->assertSame( array( 'timezone' => 'America/New_York', 'start_utc' => '2026-01-16 00:00:00', 'end_utc' => '2026-01-16 02:00:00' ), stored( $winter ) );
		$this->assertSame( array( 'timezone' => 'America/New_York', 'start_utc' => '2026-07-15 23:00:00', 'end_utc' => '2026-07-16 01:00:00' ), stored( $summer ) );

		// The wall-clock fields keep holding what the editor entered.
		$this->assertSame( '2026-03-08 01:30:00', raw_meta( $spring, '_acme_event_start' ) );
		$this->assertSame( '2026-03-08 03:30:00', raw_meta( $spring, '_acme_event_end' ) );
	}

	public function test_berlin_events_around_dst_transitions(): void {
		$spring = $this->event( 'Europe/Berlin', 'Spring', '2026-03-29 01:00', '2026-03-29 04:00' );
		$fall   = $this->event( 'Europe/Berlin', 'Fall', '2026-10-25 01:30', '2026-10-25 04:00' );
		$this->assertSame( array( 'timezone' => 'Europe/Berlin', 'start_utc' => '2026-03-29 00:00:00', 'end_utc' => '2026-03-29 02:00:00' ), stored( $spring ) );
		$this->assertSame( array( 'timezone' => 'Europe/Berlin', 'start_utc' => '2026-10-24 23:30:00', 'end_utc' => '2026-10-25 03:00:00' ), stored( $fall ) );
	}

	public function test_auckland_events_around_dst_transitions(): void {
		$april = $this->event( 'Pacific/Auckland', 'April', '2026-04-05 01:00', '2026-04-05 04:00' );
		$sept  = $this->event( 'Pacific/Auckland', 'September', '2026-09-27 01:00', '2026-09-27 04:00' );
		$this->assertSame( array( 'timezone' => 'Pacific/Auckland', 'start_utc' => '2026-04-04 12:00:00', 'end_utc' => '2026-04-04 16:00:00' ), stored( $april ) );
		$this->assertSame( array( 'timezone' => 'Pacific/Auckland', 'start_utc' => '2026-09-26 13:00:00', 'end_utc' => '2026-09-26 15:00:00' ), stored( $sept ) );
	}

	public function test_manual_utc_offsets(): void {
		$india = $this->event( '+05:30', 'Offset', '2026-03-08 10:00', '2026-03-08 11:00' );
		$this->assertSame( array( 'timezone' => '+05:30', 'start_utc' => '2026-03-08 04:30:00', 'end_utc' => '2026-03-08 05:30:00' ), stored( $india ) );

		$brazil = $this->event( '-03:00', 'Offset west', '2026-06-01 21:00', '2026-06-01 23:30' );
		$this->assertSame( array( 'timezone' => '-03:00', 'start_utc' => '2026-06-02 00:00:00', 'end_utc' => '2026-06-02 02:30:00' ), stored( $brazil ) );

		$utc = $this->event( 'UTC', 'UTC', '2026-06-01 21:00', '2026-06-01 22:00' );
		$this->assertSame( array( 'timezone' => 'UTC', 'start_utc' => '2026-06-01 21:00:00', 'end_utc' => '2026-06-01 22:00:00' ), stored( $utc ) );
	}

	public function test_all_day_events(): void {
		$picnic = $this->event( 'America/New_York', 'Picnic', '2026-07-04', '', true );
		$this->assertSame( array( 'timezone' => 'America/New_York', 'start_utc' => '2026-07-04 04:00:00', 'end_utc' => '2026-07-05 04:00:00' ), stored( $picnic ) );
		$this->assertSame( '2026-07-04 00:00:00', raw_meta( $picnic, '_acme_event_start' ) );
		$this->assertSame( '2026-07-04 23:59:59', raw_meta( $picnic, '_acme_event_end' ) );
		$this->assertSame( '1', raw_meta( $picnic, '_acme_event_all_day' ) );

		$xmas = $this->event( 'Pacific/Auckland', 'Christmas', '2026-12-25', '2026-12-26', true );
		$this->assertSame( array( 'timezone' => 'Pacific/Auckland', 'start_utc' => '2026-12-24 11:00:00', 'end_utc' => '2026-12-26 11:00:00' ), stored( $xmas ) );

		// A multi-day all-day event across the New York DST change.
		$march = $this->event( 'America/New_York', 'Camp', '2026-03-07', '2026-03-08', true );
		$this->assertSame( array( 'timezone' => 'America/New_York', 'start_utc' => '2026-03-07 05:00:00', 'end_utc' => '2026-03-09 04:00:00' ), stored( $march ) );
	}

	public function test_existing_event_keeps_its_timezone_when_saved_again(): void {
		$id = $this->event( 'America/New_York', 'Board', '2026-01-15 19:00', '2026-01-15 21:00' );

		set_site_timezone( 'Europe/Berlin' );
		$this->assertTrue( acme_events_save_event_dates( $id, '2026-01-15 20:00', '2026-01-15 22:00' ) );
		$this->assertSame( array( 'timezone' => 'America/New_York', 'start_utc' => '2026-01-16 01:00:00', 'end_utc' => '2026-01-16 03:00:00' ), stored( $id ) );

		$new = $this->event( 'Europe/Berlin', 'New', '2026-01-15 20:00', '2026-01-15 22:00' );
		$this->assertSame( array( 'timezone' => 'Europe/Berlin', 'start_utc' => '2026-01-15 19:00:00', 'end_utc' => '2026-01-15 21:00:00' ), stored( $new ) );
	}

	public function test_invalid_dates_are_rejected(): void {
		set_site_timezone( 'America/New_York' );
		$id = $this->create_post( array( 'post_type' => 'acme_event' ) );
		$this->assertWPError( acme_events_save_event_dates( $id, '2026-02-30 10:00' ) );
		$this->assertWPError( acme_events_save_event_dates( $id, 'tomorrow' ) );
		$this->assertWPError( acme_events_save_event_dates( $id, '2026-05-02 19:00', '2026-05-02 18:00' ) );
		$this->assertWPError( acme_events_save_event_dates( $this->create_post(), '2026-05-02 19:00' ), 'Only events have dates' );
		$this->assertTrue( acme_events_save_event_dates( $id, '2026-05-02 19:00' ), 'End defaults to the start' );
		$this->assertSame( '2026-05-02 19:00:00', raw_meta( $id, '_acme_event_end' ) );
	}

	private function assertWPError( $value, string $message = '' ): void {
		$this->assertInstanceOf( WP_Error::class, $value, $message );
	}
}
