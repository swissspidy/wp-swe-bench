<?php
/**
 * Stored-XSS probes: preview fields coming from a remote page (or an old cache)
 * must be rendered safely.
 */

class XssTest extends WPSB\TestCase {

	public function test_shortcode_escapes_a_malicious_cached_preview(): void {
		// The seeded cache holds a preview for https://evil.example/post whose
		// title/description/image contain script and a javascript: URL.
		$html = do_shortcode( '[acme_link_preview url="https://evil.example/post"]' );

		$this->assertNotSame( '', $html, 'The card should still render' );
		$this->assertStringNotContainsString( '<script>', $html, 'The title must be escaped' );
		$this->assertStringNotContainsString( '<img src=x', $html, 'The description must be escaped, not rendered as markup' );
		$this->assertStringNotContainsStringIgnoringCase( 'javascript:', $html, 'A javascript: URL must never reach the markup' );
		// The (escaped) text is still shown.
		$this->assertStringContainsString( 'Great deal', $html );
	}

	public function test_fetch_response_html_is_escaped_and_bad_image_dropped(): void {
		$dns = array( 'good.example' => '93.184.216.34' );
		$dns_cb = static function ( $ip, $host ) use ( $dns ) {
			return $dns[ $host ] ?? $ip;
		};
		$http_cb = static function ( $pre, $args, $url ) {
			if ( 'http://good.example/x' === $url ) {
				return array(
					'headers'  => array( 'content-type' => 'text/html' ),
					'body'     => '<html><head>'
						. '<meta property="og:title" content="Pwn &lt;script&gt;alert(1)&lt;/script&gt;">'
						. '<meta property="og:description" content="d">'
						. '<meta property="og:image" content="javascript:alert(2)">'
						. '</head></html>',
					'response' => array( 'code' => 200, 'message' => 'OK' ),
					'cookies'  => array(),
					'filename' => null,
				);
			}
			return new WP_Error( 'unexpected', 'no' );
		};
		add_filter( 'acme_lp_resolve_host', $dns_cb, 10, 2 );
		add_filter( 'pre_http_request', $http_cb, 10, 3 );

		try {
			$this->login_as( 'editor' );
			$data = $this->rest( 'POST', '/acme-link-previews/v1/fetch', array(), array( 'url' => 'http://good.example/x' ) )->get_data();
		} finally {
			remove_filter( 'acme_lp_resolve_host', $dns_cb, 10 );
			remove_filter( 'pre_http_request', $http_cb, 10 );
		}

		$this->assertSame( '', $data['image'], 'A javascript: image URL must be dropped' );
		$this->assertStringNotContainsString( '<script>', $data['html'] );
		$this->assertStringNotContainsStringIgnoringCase( 'javascript:', $data['html'] );
	}
}
