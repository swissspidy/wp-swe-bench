<?php
/**
 * A 110-site network: batched activation, first-request set-up, background runs,
 * network deactivation and uninstall across every site.
 */

use WPSB\Directory\NetworkTestCase;
use function WPSB\Directory\all_directory_tables;
use function WPSB\Directory\cron_hooks;
use function WPSB\Directory\individually_active;
use function WPSB\Directory\is_set_up;
use function WPSB\Directory\leftover_events;
use function WPSB\Directory\leftover_options;
use function WPSB\Directory\site_ids;
use function WPSB\Directory\site_path;
use function WPSB\Directory\state;
use function WPSB\Directory\table_exists;
use function WPSB\Directory\listings_table;
use const WPSB\Directory\CLEANUP;

class LargeNetworkTest extends NetworkTestCase {

	const TOTAL = 110;

	private function set_up_ids(): array {
		wp_cache_flush();
		return array_values( array_filter( site_ids(), 'WPSB\Directory\is_set_up' ) );
	}

	private function create_sites(): void {
		$network = get_network();
		$n       = count( site_ids() );
		for ( $i = 1; $n < self::TOTAL; $i++, $n++ ) {
			$id = wpmu_create_blog( $network->domain, "/town$i/", "Town $i", 1, array( 'public' => 1 ), $network->id );
			$this->assertIsInt( $id, is_wp_error( $id ) ? $id->get_error_message() : 'wpmu_create_blog failed' );
		}
		wp_cache_flush();
		$this->assertCount( self::TOTAL, site_ids() );
	}

	public function test_large_network_lifecycle(): void {
		$this->create_sites();
		$main_hooks_before = array_keys( cron_hooks( 1 ) );

		// 1. Activation sets up at most 25 sites in the request itself.
		$already = $this->set_up_ids();
		$this->cli( 'plugin activate acme-directory --network' );
		$done = $this->set_up_ids();
		$new  = count( array_diff( $done, $already ) );
		$this->assertLessThanOrEqual( 25, $new, 'Network activation may set up at most 25 sites itself on a large network; set up: ' . $new );

		// 2. A site that has not been reached yet sets itself up on its first request.
		$pending = array_values( array_diff( site_ids(), $done ) );
		$this->assertNotEmpty( $pending );
		$rest_site  = end( $pending );
		$front_site = $pending[ intdiv( count( $pending ), 2 ) ];

		$r = $this->http( 'GET', site_path( $rest_site ) . 'wp-json/acme-directory/v1/categories' );
		$this->assertSame( 200, $r['status'], $r['body'] );
		$this->assertSame( array( 'general', 'network-partners' ), array_column( (array) $r['json'], 'slug' ), 'REST on a site not reached by the background set-up yet' );
		wp_cache_flush();
		$this->assertSetUp( $rest_site, 'first REST request' );

		$r = $this->http( 'GET', site_path( $front_site ) );
		$this->assertSame( 200, $r['status'] );
		wp_cache_flush();
		$this->assertSetUp( $front_site, 'first front-end request' );

		// 3. The main site's scheduled events finish the job, <= 25 sites per run.
		$runs = 0;
		while ( count( $this->set_up_ids() ) < self::TOTAL && $runs < 8 ) {
			$before = $this->set_up_ids();
			$this->cli( 'cron event run --due-now' );
			++$runs;
			$after = $this->set_up_ids();
			$this->assertLessThanOrEqual( 25, count( array_diff( $after, $before ) ), "Background run $runs set up more than 25 sites" );
			$this->assertGreaterThan( 0, count( array_diff( $after, $before ) ), "Background run $runs made no progress" );
		}
		$this->assertCount( self::TOTAL, $this->set_up_ids(), "Every site must be set up after the background runs ($runs runs)" );
		$this->assertLessThanOrEqual( 5, $runs, 'The background set-up must make steady progress (25 sites per run)' );

		foreach ( site_ids() as $id ) {
			$this->assertSetUp( $id, 'large network after background set-up' );
		}

		// 4. Nothing is left scheduled; another run changes nothing.
		$this->cli( 'cron event run --due-now' );
		$this->assertSame( array(), array_values( array_filter( leftover_events(), static fn( $e ) => false === strpos( $e, CLEANUP ) ) ), 'No background work may remain scheduled once every site is set up' );
		$this->assertSame( array(), array_values( array_diff( $main_hooks_before, array_keys( cron_hooks( 1 ) ) ) ), 'Unrelated scheduled events of the main site must be kept' );
		foreach ( site_ids() as $id ) {
			$s = state( $id );
			$this->assertSame( 1, $s['general'], "site $id: set up once" );
			$this->assertSame( 1, $s['cleanup_events'], "site $id: one cleanup event" );
		}

		// 5. Network deactivation reaches every site.
		$this->cli( 'plugin deactivate acme-directory --network' );
		foreach ( site_ids() as $id ) {
			$this->assertTrue( table_exists( listings_table( $id ) ), "site $id: data kept on deactivation" );
			$this->assertSame( individually_active( $id ) ? 1 : 0, state( $id )['cleanup_events'], "site $id: cleanup event after network deactivation" );
		}

		// 6. Uninstall reaches every site.
		foreach ( site_ids() as $id ) {
			if ( individually_active( $id ) ) {
				$this->cli( 'plugin deactivate acme-directory --url=http://127.0.0.1:9400' . site_path( $id ) );
			}
		}
		$this->cli( 'plugin uninstall acme-directory --skip-delete' );
		$this->assertSame( array(), all_directory_tables(), 'Uninstall must drop the tables of all ' . self::TOTAL . ' sites' );
		$this->assertSame( array(), leftover_options(), 'Uninstall must delete the options of all sites' );
		$this->assertSame( array(), leftover_events(), 'Uninstall must remove the events of all sites' );
	}

	public function test_background_runs_stop_on_network_deactivation(): void {
		$this->create_sites();
		$this->cli( 'plugin activate acme-directory --network' );
		$done = count( $this->set_up_ids() );
		$this->assertLessThan( self::TOTAL, $done );

		$this->cli( 'plugin deactivate acme-directory --network' );
		$this->assertSame( array(), array_values( array_filter( leftover_events(), static fn( $e ) => false === strpos( $e, CLEANUP ) ) ), 'Network deactivation must cancel the pending background set-up' );

		// Re-activation later picks the job up again and finishes it.
		$this->cli( 'plugin activate acme-directory --network' );
		for ( $runs = 0; $runs < 8 && count( $this->set_up_ids() ) < self::TOTAL; $runs++ ) {
			$this->cli( 'cron event run --due-now' );
		}
		$this->assertCount( self::TOTAL, $this->set_up_ids() );
		foreach ( site_ids() as $id ) {
			$this->assertSetUp( $id, 'after deactivate + reactivate' );
		}
	}
}
