<?php
/**
 * Template tags and helpers.
 *
 * @package Acme\Events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Meta key holding the event start ("Y-m-d H:i", site time).
 */
const ACME_EVENTS_META_START = '_acme_event_start';

/**
 * Meta key holding the event end ("Y-m-d H:i", site time, optional).
 */
const ACME_EVENTS_META_END = '_acme_event_end';

/**
 * Meta key holding the venue name.
 */
const ACME_EVENTS_META_VENUE = '_acme_event_venue';

/**
 * Get the start date of an event as a DateTimeImmutable (site timezone).
 *
 * @param int|WP_Post $post Event.
 * @return DateTimeImmutable|null
 */
function acme_events_get_start( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return null;
	}
	$raw = (string) get_post_meta( $post->ID, ACME_EVENTS_META_START, true );
	if ( '' === $raw ) {
		return null;
	}
	$date = date_create_immutable_from_format( 'Y-m-d H:i', $raw, wp_timezone() );
	return $date ? $date : null;
}

/**
 * Get the venue of an event.
 *
 * @param int|WP_Post $post Event.
 * @return string
 */
function acme_events_get_venue( $post ) {
	$post = get_post( $post );
	return $post ? (string) get_post_meta( $post->ID, ACME_EVENTS_META_VENUE, true ) : '';
}

/**
 * Human readable date of an event, e.g. "October 1, 2031 at 6:00 pm".
 *
 * @param int|WP_Post $post Event.
 * @return string
 */
function acme_events_format_date( $post ) {
	$start = acme_events_get_start( $post );
	if ( ! $start ) {
		return '';
	}
	/**
	 * Filters the date format used in event listings.
	 *
	 * @param string  $format PHP date format.
	 * @param WP_Post $post   The event.
	 */
	$format = apply_filters( 'acme_events_date_format', get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), get_post( $post ) );
	return wp_date( $format, $start->getTimestamp() );
}

/**
 * Interpret "yes"/"no" style shortcode values.
 *
 * @param mixed $value Value.
 * @return bool
 */
function acme_events_string_to_bool( $value ) {
	if ( is_bool( $value ) ) {
		return $value;
	}
	return in_array( strtolower( trim( (string) $value ) ), array( '1', 'yes', 'true', 'on' ), true );
}

/**
 * Locate a template, allowing themes to override it in `{theme}/acme-events/{name}`.
 *
 * @param string $name Template file name, e.g. "event-list.php".
 * @return string Absolute path.
 */
function acme_events_locate_template( $name ) {
	$name  = ltrim( $name, '/' );
	$theme = locate_template( array( 'acme-events/' . $name ) );
	$path  = $theme ? $theme : ACME_EVENTS_DIR . 'templates/' . $name;

	/**
	 * Filters the path of an Acme Events template.
	 *
	 * @param string $path Absolute path.
	 * @param string $name Template name.
	 */
	return apply_filters( 'acme_events_template', $path, $name );
}

/**
 * Render a template and return the output.
 *
 * @param string $name Template name.
 * @param array  $vars Variables made available to the template.
 * @return string
 */
function acme_events_get_template_html( $name, array $vars = array() ) {
	$path = acme_events_locate_template( $name );
	if ( ! is_readable( $path ) ) {
		return '';
	}
	ob_start();
	// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template variables.
	extract( $vars, EXTR_SKIP );
	include $path;
	return (string) ob_get_clean();
}
