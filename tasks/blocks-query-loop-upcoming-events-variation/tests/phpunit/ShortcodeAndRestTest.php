<?php
/**
 * The existing [acme_upcoming_events] shortcode follows the same rules as the loop,
 * and the public REST data of events is unchanged.
 */

use function WPSB\Events\set_now;
use function WPSB\Events\clear_now;
use function WPSB\Events\squish;
use const WPSB\Events\UPCOMING_AT_1030;

class ShortcodeAndRestTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	protected function tearDown(): void {
		clear_now();
		parent::tearDown();
	}

	private function shortcode_titles( string $html ): array {
		$x   = WPSB\Events\dom( $html );
		$out = array();
		foreach ( $x->query( '//ul[contains(@class, "acme-upcoming-events")]/li//a[contains(@class, "acme-upcoming-events__title")]' ) as $a ) {
			$out[] = squish( $a->textContent );
		}
		return $out;
	}

	public function test_shortcode_far_ahead_of_time(): void {
		// Plenty of time before anything happens: plain chronological list.
		set_now( '2031-01-01 12:00' );
		$res = $this->http( 'GET', '/whats-on/' );
		$this->assertSame( 200, $res['status'] );
		$titles = $this->shortcode_titles( $res['body'] );
		$this->assertSame( 'Last week\'s workshop', $titles[0] ?? null );
		$this->assertContains( 'Winter meetup', $titles );
		$this->assertNotContains( 'Wine tasting', $titles, 'cancelled' );
		$this->assertNotContains( 'Secret planning session', $titles, 'draft' );
		$this->assertStringContainsString( '<time class="acme-upcoming-events__date" datetime="2031-06-20T18:00:00+12:00">20 June 2031 18:00</time>', $res['body'] );
	}

	public function test_shortcode_uses_the_same_rules_as_the_loop(): void {
		set_now( '2031-06-15 10:30' );
		$res = $this->http( 'GET', '/whats-on/' );
		$this->assertSame( UPCOMING_AT_1030, $this->shortcode_titles( $res['body'] ) );

		set_now( '2031-06-15 23:45' );
		$titles = $this->shortcode_titles( $this->http( 'GET', '/whats-on/' )['body'] );
		$this->assertSame( array( 'Winter festival', 'Sunday market', 'Afternoon talk', 'Board games night' ), array_slice( $titles, 0, 4 ) );

		set_now( '2031-06-16 00:15' );
		$titles = $this->shortcode_titles( $this->http( 'GET', '/whats-on/' )['body'] );
		$this->assertSame( array( 'Winter festival', 'Board games night', 'Winter meetup', 'Harbour cleanup', 'Kayak trip', 'Spring conference' ), $titles );
	}

	public function test_shortcode_limit_is_honoured_in_process(): void {
		set_now( '2031-06-15 10:30' );
		$html = do_shortcode( '[acme_upcoming_events limit="3" show_venue="0"]' );
		$this->assertSame( array_slice( UPCOMING_AT_1030, 0, 3 ), $this->shortcode_titles( $html ) );
		$this->assertStringNotContainsString( 'acme-upcoming-events__venue', $html );

		set_now( '2031-06-15 07:30' );
		// Morning yoga (07:00-08:00) is on, the three soonest are festival, yoga, market.
		$this->assertSame( array( 'Winter festival', 'Morning yoga', 'Sunday market' ), $this->shortcode_titles( do_shortcode( '[acme_upcoming_events limit="3"]' ) ) );

		set_now( '2032-01-01 00:00' );
		$this->assertStringContainsString( 'No upcoming events.', do_shortcode( '[acme_upcoming_events]' ) );
	}

	public function test_events_rest_collection_is_unchanged(): void {
		set_now( '2031-06-15 10:30' );
		$res = $this->http( 'GET', '/wp-json/wp/v2/events?per_page=50' );
		$this->assertSame( 200, $res['status'] );
		$this->assertCount( 13, $res['json'], 'all published events, past and cancelled included' );
		$this->assertSame( '13', $res['headers']['x-wp-total'] ?? null );
		$dates = array_column( $res['json'], 'date_gmt' );
		$sorted = $dates;
		rsort( $sorted );
		$this->assertSame( $sorted, $dates, 'default REST ordering (newest publish date first)' );

		$by_slug = array_column( $res['json'], null, 'slug' );
		$this->assertSame( 'cancelled', $by_slug['book-club']['acme_event']['status'] );
		$this->assertSame( '2031-06-20T18:00:00+12:00', $by_slug['winter-meetup']['acme_event']['start'] );
		$this->assertSame( '20 June 2031 18:00', $by_slug['winter-meetup']['acme_event']['date_label'] );

		$res = $this->http( 'GET', '/wp-json/wp/v2/events?per_page=3&page=2&orderby=title&order=asc' );
		$this->assertSame( 200, $res['status'] );
		$this->assertSame( array( 'harbour-cleanup', 'kayak-trip', 'last-weeks-workshop' ), array_column( $res['json'], 'slug' ) );
	}
}
