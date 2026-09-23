<?php
/**
 * Plugin Name: wpsb events clock (grading only)
 * Description: Pins Acme Events' clock (acme_events_now) to the `wpsb_events_now` option when set.
 */

add_filter(
	'acme_events_now',
	static function ( $now ) {
		$fixed = (int) get_option( 'wpsb_events_now', 0 );
		return $fixed > 0 ? $fixed : $now;
	},
	1000
);
