<?php
/**
 * Plugin Name: wpsb test: contact rate limit
 * Description: Lets the hidden tests control the rate limit through the plugin's own filter.
 */
add_filter(
	'acme_contact_rate_limit',
	static function () {
		$limit = (int) get_option( 'wpsb_contact_rate_limit', 0 );
		return $limit > 0 ? $limit : 100000;
	},
	1000
);
