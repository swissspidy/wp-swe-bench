<?php
/**
 * Requests against the real server: iCal feed, single event page, event editor.
 */

use function WPSB\Events\ics_events;
use function WPSB\Events\parse_when;
use function WPSB\Events\seeded;
use function WPSB\Events\set_site_timezone;
use function WPSB\Events\stored;

class HttpTest extends WPSB\Events\EventsTestCase {

	protected bool $use_transactions = false;

	private array $created = array();

	protected function tearDown(): void {
		foreach ( $this->created as $id ) {
			wp_delete_post( $id, true );
		}
		set_site_timezone( 'America/New_York' );
		parent::tearDown();
	}

	private function feed(): array {
		$res = $this->http( 'GET', '/feed/acme-events-ics/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringStartsWith( 'text/calendar', $res['headers']['content-type'] ?? '' );
		$this->assertStringStartsWith( "BEGIN:VCALENDAR\r\n", $res['body'] );
		$this->assertStringContainsString( "END:VCALENDAR", $res['body'] );
		return ics_events( $res['body'] );
	}

	private function assertTimes( array $events, int $id, string $start, string $end, string $what ): void {
		$this->assertArrayHasKey( $id, $events, "$what missing from the feed" );
		$e = $events[ $id ];
		if ( 8 === strlen( $start ) ) {
			$this->assertSame( array( $start, $end ), array( $e['DTSTART;VALUE=DATE'] ?? null, $e['DTEND;VALUE=DATE'] ?? null ), $what );
		} else {
			$this->assertSame( array( $start, $end ), array( $e['DTSTART'] ?? null, $e['DTEND'] ?? null ), $what );
		}
	}

	public function test_feed_of_existing_events(): void {
		$events = $this->feed();
		$this->assertTimes( $events, seeded( 'board-meeting' ), '20260120T230000Z', '20260121T010000Z', 'board meeting' );
		$this->assertTimes( $events, seeded( 'spring-hack-night' ), '20260308T030000Z', '20260308T080000Z', 'hack night' );
		$this->assertTimes( $events, seeded( 'summer-concert' ), '20260718T233000Z', '20260719T020000Z', 'concert' );
		$this->assertTimes( $events, seeded( 'fall-back-social' ), '20261101T043000Z', '20261101T080000Z', 'fall back social' );
		$this->assertTimes( $events, seeded( 'independence-day-picnic' ), '20260704', '20260705', 'picnic' );
		$this->assertTimes( $events, seeded( 'winter-retreat' ), '20261228', '20261231', 'retreat' );
		$this->assertTimes( $events, seeded( 'nye-party' ), '20270101T030000Z', '20270101T070000Z', 'NYE' );
		$this->assertTimes( $events, seeded( 'founders-day-2019' ), '20190510', '20190511', 'founders day' );
		$this->assertArrayNotHasKey( seeded( 'tba-meetup' ), $events );
		$this->assertSame( 'Summer concert', $events[ seeded( 'summer-concert' ) ]['SUMMARY'] );
		$this->assertSame( 'Riverside park', $events[ seeded( 'summer-concert' ) ]['LOCATION'] );
	}

	public function test_feed_for_sites_in_other_timezones(): void {
		$make = function ( string $tz, string $title, string $start, string $end, bool $all_day = false ): int {
			$id              = $this->event( $tz, $title, $start, $end, $all_day );
			$this->created[] = $id;
			return $id;
		};
		$berlin     = $make( 'Europe/Berlin', 'Berlin', '2026-10-25 01:30', '2026-10-25 04:00' );
		$berlin_day = $make( 'Europe/Berlin', 'Berlin day', '2026-12-24', '2026-12-24', true );
		$akl        = $make( 'Pacific/Auckland', 'Auckland', '2026-04-05 01:00', '2026-04-05 04:00' );
		$akl_day    = $make( 'Pacific/Auckland', 'Auckland day', '2026-12-25', '2026-12-26', true );
		$india      = $make( '+05:30', 'India', '2026-03-08 10:00', '2026-03-08 11:00' );
		$india_day  = $make( '+05:30', 'India day', '2026-03-08', '2026-03-10', true );

		foreach ( array( 'Asia/Tokyo', 'America/Los_Angeles' ) as $site_tz ) {
			set_site_timezone( $site_tz );
			$events = $this->feed();
			$this->assertTimes( $events, $berlin, '20261024T233000Z', '20261025T030000Z', "Berlin ($site_tz)" );
			$this->assertTimes( $events, $berlin_day, '20261224', '20261225', "Berlin all-day ($site_tz)" );
			$this->assertTimes( $events, $akl, '20260404T120000Z', '20260404T160000Z', "Auckland ($site_tz)" );
			$this->assertTimes( $events, $akl_day, '20261225', '20261227', "Auckland all-day ($site_tz)" );
			$this->assertTimes( $events, $india, '20260308T043000Z', '20260308T053000Z', "India ($site_tz)" );
			$this->assertTimes( $events, $india_day, '20260308', '20260311', "India all-day ($site_tz)" );
		}
	}

	public function test_single_event_page(): void {
		set_site_timezone( 'Europe/Berlin' );
		$res = $this->http( 'GET', '/events/summer-concert/' );
		$this->assertSame( 200, $res['status'] );
		$when = parse_when( $res['body'] );
		$this->assertSame( array( 'datetime' => '2026-07-18T19:30:00-04:00', 'text' => 'July 18, 2026 7:30 pm' ), $when['start'] );
		$this->assertStringContainsString( '<p class="acme-event-location">Riverside park</p>', $res['body'] );

		$res  = $this->http( 'GET', '/events/independence-day-picnic/' );
		$when = parse_when( $res['body'] );
		$this->assertSame( array( 'datetime' => '2026-07-04', 'text' => 'July 4, 2026' ), $when['start'] );
		$this->assertTrue( $when['all_day'] );
	}

	private function save_form( int $id, array $login, array $fields, bool $with_nonce = true ): array {
		$body = array_merge(
			array(
				'_wpnonce'             => $this->nonce_for( $login['user_id'], 'update-post_' . $id, $login['logged_in'] ),
				'_wp_http_referer'     => '/wp-admin/post.php?post=' . $id . '&action=edit',
				'user_ID'              => $login['user_id'],
				'action'               => 'editpost',
				'originalaction'       => 'editpost',
				'post_type'            => 'acme_event',
				'post_ID'              => $id,
				'original_post_status' => 'publish',
				'post_status'          => 'publish',
				'hidden_post_status'   => 'publish',
				'visibility'           => 'public',
				'post_title'           => get_post_field( 'post_title', $id ),
				'content'              => get_post_field( 'post_content', $id ),
				'save'                 => 'Update',
			),
			$fields
		);
		if ( $with_nonce ) {
			$body['acme_event_nonce'] = $this->nonce_for( $login['user_id'], 'acme_event_save', $login['logged_in'] );
		}
		return $this->http( 'POST', '/wp-admin/post.php', array( 'login' => $login, 'body' => $body ) );
	}

	public function test_event_editor_after_the_site_timezone_changed(): void {
		$admin = $this->create_user( 'administrator' );
		$login = $this->http_login( $admin );
		$id    = seeded( 'board-meeting' );
		set_site_timezone( 'Europe/Berlin' );

		$res = $this->http( 'GET', '/wp-admin/post.php?post=' . $id . '&action=edit', array( 'login' => $login ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertMatchesRegularExpression( '/name="acme_event_start_date" value="2026-01-20"/', $res['body'] );
		$this->assertMatchesRegularExpression( '/name="acme_event_start_time" value="18:00"/', $res['body'] );
		$this->assertMatchesRegularExpression( '/name="acme_event_end_time" value="20:00"/', $res['body'] );

		// Saving the form unchanged keeps the event where it is; a new time is taken in the event's timezone.
		$res = $this->save_form(
			$id,
			$login,
			array(
				'acme_event_start_date' => '2026-01-20',
				'acme_event_start_time' => '18:30',
				'acme_event_end_date'   => '2026-01-20',
				'acme_event_end_time'   => '20:00',
				'acme_event_location'   => 'Room 102',
			)
		);
		$this->assertContains( $res['status'], array( 302, 303 ), substr( strip_tags( $res['body'] ), 0, 500 ) );
		wp_cache_flush();
		$this->assertSame( array( 'timezone' => 'America/New_York', 'start_utc' => '2026-01-20 23:30:00', 'end_utc' => '2026-01-21 01:00:00' ), stored( $id ) );
		$this->assertSame( 'Room 102', get_post_meta( $id, '_acme_event_location', true ) );

		// Without the meta box nonce nothing changes.
		$res = $this->save_form(
			$id,
			$login,
			array(
				'acme_event_start_date' => '2026-02-01',
				'acme_event_start_time' => '09:00',
			),
			false
		);
		wp_cache_flush();
		$this->assertSame( '2026-01-20 23:30:00', stored( $id )['start_utc'] );

		// A new event created in the editor gets the site's timezone.
		$new             = $this->create_post( array( 'post_type' => 'acme_event', 'post_title' => 'Editor event' ) );
		$this->created[] = $new;
		$res             = $this->save_form(
			$new,
			$login,
			array(
				'acme_event_start_date' => '2026-03-29',
				'acme_event_start_time' => '01:00',
				'acme_event_end_date'   => '2026-03-29',
				'acme_event_end_time'   => '04:00',
			)
		);
		$this->assertContains( $res['status'], array( 302, 303 ) );
		wp_cache_flush();
		$this->assertSame( array( 'timezone' => 'Europe/Berlin', 'start_utc' => '2026-03-29 00:00:00', 'end_utc' => '2026-03-29 02:00:00' ), stored( $new ) );
	}
}
