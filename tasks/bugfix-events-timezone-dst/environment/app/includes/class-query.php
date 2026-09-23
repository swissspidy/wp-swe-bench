<?php
/**
 * Event queries.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Upcoming events.
 */
class Query {

	/**
	 * Events that have not ended yet, soonest first.
	 *
	 * @param array $args {
	 *     @type int   $limit    Maximum number of events. Default from the settings (5).
	 *     @type int[] $category Event category term IDs.
	 *     @type int   $now      Reference time (Unix timestamp). Default: now.
	 * }
	 * @return Event[]
	 */
	public static function upcoming( array $args = array() ) {
		$settings = get_option( 'acme_events_settings', array() );
		$args     = wp_parse_args(
			$args,
			array(
				'limit'    => isset( $settings['upcoming_limit'] ) ? (int) $settings['upcoming_limit'] : 5,
				'category' => array(),
				'now'      => Clock::now(),
			)
		);

		$query_args = array(
			'post_type'              => Post_Type::NAME,
			'post_status'            => 'publish',
			'posts_per_page'         => max( 1, (int) $args['limit'] ),
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'meta_key'               => '_acme_event_start_ts', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'                => array(
				'meta_value_num' => 'ASC',
				'ID'             => 'ASC',
			),
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_acme_event_end_ts',
					'value'   => (int) $args['now'],
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
		);

		if ( ! empty( $args['category'] ) ) {
			$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => Post_Type::TAXONOMY,
					'terms'    => array_map( 'absint', (array) $args['category'] ),
				),
			);
		}

		/**
		 * Filters the WP_Query arguments of the upcoming events query.
		 *
		 * @since 1.2.0
		 *
		 * @param array $query_args WP_Query arguments.
		 * @param array $args       Upcoming query arguments.
		 */
		$query_args = apply_filters( 'acme_events_upcoming_query_args', $query_args, $args );

		$query = new \WP_Query( $query_args );
		return array_values( array_filter( array_map( array( Event::class, 'get' ), $query->posts ) ) );
	}
}
