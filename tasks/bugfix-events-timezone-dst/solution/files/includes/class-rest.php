<?php
/**
 * REST API for the mobile app.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * `GET /acme-events/v1/events`
 *
 * Query parameters: `upcoming` (bool, default true: only events that have not ended yet),
 * `per_page` (1–100, default 10).
 *
 * Each item: `id`, `title`, `link`, `all_day`, `start`, `end`, `location`, `timezone`.
 * Timed events: `start`/`end` are ISO 8601 date-times with the event's UTC offset on that date
 * (`2026-05-02T19:00:00-04:00`).
 * All-day events: `start`/`end` are dates (`2026-07-04`), `end` being the last day.
 */
class Rest {

	const NAMESPACE_V1 = 'acme-events/v1';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Registers the routes.
	 */
	public function routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/events',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_events' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'upcoming' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 10,
						'minimum' => 1,
						'maximum' => 100,
					),
				),
			)
		);
	}

	/**
	 * Lists events.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_events( \WP_REST_Request $request ) {
		if ( $request['upcoming'] ) {
			$events = Query::upcoming( array( 'limit' => (int) $request['per_page'] ) );
		} else {
			$posts  = get_posts(
				array(
					'post_type'      => Post_Type::NAME,
					'post_status'    => 'publish',
					'posts_per_page' => (int) $request['per_page'],
					'meta_key'       => '_acme_event_start_utc', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'orderby'        => array(
						'meta_value' => 'ASC',
						'ID'         => 'ASC',
					),
				)
			);
			$events = array_filter( array_map( array( Event::class, 'get' ), $posts ) );
		}

		$data = array();
		foreach ( $events as $event ) {
			$data[] = self::prepare( $event );
		}
		return rest_ensure_response( $data );
	}

	/**
	 * Response item for an event.
	 *
	 * @param Event $event Event.
	 * @return array
	 */
	public static function prepare( Event $event ) {
		if ( $event->is_all_day() ) {
			$start = $event->get_start_date();
			$end   = $event->get_end_date();
		} else {
			$start = Dates::iso8601( $event->get_start_timestamp(), $event->get_timezone() );
			$end   = Dates::iso8601( $event->get_end_timestamp(), $event->get_timezone() );
		}

		$item = array(
			'id'       => $event->get_id(),
			'title'    => html_entity_decode( get_the_title( $event->get_post() ), ENT_QUOTES, 'UTF-8' ),
			'link'     => get_permalink( $event->get_post() ),
			'all_day'  => $event->is_all_day(),
			'start'    => $start,
			'end'      => $end,
			'location' => $event->get_location(),
			'timezone' => $event->get_timezone_string(),
		);

		/**
		 * Filters the REST representation of an event.
		 *
		 * @since 1.5.0
		 *
		 * @param array $item  Item.
		 * @param Event $event Event.
		 */
		return apply_filters( 'acme_events_rest_item', $item, $event );
	}
}
