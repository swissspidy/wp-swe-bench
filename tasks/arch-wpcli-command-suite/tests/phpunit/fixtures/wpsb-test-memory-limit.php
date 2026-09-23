<?php
/**
 * Test helper: WP-CLI lifts the memory limit after loading WordPress; re-apply one
 * right before the command runs when asked to.
 */
if ( defined( 'WP_CLI' ) && WP_CLI && getenv( 'WPSB_TEST_MEMORY_LIMIT' ) ) {
	WP_CLI::add_hook(
		'after_wp_load',
		static function () {
			ini_set( 'memory_limit', getenv( 'WPSB_TEST_MEMORY_LIMIT' ) );
		}
	);
}
