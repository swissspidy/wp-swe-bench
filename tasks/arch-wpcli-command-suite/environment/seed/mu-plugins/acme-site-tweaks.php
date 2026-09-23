<?php
/**
 * Plugin Name: Acme site tweaks
 * Description: Site-specific integrations (campaign previews, partner referral tags, CDN purge log).
 */

// Marketing previews campaign pages with ?preview=… before a promo goes live: never redirect those.
add_filter(
	'acme_redirects_match',
	static function ( $match, $rule, $path, $query ) {
		if ( $match && isset( $query['preview'] ) ) {
			return false;
		}
		return $match;
	},
	10,
	4
);

// Partners want to see where their traffic comes from.
add_filter(
	'acme_redirects_target',
	static function ( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( $host && wp_parse_url( home_url(), PHP_URL_HOST ) !== $host ) {
			$url = add_query_arg( 'ref', 'acme', $url );
		}
		return $url;
	}
);

// The CDN caches redirects: purge whenever a rule changes (logged instead of calling the CDN here).
add_action(
	'acme_redirects_rules_changed',
	static function ( $action, $rule ) {
		$log   = get_option( 'acme_cdn_purge_log', array() );
		$log   = is_array( $log ) ? $log : array();
		$log[] = $action . ':' . $rule->id;
		update_option( 'acme_cdn_purge_log', array_slice( $log, -200 ), false );
	},
	10,
	2
);

// Legal: never send visitors to hosts on the blocklist.
add_filter(
	'acme_redirects_validate_rule',
	static function ( $errors, $input ) {
		foreach ( array( 'evil.example', 'tracking.example' ) as $host ) {
			if ( false !== stripos( (string) ( $input['target'] ?? '' ), $host ) ) {
				$errors->add( 'acme_blocked_host', 'Redirects to this host are not allowed.' );
			}
		}
		return $errors;
	},
	10,
	2
);
