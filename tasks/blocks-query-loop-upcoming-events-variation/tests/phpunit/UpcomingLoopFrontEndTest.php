<?php
/**
 * "Upcoming events" Query Loop variation on the front end (served by Playground).
 */

use function WPSB\Events\set_now;
use function WPSB\Events\clear_now;
use function WPSB\Events\upcoming_loop;
use function WPSB\Events\plain_loop;
use function WPSB\Events\loop_titles;
use function WPSB\Events\loop_dates;
use function WPSB\Events\loop_pagination;
use function WPSB\Events\local;
use const WPSB\Events\UPCOMING_AT_1030;

class UpcomingLoopFrontEndTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private array $cleanup = array();
	private array $options = array();

	protected function setUp(): void {
		parent::setUp();
		foreach ( array( 'date_format', 'time_format' ) as $o ) {
			$this->options[ $o ] = get_option( $o );
		}
		$this->assertSame( 'Pacific/Auckland', get_option( 'timezone_string' ) );
	}

	protected function tearDown(): void {
		foreach ( $this->cleanup as $id ) {
			wp_delete_post( $id, true );
		}
		foreach ( $this->options as $o => $v ) {
			update_option( $o, $v );
		}
		clear_now();
		parent::tearDown();
	}

	private function page_with( string $content ): string {
		$id              = $this->create_post( array( 'post_type' => 'page', 'post_content' => $content, 'post_title' => 'Loop test ' . wp_rand() ) );
		$this->cleanup[] = $id;
		return wp_make_link_relative( get_permalink( $id ) );
	}

	private function get( string $path ): string {
		$res = $this->http( 'GET', $path );
		$this->assertSame( 200, $res['status'], "GET $path" );
		$this->assertStringNotContainsString( 'critical error', $res['body'] );
		return $res['body'];
	}

	public function test_lists_upcoming_events_in_start_order(): void {
		set_now( '2031-06-15 10:30' );
		$path = $this->page_with( upcoming_loop( 7, 20 ) );
		$this->assertSame( UPCOMING_AT_1030, loop_titles( $this->get( $path ), 'upcoming-loop' ) );
	}

	public function test_importer_loops_with_only_the_namespace_work(): void {
		set_now( '2031-06-15 10:30' );
		// Created by our importer: only the namespace and perPage, no post type.
		$content = '<!-- wp:query {"queryId":9,"query":{"perPage":4},"namespace":"acme/upcoming-events","className":"imported-loop"} -->
<div class="wp-block-query imported-loop"><!-- wp:post-template -->
<!-- wp:post-title /-->
<!-- /wp:post-template --></div>
<!-- /wp:query -->';
		$path    = $this->page_with( $content );
		$this->assertSame( array_slice( UPCOMING_AT_1030, 0, 4 ), loop_titles( $this->get( $path ), 'imported-loop' ) );

		// A loop whose query says something else (post type, order, search) is still an upcoming events loop.
		$path = $this->page_with( upcoming_loop( 10, 3, 'odd-loop', array( 'postType' => 'post', 'order' => 'desc', 'orderBy' => 'title' ) ) );
		$this->assertSame( array_slice( UPCOMING_AT_1030, 0, 3 ), loop_titles( $this->get( $path ), 'odd-loop' ) );
	}

	public function test_pagination(): void {
		set_now( '2031-06-15 10:30' );
		$path = $this->page_with( upcoming_loop( 7, 3 ) );

		$html = $this->get( $path );
		$this->assertSame( array_slice( UPCOMING_AT_1030, 0, 3 ), loop_titles( $html, 'upcoming-loop' ) );
		$pg = loop_pagination( $html, 'upcoming-loop' );
		$this->assertSame( array( 1, 2, 3 ), $pg['numbers'], 'three pages of upcoming events' );
		$this->assertNotNull( $pg['next'] );
		$this->assertNull( $pg['prev'] );

		$html = $this->get( $pg['next'] );
		$this->assertSame( array_slice( UPCOMING_AT_1030, 3, 3 ), loop_titles( $html, 'upcoming-loop' ) );

		$html = $this->get( add_query_arg( 'query-7-page', 3, $path ) );
		$this->assertSame( array_slice( UPCOMING_AT_1030, 6, 3 ), loop_titles( $html, 'upcoming-loop' ) );
		$pg = loop_pagination( $html, 'upcoming-loop' );
		$this->assertNull( $pg['next'], 'no "next" link on the last page' );
		$this->assertNotNull( $pg['prev'] );
		$this->assertSame( array( 1, 2, 3 ), $pg['numbers'] );

		$html = $this->get( add_query_arg( 'query-7-page', 4, $path ) );
		$this->assertSame( array(), loop_titles( $html, 'upcoming-loop' ) );
	}

	public function test_today_until_it_ends_in_the_site_timezone(): void {
		$path = $this->page_with( upcoming_loop( 7, 20 ) );

		// 23:45 local (11:45 UTC): events without an end last until local midnight.
		set_now( '2031-06-15 23:45' );
		$titles = loop_titles( $this->get( $path ), 'upcoming-loop' );
		$this->assertSame( array( 'Winter festival', 'Sunday market', 'Afternoon talk', 'Board games night' ), array_slice( $titles, 0, 4 ), 'late evening' );
		$this->assertNotContains( 'Late show', $titles, 'ended at 23:30' );

		// 00:15 the next day (still the 15th in UTC).
		set_now( '2031-06-16 00:15' );
		$titles = loop_titles( $this->get( $path ), 'upcoming-loop' );
		$this->assertSame( array( 'Winter festival', 'Board games night', 'Winter meetup', 'Harbour cleanup', 'Kayak trip', 'Spring conference' ), $titles, 'just after local midnight' );

		// 08:30: the morning's ended event is gone, the market (no end) is on.
		set_now( '2031-06-15 08:30' );
		$titles = loop_titles( $this->get( $path ), 'upcoming-loop' );
		$this->assertNotContains( 'Morning yoga', $titles );
		$this->assertSame( UPCOMING_AT_1030, $titles );
		set_now( '2031-06-15 07:30' );
		$this->assertSame( array( 'Winter festival', 'Morning yoga', 'Sunday market' ), array_slice( loop_titles( $this->get( $path ), 'upcoming-loop' ), 0, 3 ) );
	}

	public function test_multi_day_and_all_day_events(): void {
		$path = $this->page_with( upcoming_loop( 7, 20 ) );

		set_now( '2031-06-16 21:59' );
		$this->assertSame( 'Winter festival', loop_titles( $this->get( $path ), 'upcoming-loop' )[0] ?? null, 'running multi-day event' );
		set_now( '2031-06-16 22:01' );
		$this->assertNotContains( 'Winter festival', loop_titles( $this->get( $path ), 'upcoming-loop' ) );

		// All-day event stored with the importer's end = 0.
		set_now( '2031-06-22 23:58' );
		$this->assertSame( array( 'Harbour cleanup', 'Kayak trip', 'Spring conference' ), loop_titles( $this->get( $path ), 'upcoming-loop' ) );
		set_now( '2031-06-23 00:02' );
		$this->assertSame( array( 'Kayak trip', 'Spring conference' ), loop_titles( $this->get( $path ), 'upcoming-loop' ) );

		set_now( '2031-12-01 00:00' );
		$html = $this->get( $path );
		$this->assertSame( array(), loop_titles( $html, 'upcoming-loop' ) );
		$this->assertStringContainsString( 'No upcoming events.', $html );
	}

	public function test_cancelled_draft_and_new_events(): void {
		set_now( '2031-06-15 10:30' );
		$path   = $this->page_with( upcoming_loop( 7, 20 ) );
		$titles = loop_titles( $this->get( $path ), 'upcoming-loop' );
		foreach ( array( 'Book club', 'Wine tasting', 'Secret planning session', 'Last week\'s workshop', 'Morning yoga' ) as $gone ) {
			$this->assertNotContains( $gone, $titles );
		}
		$this->assertContains( 'Kayak trip', $titles, 'postponed events are still listed' );

		// An event created and cancelled with the current editor.
		$id              = $this->create_post( array( 'post_type' => 'acme_event', 'post_title' => 'Pop-up cinema' ) );
		$this->cleanup[] = $id;
		update_post_meta( $id, '_acme_event_start', local( '2031-06-19 20:00' ) );
		update_post_meta( $id, '_acme_event_end', local( '2031-06-19 22:00' ) );
		$titles = loop_titles( $this->get( $path ), 'upcoming-loop' );
		$this->assertSame( 'Pop-up cinema', $titles[5] ?? null, 'new event sorted by its start' );
		update_post_meta( $id, '_acme_event_status', 'cancelled' );
		$this->assertNotContains( 'Pop-up cinema', loop_titles( $this->get( $path ), 'upcoming-loop' ) );
	}

	public function test_event_date_block_in_the_loop(): void {
		set_now( '2031-06-15 10:30' );
		$path  = $this->page_with( upcoming_loop( 7, 20 ) );
		$html  = $this->get( $path );
		$dates = loop_dates( $html, 'upcoming-loop' );
		$this->assertCount( count( UPCOMING_AT_1030 ), $dates, 'one Event date per listed event' );
		$by_title = array_combine( loop_titles( $html, 'upcoming-loop' ), $dates );

		$this->assertSame( array( 'text' => '20 June 2031 18:00', 'datetime' => '2031-06-20T18:00:00+12:00' ), $by_title['Winter meetup'] );
		$this->assertSame( array( 'text' => '15 June 2031 09:00', 'datetime' => '2031-06-15T09:00:00+12:00' ), $by_title['Sunday market'] );
		$this->assertSame( '22 June 2031', $by_title['Harbour cleanup']['text'], 'all-day events show the date only' );
		$this->assertSame( '2031-06-22T00:00:00+12:00', $by_title['Harbour cleanup']['datetime'] );
		$this->assertSame( '13 June 2031 10:00', $by_title['Winter festival']['text'] );

		update_option( 'date_format', 'Y/m/d' );
		update_option( 'time_format', 'g:i a' );
		$by_title = array_combine( loop_titles( $html = $this->get( $path ), 'upcoming-loop' ), loop_dates( $html, 'upcoming-loop' ) );
		$this->assertSame( '2031/09/10 9:00 am', $by_title['Spring conference']['text'] );
	}

	public function test_event_date_block_outside_the_loop(): void {
		$event           = $this->create_post( array( 'post_type' => 'acme_event', 'post_title' => 'Solo show', 'post_content' => '<!-- wp:acme/event-date /--><!-- wp:paragraph --><p>Bring a friend.</p><!-- /wp:paragraph -->' ) );
		$this->cleanup[] = $event;
		update_post_meta( $event, '_acme_event_start', local( '2031-08-01 19:30' ) );
		$html = $this->get( wp_make_link_relative( get_permalink( $event ) ) );
		$x    = WPSB\Events\dom( $html );
		$this->assertSame( array( array( 'text' => '1 August 2031 19:30', 'datetime' => '2031-08-01T19:30:00+12:00' ) ), WPSB\Events\event_dates_in( $x ) );

		// On something that isn't an event it renders nothing.
		$page = $this->page_with( '<!-- wp:acme/event-date /--><!-- wp:paragraph --><p>Not an event.</p><!-- /wp:paragraph -->' );
		$x    = WPSB\Events\dom( $this->get( $page ) );
		$this->assertSame( array(), WPSB\Events\event_dates_in( $x ) );
	}

	public function test_other_loops_on_the_same_page_are_not_affected(): void {
		set_now( '2031-06-15 10:30' );
		$path = $this->page_with( upcoming_loop( 7, 3 ) . "\n\n" . plain_loop( 8 ) . "\n\n" . upcoming_loop( 11, 2, 'second-upcoming' ) );
		$html = $this->get( $path );
		$this->assertSame( array_slice( UPCOMING_AT_1030, 0, 3 ), loop_titles( $html, 'upcoming-loop' ) );
		$this->assertSame( array_slice( UPCOMING_AT_1030, 0, 2 ), loop_titles( $html, 'second-upcoming' ) );
		$plain = loop_titles( $html, 'plain-loop' );
		$this->assertCount( 13, $plain, 'plain loop lists all published events' );
		$this->assertContains( 'Last week\'s workshop', $plain );
		$this->assertContains( 'Wine tasting', $plain );
		$sorted = $plain;
		sort( $sorted, SORT_STRING | SORT_FLAG_CASE );
		$this->assertSame( $sorted, $plain, 'plain loop keeps its own ordering (title)' );
	}
}
