<?php
/**
 * Behaviour that already works (UTC sites, filters, shortcode options) and must keep working.
 */

use function WPSB\Events\dom;
use function WPSB\Events\parse_when;
use function WPSB\Events\when_html;

class CompatTest extends WPSB\Events\EventsTestCase {

	public function test_utc_site(): void {
		$id = $this->event( 'UTC', 'Launch', '2026-06-01 21:00', '2026-06-01 22:30' );
		$this->assertSame(
			array(
				'start'   => array( 'datetime' => '2026-06-01T21:00:00+00:00', 'text' => 'June 1, 2026 9:00 pm' ),
				'end'     => array( 'datetime' => '2026-06-01T22:30:00+00:00', 'text' => '10:30 pm' ),
				'all_day' => false,
			),
			parse_when( when_html( $id ) )
		);
		$items = $this->rest_events( array( 'upcoming' => false ) );
		$this->assertSame( '2026-06-01T21:00:00+00:00', $items[ $id ]['start'] );
		$this->assertSame( '2026-06-01T22:30:00+00:00', $items[ $id ]['end'] );
		$this->assertFalse( $items[ $id ]['all_day'] );

		$this->set_now( '2026-06-01 22:29:00' );
		$this->assertSame( array( $id ), $this->upcoming_ids( array( $id ) ) );
		$this->set_now( '2026-06-01 22:31:00' );
		$this->assertSame( array(), $this->upcoming_ids( array( $id ) ) );
	}

	public function test_shortcode_category_and_limit(): void {
		$term = wp_insert_term( 'Workshops', 'acme_event_category' );
		$a    = $this->event( 'UTC', 'Workshop A', '2031-02-01 10:00', '2031-02-01 12:00' );
		$b    = $this->event( 'UTC', 'Talk', '2031-02-02 10:00', '2031-02-02 12:00' );
		$c    = $this->event( 'UTC', 'Workshop B', '2031-02-03 10:00', '2031-02-03 12:00' );
		wp_set_object_terms( $a, array( $term['term_id'] ), 'acme_event_category' );
		wp_set_object_terms( $c, array( $term['term_id'] ), 'acme_event_category' );
		$this->set_now( '2031-01-01 00:00:00' );

		$ids = function ( string $shortcode ) {
			$out = array();
			foreach ( dom( do_shortcode( $shortcode ) )->query( '//li[@data-event-id]' ) as $li ) {
				$out[] = (int) $li->getAttribute( 'data-event-id' );
			}
			return $out;
		};
		$this->assertSame( array( $a, $c ), $ids( '[acme_upcoming_events category="' . $term['term_id'] . '"]' ) );
		$this->assertSame( array( $a, $b ), $ids( '[acme_upcoming_events limit="2"]' ) );
	}

	public function test_extension_points_still_apply(): void {
		$saved = array();
		$cb    = static function ( $id ) use ( &$saved ) {
			$saved[] = $id;
		};
		add_action( 'acme_events_dates_saved', $cb );
		$id = $this->event( 'UTC', 'Hooked', '2031-03-01 10:00', '2031-03-01 11:00' );
		remove_action( 'acme_events_dates_saved', $cb );
		$this->assertSame( array( $id ), $saved );

		$details = static fn( $html ) => $html . '<p class="theme-extra">extra</p>';
		add_filter( 'acme_events_details_html', $details );
		$this->assertStringContainsString( 'theme-extra', \Acme\Events\Frontend::render_details( \Acme\Events\Event::get( $id ) ) );
		remove_filter( 'acme_events_details_html', $details );

		$rest = static function ( $item ) {
			$item['source'] = 'filtered';
			return $item;
		};
		add_filter( 'acme_events_rest_item', $rest );
		$items = $this->rest_events( array( 'upcoming' => false ) );
		remove_filter( 'acme_events_rest_item', $rest );
		$this->assertSame( 'filtered', $items[ $id ]['source'] );

		$exclude = static function ( $args ) use ( $id ) {
			$args['post__not_in'] = array( $id );
			return $args;
		};
		$this->set_now( '2031-01-01 00:00:00' );
		$this->assertSame( array( $id ), $this->upcoming_ids( array( $id ) ) );
		add_filter( 'acme_events_upcoming_query_args', $exclude );
		$this->assertSame( array(), $this->upcoming_ids( array( $id ) ) );
		remove_filter( 'acme_events_upcoming_query_args', $exclude );
	}
}
