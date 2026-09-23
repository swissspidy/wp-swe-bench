<?php
/**
 * `wp acme-redirects test <url>`: parity with the real front end.
 */

class TestCommandTest extends AcmeRedirectsCase {

	/** URL => expected rule ID (null: no match). */
	const CASES = array(
		'/old-about'                                     => 1,
		'/OLD-About/'                                    => 1,
		'/old%2Dabout'                                   => 1,
		'/old-contact?utm_source=newsletter&utm_medium=x' => 2,
		'http://127.0.0.1:9400/shop/shoes/red?size=4'    => 3,
		'/shop/sale/now'                                 => 4,
		'/shopping'                                      => null,
		'/blog/2020/05/Hello-World/'                     => 5,
		'/blog/2020/5/hello/'                            => null,
		'/products/Widget-9?color=red'                   => 6,
		'/search?cat=5'                                  => 7,
		'/search?cat=5&page=2'                           => null,
		'/retired-product/'                              => 8,
		'/promo'                                         => 9,
		'/promo?preview=1'                               => null,
		'/disabled-page'                                 => null,
		'/legacy-faq/'                                   => null,
		'/docs'                                          => 11,
		'/docs/v1/auth/tokens'                           => 12,
		'/docs/v2/auth?x=1'                              => 11,
		'/events/summer-fest'                            => 13,
		'/careers'                                       => 17,
		'/123'                                           => 18,
		'/nothing-here/'                                 => null,
	);

	private function test_json( string $url ): array {
		$run  = $this->cmd( 'test ' . escapeshellarg( $url ) . ' --format=json' );
		$data = $this->json( $run );
		$this->assertIsArray( $data, $url );
		if ( empty( $data['matched'] ) ) {
			$this->assertSame( array( 'matched' => false ), $data, "no-match output for $url" );
			$this->assertSame( 1, $run['exit'], "no match => exit 1 ($url)" );
		} else {
			$this->assertSame( 0, $run['exit'], "match => exit 0 ($url)" );
			$this->assertSame( array( 'matched', 'id', 'source', 'match_type', 'priority', 'status', 'target' ), array_keys( $data ), "keys for $url" );
		}
		return $data;
	}

	private static function path_of( string $url ): string {
		return preg_replace( '#^https?://[^/]+#', '', $url );
	}

	public function test_matches_what_the_front_end_does(): void {
		foreach ( self::CASES as $url => $expected_id ) {
			$cli   = $this->test_json( $url );
			$front = $this->front( self::path_of( $url ) );

			if ( null === $expected_id ) {
				$this->assertFalse( $cli['matched'], "$url must not match: " . wp_json_encode( $cli ) );
				$this->assertFalse( $front['acme'], "front end: $url" );
				$this->assertNotSame( 410, $front['status'] );
				continue;
			}

			$this->assertTrue( $cli['matched'], "$url must match rule $expected_id" );
			$this->assertSame( $expected_id, $cli['id'], "rule for $url" );
			$this->assertSame( $front['status'], $cli['status'], "status for $url" );
			if ( 410 === $cli['status'] ) {
				$this->assertSame( '', $cli['target'] );
			} else {
				$this->assertTrue( $front['acme'], "front end must redirect $url" );
				$this->assertSame( $front['location'], $cli['target'], "target for $url must be the Location header" );
			}
		}
	}

	public function test_output_values(): void {
		$data = $this->test_json( '/blog/2020/05/hello-world/?utm_source=x' );
		$this->assertSame(
			array(
				'matched'    => true,
				'id'         => 5,
				'source'     => '^/blog/(\d{4})/(\d{2})/(.+?)/?$',
				'match_type' => 'regex',
				'priority'   => 10,
				'status'     => 301,
				'target'     => 'http://127.0.0.1:9400/news/hello-world/?utm_source=x',
			),
			$data
		);

		$data = $this->test_json( '/careers' );
		$this->assertSame( 'https://jobs.example.org/acme?ref=acme', $data['target'], 'filters applied by the site are part of the answer' );

		$data = $this->test_json( '/old-contact' );
		$this->assertSame( '/old-contact', $data['source'] );
		$this->assertSame( 'exact', $data['match_type'] );
		$this->assertSame( 301, $data['status'] );
		$this->assertSame( 10, $data['priority'] );

		$data = $this->test_json( '/retired-product' );
		$this->assertSame( 410, $data['status'] );
		$this->assertSame( '', $data['target'] );
	}

	public function test_does_not_count_hits(): void {
		$before = $this->rows();
		foreach ( array( '/old-about', '/shop/x', '/retired-product', '/nothing' ) as $url ) {
			$this->cmd( 'test ' . $url . ' --format=json' );
			$this->cmd( 'test ' . $url );
		}
		$this->assertSame( $before, $this->rows(), 'test must not touch hits or last_hit' );
	}

	public function test_table_format_and_no_match_error(): void {
		$run = $this->cmd( 'test /old-about' );
		$this->assertOk( $run );
		$this->assertStringContainsString( 'http://127.0.0.1:9400/about/', $run['stdout'] );
		$this->assertStringContainsString( '/old-about', $run['stdout'] );

		$run = $this->cmd( 'test /nothing-here/' );
		$this->assertFails( $run );
		$this->assertStringContainsString( 'Error: No redirect matches /nothing-here/.', $run['stderr'] );
	}

	public function test_reflects_changes_made_with_commands(): void {
		$this->warm_front_end();
		$this->assertSame( 3, $this->test_json( '/shop/shoes' )['id'] );

		$run = $this->cmd( 'add /shop/shoes /footwear/ --priority=1 --porcelain' );
		$this->assertOk( $run );
		$id = (int) trim( $run['stdout'] );

		$cli = $this->test_json( '/shop/shoes/?page=2' );
		$this->assertSame( $id, $cli['id'] );
		$this->assertSame( self::h( '/footwear/?page=2' ), $cli['target'] );
		$this->assertFrontRedirect( '/shop/shoes/?page=2', 301, self::h( '/footwear/?page=2' ) );

		$this->assertOk( $this->cmd( 'update ' . $id . ' --enabled=no' ) );
		$this->assertSame( 3, $this->test_json( '/shop/shoes' )['id'] );
		$this->assertFrontRedirect( '/shop/shoes', 301, self::h( '/store/shoes' ) );

		$this->assertOk( $this->cmd( 'update 3 --priority=4' ) );
		$this->assertSame( 3, $this->test_json( '/shop/sale/now' )['id'], '/shop (4) now wins over /shop/sale (5)' );
		$this->assertFrontRedirect( '/shop/sale/now', 301, self::h( '/store/sale/now' ) );
	}
}
