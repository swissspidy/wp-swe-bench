<?php
/**
 * One runner at a time: the lock option, stale locks, concurrent requests.
 */

use WPSB\CRM\CrmTestCase;
use function WPSB\CRM\columns;
use function WPSB\CRM\contacts_table;
use function WPSB\CRM\db_version;
use function WPSB\CRM\install_test_migrations;
use function WPSB\CRM\latest;
use function WPSB\CRM\log_entries;
use function WPSB\CRM\raw_option;
use function WPSB\CRM\set_raw_option;
use function WPSB\CRM\test_log;

class MigrationLockTest extends CrmTestCase {

	public function test_a_fresh_lock_is_respected(): void {
		set_raw_option( 'acme_crm_migration_lock', time() - 30 );

		foreach ( array( '/', '/wp-json/', '/contact/' ) as $path ) {
			$r = $this->visit( $path );
			$this->assertSame( 200, $r['status'], "$path must still be served while another request holds the lock" );
		}
		$this->assertNotContains( 'stage', columns( contacts_table() ), 'No migration may run while another run holds the lock' );
		$this->assertContains( db_version(), array( null, 1 ) );
		$this->assertSame( array(), log_entries( 2 ) );
		$this->assertNotNull( raw_option( 'acme_crm_migration_lock' ), 'Someone else\'s lock must not be removed' );
	}

	public function test_a_stale_lock_is_taken_over(): void {
		set_raw_option( 'acme_crm_migration_lock', time() - 11 * MINUTE_IN_SECONDS );
		$r = $this->visit( '/' );
		$this->assertSame( 200, $r['status'] );
		$this->assertContains( 'stage', columns( contacts_table() ), 'A lock older than 10 minutes is abandoned and must be taken over' );
		$this->assertCount( 1, log_entries( 2, 'applied' ) );
		$this->assertNull( raw_option( 'acme_crm_migration_lock' ), 'The lock must be released after the run' );
	}

	public function test_concurrent_requests_run_each_migration_once(): void {
		$this->migrate_fully();
		install_test_migrations(
			array(
				90 => array(
					'mode'  => 'ok',
					'sleep' => 3,
				),
				91 => array( 'mode' => 'ok' ),
			)
		);

		$paths = array( '/', '/wp-json/', '/contact/', '/?s=acme', '/wp-json/wp/v2/types', '/?feed=rss2' );
		$mh    = curl_multi_init();
		$hs    = array();
		foreach ( $paths as $path ) {
			$h = curl_init( 'http://127.0.0.1:9400' . $path );
			curl_setopt_array(
				$h,
				array(
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_TIMEOUT        => 120,
					CURLOPT_PROXY          => '',
				)
			);
			curl_multi_add_handle( $mh, $h );
			$hs[ $path ] = $h;
		}
		do {
			$status = curl_multi_exec( $mh, $running );
			if ( $running ) {
				curl_multi_select( $mh, 1.0 );
			}
		} while ( $running && CURLM_OK === $status );
		foreach ( $hs as $path => $h ) {
			$this->assertSame( 200, (int) curl_getinfo( $h, CURLINFO_RESPONSE_CODE ), "$path failed during concurrent migrations: " . substr( (string) curl_multi_getcontent( $h ), 0, 300 ) );
			curl_multi_remove_handle( $mh, $h );
			curl_close( $h );
		}
		curl_multi_close( $mh );
		wp_cache_flush();

		// Stragglers (if every concurrent request found the lock taken) finish on the next request.
		for ( $i = 0; $i < 3 && 91 !== db_version(); $i++ ) {
			$this->visit( '/' );
		}

		$this->assertSame( array( 90, 91 ), test_log( 'up' ), 'Each migration must run exactly once, however many requests arrive at the same time' );
		$this->assertSame( 91, db_version() );
		$this->assertCount( 1, log_entries( 90, 'applied' ) );
		$this->assertCount( 1, log_entries( 91, 'applied' ) );
		$this->assertCount( 1, log_entries( latest(), 'applied' ) );
		$this->assertNull( raw_option( 'acme_crm_migration_lock' ) );
	}
}
