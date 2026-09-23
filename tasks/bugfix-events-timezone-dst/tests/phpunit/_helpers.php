<?php
/**
 * Helpers for the Acme Events timezone tests.
 */

namespace WPSB\Events;

/**
 * Switches the site's timezone setting like Settings → General does: a named zone, or a manual UTC
 * offset such as '+05:30' / '-03:00' (stored as gmt_offset with an empty timezone_string).
 */
function set_site_timezone( string $tz ): void {
	if ( preg_match( '/^([+-])(\d{2}):(\d{2})$/', $tz, $m ) ) {
		$hours = ( (int) $m[2] + (int) $m[3] / 60 ) * ( '-' === $m[1] ? -1 : 1 );
		update_option( 'timezone_string', '' );
		update_option( 'gmt_offset', $hours );
	} else {
		update_option( 'timezone_string', $tz );
		update_option( 'gmt_offset', 0 );
	}
	wp_cache_delete( 'alloptions', 'options' );
}

/** Seeded event ID by slug. */
function seeded( string $slug ): int {
	global $wpdb;
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'acme_event'", $slug ) );
	if ( ! $id ) {
		throw new \RuntimeException( "Seeded event $slug not found" );
	}
	return $id;
}

/** Raw meta value (single) straight from the DB. */
function raw_meta( int $id, string $key ): ?string {
	global $wpdb;
	$v = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id DESC LIMIT 1", $id, $key ) );
	return null === $v ? null : (string) $v;
}

/** The stored timezone/UTC fields of an event. */
function stored( int $id ): array {
	return array(
		'timezone'  => raw_meta( $id, '_acme_event_timezone' ),
		'start_utc' => raw_meta( $id, '_acme_event_start_utc' ),
		'end_utc'   => raw_meta( $id, '_acme_event_end_utc' ),
	);
}

function dom( string $html ): \DOMXPath {
	$doc = new \DOMDocument();
	$old = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	return new \DOMXPath( $doc );
}

/**
 * Parses the `<time>` markup of the event details.
 *
 * @return array{start:?array{datetime:string,text:string}, end:?array{datetime:string,text:string}, all_day:bool}
 */
function parse_when( string $html ): array {
	$x   = dom( $html );
	$get = static function ( string $class ) use ( $x ) {
		$n = $x->query( "//time[contains(concat(' ', normalize-space(@class), ' '), ' $class ')]" )->item( 0 );
		return $n ? array(
			'datetime' => $n->getAttribute( 'datetime' ),
			'text'     => trim( preg_replace( '/\s+/u', ' ', $n->textContent ) ),
		) : null;
	};
	return array(
		'start'   => $get( 'acme-event-start' ),
		'end'     => $get( 'acme-event-end' ),
		'all_day' => $x->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' acme-event-all-day ')]" )->length > 0,
	);
}

/** The `<time>` markup of an event through the plugin's template tag. */
function when_html( int $id ): string {
	ob_start();
	acme_events_the_when( $id );
	return (string) ob_get_clean();
}

/**
 * Parses an iCalendar document: [event ID => [PROPERTY(;PARAMS) => value]] for `acme-event-{ID}@` UIDs.
 */
function ics_events( string $ics ): array {
	$ics    = preg_replace( "/\r\n[ \t]/", '', $ics );
	$events = array();
	foreach ( preg_split( '/BEGIN:VEVENT\r?\n/', $ics ) as $i => $chunk ) {
		if ( 0 === $i ) {
			continue;
		}
		$chunk = substr( $chunk, 0, (int) strpos( $chunk, 'END:VEVENT' ) );
		$props = array();
		foreach ( preg_split( '/\r?\n/', trim( $chunk ) ) as $line ) {
			$pos = strpos( $line, ':' );
			if ( false !== $pos ) {
				$props[ substr( $line, 0, $pos ) ] = substr( $line, $pos + 1 );
			}
		}
		if ( isset( $props['UID'] ) && preg_match( '/^acme-event-(\d+)@/', $props['UID'], $m ) ) {
			$events[ (int) $m[1] ] = $props;
		}
	}
	return $events;
}

/**
 * Base class: creates events through the plugin's public API and pins "now".
 */
abstract class EventsTestCase extends \WPSB\TestCase {

	/** @var callable|null */
	private $now_filter = null;

	private array $saved_tz = array();

	protected function setUp(): void {
		parent::setUp();
		$this->saved_tz = array(
			'timezone_string' => get_option( 'timezone_string' ),
			'gmt_offset'      => get_option( 'gmt_offset' ),
		);
	}

	protected function tearDown(): void {
		if ( $this->now_filter ) {
			remove_filter( 'acme_events_now', $this->now_filter, 999 );
			$this->now_filter = null;
		}
		update_option( 'timezone_string', $this->saved_tz['timezone_string'] );
		update_option( 'gmt_offset', $this->saved_tz['gmt_offset'] );
		parent::tearDown();
	}

	/** Pins the plugin clock (UTC date-time string or timestamp). */
	protected function set_now( $when ): void {
		$ts = is_int( $when ) ? $when : strtotime( $when . ' UTC' );
		if ( $this->now_filter ) {
			remove_filter( 'acme_events_now', $this->now_filter, 999 );
		}
		$this->now_filter = static fn() => $ts;
		add_filter( 'acme_events_now', $this->now_filter, 999 );
	}

	/** Creates a published event in the given site timezone. */
	protected function event( string $tz, string $title, string $start, string $end = '', bool $all_day = false ): int {
		set_site_timezone( $tz );
		$id     = $this->create_post(
			array(
				'post_type'  => 'acme_event',
				'post_title' => $title,
			)
		);
		$result = acme_events_save_event_dates( $id, $start, $end, $all_day );
		$this->assertTrue( $result, is_wp_error( $result ) ? $result->get_error_message() : 'save failed' );
		clean_post_cache( $id );
		return $id;
	}

	/** Upcoming event IDs (plugin API), limited to the given IDs, in order. */
	protected function upcoming_ids( array $only ): array {
		$ids = array_map( static fn( $e ) => $e->get_id(), acme_events_get_upcoming( array( 'limit' => 100 ) ) );
		return array_values( array_intersect( $ids, $only ) );
	}

	protected function rest_events( array $query = array() ): array {
		$res = $this->rest( 'GET', '/acme-events/v1/events', array_merge( array( 'per_page' => 100 ), $query ) );
		$this->assertSame( 200, $res->get_status() );
		$out = array();
		foreach ( $res->get_data() as $item ) {
			$out[ (int) $item['id'] ] = $item;
		}
		return $out;
	}
}
