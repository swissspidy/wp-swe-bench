<?php
/**
 * Event model.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Read access to an event's data.
 *
 * Stored meta (2.0):
 * - `_acme_event_timezone`: the timezone the event's times are in (a named zone such as
 *   `America/New_York`, or a fixed offset such as `+05:30`): the site's timezone when the event was
 *   first saved (or when it was upgraded to 2.0).
 * - `_acme_event_start_utc` / `_acme_event_end_utc`: `Y-m-d H:i:s` in UTC. For all-day events: midnight
 *   (event timezone) at the start of the first day / at the end of the last day.
 * - `_acme_event_start` / `_acme_event_end`: wall-clock time (`Y-m-d H:i:s`) in the event's timezone.
 *   For all-day events the start is the first day at 00:00:00 and the end the last day at 23:59:59.
 * - `_acme_event_all_day`: '1' for all-day events.
 * - `_acme_event_location`: free text.
 *
 * Events created with 1.0–1.2 only had `_acme_event_timestamp` (a local timestamp) and
 * `_acme_event_duration` (minutes), events from 1.3–1.6 local strings and timestamps computed with
 * the wrong UTC offset. The 2.0 upgrade converts both (see Upgrader); until then they are read here.
 */
class Event {

	/**
	 * Post object.
	 *
	 * @var \WP_Post
	 */
	protected $post;

	/**
	 * Constructor.
	 *
	 * @param \WP_Post $post Event post.
	 */
	public function __construct( \WP_Post $post ) {
		$this->post = $post;
	}

	/**
	 * Gets an event object for a post.
	 *
	 * @param int|\WP_Post|null $post Post.
	 * @return Event|null
	 */
	public static function get( $post = null ) {
		$post = get_post( $post );
		if ( ! $post || Post_Type::NAME !== $post->post_type ) {
			return null;
		}
		return new static( $post );
	}

	/**
	 * Post ID.
	 *
	 * @return int
	 */
	public function get_id() {
		return (int) $this->post->ID;
	}

	/**
	 * Post object.
	 *
	 * @return \WP_Post
	 */
	public function get_post() {
		return $this->post;
	}

	/**
	 * Meta value helper.
	 *
	 * @param string $key Meta key.
	 * @return string
	 */
	protected function meta( $key ) {
		return (string) get_post_meta( $this->post->ID, $key, true );
	}

	/**
	 * Whether the event has dates at all.
	 *
	 * @return bool
	 */
	public function has_dates() {
		return '' !== $this->get_start_local();
	}

	/**
	 * Whether this is an all-day event.
	 *
	 * @return bool
	 */
	public function is_all_day() {
		return '1' === $this->meta( '_acme_event_all_day' );
	}

	/**
	 * Whether the event has been stored in the 2.0 format (with its own timezone).
	 *
	 * @return bool
	 */
	public function has_timezone() {
		return '' !== $this->meta( '_acme_event_timezone' );
	}

	/**
	 * Timezone string of the event (named zone or offset).
	 *
	 * @return string
	 */
	public function get_timezone_string() {
		$tz = $this->meta( '_acme_event_timezone' );
		return '' !== $tz ? $tz : Dates::site_timezone_string();
	}

	/**
	 * Timezone of the event.
	 *
	 * @return \DateTimeZone
	 */
	public function get_timezone() {
		return Dates::timezone( $this->get_timezone_string() );
	}

	/**
	 * Start as a local string (event timezone).
	 *
	 * @return string
	 */
	public function get_start_local() {
		$start = $this->meta( '_acme_event_start' );
		if ( '' === $start && $this->is_legacy() ) {
			$start = gmdate( Dates::MYSQL, (int) $this->meta( '_acme_event_timestamp' ) );
		}
		return $start;
	}

	/**
	 * End as a local string (event timezone).
	 *
	 * @return string
	 */
	public function get_end_local() {
		$end = $this->meta( '_acme_event_end' );
		if ( '' === $end && $this->is_legacy() ) {
			$start    = (int) $this->meta( '_acme_event_timestamp' );
			$duration = (int) $this->meta( '_acme_event_duration' );
			if ( $this->is_all_day() ) {
				// 1.0 stored all-day events as midnight + a whole number of days.
				$end = gmdate( Dates::MYSQL, $start + max( 1, (int) round( $duration / 1440 ) ) * DAY_IN_SECONDS - 1 );
			} else {
				$end = gmdate( Dates::MYSQL, $start + $duration * MINUTE_IN_SECONDS );
			}
		}
		if ( '' === $end ) {
			$end = $this->get_start_local();
		}
		return $end;
	}

	/**
	 * First day (`Y-m-d`, event timezone).
	 *
	 * @return string
	 */
	public function get_start_date() {
		return Dates::date_part( $this->get_start_local() );
	}

	/**
	 * Last day (`Y-m-d`, event timezone). For timed events, the day the event ends.
	 *
	 * @return string
	 */
	public function get_end_date() {
		return Dates::date_part( $this->get_end_local() );
	}

	/**
	 * Start in UTC (`Y-m-d H:i:s`).
	 *
	 * @return string
	 */
	public function get_start_utc() {
		$utc = $this->meta( '_acme_event_start_utc' );
		if ( '' !== $utc ) {
			return $utc;
		}
		$start = $this->is_all_day() ? $this->get_start_date() . ' 00:00:00' : $this->get_start_local();
		return Dates::local_to_utc( $start, $this->get_timezone() );
	}

