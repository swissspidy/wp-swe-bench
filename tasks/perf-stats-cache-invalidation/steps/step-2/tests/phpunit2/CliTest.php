<?php
/**
 * wp acme-stats warm / flush.
 */

use function WPSB\Stats\expected;
use function WPSB\Stats\new_request;
use function WPSB\Stats\numbers;
use function WPSB\Stats\uid;

class CliTest extends WPSB\Stats\HttpStatsTestCase {

	private function cli( string $args ): string {
		$out = $this->wp_cli( $args );
		$this->assertSame( 0, $out['exit'], "wp $args failed: " . $out['stderr'] . $out['stdout'] );
		$this->assertMatchesRegularExpression( '/^Success: /m', $out['stdout'] . $out['stderr'], "wp $args must report success" );
		return $out['stdout'];
	}

	public function test_warm_everything(): void {
		$this->cli( 'acme-stats flush' );
		$this->reset_computes();
		$this->cli( 'acme-stats warm' );
		$warmed = $this->computes();
		$this->assertContains( 'site', $warmed );
		foreach ( array( 'bruno', 'chiara', 'dmitri', 'esme', 'farid', 'hugo' ) as $login ) {
			$this->assertContains( 'author:' . uid( $login ), $warmed, "warm must cover $login" );
		}
		$this->assertNotContains( 'author:' . uid( 'gina' ), $warmed, 'Subscribers never see stats' );

		$this->reset_computes();
		foreach ( array( 'eva', 'bruno', 'farid', 'hugo' ) as $login ) {
			$data = $this->http_summary( uid( $login ) );
			new_request();
			$this->assertSame( expected( 'eva' === $login ? 0 : uid( $login ) ), numbers( $data ) );
		}
		new_request();
		list( $data, $computes ) = $this->load( uid( 'esme' ) );
		$this->assertSame( array(), $computes );
		$this->assertSame( array(), $this->computes(), 'Warmed numbers must not be recomputed' );
	}

	public function test_warm_one_user(): void {
		$this->cli( 'acme-stats flush' );
		$this->reset_computes();
		$this->cli( 'acme-stats warm bruno' );
		$this->assertSame( array( 'author:' . uid( 'bruno' ) ), $this->computes() );
		$this->cli( 'acme-stats warm ' . uid( 'eva' ) );
		$this->assertSame( array( 'author:' . uid( 'bruno' ), 'site' ), $this->computes() );

		$this->reset_computes();
		$this->http_summary( uid( 'bruno' ) );
		$this->http_summary( uid( 'eva' ) );
		$this->assertSame( array(), $this->computes() );
		$this->http_summary( uid( 'chiara' ) );
		$this->assertSame( array( 'author:' . uid( 'chiara' ) ), $this->computes() );

		$out = $this->wp_cli( 'acme-stats warm nobody-here' );
		$this->assertNotSame( 0, $out['exit'], 'Unknown users must be an error' );
		$out = $this->wp_cli( 'acme-stats warm gina' );
		$this->assertNotSame( 0, $out['exit'], 'Users who cannot see stats must be an error' );
	}

	public function test_flush(): void {
		$this->http_summary( uid( 'eva' ) );
		$this->http_summary( uid( 'dmitri' ) );
		$this->cli( 'acme-stats flush' );
		$this->reset_computes();
		$site = $this->http_summary( uid( 'eva' ) );
		$this->assertFalse( $site['stale'], 'After a flush there is nothing stale to serve' );
		$this->assertSame( array( 'site' ), $this->computes() );
		$this->http_summary( uid( 'dmitri' ) );
		$this->assertSame( array( 'site', 'author:' . uid( 'dmitri' ) ), $this->computes() );
		new_request();
		$this->assertSame( expected( 0 ), numbers( $site ) );
	}
}
