<?php
/**
 * Event value object.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only view of an event post and its meta.
 *
 * Data model (see readme.txt):
 * - _acme_event_start   int  Unix timestamp (UTC). Required.
 * - _acme_event_end     int  Unix timestamp (UTC). Optional: missing on events created
 *                            before 1.3, and stored as 0 by the 1.3 CSV importer.
 * - _acme_event_all_day bool All-day event (start is local midnight).
 * - _acme_event_status  string scheduled (default) | postponed | cancelled.
 *                            Events from 1.0 may say "canceled".
 * - _acme_event_venue   string Free text.
 */
class Event {

	/**
	 * Post.
	 *
	 * @var \WP_Post
	 */
	private $post;

	/**
	 * Constructor.
	 *
	 * @param \WP_Post $post Event post.
	 */
	public function __construct( \WP_Post $post ) {
		$this->post = $post;
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
	 * Start timestamp (0 if not set).
	 *
	 * @return int
	 */
	public function get_start() {
		return (int) get_post_meta( $this->post->ID, META_START, true );
	}

	/**
	 * Whether an explicit end is stored.
	 *
	 * @return bool
	 */
	public function has_end() {
		return (int) get_post_meta( $this->post->ID, META_END, true ) > 0;
	}

	/**
	 * Effective end timestamp.
	 *
	 * Events without an end (or with the importer's 0) last until the end of the day
	 * they start on, in the site's timezone. So do all-day events without an end.
	 *
	 * @return int
	 */
	public function get_end() {
		$end = (int) get_post_meta( $this->post->ID, META_END, true );
		if ( $end > 0 ) {
			return $end;
		}
		$start = $this->get_start();
		if ( ! $start ) {
			return 0;
		}
		$day_end = ( new \DateTimeImmutable( '@' . $start ) )
			->setTimezone( wp_timezone() )
			->setTime( 23, 59, 59 );
		return $day_end->getTimestamp();
	}

	/**
	 * All-day event?
	 *
	 * @return bool
	 */
	public function is_all_day() {
		return (bool) get_post_meta( $this->post->ID, META_ALL_DAY, true );
	}

	/**
	 * Normalized status.
	 *
	 * @return string scheduled|postponed|cancelled
	 */
	public function get_status() {
		$status = (string) get_post_meta( $this->post->ID, META_STATUS, true );
		if ( 'canceled' === $status ) {
			$status = 'cancelled'; // 1.0 spelling.
		}
		return in_array( $status, array( 'scheduled', 'postponed', 'cancelled' ), true ) ? $status : 'scheduled';
	}

	/**
	 * Cancelled?
	 *
	 * @return bool
	 */
	public function is_cancelled() {
		return 'cancelled' === $this->get_status();
	}

	/**
	 * Venue.
	 *
	 * @return string
	 */
	public function get_venue() {
		return (string) get_post_meta( $this->post->ID, META_VENUE, true );
	}

	/**
	 * Whether the event is over at the given time.
	 *
	 * @param int|null $time Timestamp, defaults to now().
	 * @return bool
	 */
	public function has_ended( $time = null ) {
		$time = null === $time ? now() : (int) $time;
		return $this->get_end() <= $time;
	}
}
