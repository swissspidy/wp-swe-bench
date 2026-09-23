<?php
/**
 * Campaign click tracking for CTA buttons.
 *
 * Marketing wants to know which CTA brought a visitor to our landing pages
 * and partner sites, so outgoing CTA links get UTM parameters and a
 * `data-acme-cta` attribute that the analytics snippet listens to.
 *
 * @package Acme\CTA
 */

namespace Acme\CTA;

defined( 'ABSPATH' ) || exit;

/**
 * Adds UTM parameters to CTA button links on the front end.
 */
class Tracking {

	/**
	 * Register hooks.
	 *
	 * Since 2.4.0 the CTA block renders its (tracked) link itself, from the
	 * attributes that are actually displayed (including pattern overrides).
	 */
	public function register_hooks() {}

	/**
	 * Is tracking enabled in Settings → CTA tracking?
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return (bool) acme_cta_get_option( 'enabled' );
	}

	/**
	 * Add the UTM parameters to an outgoing URL.
	 *
	 * Only absolute http(s) links to other hosts are tracked; internal links
	 * and mailto:/tel: links are returned unchanged.
	 *
	 * @param string $url      Button URL.
	 * @param string $campaign Campaign slug ('' = generic "cta").
	 * @return string
	 */
	public function tracked_url( $url, $campaign = '' ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || ! $host || wp_parse_url( home_url(), PHP_URL_HOST ) === $host ) {
			return $url;
		}

		$tracked = add_query_arg(
			array(
				'utm_source'   => rawurlencode( (string) acme_cta_get_option( 'utm_source' ) ),
				'utm_medium'   => rawurlencode( (string) acme_cta_get_option( 'utm_medium' ) ),
				'utm_campaign' => rawurlencode( $campaign ? $campaign : 'cta' ),
			),
			$url
		);

		/**
		 * Filters a tracked CTA URL.
		 *
		 * @param string $tracked  URL with UTM parameters.
		 * @param string $url      Original URL.
		 * @param string $campaign Campaign slug.
		 */
		return (string) apply_filters( 'acme_cta_tracked_url', $tracked, $url, $campaign );
	}
}
