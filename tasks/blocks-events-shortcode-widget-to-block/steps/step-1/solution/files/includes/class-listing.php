<?php
/**
 * Event listings: options, query and rendering shared by the shortcode, the widget and the block.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Builds and renders event listings.
 *
 * All front ends (the `[acme_events]` shortcode, the "Upcoming events" block and the classic
 * widget) describe a listing with the same normalized options:
 *
 * - `limit`      int    1–20
 * - `category`   string comma separated event category slugs ('' = all)
 * - `show_past`  bool   also list past events (newest first)
 * - `layout`     string `list` or `grid`
 * - `title`      string optional heading
 * - `show_venue` bool
 */
class Listing {

	/**
	 * Hard cap on the number of events in one listing.
	 */
	const MAX_LIMIT = 20;

	/**
	 * Supported layouts.
	 */
	const LAYOUTS = array( 'list', 'grid' );

	/**
	 * Default options.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'limit'      => 5,
			'category'   => '',
			'show_past'  => false,
			'layout'     => 'list',
			'title'      => '',
			'show_venue' => true,
		);
	}

	/**
	 * Options from raw shortcode attributes.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @param string       $tag  Shortcode tag (for the shortcode_atts_{$tag} filter).
	 * @return array
	 */
	public static function from_shortcode_atts( $atts, $tag = Shortcode::TAG ) {
		$atts = shortcode_atts( Shortcode::defaults(), $atts, $tag );
		return self::normalize( $atts );
	}

	/**
	 * Options from the attributes of the acme/upcoming-events block.
	 *
	 * @param array $attributes Block attributes.
	 * @return array
	 */
	public static function from_block_attributes( $attributes ) {
		$attributes = is_array( $attributes ) ? $attributes : array();
		$defaults   = self::defaults();
		return self::normalize(
			array(
				'limit'      => $attributes['limit'] ?? $defaults['limit'],
				'category'   => $attributes['category'] ?? $defaults['category'],
				'show_past'  => $attributes['showPast'] ?? $defaults['show_past'],
				'layout'     => $attributes['layout'] ?? $defaults['layout'],
				'title'      => $attributes['title'] ?? $defaults['title'],
				'show_venue' => $attributes['showVenue'] ?? $defaults['show_venue'],
			)
		);
	}

	/**
	 * Block attributes equivalent to a set of options (only non-default values).
	 *
	 * @param array $options Normalized options.
	 * @return array
	 */
	public static function to_block_attributes( array $options ) {
		$options  = self::normalize( $options );
		$defaults = self::defaults();
		$map      = array(
			'limit'      => 'limit',
			'category'   => 'category',
			'show_past'  => 'showPast',
			'layout'     => 'layout',
			'title'      => 'title',
			'show_venue' => 'showVenue',
		);
		$attrs    = array();
		foreach ( $map as $option => $attribute ) {
			if ( $options[ $option ] !== $defaults[ $option ] ) {
				$attrs[ $attribute ] = $options[ $option ];
			}
		}
		return $attrs;
	}

	/**
	 * Normalize raw option values (strings from shortcodes, typed values from blocks).
	 *
	 * @param array $raw Raw values keyed like the options.
	 * @return array
	 */
	public static function normalize( array $raw ) {
		$raw        = array_merge( self::defaults(), $raw );
		$limit      = is_numeric( $raw['limit'] ) ? (int) $raw['limit'] : 0;
		$layout     = is_string( $raw['layout'] ) && in_array( $raw['layout'], self::LAYOUTS, true ) ? $raw['layout'] : 'list';
		$categories = is_scalar( $raw['category'] ) ? explode( ',', (string) $raw['category'] ) : array();
		$categories = array_values( array_filter( array_map( 'sanitize_title', $categories ) ) );

		return array(
			'limit'      => min( self::MAX_LIMIT, max( 1, absint( $limit ) ) ),
			'category'   => implode( ',', $categories ),
			'show_past'  => is_scalar( $raw['show_past'] ) && acme_events_string_to_bool( $raw['show_past'] ),
			'layout'     => $layout,
			'title'      => is_scalar( $raw['title'] ) ? trim( wp_strip_all_tags( (string) $raw['title'] ) ) : '',
			'show_venue' => is_scalar( $raw['show_venue'] ) && acme_events_string_to_bool( $raw['show_venue'] ),
		);
	}

	/**
	 * WP_Query arguments for a listing (after the `acme_events_query_args` filter).
	 *
	 * @param array $options Normalized options.
	 * @return array
	 */
	public static function query_args( array $options ) {
		$query_args = array(
			'post_type'           => Post_Type::POST_TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => $options['limit'],
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
		if ( '' !== $options['category'] ) {
			$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => Post_Type::TAXONOMY,
					'field'    => 'slug',
					'terms'    => explode( ',', $options['category'] ),
				),
			);
		}

		/**
		 * Filters the WP_Query arguments of an event listing.
		 *
		 * @param array $query_args WP_Query arguments.
		 * @param array $options    Normalized listing options (limit, category, show_past, layout, title, show_venue).
		 */
		return apply_filters( 'acme_events_query_args', $query_args, $options );
	}

	/**
	 * Render a listing.
	 *
	 * @param array $options Normalized options.
	 * @return string
	 */
	public static function render( array $options ) {
		$options = self::normalize( $options );
		$query   = new \WP_Query( self::query_args( $options ) );
		$layout  = $options['layout'];

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
