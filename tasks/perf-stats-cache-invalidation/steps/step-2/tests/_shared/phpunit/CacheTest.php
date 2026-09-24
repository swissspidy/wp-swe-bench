<?php
/**
 * Repeat loads are served from the cache (F2P), per scope, without leaking between users.
 */

use function WPSB\Stats\expected;
use function WPSB\Stats\ext_cache;
use function WPSB\Stats\new_request;
use function WPSB\Stats\numbers;
use function WPSB\Stats\uid;
use WPSB\Stats\Probe;

class CacheTest extends WPSB\Stats\StatsTestCase {

	private function max_queries(): int {
		return ext_cache() ? 0 : 3;
	}

	public function test_repeat_load_is_served_from_the_cache(): void {
		list( $first, $computes ) = $this->load( uid( 'eva' ) );
		$this->assertSame( array( 'site' ), $computes );
		$this->assertSame( expected( 0 ), numbers( $first ) );

		new_request();
		list( $second, $computes, $queries, $log ) = $this->load( uid( 'eva' ) );
		$this->assertSame( array(), $computes, 'A repeat load must not recompute' );
		$this->assertLessThanOrEqual( $this->max_queries(), $queries, "Repeat load: $queries queries\n" . implode( "\n", $log ) );
		$this->assertSame( numbers( $first ), numbers( $second ) );
		$this->assertSame( $first['generated_at'], $second['generated_at'], 'generated_at must tell when the numbers were computed' );
		$this->assertSame( 'site', $second['scope'] );

		// Same request again.
		list( , $computes ) = $this->load( uid( 'eva' ) );
		$this->assertSame( array(), $computes );
	}

	public function test_all_surfaces_share_the_cache(): void {
		$this->load( uid( 'eva' ) );
		new_request();
		Probe::$computes = array();

		$html = do_shortcode( '[acme_stats]' );
		$this->assertStringContainsString( number_format_i18n( expected( 0 )['posts']['total'] ) . ' published posts', $html );
		acme_stats_get();
		wp_set_current_user( 1 );
		require_once ABSPATH . 'wp-admin/includes/dashboard.php';
		set_current_screen( 'dashboard' );
		ob_start();
		do_action( 'wp_dashboard_setup' );
		$widget = $GLOBALS['wp_meta_boxes']['dashboard']['normal']['core']['acme_stats_widget'] ?? null;
		$this->assertNotNull( $widget, 'Dashboard widget missing' );
		call_user_func( $widget['callback'] );
		$out = ob_get_clean();
		unset( $GLOBALS['current_screen'] );
		$this->assertStringContainsString( 'data-scope="site"', $out );
		$this->assertSame( array(), Probe::$computes, 'Shortcode, PHP API and dashboard widget must use the cached site numbers' );

		// An author's own numbers are cached separately.
		wp_set_current_user( uid( 'bruno' ) );
		do_shortcode( '[acme_stats scope="me"]' );
		$this->assertSame( array( 'author:' . uid( 'bruno' ) ), Probe::$computes );
		new_request();
		Probe::$computes = array();
		list( $data, $computes ) = $this->load( uid( 'bruno' ) );
		$this->assertSame( array(), $computes );
		$this->assertSame( expected( uid( 'bruno' ) ), numbers( $data ) );
	}

	public function test_results_never_leak_between_users(): void {
		$bruno  = uid( 'bruno' );
		$chiara = uid( 'chiara' );
		list( $b1 ) = $this->load( $bruno );
		list( $s1 ) = $this->load( uid( 'eva' ) );
		new_request();
		list( $c1, $computes ) = $this->load( $chiara );
		$this->assertSame( array( 'author:' . $chiara ), $computes );
		$this->assertSame( expected( $chiara ), numbers( $c1 ) );
		$this->assertSame( 'author:' . $chiara, $c1['scope'] );
		$this->assertNotSame( numbers( $b1 ), numbers( $c1 ) );

		new_request();
		list( $b2, $computes ) = $this->load( $bruno );
		$this->assertSame( array(), $computes );
		$this->assertSame( expected( $bruno ), numbers( $b2 ) );
		list( $s2 ) = $this->load( uid( 'eva' ) );
		$this->assertSame( expected( 0 ), numbers( $s2 ) );
		list( $s3 ) = $this->load( 1 );
		$this->assertSame( expected( 0 ), numbers( $s3 ) );
		list( $eb ) = $this->load( uid( 'eva' ), $bruno );
		$this->assertSame( expected( $bruno ), numbers( $eb ) );

		// A user without posts gets empty numbers, not somebody else's.
		list( $hugo ) = $this->load( uid( 'hugo' ) );
		$this->assertSame( 0, $hugo['posts']['total'] );
		$this->assertSame( expected( uid( 'hugo' ) ), numbers( $hugo ) );
		$this->assertSame( 403, $this->summary( $bruno, $chiara )->get_status() );
	}

	public function test_scopes_are_cached_independently(): void {
		foreach ( array( 'bruno', 'chiara', 'dmitri' ) as $login ) {
			$this->load( uid( $login ) );
		}
		$this->load( uid( 'eva' ) );
		new_request();
		foreach ( array( 'bruno', 'chiara', 'dmitri' ) as $login ) {
			list( $data, $computes, $queries ) = $this->load( uid( $login ) );
			$this->assertSame( array(), $computes, $login );
			$this->assertLessThanOrEqual( $this->max_queries(), $queries, $login );
			$this->assertSame( expected( uid( $login ) ), numbers( $data ) );
		}
	}
}
