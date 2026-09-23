<?php
/**
 * Plugin Name: wpsb stats probe (grading only)
 * Description: Logs every stats computation (acme_stats_before_compute) to a file shared by all
 * PHP processes, and can hold a computation until released (to observe concurrent requests).
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'acme_stats_before_compute',
	static function ( $scope ) {
		$dir = WP_CONTENT_DIR . '/wpsb-probe';
		if ( ! is_dir( $dir ) ) {
			@mkdir( $dir, 0777, true );
		}
		file_put_contents( $dir . '/computes.log', $scope . "\n", FILE_APPEND | LOCK_EX );
		if ( is_file( $dir . '/block' ) && ( defined( 'REST_REQUEST' ) || ! empty( $_SERVER['HTTP_X_WPSB_BLOCKABLE'] ) ) ) {
			@file_put_contents( $dir . '/started', $scope . "\n", FILE_APPEND | LOCK_EX );
			$start = time();
			while ( ! is_file( $dir . '/release' ) && time() - $start < 40 ) {
				usleep( 100000 );
			}
		}
	},
	1
);

// Query count of REST requests when asked for (X-WPSB-Count-Queries header).
add_filter(
	'rest_post_dispatch',
	static function ( $response ) {
		global $wpdb;
		if ( ! empty( $_SERVER['HTTP_X_WPSB_COUNT_QUERIES'] ) && $response instanceof WP_REST_Response ) {
			$response->header( 'X-WPSB-Queries', (string) $wpdb->num_queries );
		}
		return $response;
	},
	PHP_INT_MAX
);
