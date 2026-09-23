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
 * Stored meta (1.6):
 * - `_acme_event_start` / `_acme_event_end`: local strings (`Y-m-d H:i:s`). For all-day events the
 *   start is the first day at 00:00:00 and the end the last day at 23:59:59.
 * - `_acme_event_start_ts` / `_acme_event_end_ts`: Unix timestamps, for sorting and queries.
 * - `_acme_event_all_day`: '1' for all-day events.
 * - `_acme_event_location`: free text.
 *
 * Events created with 1.0–1.2 only have `_acme_event_timestamp` (a local timestamp) and
 * `_acme_event_duration` (minutes); they are read transparently.
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
		return '1' === (string) get_post_meta( $this->post->ID, '_acme_event_all_day', true );
	}

	/**
	 * Start as a local string.
	 *
	 * @return string
	 */
	public function get_start_local() {
		$start = (string) get_post_meta( $this->post->ID, '_acme_event_start', true );
		if ( '' === $start && $this->is_legacy() ) {
			$start = gmdate( Dates::MYSQL, (int) get_post_meta( $this->post->ID, '_acme_event_timestamp', true ) );
		}
		return $start;
	}

	/**
	 * End as a local string.
	 *
	 * @return string
	 */
	public function get_end_local() {
		$end = (string) get_post_meta( $this->post->ID, '_acme_event_end', true );
		if ( '' === $end && $this->is_legacy() ) {
			$start    = (int) get_post_meta( $this->post->ID, '_acme_event_timestamp', true );
			$duration = (int) get_post_meta( $this->post->ID, '_acme_event_duration', true );
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
	 * Start as a Unix timestamp.
	 *
	 * @return int
	 */
	public function get_start_timestamp() {
		$ts = get_post_meta( $this->post->ID, '_acme_event_start_ts', true );
		if ( '' === $ts ) {
			return Dates::local_to_timestamp( $this->get_start_local() );
		}
		return (int) $ts;
	}

	/**
	 * End as a Unix timestamp.
	 *
	 * @return int
	 */
	public function get_end_timestamp() {
		$ts = get_post_meta( $this->post->ID, '_acme_event_end_ts', true );
		if ( '' === $ts ) {
			return Dates::local_to_timestamp( $this->get_end_local() );
		}
		return (int) $ts;
	}

	/**
	 * Location.
	 *
	 * @return string
	 */
	public function get_location() {
		return (string) get_post_meta( $this->post->ID, '_acme_event_location', true );
	}

	/**
	 * Whether the event only has the 1.0 data format.
	 *
	 * @return bool
	 */
	public function is_legacy() {
		return '' === (string) get_post_meta( $this->post->ID, '_acme_event_start', true )
			&& '' !== (string) get_post_meta( $this->post->ID, '_acme_event_timestamp', true );
	}

	/**
	 * Whether the event spans more than one (local) day.
	 *
	 * @return bool
	 */
	public function is_multi_day() {
		return Dates::date_part( $this->get_start_local() ) !== Dates::date_part( $this->get_end_local() );
	}

	/**
	 * Saves the event dates.
	 *
	 * @param string $start   Start (`Y-m-d H:i`, or `Y-m-d` for all-day events), site time.
	 * @param string $end     End (same formats). Empty: same as start.
	 * @param bool   $all_day All-day event.
	 * @return true|\WP_Error
	 */
	public function save_dates( $start, $end = '', $all_day = false ) {
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
		if ( $end_local < $start_local ) {
			return new \WP_Error( 'acme_events_end_before_start', __( 'The event cannot end before it starts.', 'acme-events' ) );
		}

		$id = $this->post->ID;
		update_post_meta( $id, '_acme_event_start', $start_local );
		update_post_meta( $id, '_acme_event_end', $end_local );
		update_post_meta( $id, '_acme_event_start_ts', Dates::local_to_timestamp( $start_local ) );
		update_post_meta( $id, '_acme_event_end_ts', Dates::local_to_timestamp( $end_local ) );
		if ( $all_day ) {
			update_post_meta( $id, '_acme_event_all_day', '1' );
		} else {
			delete_post_meta( $id, '_acme_event_all_day' );
		}
		// Converted to the current format: drop the 1.0 fields.
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
}
