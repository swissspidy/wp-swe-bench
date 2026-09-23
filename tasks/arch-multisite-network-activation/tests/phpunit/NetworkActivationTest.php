<?php
/**
 * Network activation on the seeded six-site network (<= 25 sites: everything right away).
 */

use WPSB\Directory\NetworkTestCase;
use function WPSB\Directory\categories_table;
use function WPSB\Directory\cron_hooks;
use function WPSB\Directory\leftover_events;
use function WPSB\Directory\listings_table;
use function WPSB\Directory\raw_option;
use function WPSB\Directory\rows;
use function WPSB\Directory\site_ids;
use function WPSB\Directory\state;
use const WPSB\Directory\CLEANUP;
use const WPSB\Directory\SITES;

class NetworkActivationTest extends NetworkTestCase {

	public function test_network_admin_activation_sets_up_every_site(): void {
		$before = $this->seeded_data();
		$this->assertCount( 8, $before['main_listings'], 'seed sanity' );
		$this->assertCount( 5, $before['south_listings'], 'seed sanity' );

		$this->network_activate_http();

		foreach ( array_keys( SITES ) as $id ) {
			$this->assertSetUp( $id, 'after network activation from Network Admin' );
		}

		// Sites that were not set up before get the defaults, computed in their own context.
		foreach ( array( 2, 4, 5, 6 ) as $id ) {
			$settings = raw_option( $id, 'acme_directory_settings' );
			$this->assertSame( "directory+$id@acme.example", $settings['notify_email'] ?? null, "site $id: default settings must be computed in the site's own context" );
			$this->assertSame( 20, (int) ( $settings['per_page'] ?? 0 ), "site $id: default settings" );
			$this->assertSame( array(), rows( listings_table( $id ) ), "site $id starts with an empty directory" );
		}

		// Existing directories are untouched.
		$after = $this->seeded_data();
		$this->assertEquals( $before, $after, 'Network activation must not change the data or settings of sites that were already set up' );

		// Every site's directory works over HTTP.
		$r = $this->http( 'GET', '/north/wp-json/acme-directory/v1/categories' );
		$this->assertSame( 200, $r['status'], $r['body'] );
		$this->assertSame( array( 'general', 'network-partners' ), array_column( (array) $r['json'], 'slug' ) );

		$r = $this->http( 'GET', '/south/wp-json/acme-directory/v1/listings' );
		$this->assertSame( 200, $r['status'], $r['body'] );
		$this->assertSame( array( 'South Beach Surf School', 'Dune Kiosk', 'Lighthouse Diner', 'Tidepool Tours' ), array_column( (array) $r['json'], 'name' ) );

		$r = $this->http( 'GET', '/harbour/wp-json/acme-directory/v1/listings' );
		$this->assertSame( 200, $r['status'], $r['body'] );
		$this->assertSame( array(), $r['json'] );

		// Nothing left to do in the background on a small network.
		$this->assertSame( array(), array_values( array_filter( leftover_events(), static fn( $e ) => false === strpos( $e, CLEANUP ) ) ), 'No background work may remain scheduled once every site is set up' );
	}

	public function test_cli_network_activation_is_idempotent(): void {
		$before = $this->seeded_data();

		$this->cli( 'plugin activate acme-directory --network' );
		foreach ( array_keys( SITES ) as $id ) {
			$this->assertSetUp( $id, 'after wp plugin activate --network' );
		}

		// Deactivate and activate again: nothing is duplicated or reset.
		$this->cli( 'plugin deactivate acme-directory --network' );
		$this->cli( 'plugin activate acme-directory --network' );
		foreach ( array_keys( SITES ) as $id ) {
			$this->assertSetUp( $id, 'after re-activating the network' );
		}

		$after = $this->seeded_data();
		$this->assertEquals( $before, $after, 'Re-activation must keep existing data, settings and install dates' );
	}

	public function test_activation_from_a_sub_site_context(): void {
		$main_hooks_before = array_keys( cron_hooks( 1 ) );

		$this->cli( 'plugin activate acme-directory --network --url=http://127.0.0.1:9400/east/' );
		foreach ( array_keys( SITES ) as $id ) {
			$this->assertSetUp( $id, 'network-activated from /east/' );
		}

		$this->assertSame( array(), array_values( array_filter( leftover_events(), static fn( $e ) => false === strpos( $e, CLEANUP ) ) ), 'No background work may remain scheduled once every site is set up' );
		$this->assertSame( array(), array_values( array_diff( $main_hooks_before, array_keys( cron_hooks( 1 ) ) ) ), 'Unrelated scheduled events of the main site must be kept' );
	}

	public function test_sites_not_yet_set_up_before_network_activation(): void {
		// Pass-to-pass sanity of the starting state: only the main site and /south/ have the directory.
		$this->assertSame( array( 1, 2, 3, 4, 5, 6 ), site_ids() );
		$this->assertSetUp( 1, 'seeded' );
		$this->assertSetUp( 3, 'seeded' );
		foreach ( array( 2, 4, 5, 6 ) as $id ) {
			$this->assertNotSetUp( $id, 'seeded' );
		}
		$this->assertSame( 'south-desk@acme.example', raw_option( 3, 'acme_directory_settings' )['notify_email'] );
		$slugs = array_column( rows( categories_table( 3 ) ), 'slug' );
		sort( $slugs );
		$this->assertSame( array( 'beaches', 'food', 'general', 'network-partners' ), $slugs );
		$this->assertSame( 1, state( 1 )['cleanup_events'] );
	}
}
