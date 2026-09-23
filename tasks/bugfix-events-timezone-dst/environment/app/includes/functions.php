<?php
/**
 * Public API and template tags.
 *
 * @package Acme\Events
 */

defined( 'ABSPATH' ) || exit;

use Acme\Events\Event;
use Acme\Events\Frontend;
use Acme\Events\Query;

/**
 * Saves the dates of an event (used by the event editor and by our importers).
 *
 * @since 1.3.0
 *
 * @param int    $post_id Event ID.
 * @param string $start   Start: `Y-m-d H:i` (or `Y-m-d` for all-day events), site time.
 * @param string $end     End, same format. For all-day events the last day. Empty: same as start.
 * @param bool   $all_day Whether this is an all-day event.
 * @return true|WP_Error
 */
function acme_events_save_event_dates( $post_id, $start, $end = '', $all_day = false ) {
	$event = Event::get( $post_id );
	if ( ! $event ) {
		return new WP_Error( 'acme_events_not_an_event', __( 'Not an event.', 'acme-events' ) );
	}
	return $event->save_dates( $start, $end, $all_day );
}

/**
 * Upcoming events.
 *
 * @since 1.0.0
 *
 * @param array $args See Query::upcoming().
 * @return Event[]
 */
function acme_events_get_upcoming( array $args = array() ) {
	return Query::upcoming( $args );
}

/**
 * Echoes the `<time>` markup of an event.
 *
 * @since 1.1.0
 *
 * @param int|WP_Post|null $post Event.
 */
function acme_events_the_when( $post = null ) {
	$event = Event::get( $post );
	if ( $event && $event->has_dates() ) {
		echo Frontend::render_when( $event ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in render_when().
	}
}
