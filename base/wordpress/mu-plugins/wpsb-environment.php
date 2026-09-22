<?php
/**
 * Plugin Name: wp-swe-bench environment
 * Description: Development-site conveniences: captures outgoing email to
 *              wp-content/wpsb-mail.log (one JSON object per line) instead of sending it.
 */

add_filter(
	'pre_wp_mail',
	static function ( $short_circuit, $atts ) {
		if ( null !== $short_circuit ) {
			return $short_circuit;
		}
		$line = wp_json_encode(
			array(
				'time'    => time(),
				'to'      => $atts['to'],
				'subject' => $atts['subject'],
				'message' => $atts['message'],
				'headers' => $atts['headers'],
			)
		);
		file_put_contents( WP_CONTENT_DIR . '/wpsb-mail.log', $line . "\n", FILE_APPEND | LOCK_EX );
		return true;
	},
	1000,
	2
);
