<?php
/**
 * Plugin Name: wp-swe-bench importer test hooks
 * Description: Installed by the verifier. Every behaviour is off unless a test switches it on through an option.
 */

// Shorter claim timeout.
add_filter(
	'acme_importer_lock_timeout',
	static function ( $seconds ) {
		$override = (int) get_option( 'wpsb_test_lock_timeout', 0 );
		return $override > 0 ? $override : $seconds;
	},
	99
);

// Shorter retry delays (step 2).
add_filter(
	'acme_importer_retry_delay',
	static function ( $seconds ) {
		$override = get_option( 'wpsb_test_retry_delay', '' );
		return '' === $override ? $seconds : (int) $override;
	},
	99
);

// Crash: kill the process right after the armed SKU was saved (once).
add_action(
	'acme_importer_product_saved',
	static function ( $id, $data ) {
		$sku = (string) get_option( 'wpsb_test_crash_sku', '' );
		if ( '' !== $sku && strtoupper( (string) $data['sku'] ) === strtoupper( $sku ) && file_exists( '/tmp/wpsb-crash-armed' ) ) {
			unlink( '/tmp/wpsb-crash-armed' );
			posix_kill( getmypid(), 9 );
			exit( 137 );
		}
	},
	10,
	2
);

// Record every save (pid, sku) to spot duplicate processing.
add_action(
	'acme_importer_product_saved',
	static function ( $id, $data ) {
		if ( get_option( 'wpsb_test_log_saves' ) ) {
			file_put_contents( '/tmp/wpsb-saves.log', getmypid() . ' ' . $data['sku'] . "\n", FILE_APPEND | LOCK_EX );
		}
	},
	20,
	2
);

// Failing rows (step 2): SKU => number of attempts that throw.
add_filter(
	'acme_importer_row_data',
	static function ( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$throw = get_option( 'wpsb_test_throw_skus', array() );
		$sku   = strtoupper( (string) ( $data['sku'] ?? '' ) );
		if ( is_array( $throw ) && ! empty( $throw[ $sku ] ) ) {
			--$throw[ $sku ];
			update_option( 'wpsb_test_throw_skus', $throw, false );
			throw new RuntimeException( 'ERP connection reset while saving ' . $sku );
		}
		return $data;
	},
	99
);

// Products whose title contains a marker cannot be saved (WP_Error from core).
add_filter(
	'wp_insert_post_empty_content',
	static function ( $maybe_empty, $postarr ) {
		$marker = (string) get_option( 'wpsb_test_reject_marker', '' );
		if ( '' !== $marker && 'acme_product' === ( $postarr['post_type'] ?? '' ) && false !== strpos( (string) ( $postarr['post_title'] ?? '' ), $marker ) ) {
			return true;
		}
		return $maybe_empty;
	},
	10,
	2
);
