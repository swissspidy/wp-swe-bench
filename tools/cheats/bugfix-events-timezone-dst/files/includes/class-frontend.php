<?php
/**
 * Front end: event details and the upcoming events list.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end output.
 *
 * Markup (themes style it, do not change the classes):
 *
 *     <div class="acme-event-details">
 *       <p class="acme-event-when">
 *         <time class="acme-event-start" datetime="2026-05-02T19:00:00-04:00">May 2, 2026 7:00 pm</time>
 *         – <time class="acme-event-end" datetime="2026-05-02T21:00:00-04:00">9:00 pm</time>
 *       </p>
 *       <p class="acme-event-location">Main hall</p>
 *     </div>
 *
 * All-day events use dates (`datetime="2026-07-04"`) and add `<span class="acme-event-all-day">All day</span>`.
 */
class Frontend {

	/**
	 * Hooks.
	 */
	public function register() {
		add_filter( 'the_content', array( $this, 'append_details' ), 20 );
		add_shortcode( 'acme_upcoming_events', array( $this, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueues the stylesheet.
	 */
	public function enqueue() {
		wp_register_style( 'acme-events', ACME_EVENTS_URL . 'assets/events.css', array(), ACME_EVENTS_VERSION );
		if ( is_singular( Post_Type::NAME ) || ( is_singular() && has_shortcode( (string) get_post_field( 'post_content' ), 'acme_upcoming_events' ) ) ) {
			wp_enqueue_style( 'acme-events' );
		}
	}

	/**
	 * Appends the details box on single events.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public function append_details( $content ) {
		if ( ! is_singular( Post_Type::NAME ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$event = Event::get();
		if ( ! $event || ! $event->has_dates() ) {
			return $content;
		}
		return $content . self::render_details( $event );
	}

	/**
	 * Details box.
	 *
	 * @param Event $event Event.
	 * @return string
	 */
	public static function render_details( Event $event ) {
		$html  = '<div class="acme-event-details">';
		$html .= '<p class="acme-event-when">' . self::render_when( $event ) . '</p>';
		if ( '' !== $event->get_location() ) {
			$html .= '<p class="acme-event-location">' . esc_html( $event->get_location() ) . '</p>';
		}
		$html .= '</div>';

		/**
		 * Filters the event details markup.
		 *
		 * @since 1.1.0
		 *
		 * @param string $html  Markup.
		 * @param Event  $event Event.
		 */
		return apply_filters( 'acme_events_details_html', $html, $event );
	}

	/**
	 * The `<time>` elements for an event.
	 *
	 * @param Event $event Event.
	 * @return string
	 */
	public static function render_when( Event $event ) {
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );

		if ( $event->is_all_day() ) {
			$start_date = Dates::date_part( $event->get_start_local() );
			$end_date   = Dates::date_part( $event->get_end_local() );
			$html       = sprintf(
				'<time class="acme-event-start" datetime="%s">%s</time>',
				esc_attr( $start_date ),
				esc_html( wp_date( $date_format, strtotime( $start_date ), new \DateTimeZone( 'UTC' ) ) )
			);
			if ( $end_date !== $start_date ) {
				$html .= sprintf(
					' &ndash; <time class="acme-event-end" datetime="%s">%s</time>',
					esc_attr( $end_date ),
					esc_html( wp_date( $date_format, strtotime( $end_date ), new \DateTimeZone( 'UTC' ) ) )
				);
			}
			return $html . ' <span class="acme-event-all-day">' . esc_html__( 'All day', 'acme-events' ) . '</span>';
		}

		$start = $event->get_start_timestamp();
		$end   = $event->get_end_timestamp();
		$html  = sprintf(
			'<time class="acme-event-start" datetime="%s">%s</time>',
			esc_attr( wp_date( DATE_ATOM, $start ) ),
			esc_html( wp_date( $date_format . ' ' . $time_format, $start ) )
		);
		if ( $end > $start ) {
			$html .= sprintf(
				' &ndash; <time class="acme-event-end" datetime="%s">%s</time>',
				esc_attr( wp_date( DATE_ATOM, $end ) ),
				esc_html( wp_date( $event->is_multi_day() ? $date_format . ' ' . $time_format : $time_format, $end ) )
			);
		}
		return $html;
	}

	/**
	 * Short start label (admin list, upcoming list).
	 *
	 * @param Event $event Event.
	 * @return string
	 */
	public static function format_start( Event $event ) {
		if ( $event->is_all_day() ) {
			return wp_date( get_option( 'date_format' ), strtotime( Dates::date_part( $event->get_start_local() ) ), new \DateTimeZone( 'UTC' ) );
		}
		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $event->get_start_timestamp() );
	}

	/**
	 * `[acme_upcoming_events limit="5" category="12,13"]`
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'    => '',
				'category' => '',
			),
			$atts,
			'acme_upcoming_events'
		);

		$args = array();
		if ( '' !== $atts['limit'] ) {
			$args['limit'] = absint( $atts['limit'] );
		}
		if ( '' !== $atts['category'] ) {
			$args['category'] = wp_parse_id_list( $atts['category'] );
		}

		$events = Query::upcoming( $args );
		if ( ! $events ) {
			return '<p class="acme-upcoming-events-empty">' . esc_html__( 'No upcoming events.', 'acme-events' ) . '</p>';
		}

		$html = '<ul class="acme-upcoming-events">';
		foreach ( $events as $event ) {
			$html .= sprintf(
				'<li class="acme-upcoming-event" data-event-id="%d"><a href="%s">%s</a> <span class="acme-upcoming-event-date">%s</span></li>',
				$event->get_id(),
				esc_url( get_permalink( $event->get_post() ) ),
				esc_html( get_the_title( $event->get_post() ) ),
				esc_html( self::format_start( $event ) )
			);
		}
		return $html . '</ul>';
	}
}
