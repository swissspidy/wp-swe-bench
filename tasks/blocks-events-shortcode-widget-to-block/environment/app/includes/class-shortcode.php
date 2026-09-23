<?php
/**
 * The [acme_events] shortcode.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Renders event listings: `[acme_events limit="5" category="workshops" show_past="no" layout="list" title="" show_venue="yes"]`.
 *
 * The widget renders through this class as well.
 *
 * @todo The query + template code should move into its own class at some point.
 */
class Shortcode {

	const TAG = 'acme_events';

	/**
	 * Hard cap on the number of events in one listing.
	 */
	const MAX_LIMIT = 20;

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the shortcode.
	 */
	public function register() {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Default shortcode attributes.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'limit'      => 5,
			'category'   => '',
			'show_past'  => 'no',
			'layout'     => 'list',
			'title'      => '',
			'show_venue' => 'yes',
		);
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array|string $atts    Attributes.
	 * @param string       $content Unused.
	 * @param string       $tag     Shortcode tag.
	 * @return string
	 */
	public function render( $atts, $content = '', $tag = self::TAG ) {
		$atts = shortcode_atts( self::defaults(), $atts, $tag ? $tag : self::TAG );

		// Normalize.
		$limit      = min( self::MAX_LIMIT, max( 1, absint( $atts['limit'] ) ) );
		$layout     = in_array( $atts['layout'], array( 'list', 'grid' ), true ) ? $atts['layout'] : 'list';
		$categories = array_values( array_filter( array_map( 'sanitize_title', explode( ',', (string) $atts['category'] ) ) ) );
		$options    = array(
			'limit'      => $limit,
			'category'   => implode( ',', $categories ),
			'show_past'  => acme_events_string_to_bool( $atts['show_past'] ),
			'layout'     => $layout,
			'title'      => trim( wp_strip_all_tags( (string) $atts['title'] ) ),
			'show_venue' => acme_events_string_to_bool( $atts['show_venue'] ),
		);

		// Query.
		$query_args = array(
			'post_type'           => Post_Type::POST_TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'meta_key'            => ACME_EVENTS_META_START, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'             => 'meta_value',
			'order'               => $options['show_past'] ? 'DESC' : 'ASC',
			'meta_query'          => array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);
		if ( ! $options['show_past'] ) {
			$query_args['meta_query'][] = array(
				'key'     => ACME_EVENTS_META_START,
				'value'   => current_time( 'Y-m-d H:i' ),
				'compare' => '>=',
				'type'    => 'CHAR',
			);
		}
		if ( $categories ) {
			$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => Post_Type::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $categories,
				),
			);
		}

		/**
		 * Filters the WP_Query arguments of an event listing.
		 *
		 * @param array $query_args WP_Query arguments.
		 * @param array $options    Normalized listing options (limit, category, show_past, layout, title, show_venue).
		 */
		$query_args = apply_filters( 'acme_events_query_args', $query_args, $options );
		$query      = new \WP_Query( $query_args );

		// Render.
		$items = array();
		foreach ( $query->posts as $event ) {
			$start = acme_events_get_start( $event );
			$html  = acme_events_get_template_html(
				'event-' . $layout . '.php',
				array(
					'event'      => $event,
					'date'       => acme_events_format_date( $event ),
					'datetime'   => $start ? $start->format( 'Y-m-d\TH:i' ) : '',
					'venue'      => acme_events_get_venue( $event ),
					'show_venue' => $options['show_venue'],
					'options'    => $options,
				)
			);

			/**
			 * Filters the markup of a single event in a listing.
			 *
			 * @param string   $html    Item markup.
			 * @param \WP_Post $event   The event.
			 * @param array    $options Normalized listing options.
			 */
			$items[] = apply_filters( 'acme_events_item_html', $html, $event, $options );
		}

		/**
		 * Filters the message shown when a listing has no events.
		 *
		 * @param string $message Message (plain text).
		 * @param array  $options Normalized listing options.
		 */
		$empty = apply_filters( 'acme_events_empty_message', __( 'No upcoming events.', 'acme-events' ), $options );

		return acme_events_get_template_html(
			'events.php',
			array(
				'items'   => $items,
				'layout'  => $layout,
				'title'   => $options['title'],
				'empty'   => $empty,
				'options' => $options,
			)
		);
	}
}
