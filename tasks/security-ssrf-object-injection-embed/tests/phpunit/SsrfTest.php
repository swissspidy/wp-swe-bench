<?php
/**
 * SSRF probes against POST /acme-link-previews/v1/fetch.
 *
 * The network is simulated with a `pre_http_request` layer (which runs before
 * WP_HTTP_BLOCK_EXTERNAL), and host name resolution with the plugin's
 * `acme_lp_resolve_host` filter, so no real network is used.
 */

class SsrfTest extends WPSB\TestCase {

	/** @var array<string, array> URL => simulated HTTP response. */
	private array $routes = array();

	/** @var array<string, string> host => IP the resolver returns. */
	private array $dns = array();

	protected function setUp(): void {
		parent::setUp();
		$this->routes = array();
		$this->dns    = array();

		add_filter( 'pre_http_request', array( $this, 'fake_http' ), 10, 3 );
		add_filter( 'acme_lp_resolve_host', array( $this, 'fake_dns' ), 10, 2 );

		// Public hosts used by the "allowed" cases.
		$this->dns['good.example']  = '93.184.216.34';
		$this->dns['good2.example'] = '93.184.216.34';
		// Attacker-controlled host names that resolve to internal addresses.
		$this->dns['metadata.evil.example'] = '169.254.169.254';
		$this->dns['intranet.evil.example'] = '10.1.2.3';

		$this->login_as( 'editor' );
	}

	protected function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'fake_http' ), 10 );
		remove_filter( 'acme_lp_resolve_host', array( $this, 'fake_dns' ), 10 );
		parent::tearDown();
	}

	public function fake_dns( $ip, $host ) {
		return $this->dns[ $host ] ?? $ip;
	}

	public function fake_http( $pre, $args, $url ) {
		if ( isset( $this->routes[ $url ] ) ) {
			return $this->routes[ $url ];
		}
		// Any URL we did not explicitly allow is treated as a hard failure, so a
		// test only passes when the plugin refused *before* making the request.
		return new WP_Error( 'test_unexpected_request', "Unexpected outbound request to $url" );
	}

	private function html_response( string $html ): array {
		return array(
			'headers'  => array( 'content-type' => 'text/html' ),
			'body'     => $html,
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	private function redirect_response( string $location ): array {
		return array(
			'headers'  => array( 'location' => $location ),
			'body'     => '',
			'response' => array( 'code' => 302, 'message' => 'Found' ),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	private function fetch( string $url ): array {
		$response = $this->rest( 'POST', '/acme-link-previews/v1/fetch', array(), array( 'url' => $url ) );
		return array( $response->get_status(), $response->get_data() );
	}

	private function assertBlocked( string $url ): void {
		list( $status, $data ) = $this->fetch( $url );
		$code = is_array( $data ) ? ( $data['code'] ?? '' ) : ( is_wp_error( $data ) ? $data->get_error_code() : '' );
		$this->assertContains(
			$code,
			array( 'acme_lp_blocked_host', 'acme_lp_invalid_url' ),
			"Fetching $url should be refused, got status $status code '$code'"
		);
	}

	public function test_allowed_public_url_is_fetched(): void {
		$this->routes['http://good.example/post'] = $this->html_response(
			'<html><head><meta property="og:title" content="A Good Post"><meta property="og:description" content="Nice."><meta property="og:image" content="https://good.example/i.png"></head></html>'
		);

		list( $status, $data ) = $this->fetch( 'http://good.example/post' );
		$this->assertSame( 200, $status, wp_json_encode( $data ) );
		$this->assertSame( 'A Good Post', $data['title'] );
		$this->assertSame( 'Nice.', $data['description'] );
		$this->assertSame( 'https://good.example/i.png', $data['image'] );
	}

	public function test_loopback_and_metadata_are_blocked(): void {
		$this->assertBlocked( 'http://127.0.0.1/' );
		$this->assertBlocked( 'http://127.0.0.1:80/admin' );
		$this->assertBlocked( 'http://localhost/' );
		$this->assertBlocked( 'http://169.254.169.254/latest/meta-data/' );
		$this->assertBlocked( 'http://[::1]/' );
	}

	public function test_private_ranges_are_blocked(): void {
		$this->assertBlocked( 'http://10.0.0.5/' );
		$this->assertBlocked( 'http://192.168.1.1/' );
		$this->assertBlocked( 'http://172.16.9.9/' );
		$this->assertBlocked( 'http://100.64.0.1/' );
		$this->assertBlocked( 'http://0.0.0.0/' );
	}

	public function test_alternative_ip_encodings_are_blocked(): void {
		$this->assertBlocked( 'http://2130706433/' );      // decimal 127.0.0.1
		$this->assertBlocked( 'http://0177.0.0.1/' );      // octal 127.0.0.1
		$this->assertBlocked( 'http://0x7f.0.0.1/' );      // hex 127.0.0.1
		$this->assertBlocked( 'http://[::ffff:127.0.0.1]/' ); // IPv4-mapped IPv6
		$this->assertBlocked( 'http://2852039166/' );      // decimal 169.254.169.254
	}

	public function test_hostnames_resolving_to_internal_are_blocked(): void {
		$this->assertBlocked( 'http://metadata.evil.example/latest/meta-data/' );
		$this->assertBlocked( 'http://intranet.evil.example/' );
	}

	public function test_non_http_schemes_are_rejected(): void {
		$this->assertBlocked( 'file:///etc/passwd' );
		$this->assertBlocked( 'gopher://127.0.0.1/' );
		$this->assertBlocked( 'ftp://10.0.0.1/' );
	}

	public function test_redirect_to_internal_host_is_blocked(): void {
		// A public URL that 302-redirects to the cloud metadata service.
		$this->routes['http://good.example/redir']  = $this->redirect_response( 'http://169.254.169.254/latest/meta-data/' );
		$this->assertBlocked( 'http://good.example/redir' );

		// Redirect to an internal host name.
		$this->routes['http://good.example/redir2'] = $this->redirect_response( 'http://intranet.evil.example/secret' );
		$this->assertBlocked( 'http://good.example/redir2' );

		// Redirect to a decimal-encoded loopback address.
		$this->routes['http://good.example/redir3'] = $this->redirect_response( 'http://2130706433/' );
		$this->assertBlocked( 'http://good.example/redir3' );
	}

	public function test_redirect_to_public_host_is_followed(): void {
		$this->routes['http://good.example/start'] = $this->redirect_response( 'http://good2.example/final' );
		$this->routes['http://good2.example/final'] = $this->html_response(
			'<html><head><title>Final Page</title></head></html>'
		);

		list( $status, $data ) = $this->fetch( 'http://good.example/start' );
		$this->assertSame( 200, $status, wp_json_encode( $data ) );
		$this->assertSame( 'Final Page', $data['title'] );
	}
}
