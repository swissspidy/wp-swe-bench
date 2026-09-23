<?php
/**
 * Plugin Name: wpsb query counter (grading only)
 * Description: When a request carries the X-WPSB-Count-Queries header, appends the number of DB queries of the request as an HTML comment / response header.
 */

if ( empty( $_SERVER['HTTP_X_WPSB_COUNT_QUERIES'] ) ) {
	return;
}
if ( ! defined( 'SAVEQUERIES' ) ) {
	define( 'SAVEQUERIES', true );
}

add_filter(
	'rest_post_dispatch',
	static function ( $response ) {
		global $wpdb;
		if ( $response instanceof WP_REST_Response ) {
			$response->header( 'X-WPSB-Queries', (string) $wpdb->num_queries );
		}
		return $response;
	},
	PHP_INT_MAX
);

add_action(
	'shutdown',
	static function () {
		global $wpdb;
		if ( wp_is_serving_rest_request() || wp_doing_ajax() ) {
			return;
		}
		$queries = array();
		foreach ( (array) $wpdb->queries as $q ) {
			$queries[] = is_array( $q ) ? $q[0] : $q;
		}
		echo "\n<!-- wpsb-queries:" . (int) $wpdb->num_queries . ' -->';
		echo "\n<!-- wpsb-query-log:" . base64_encode( (string) wp_json_encode( $queries ) ) . ' -->'; // phpcs:ignore
	},
	PHP_INT_MAX
);
