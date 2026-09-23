<?php
/**
 * Upcoming events query (used by the shortcode and the mobile app endpoint).
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
	 * WP_Query arguments for upcoming events.
	 *
	 * @param int $limit Max number of events.
	 * @return array
	 */
	public static function query_args( $limit = 5 ) {
		// Everything from today on.
		$today = strtotime( 'today', now() );

		$args = array(
			'post_type'           => POST_TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => (int) $limit,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'meta_key'            => META_START, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key
			'orderby'             => 'meta_value_num',
			'order'               => 'ASC',
			'meta_query'          => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_query
				'relation' => 'AND',
				array(
					'key'     => META_START,
					'value'   => $today,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => META_STATUS,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => META_STATUS,
						'value'   => 'cancelled',
						'compare' => '!=',
					),
				),
			),
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
		$query  = new \WP_Query( self::query_args( $limit ) );
		$events = array();
		foreach ( $query->posts as $post ) {
			$event = new Event( $post );
			if ( $event->has_ended() ) {
				continue;
			}
			$events[] = $event;
		}
		return $events;
	}
}
