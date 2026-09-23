<?php
/**
 * iCalendar feed.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * The `/feed/acme-events-ics/` feed (RFC 5545), subscribed to by calendar apps.
 *
 * - One VEVENT per published event, UID `acme-event-{ID}@{site host}`.
 * - Timed events: `DTSTART:20260502T230000Z` / `DTEND:…Z` (UTC).
 * - All-day events: `DTSTART;VALUE=DATE:20260704` / `DTEND;VALUE=DATE:20260705` (the day after the last day).
 */
class ICal {

	const FEED = 'acme-events-ics';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'add_feed' ) );
	}

	/**
	 * Registers the feed.
	 */
	public function add_feed() {
		$settings = get_option( 'acme_events_settings', array() );
		if ( isset( $settings['feed_enabled'] ) && ! $settings['feed_enabled'] ) {
			return;
		}
		add_feed( self::FEED, array( $this, 'render' ) );
	}

	/**
	 * Outputs the feed.
	 */
	public function render() {
		$posts = get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'meta_key'       => '_acme_event_start_ts', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
			)
		);

		// Events from 1.0 have no start timestamp meta and are not matched by the query above.
		$legacy = get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'meta_key'       => '_acme_event_timestamp', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);

		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Acme//Acme Events ' . ACME_EVENTS_VERSION . '//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:' . self::escape( get_bloginfo( 'name' ) ),
		);

		foreach ( array_merge( $posts, $legacy ) as $post ) {
			$event = Event::get( $post );
			if ( ! $event || ! $event->has_dates() ) {
				continue;
			}
			$lines = array_merge( $lines, self::vevent( $event ) );
		}
		$lines[] = 'END:VCALENDAR';

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/calendar; charset=utf-8' );
			header( 'Content-Disposition: inline; filename="events.ics"' );
		}
		echo implode( "\r\n", array_map( array( self::class, 'fold' ), $lines ) ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iCalendar, escaped per RFC 5545.
	}

	/**
	 * VEVENT lines for an event.
	 *
	 * @param Event $event Event.
	 * @return string[]
	 */
	public static function vevent( Event $event ) {
		$post  = $event->get_post();
		$lines = array(
			'BEGIN:VEVENT',
			'UID:acme-event-' . $event->get_id() . '@' . wp_parse_url( home_url(), PHP_URL_HOST ),
			'DTSTAMP:' . Dates::ics_utc( Clock::now() ),
		);

		if ( $event->is_all_day() ) {
			$lines[] = 'DTSTART;VALUE=DATE:' . gmdate( 'Ymd', $event->get_start_timestamp() );
			$lines[] = 'DTEND;VALUE=DATE:' . gmdate( 'Ymd', $event->get_end_timestamp() + 1 );
		} else {
			$lines[] = 'DTSTART:' . Dates::ics_utc( $event->get_start_timestamp() );
			$lines[] = 'DTEND:' . Dates::ics_utc( $event->get_end_timestamp() );
		}

		$lines[] = 'SUMMARY:' . self::escape( get_the_title( $post ) );
		if ( '' !== $event->get_location() ) {
			$lines[] = 'LOCATION:' . self::escape( $event->get_location() );
		}
		$lines[] = 'URL:' . get_permalink( $post );
		$lines[] = 'LAST-MODIFIED:' . Dates::ics_utc( strtotime( $post->post_modified_gmt . ' UTC' ) );
		$lines[] = 'END:VEVENT';

		/**
		 * Filters the VEVENT lines of an event.
		 *
		 * @since 1.5.0
		 *
		 * @param string[] $lines Lines (without line endings).
		 * @param Event    $event Event.
		 */
		return apply_filters( 'acme_events_ical_vevent', $lines, $event );
	}

	/**
	 * Escapes a TEXT value.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function escape( $text ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );
		return str_replace( array( '\\', ';', ',', "\r\n", "\n" ), array( '\\\\', '\;', '\,', '\n', '\n' ), $text );
	}

	/**
	 * Folds a content line at 75 octets.
	 *
	 * @param string $line Line.
	 * @return string
	 */
	public static function fold( $line ) {
		if ( strlen( $line ) <= 75 ) {
			return $line;
		}
		$out = '';
		while ( strlen( $line ) > 75 ) {
			$cut = 75;
			// Don't split multibyte characters.
			while ( $cut > 0 && ( ord( $line[ $cut ] ) & 0xC0 ) === 0x80 ) {
				--$cut;
			}
			$out .= substr( $line, 0, $cut ) . "\r\n ";
			$line  = substr( $line, $cut );
		}
		return $out . $line;
	}
}