	/**
	 * End in UTC (`Y-m-d H:i:s`). All-day events end at midnight after their last day.
	 *
	 * @return string
	 */
	public function get_end_utc() {
		$utc = $this->meta( '_acme_event_end_utc' );
		if ( '' !== $utc ) {
			return $utc;
		}
		if ( $this->is_all_day() ) {
			return Dates::local_to_utc( Dates::next_day( $this->get_end_date() ) . ' 00:00:00', $this->get_timezone() );
		}
		return Dates::local_to_utc( $this->get_end_local(), $this->get_timezone() );
	}

	/**
	 * Start as a Unix timestamp.
	 *
	 * @return int
	 */
	public function get_start_timestamp() {
		return Dates::utc_to_timestamp( $this->get_start_utc() );
	}

	/**
	 * End as a Unix timestamp.
	 *
	 * @return int
	 */
	public function get_end_timestamp() {
		return Dates::utc_to_timestamp( $this->get_end_utc() );
	}

	/**
	 * Location.
	 *
	 * @return string
	 */
	public function get_location() {
		return $this->meta( '_acme_event_location' );
	}

	/**
	 * Whether the event only has the 1.0 data format.
	 *
	 * @return bool
	 */
	public function is_legacy() {
		return '' === $this->meta( '_acme_event_start' ) && '' !== $this->meta( '_acme_event_timestamp' );
	}

	/**
	 * Whether the event spans more than one day (in its timezone).
	 *
	 * @return bool
	 */
	public function is_multi_day() {
		return $this->get_start_date() !== $this->get_end_date();
	}

	/**
	 * Saves the event dates.
	 *
	 * The times are wall-clock times in the event's timezone. Events that don't have a timezone yet
	 * get the site's current timezone (or $timezone if given).
	 *
	 * @param string      $start    Start (`Y-m-d H:i`, or `Y-m-d` for all-day events).
	 * @param string      $end      End (same formats). Empty: same as start.
	 * @param bool        $all_day  All-day event.
	 * @param string|null $timezone Timezone string to use instead of the event's/site's.
	 * @return true|\WP_Error
	 */
	public function save_dates( $start, $end = '', $all_day = false, $timezone = null ) {
		$start_local = Dates::normalize_local( $start, '00:00:00' );
		if ( null === $start_local ) {
			return new \WP_Error( 'acme_events_invalid_start', __( 'Invalid start date.', 'acme-events' ) );
		}
		if ( '' === (string) $end ) {
			$end = $all_day ? Dates::date_part( $start_local ) : $start_local;
		}
		$end_local = Dates::normalize_local( $end, $all_day ? '23:59:59' : '00:00:00' );
		if ( null === $end_local ) {
			return new \WP_Error( 'acme_events_invalid_end', __( 'Invalid end date.', 'acme-events' ) );
		}
		if ( $all_day ) {
			$start_local = Dates::date_part( $start_local ) . ' 00:00:00';
			$end_local   = Dates::date_part( $end_local ) . ' 23:59:59';
		}

		$tz_string = null !== $timezone && '' !== $timezone ? (string) $timezone : $this->get_timezone_string();
		$tz        = Dates::timezone( $tz_string );

		if ( $all_day ) {
			$start_utc = Dates::local_to_utc( Dates::date_part( $start_local ) . ' 00:00:00', $tz );
			$end_utc   = Dates::local_to_utc( Dates::next_day( Dates::date_part( $end_local ) ) . ' 00:00:00', $tz );
		} else {
			$start_utc = Dates::local_to_utc( $start_local, $tz );
			$end_utc   = Dates::local_to_utc( $end_local, $tz );
		}
		if ( $end_utc < $start_utc ) {
			return new \WP_Error( 'acme_events_end_before_start', __( 'The event cannot end before it starts.', 'acme-events' ) );
		}

		$id = $this->post->ID;
		update_post_meta( $id, '_acme_event_timezone', $tz_string );
		update_post_meta( $id, '_acme_event_start', $start_local );
		update_post_meta( $id, '_acme_event_end', $end_local );
		update_post_meta( $id, '_acme_event_start_utc', $start_utc );
		update_post_meta( $id, '_acme_event_end_utc', $end_utc );
		if ( $all_day ) {
			update_post_meta( $id, '_acme_event_all_day', '1' );
		} else {
			delete_post_meta( $id, '_acme_event_all_day' );
		}
		// Superseded by the fields above: the 1.6 timestamps and the 1.0 fields.
		delete_post_meta( $id, '_acme_event_start_ts' );
		delete_post_meta( $id, '_acme_event_end_ts' );
		delete_post_meta( $id, '_acme_event_timestamp' );
		delete_post_meta( $id, '_acme_event_duration' );

		/**
		 * Fires after the dates of an event were saved.
		 *
		 * @since 1.3.0
		 *
		 * @param int   $id    Event ID.
		 * @param Event $event Event.
		 */
		do_action( 'acme_events_dates_saved', $id, $this );

		return true;
	}

	/**
	 * Converts an event stored by 1.x to the 2.0 format, keeping its wall-clock times and
	 * interpreting them in the site's current timezone. No-op for events already converted.
	 *
	 * @return bool Whether the event was converted.
	 */
	public function upgrade() {
		if ( $this->has_timezone() || ! $this->has_dates() ) {
			return false;
		}
		$all_day = $this->is_all_day();
		$start   = $all_day ? $this->get_start_date() : $this->get_start_local();
		$end     = $all_day ? $this->get_end_date() : $this->get_end_local();
		return true === $this->save_dates( $start, $end, $all_day, Dates::site_timezone_string() );
	}
}
