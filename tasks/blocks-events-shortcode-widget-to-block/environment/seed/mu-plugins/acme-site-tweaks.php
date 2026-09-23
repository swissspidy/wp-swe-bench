<?php
/**
 * Plugin Name: Acme site tweaks
 * Description: Site-specific customizations for the Acme site.
 */

/*
 * Cancelled events stay published (their page explains why) but must never show up in listings.
 */
add_filter(
	'acme_events_query_args',
	static function ( $args ) {
		$args['meta_query']   = isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$args['meta_query'][] = array(
			'relation' => 'OR',
			array(
				'key'     => '_acme_event_status',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_acme_event_status',
				'value'   => 'cancelled',
				'compare' => '!=',
			),
		);
		return $args;
	}
);

/*
 * Mark events that are "members only" in listings.
 */
add_filter(
	'acme_events_item_html',
	static function ( $html, $event ) {
		if ( get_post_meta( $event->ID, '_acme_members_only', true ) ) {
			$html = str_replace( 'class="acme-event ', 'class="acme-event acme-event--members-only ', $html );
		}
		return $html;
	},
	10,
	2
);
