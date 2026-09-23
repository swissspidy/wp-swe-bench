<?php
/**
 * Helper functions.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Post type slug.
 */
const POST_TYPE = 'acme_event';

/**
 * Meta keys (see readme.txt, "Data model").
 */
const META_START   = '_acme_event_start';
const META_END     = '_acme_event_end';
const META_ALL_DAY = '_acme_event_all_day';
const META_STATUS  = '_acme_event_status';
const META_VENUE   = '_acme_event_venue';

/**
 * The plugin's clock: current Unix timestamp (UTC).
 *
 * Everything that compares event dates with "now" goes through this function so
 * that staging sites can time-travel with the `acme_events_now` filter.
 *
 * @return int
 */
function now() {
	/**
	 * Filters the current time used by Acme Events (Unix timestamp, UTC).
	 *
	 * @param int $now Current timestamp.
	 */
	return (int) apply_filters( 'acme_events_now', time() );
}

/**
 * Get an Event object.
 *
 * @param \WP_Post|int|null $post Post.
 * @return Event|null
 */
function get_event( $post = null ) {
	$post = get_post( $post );
	if ( ! $post || POST_TYPE !== $post->post_type ) {
		return null;
	}
	return new Event( $post );
}

/**
 * Human readable start date of an event, in the site's timezone and formats.
 *
 * All-day events show the date only, other events "{date} {time}".
 *
 * @param \WP_Post|int|null $post Post.
 * @return string Empty string when the post is not an event or has no start.
 */
function format_event_date( $post = null ) {
	$event = get_event( $post );
	if ( ! $event || ! $event->get_start() ) {
		return '';
	}
	$format = get_option( 'date_format' );
	if ( ! $event->is_all_day() ) {
		$format .= ' ' . get_option( 'time_format' );
	}
	return (string) wp_date( $format, $event->get_start() );
}

/**
 * Start of an event as ISO 8601 with the site's UTC offset (for <time datetime>).
 *
 * @param \WP_Post|int|null $post Post.
 * @return string
 */
function event_datetime_attr( $post = null ) {
	$event = get_event( $post );
	if ( ! $event || ! $event->get_start() ) {
		return '';
	}
	return (string) wp_date( 'c', $event->get_start() );
}

/**
 * Parse a local date/time string ("Y-m-d H:i" in the site timezone) to a timestamp.
 *
 * @param string $local Local date/time.
 * @return int 0 when invalid.
 */
function local_to_timestamp( $local ) {
	try {
		$date = new \DateTimeImmutable( (string) $local, wp_timezone() );
	} catch ( \Exception $e ) {
		return 0;
	}
	return $date->getTimestamp();
}
