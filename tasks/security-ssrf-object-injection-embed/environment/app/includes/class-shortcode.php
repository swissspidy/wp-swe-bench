<?php
/**
 * The [acme_link_preview] shortcode.
 *
 * @package Acme\LinkPreviews
 */

namespace Acme\LinkPreviews;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode.
 */
class Shortcode {

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_shortcode( 'acme_link_preview', array( $this, 'render' ) );
	}

	/**
	 * Render `[acme_link_preview url="https://example.com/"]`.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts( array( 'url' => '' ), $atts, 'acme_link_preview' );
		$url  = trim( (string) $atts['url'] );
		if ( '' === $url ) {
			return '';
		}

		$preview = Cache::get( $url );
		if ( null === $preview ) {
			$fetched = Fetcher::fetch( $url );
			if ( is_wp_error( $fetched ) ) {
				return '';
			}
			$preview = $fetched;
		}

		return Card::render( $preview );
	}
}
