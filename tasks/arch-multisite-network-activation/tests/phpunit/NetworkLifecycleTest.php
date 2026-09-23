<?php
/**
 * New sites, deleted sites, network deactivation, per-site activation and uninstall.
 */

use WPSB\Directory\NetworkTestCase;
use function WPSB\Directory\all_directory_tables;
use function WPSB\Directory\categories_table;
use function WPSB\Directory\cron_count;
use function WPSB\Directory\debug_log_offset;
use function WPSB\Directory\debug_log_since;
use function WPSB\Directory\individually_active;
use function WPSB\Directory\leftover_events;
use function WPSB\Directory\leftover_options;
use function WPSB\Directory\listings_table;
use function WPSB\Directory\php_problems;
use function WPSB\Directory\raw_option;
use function WPSB\Directory\rows;
use function WPSB\Directory\site_ids;
use function WPSB\Directory\state;
use function WPSB\Directory\table_exists;
use const WPSB\Directory\PLUGIN;
use const WPSB\Directory\SITES;

class NetworkLifecycleTest extends NetworkTestCase {

	private function new_site_id( string $slug ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT blog_id FROM {$wpdb->blogs} WHERE path = %s", "/$slug/" ) );
	}

	public function test_site_created_with_wp_cli_is_set_up(): void {
		$this->cli( 'plugin activate acme-directory --network' );
		$offset = debug_log_offset();

		$r  = $this->cli( 'site create --slug=pier --title="Acme Pier" --porcelain' );
		$id = (int) trim( $r['stdout'] );
		$this->assertGreaterThan( 6, $id );
		$this->assertSetUp( $id, 'created while network-active' );
		$this->assertSame( "directory+$id@acme.example", raw_option( $id, 'acme_directory_settings' )['notify_email'] ?? null );
		$this->assertSame( array(), php_problems( debug_log_since( $offset ) ), 'Creating a site must not cause PHP notices, deprecations or database errors' );

		$h = $this->http( 'GET', '/pier/wp-json/acme-directory/v1/categories' );
		$this->assertSame( 200, $h['status'], $h['body'] );
		$this->assertSame( array( 'general', 'network-partners' ), array_column( (array) $h['json'], 'slug' ) );
	}

	public function test_site_created_in_network_admin_is_set_up(): void {
		$this->network_activate_http();
		$login = $this->http_login( 1 );
		$nonce = $this->nonce_for( 1, 'add-blog', $login['logged_in'] );
		$r     = $this->http(
			'POST',
			'/wp-admin/network/site-new.php?action=add-site',
			array(
				'login' => $login,
				'body'  => array(
					'_wpnonce_add-blog' => $nonce,
					'blog'              => array(
						'domain' => 'lagoon',
						'title'  => 'Acme Lagoon',
						'email'  => 'admin@example.org',
					),
				),
			)
		);
		$this->assertSame( 302, $r['status'], substr( $r['body'], 0, 2000 ) );
		wp_cache_flush();
		$id = $this->new_site_id( 'lagoon' );
		$this->assertGreaterThan( 6, $id, 'The site should have been created' );
		$this->assertSetUp( $id, 'created in Network Admin while network-active' );
	}

	public function test_site_created_while_not_network_active_is_left_alone(): void {
		$r  = $this->cli( 'site create --slug=quiet --title="Acme Quiet" --porcelain' );
		$id = (int) trim( $r['stdout'] );
		$this->assertNotSetUp( $id, 'plugin not network-active' );
	}

	public function test_deleting_a_site_removes_its_directory(): void {
		global $wpdb;
		$this->cli( 'plugin activate acme-directory --network' );
		$this->assertSetUp( 4 );
		$wpdb->insert(
			listings_table( 4 ),
			array(
				'name'        => 'East Side Deli',
				'slug'        => 'east-side-deli',
				'description' => '',
				'status'      => 'published',
				'created_at'  => '2026-01-01 00:00:00',
				'updated_at'  => '2026-01-01 00:00:00',
			)
		);
		$before = $this->seeded_data();

		$this->cli( 'site delete 4 --yes' );
		$this->assertFalse( table_exists( listings_table( 4 ) ), 'The deleted site\'s listings table must be dropped' );
		$this->assertFalse( table_exists( categories_table( 4 ) ), 'The deleted site\'s categories table must be dropped' );

		foreach ( array( 1, 2, 3, 5, 6 ) as $id ) {
			$this->assertSetUp( $id, 'other sites are untouched by the deletion' );
		}
		$this->assertEquals( $before, $this->seeded_data() );

		// The network keeps working afterwards: a new site is still set up.
		$r  = $this->cli( 'site create --slug=east2 --title="Acme East 2" --porcelain' );
		$this->assertSetUp( (int) trim( $r['stdout'] ), 'site created after a deletion' );
	}

