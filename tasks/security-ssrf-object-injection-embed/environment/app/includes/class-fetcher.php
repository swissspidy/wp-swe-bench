<?php
/**
 * Fetches a remote URL and extracts a preview (title, description, image).
 *
 * Redirects are followed one hop at a time so the redirect chain can be
 * recorded for diagnostics (see the `acme_lp_resolve_host` filter, which lets
 * infrastructure override how a host name is resolved to an IP address).
 *
 * @package Acme\LinkPreviews
 */

namespace Acme\LinkPreviews;

defined( 'ABSPATH' ) || exit;

/**
 * Remote fetcher.
 */
class Fetcher {

	const MAX_HOPS = 5;
	const TIMEOUT  = 10;

	/**
	 * Fetch a URL and return its preview.
	 *
	 * @param string $url URL to fetch.
	 * @return array|\WP_Error
	 */
	public static function fetch( $url ) {
		$url = trim( (string) $url );

		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return new \WP_Error( 'acme_lp_invalid_url', __( 'That does not look like a URL.', 'acme-link-previews' ), array( 'status' => 400 ) );
		}

		$current = $url;
		$body    = '';
		for ( $hop = 0; $hop <= self::MAX_HOPS; $hop++ ) {
			$hop_parts = wp_parse_url( $current );
			$host      = isset( $hop_parts['host'] ) ? $hop_parts['host'] : '';

			// Resolve the host for the diagnostics log.
			$ip = apply_filters( 'acme_lp_resolve_host', gethostbyname( $host ), $host );
			/**
			 * Fires for every hop the fetcher makes.
			 *
			 * @param string $current URL being requested.
			 * @param string $ip      Resolved IP (for diagnostics).
			 */
			do_action( 'acme_lp_fetch_hop', $current, $ip );

			$response = wp_remote_get(
				$current,
				array(
					'timeout'     => self::TIMEOUT,
					'redirection' => 0,
					'user-agent'  => 'AcmeLinkPreviews/' . ACME_LP_VERSION,
				)
			);
			if ( is_wp_error( $response ) ) {
				return new \WP_Error( 'acme_lp_fetch_failed', __( 'Could not fetch the URL.', 'acme-link-previews' ), array( 'status' => 502 ) );
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
				$location = wp_remote_retrieve_header( $response, 'location' );
				if ( '' === $location ) {
					break;
				}
				$current = self::absolutize( $location, $current );
				continue;
			}

			if ( $code < 200 || $code >= 400 ) {
				return new \WP_Error( 'acme_lp_fetch_failed', __( 'The server returned an error.', 'acme-link-previews' ), array( 'status' => 502 ) );
			}

			$body = (string) wp_remote_retrieve_body( $response );
			break;
		}

		$preview = self::parse( $url, $body );
		Cache::set( $url, $preview );
		return $preview;
	}

	/**
	 * Resolve a possibly relative redirect target against the current URL.
	 *
	 * @param string $location Location header value.
	 * @param string $base     Current URL.
	 * @return string
	 */
	protected static function absolutize( $location, $base ) {
		if ( preg_match( '#^https?://#i', $location ) ) {
			return $location;
		}
		$b = wp_parse_url( $base );
		$scheme = isset( $b['scheme'] ) ? $b['scheme'] : 'http';
		$host   = isset( $b['host'] ) ? $b['host'] : '';
		$port   = isset( $b['port'] ) ? ':' . $b['port'] : '';
		if ( '' !== $location && '/' === $location[0] ) {
			return "$scheme://$host$port$location";
		}
		return "$scheme://$host$port/" . ltrim( $location, '/' );
	}

	/**
	 * Extract the preview fields from an HTML document.
	 *
	 * @param string $url  Original URL.
	 * @param string $html HTML body.
	 * @return array<string, string>
	 */
	protected static function parse( $url, $html ) {
		$title = self::meta( $html, 'og:title' );
		if ( '' === $title && preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $m ) ) {
			$title = trim( html_entity_decode( $m[1], ENT_QUOTES ) );
		}
		$description = self::meta( $html, 'og:description' );
		if ( '' === $description ) {
			$description = self::meta( $html, 'description' );
		}
		$image = self::meta( $html, 'og:image' );

		return array(
			'url'         => $url,
			'title'       => $title,
			'description' => $description,
			'image'       => $image,
		);
	}

	/**
	 * Read a <meta> property/name value from HTML.
	 *
	 * @param string $html     HTML.
	 * @param string $property Property or name.
	 * @return string
	 */
	protected static function meta( $html, $property ) {
		$quoted = preg_quote( $property, '#' );
		if ( preg_match( '#<meta[^>]+(?:property|name)=["\']' . $quoted . '["\'][^>]+content=["\'](.*?)["\']#is', $html, $m ) ) {
			return trim( html_entity_decode( $m[1], ENT_QUOTES ) );
		}
		if ( preg_match( '#<meta[^>]+content=["\'](.*?)["\'][^>]+(?:property|name)=["\']' . $quoted . '["\']#is', $html, $m ) ) {
			return trim( html_entity_decode( $m[1], ENT_QUOTES ) );
		}
		return '';
	}
}
