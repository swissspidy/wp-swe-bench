<?php
/**
 * Upcoming events: the single definition of "upcoming", shared by the shortcode,
 * the "Upcoming events" Query Loop variation (front end + editor preview).
 *
 * An event is upcoming when it is published, not cancelled (incl. the 1.0 spelling
 * "canceled") and has not ended yet at now():
 * - events with an end: end > now;
 * - events without an end (no meta, or the 1.3 importer's 0) last until the end of
 *   their start day in the site timezone, i.e. they are upcoming while their start
 *   is on or after the start of today (site timezone).
 * Ordered by start, then ID.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Upcoming events.
 */
class Upcoming {

	/**
	 * Name of the meta query clause used for ordering by start.
	 */
	const START_CLAUSE = 'acme_event_start';

	/**
	 * Start of the local (site timezone) day that contains $time.
	 *
	 * @param int $time Timestamp.
	 * @return int
	 */
	public static function start_of_local_day( $time ) {
		return ( new \DateTimeImmutable( '@' . (int) $time ) )
			->setTimezone( wp_timezone() )
			->setTime( 0, 0, 0 )
			->getTimestamp();
	}

	/**
	 * Meta query selecting upcoming, non-cancelled events.
	 *
	 * @param int|null $time Reference time, defaults to now().
	 * @return array
	 */
	public static function meta_query( $time = null ) {
		$time  = null === $time ? now() : (int) $time;
		$today = self::start_of_local_day( $time );

		return array(
			'relation'         => 'AND',
			self::START_CLAUSE => array(
				'key'     => META_START,
				'compare' => 'EXISTS',
				'type'    => 'NUMERIC',
			),
			array(
				'relation' => 'OR',
				// Explicit end still in the future.
				array(
					'key'     => META_END,
					'value'   => $time,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
				// No end: lasts until the end of its (local) start day.
				array(
					'relation' => 'AND',
					array(
						'relation' => 'OR',
						array(
							'key'     => META_END,
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => META_END,
							'value'   => 0,
							'compare' => '<=',
							'type'    => 'NUMERIC',
						),
					),
					array(
						'key'     => META_START,
						'value'   => $today,
						'compare' => '>=',
						'type'    => 'NUMERIC',
					),
				),
			),
			array(
				'relation' => 'OR',
				array(
					'key'     => META_STATUS,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => META_STATUS,
					'value'   => array( 'cancelled', 'canceled' ),
					'compare' => 'NOT IN',
				),
			),
		);
	}

	/**
	 * Turn (Query Loop / REST / custom) WP_Query vars into an upcoming events query.
	 * Keeps paging related vars (posts_per_page, paged, offset) untouched.
	 *
	 * @param array    $vars WP_Query vars.
	 * @param int|null $time Reference time, defaults to now().
	 * @return array
	 */
	public static function apply( array $vars, $time = null ) {
		$vars['post_type']           = POST_TYPE;
		$vars['post_status']         = 'publish';
		$vars['ignore_sticky_posts'] = true;
		$vars['meta_query']          = self::meta_query( $time ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_query
		$vars['orderby']             = array(
			self::START_CLAUSE => 'ASC',
			'ID'               => 'ASC',
		);
		unset( $vars['order'], $vars['meta_key'], $vars['meta_value'], $vars['meta_compare'], $vars['s'], $vars['search'] );
		return $vars;
	}

	/**
	 * WP_Query arguments for upcoming events.
	 *
	 * @param int $limit Max number of events.
	 * @return array
	 */
	public static function query_args( $limit = 5 ) {
		$args = self::apply(
			array(
				'posts_per_page' => (int) $limit,
				'no_found_rows'  => true,
			)
		);

		/**
		 * Filters the upcoming events query arguments.
		 *
		 * @param array $args  WP_Query arguments.
		 * @param int   $limit Requested limit.
		 */
		return apply_filters( 'acme_events_upcoming_query_args', $args, $limit );
	}

	/**
	 * Upcoming events.
	 *
	 * @param int $limit Max number of events.
	 * @return Event[]
	 */
	public static function get_events( $limit = 5 ) {
		$query = new \WP_Query( self::query_args( $limit ) );
		return array_map(
			static function ( $post ) {
				return new Event( $post );
			},
			$query->posts
		);
	}
}