	public function test_network_deactivation_from_network_admin(): void {
		$this->network_activate_http();
		$before = $this->seeded_data();
		$this->network_deactivate_http();

		// The main site and /south/ still have the plugin activated individually.
		$this->assertTrue( individually_active( 1 ) && individually_active( 3 ), 'seed sanity: individually active on / and /south/' );
		foreach ( array_keys( SITES ) as $id ) {
			$s = state( $id );
			$this->assertTrue( $s['listings_table'] && $s['categories_table'], "site $id: network deactivation must keep the tables" );
			$this->assertSame( 3, (int) $s['db_version'], "site $id: network deactivation must keep the options" );
			$this->assertIsArray( $s['settings'], "site $id: network deactivation must keep the settings" );
			$expected = in_array( $id, array( 1, 3 ), true ) ? 1 : 0;
			$this->assertSame( $expected, $s['cleanup_events'], "site $id: the daily cleanup must stay only where the plugin is still activated individually" );
		}
		$this->assertEquals( $before, $this->seeded_data(), 'Network deactivation must not touch the data' );

		// And the sites that still use the plugin keep working.
		$r = $this->http( 'GET', '/south/wp-json/acme-directory/v1/listings' );
		$this->assertSame( 200, $r['status'] );
		$this->assertCount( 4, (array) $r['json'] );
	}

	public function test_network_deactivation_with_wp_cli(): void {
		$this->cli( 'plugin activate acme-directory --network' );
		$this->cli( 'plugin deactivate acme-directory --network' );
		foreach ( array_keys( SITES ) as $id ) {
			$s = state( $id );
			$this->assertTrue( $s['listings_table'] && $s['categories_table'] && is_array( $s['settings'] ), "site $id: data must be kept" );
			$this->assertSame( individually_active( $id ) ? 1 : 0, $s['cleanup_events'], "site $id: cleanup event only where the plugin is still active" );
		}
		$this->assertSame( 1, cron_count( 3 ), '/south/ still has the plugin activated individually' );
		$this->assertSame( 0, cron_count( 2 ) );
		$this->assertSame( array( 'site 3: acme_directory_daily_cleanup' ), array_values( array_filter( leftover_events(), static fn( $e ) => 0 !== strpos( $e, 'site 1:' ) ) ), 'No other plugin events may remain' );
	}

	public function test_per_site_activation_only_touches_that_site(): void {
		$this->cli( 'plugin activate acme-directory --url=http://127.0.0.1:9400/north/' );
		$this->assertSetUp( 2, 'activated on /north/ only' );
		$this->assertSame( 'directory+2@acme.example', raw_option( 2, 'acme_directory_settings' )['notify_email'] ?? null );
		foreach ( array( 4, 5, 6 ) as $id ) {
			$this->assertNotSetUp( $id, 'plugin activated on /north/ only' );
		}
		$this->assertSetUp( 1 );
		$this->assertSetUp( 3 );
	}

	public function test_per_site_deactivation_only_touches_that_site(): void {
		$before = $this->seeded_data();
		$this->cli( 'plugin deactivate acme-directory --url=http://127.0.0.1:9400/south/' );
		$this->assertSame( 0, cron_count( 3 ), '/south/ cleanup must be unscheduled' );
		$this->assertSame( 1, cron_count( 1 ), 'the main site keeps its cleanup' );
		$this->assertEquals( $before, $this->seeded_data(), 'Deactivation keeps the data' );
	}

	public function test_uninstall_removes_everything_from_every_site(): void {
		$this->cli( 'plugin activate acme-directory --network' );
		$r   = $this->cli( 'site create --slug=pier --title="Acme Pier" --porcelain' );
		$new = (int) trim( $r['stdout'] );
		// Warm the category counts cache on a few sites.
		foreach ( array( '/', '/south/', '/pier/' ) as $path ) {
			$h = $this->http( 'GET', $path . 'wp-json/acme-directory/v1/categories' );
			$this->assertSame( 200, $h['status'] );
		}
		$this->assertNotNull( raw_option( 3, '_transient_acme_directory_counts' ), 'sanity: counts cached on /south/' );

		$this->cli( 'plugin deactivate acme-directory --network' );
		foreach ( site_ids() as $id ) {
			if ( individually_active( $id ) ) {
				$this->cli( 'plugin deactivate acme-directory --url=http://127.0.0.1:9400' . \WPSB\Directory\site_path( $id ) );
			}
		}
		$this->cli( 'plugin uninstall acme-directory --skip-delete' );

		$this->assertFileExists( WP_PLUGIN_DIR . '/' . PLUGIN );
		$this->assertSame( array(), all_directory_tables(), 'Uninstall must drop the directory tables of every site' );
		$this->assertSame( array(), leftover_options(), 'Uninstall must delete the plugin options of every site and of the network' );
		$this->assertSame( array(), leftover_events(), 'Uninstall must remove the plugin\'s scheduled events from every site' );
		$this->assertGreaterThan( 6, $new );
	}

	public function test_uninstall_after_individual_activations_only(): void {
		$this->cli( 'plugin activate acme-directory --url=http://127.0.0.1:9400/harbour/' );
		$this->assertSetUp( 6 );
		foreach ( array( '/', '/south/', '/harbour/' ) as $path ) {
			$this->cli( 'plugin deactivate acme-directory --url=http://127.0.0.1:9400' . $path );
		}
		$this->cli( 'plugin uninstall acme-directory --skip-delete' );
		$this->assertSame( array(), all_directory_tables() );
		$this->assertSame( array(), leftover_options() );
		$this->assertSame( array(), leftover_events() );
	}
}
