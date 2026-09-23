<?php
/**
 * Running the upgrade again must not change converted events.
 */

use function WPSB\Events\seeded;
use function WPSB\Events\set_site_timezone;
use function WPSB\Events\stored;

/**
 * The upgrade runs again (e.g. a second deploy), after the site timezone changed.
 */
class MigrationRerunTest extends WPSB\Events\EventsTestCase {

	protected bool $use_transactions = false;

	public function test_upgrade_does_not_touch_converted_events(): void {
		$before = array();
		foreach ( array_keys( MigrationTest::EXPECTED ) as $slug ) {
			$before[ $slug ] = stored( seeded( $slug ) );
		}
		$tz = get_option( 'timezone_string' );
		try {
			set_site_timezone( 'Europe/Berlin' );
			update_option( 'acme_events_db_version', '1.6' );
			$res = $this->wp_cli( 'eval "echo get_option( \'acme_events_db_version\' );"' );
			$this->assertSame( 0, $res['exit'], $res['stderr'] );
			$this->assertSame( '2.0', trim( $res['stdout'] ), 'The upgrade runs on any request, including WP-CLI' );
			wp_cache_flush();
			foreach ( $before as $slug => $values ) {
				$this->assertSame( $values, stored( seeded( $slug ) ), "$slug changed on the second run" );
			}
		} finally {
			set_site_timezone( $tz );
			update_option( 'acme_events_db_version', '2.0' );
		}
	}
}
