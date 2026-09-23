<?php
/**
 * Fetches a remote URL and extracts a preview (title, description, image).
 *
 * Every request — and every redirect hop — is validated against {@see Url_Guard}
 * before it is made, so the fetcher can never be pointed at an internal address.
 * Responses are size- and time-limited.
 *
 * @package Acme\LinkPreviews
 */

namespace Acme\LinkPreviews;

defined( 'ABSPATH' ) || exit;

/**
 * Remote fetcher.
 */
class Fetcher {

	const MAX_HOPS  = 5;
	const TIMEOUT   = 5;
	const MAX_BYTES = 2097152; // 2 MB.

	/**
	 * Fetch a URL and return its preview.
	 *
	 * @param string $url URL to fetch.
	 * @return array|\WP_Error
	 */
	public static function fetch( $url ) {
		$url     = trim( (string) $url );
		$current = $url;
		$body    = '';

		for ( $hop = 0; $hop <= self::MAX_HOPS; $hop++ ) {
			// Validate this hop's URL (scheme + resolved IP) before touching the network.
			$check = Url_Guard::check_url( $current );
			if ( is_wp_error( $check ) ) {
				return $check;
			}

			$response = wp_remote_get(
				$current,
				array(
					'timeout'             => self::TIMEOUT,
					'redirection'         => 0,
					'limit_response_size' => self::MAX_BYTES,
					'user-agent'          => 'AcmeLinkPreviews/' . ACME_LP_VERSION,
				)
			);
			if ( is_wp_error( $response ) ) {
				return new \WP_Error( 'acme_lp_fetch_failed', __( 'Could not fetch the URL.', 'acme-link-previews' ), array( 'status' => 502 ) );
			}

			$code = (int) wp_remote_retrieve_response_code( $response );

			if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
				if ( $hop >= self::MAX_HOPS ) {
					return new \WP_Error( 'acme_lp_fetch_failed', __( 'Too many redirects.', 'acme-link-previews' ), array( 'status' => 502 ) );
				}
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
			$length = (int) wp_remote_retrieve_header( $response, 'content-length' );
			if ( $length > self::MAX_BYTES || strlen( $body ) > self::MAX_BYTES ) {
				return new \WP_Error( 'acme_lp_too_large', __( 'That page is too large to preview.', 'acme-link-previews' ), array( 'status' => 400 ) );
			}
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
		$b      = wp_parse_url( $base );
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
			'image'       => self::safe_image_url( $image ),
		);
	}

	/**
	 * Keep an image URL only if it is a plain http(s) or protocol-relative URL.
	 *
	 * @param string $image Candidate image URL.
	 * @return string
	 */
	protected static function safe_image_url( $image ) {
		$image = trim( (string) $image );
		if ( '' === $image ) {
			return '';
		}
		if ( preg_match( '#^//#', $image ) ) {
			return $image;
		}
		$scheme = strtolower( (string) wp_parse_url( $image, PHP_URL_SCHEME ) );
		return in_array( $scheme, array( 'http', 'https' ), true ) ? $image : '';
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
