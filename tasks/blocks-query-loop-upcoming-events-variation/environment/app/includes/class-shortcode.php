<?php
/**
 * [acme_upcoming_events] shortcode.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Lists upcoming events.
 */
class Shortcode {

	const TAG = 'acme_upcoming_events';

	/**
	 * Register the shortcode.
	 */
	public function register() {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Render.
	 *
	 * @param array|string $atts Attributes: limit (default 5), show_venue (default 1).
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'      => 5,
				'show_venue' => '1',
			),
			$atts,
			self::TAG
		);

		$limit  = max( 1, min( 50, (int) $atts['limit'] ) );
		$events = Upcoming::get_events( $limit );

		if ( ! $events ) {
			return '<p class="acme-upcoming-events__empty">' . esc_html__( 'No upcoming events.', 'acme-events-lite' ) . '</p>';
		}

		$items = '';
		foreach ( $events as $event ) {
			$post   = $event->get_post();
			$venue  = $event->get_venue();
			$items .= sprintf(
				'<li class="acme-upcoming-events__item%1$s"><a class="acme-upcoming-events__title" href="%2$s">%3$s</a> <time class="acme-upcoming-events__date" datetime="%4$s">%5$s</time>%6$s</li>',
				'postponed' === $event->get_status() ? ' is-postponed' : '',
				esc_url( get_permalink( $post ) ),
				esc_html( get_the_title( $post ) ),
				esc_attr( event_datetime_attr( $post ) ),
				esc_html( format_event_date( $post ) ),
				( '' !== $venue && '0' !== (string) $atts['show_venue'] ) ? ' <span class="acme-upcoming-events__venue">' . esc_html( $venue ) . '</span>' : ''
			);
		}

		return '<ul class="acme-upcoming-events">' . $items . '</ul>';
	}
}
