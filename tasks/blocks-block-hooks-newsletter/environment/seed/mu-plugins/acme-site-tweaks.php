<?php
/**
 * Plugin Name: Acme site tweaks
 * Description: Small per-site customisations (managed by the web team).
 */

// Sponsored posts must not promote our newsletter (contract with advertisers).
add_filter(
	'acme_newsletter_auto_insert',
	static function ( $insert, $post ) {
		if ( $post && has_tag( 'sponsored', $post ) ) {
			return false;
		}
		return $insert;
	},
	10,
	2
);
